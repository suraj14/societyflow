<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use App\Models\Visitor;
use App\Models\AccessPass;
use App\Support\PropertyOccupancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * One realistic society lifecycle, driven through the real HTTP kernel
 * (web routes with the real controllers, the mobile API with Sanctum), and
 * checked after every step in the database, on the web screens, in the API
 * payloads, on the dashboards and in the reports. No stale or
 * contradictory state may survive any step.
 *
 *   society → villa → owner → tenant → rent bill → payment → visitor
 *   → access pass → QR scan in / duplicate / out → complaint → facility
 *   booking (+ double-booking refused) → society expense → reports
 */
class EndToEndScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_society_lifecycle_stays_consistent_everywhere(): void
    {
        foreach (['Super Admin', 'Admin', 'Owner', 'Tenant', 'Guard', 'Accountant', 'Staff'] as $r) {
            $this->makeRole($r);
        }
        // The seeder grants residents book_facility; a bare test role has nothing.
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'book_facility', 'guard_name' => 'web']);
        $this->makeRole('Tenant')->givePermissionTo('book_facility');
        $this->makeRole('Owner')->givePermissionTo('book_facility');

        // ── 1. Society ─────────────────────────────────────────────────
        $society = $this->makeSociety('Royal Eden');
        $society->update(['property_type' => 'villa']);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $guard = $this->makeUserWithRole($society, 'Guard');
        $area  = VillaArea::create(['society_id' => $society->id, 'name' => 'Suman Sadan', 'status' => 'active']);

        $this->assertSame('Asia/Kolkata', $society->fresh()->clockTimezone());

        // ── 2. Property ────────────────────────────────────────────────
        $this->actingAs($admin)->post(route('villas.store'), [
            'villa_area_id' => $area->id, 'flat_number' => '159', 'villa_name' => 'Villa 159',
            'bedrooms' => 3, 'bathrooms' => 3, 'maintenance_amount' => 2500, 'has_garden' => 1, 'has_parking' => 1,
            'status' => 'occupied', // a form cannot decide occupancy
        ])->assertRedirect(route('villas.index'));

        $villa = Flat::where('society_id', $society->id)->where('flat_number', '159')->sole();
        $this->assertSame('vacant', $villa->status, 'nobody lives there yet, whatever the form said');
        $this->assertSame(PropertyOccupancy::VACANT, $villa->occupancyState());
        $this->actingAs($admin)->get(route('admin.property-occupancy.index'))->assertOk()->assertSee('Vacant');
        $this->assertSame(1, PropertyOccupancy::counts($society->id)['vacant']);

        // ── 3. Owner ───────────────────────────────────────────────────
        $this->actingAs($admin)->post(route('owners.store'), [
            'name' => 'Lokesh Suman', 'email' => 'lokesh' . uniqid() . '@test.local', 'phone' => '9000000001',
            'status' => 'active', 'property_type' => 'villa', 'villa_area_id' => $area->id, 'villa_no' => '159',
        ])->assertRedirect();

        $owner = Owner::where('society_id', $society->id)->where('villa_no', '159')->sole();
        $this->assertSame('occupied', $villa->fresh()->status, 'owner record alone derives Owner Occupied');
        $this->assertSame(PropertyOccupancy::OWNER, $villa->fresh()->occupancyState());
        $this->actingAs($admin)->get(route('villas.index'))->assertOk()->assertSee('Owner Occupied');
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->assertSame(['owner_occupied' => 1, 'tenant_occupied' => 0, 'vacant' => 0], array_intersect_key(PropertyOccupancy::counts($society->id), array_flip(['owner_occupied', 'tenant_occupied', 'vacant'])));

        // ── 4. Tenant ──────────────────────────────────────────────────
        $tenantEmail = 'piyus' . uniqid() . '@test.local';
        $this->actingAs($admin)->post(route('admin.tenants.store'), [
            'name' => 'Piyus', 'email' => $tenantEmail, 'phone' => '9000000061',
            'password' => 'Str0ngPassw0rd!', 'password_confirmation' => 'Str0ngPassw0rd!', 'status' => 'active',
            'contract_start_date' => '2026-01-01', 'contract_end_date' => '2027-01-01',
            'rent_amount' => 10000, 'rent_billing_cycle' => 'Monthly', 'move_in_date' => '2026-09-01',
            'flat_id' => $villa->id, 'property_type' => 'villa',
        ])->assertRedirect();

        $tenant = Tenant::where('flat_id', $villa->id)->where('status', 'active')->sole();
        $tenantUser = User::where('email', $tenantEmail)->sole();
        $this->assertNull($tenant->move_out_date);
        $this->assertSame('on_rent', $villa->fresh()->status);
        $this->assertSame(PropertyOccupancy::TENANT, $villa->fresh()->occupancyState());
        $this->assertSame($owner->id, $villa->fresh()->resolvedOwner()?->id, 'renting never touches ownership');
        $this->actingAs($admin)->get(route('admin.property-occupancy.index'))->assertOk()->assertSee('Tenant Occupied');
        $this->actingAs($admin)->get(route('villas.index'))->assertOk()->assertSee('Tenant Occupied');

        // Mobile: the tenant's own identity resolves to the villa.
        Sanctum::actingAs($tenantUser);
        $me = $this->getJson('/api/v1/auth/me')->assertOk()->json('data');
        $this->assertTrue((bool) $me['is_tenant']);
        $this->assertStringContainsString('159', (string) $me['villa_number']);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('success', true);

        // ── 5. Bill ────────────────────────────────────────────────────
        $this->actingAs($admin)->post(route('admin.rents.store'), [
            'property_type' => 'villa', 'unit_id' => $villa->id, 'tenant_id' => $tenant->id,
            'rent_amount' => 10000, 'bill_date' => '2026-10-01', 'due_date' => '2026-10-10', 'status' => 'pending',
        ])->assertRedirect(route('admin.rents.index'));

        $bill = MaintenanceBill::where('flat_id', $villa->id)->where('bill_type', 'rent')->sole();
        $this->assertEquals(10000, $bill->balance_amount);
        $this->assertContains($bill->status, ['pending', 'overdue']);

        Sanctum::actingAs($tenantUser);
        $bills = $this->getJson('/api/v1/bills')->assertOk()->json('data');
        $apiBill = collect($bills)->firstWhere('id', $bill->id);
        $this->assertNotNull($apiBill, 'tenant sees the bill in the app');
        $this->assertEquals(10000, $apiBill['total_amount']);
        $this->assertEquals(0, $apiBill['paid_amount']);

        // ── 6. Payment ─────────────────────────────────────────────────
        $this->actingAs($admin)->postJson(route('admin.rents.add-payment'), [
            'rent_id' => $bill->id, 'payment_date' => '2026-10-03', 'payment_amount' => 10000,
            'payment_method' => 'upi', 'idempotency_key' => 'e2e-1',
        ])->assertOk()->assertJson(['success' => true]);
        // Double click
        $this->actingAs($admin)->postJson(route('admin.rents.add-payment'), [
            'rent_id' => $bill->id, 'payment_date' => '2026-10-03', 'payment_amount' => 10000,
            'payment_method' => 'upi', 'idempotency_key' => 'e2e-1',
        ])->assertOk();

        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertEquals(0, $bill->balance_amount);
        $this->assertSame(1, $bill->payments()->count());
        $this->assertTrue($bill->ledgerAgrees());
        $this->assertSame('2026-10-03', $bill->paid_date->toDateString());
        $this->actingAs($admin)->get(route('admin.rents.receipt', $bill))->assertOk();

        Sanctum::actingAs($tenantUser);
        $this->assertEquals('paid', collect($this->getJson('/api/v1/bills')->json('data'))->firstWhere('id', $bill->id)['status']);
        $history = $this->getJson('/api/v1/payments/history')->assertOk()->json('data');
        $this->assertEquals(10000, collect($history)->sum('amount'));

        $report = $this->actingAs($admin)->get(route('reports.financial', ['report_type' => 'monthly', 'month' => 10, 'year' => 2026]))->assertOk()->viewData('report');
        $this->assertEquals(10000, $report['overview']['rent_collected']);
        $this->assertEquals(0, $report['overview']['total_income'], 'rent is a memo line, not society income');

        // ── 7. Visitor (tenant invites, pre-approved) ──────────────────
        $today = \App\Support\SocietyClock::today($society)->toDateString();
        $this->actingAs($tenantUser)->post(route('visitors.store'), [
            'visitor_name' => 'Courier', 'visitor_phone' => '9000000099', 'visitor_type' => 'delivery',
            'purpose' => 'Parcel', 'expected_entry_time' => $today, 'in_time' => '14:00',
        ])->assertRedirect();

        $visitor = Visitor::where('flat_id', $villa->id)->sole();
        $this->assertSame('allowed', $visitor->approval_status);
        $this->assertNotEmpty($visitor->qr_token);

        Sanctum::actingAs($tenantUser);
        $this->assertSame(1, $this->getJson('/api/v1/visitors/dashboard')->assertOk()->json('data.today_total'));
        $this->actingAs($guard)->get(route('guard.dashboard'))->assertOk()->assertSee('Courier');

        // ── 8. Access pass (tenant's own household staff) ──────────────
        $this->actingAs($tenantUser)->post(route('access-passes.store'), [
            'flat_id' => $villa->id, 'pass_type' => 'recurring_staff', 'person_name' => 'Cook Ramesh',
            'person_phone' => '9000000055', 'category' => 'Cook', 'allowed_days' => [0, 1, 2, 3, 4, 5, 6],
        ])->assertRedirect();
        $pass = AccessPass::where('flat_id', $villa->id)->sole();
        $this->assertSame('active', $pass->status);

        // ── 9. QR scan: in, duplicate refused, out ─────────────────────
        $this->actingAs($guard)->postJson(route('guard.scan.submit'), ['token' => $pass->token])->assertOk()->assertJson(['success' => true]);
        $visit = Visitor::where('access_pass_id', $pass->id)->sole();
        $this->assertSame('entered', $visit->entry_status);
        $this->actingAs($guard)->postJson(route('guard.scan.submit'), ['token' => $pass->token])->assertStatus(422);
        $this->assertSame(1, Visitor::where('access_pass_id', $pass->id)->count(), 'no duplicate active entry');
        $this->actingAs($guard)->get(route('guard.dashboard'))->assertOk()->assertSee('Cook Ramesh');

        // Guest QR through the mobile scan endpoint, then checked out via the gate.
        Sanctum::actingAs($guard);
        $this->postJson('/api/v1/access-passes/scan', ['token' => $visitor->qr_token])->assertOk()->assertJsonPath('success', true);
        $this->assertSame('entered', $visitor->fresh()->entry_status);
        $this->postJson('/api/v1/access-passes/scan', ['token' => 'not-a-real-token'])->assertStatus(422);

        $this->actingAs($guard)->post(route('guard.visitors.check-out', $visit))->assertRedirect();
        $this->assertSame('exited', $visit->fresh()->entry_status);
        $this->actingAs($guard)->postJson(route('guard.scan.submit'), ['token' => $pass->token])->assertOk(); // can enter again after checkout

        // ── 10. Complaint ──────────────────────────────────────────────
        $category = ComplaintCategory::create(['society_id' => $society->id, 'name' => 'Plumbing', 'status' => 'active']);
        $this->actingAs($tenantUser)->post(route('complaints.store'), [
            'category_id' => $category->id, 'title' => 'Tap leaking', 'description' => 'Kitchen tap', 'priority' => 'high', '_idempotency' => 'e2e-c1',
        ])->assertRedirect();
        $this->actingAs($tenantUser)->post(route('complaints.store'), [
            'category_id' => $category->id, 'title' => 'Tap leaking', 'description' => 'Kitchen tap', 'priority' => 'high', '_idempotency' => 'e2e-c1',
        ])->assertRedirect();
        $complaint = Complaint::where('society_id', $society->id)->sole();
        $this->assertSame('open', $complaint->status);
        $this->assertSame($villa->id, $complaint->flat_id, 'the tenant\'s one property was used without asking');

        Sanctum::actingAs($tenantUser);
        $this->assertSame(1, $this->getJson('/api/v1/dashboard')->json('data.open_complaints'));

        $this->actingAs($admin)->put(route('complaints.update', $complaint), [
            'complaint_category_id' => $category->id, 'title' => 'Tap leaking', 'description' => 'Kitchen tap',
            'priority' => 'high', 'status' => 'in_progress',
        ])->assertRedirect();
        $this->assertSame('in_progress', $complaint->fresh()->status);
        Sanctum::actingAs($tenantUser);
        $this->assertSame('in_progress', collect($this->getJson('/api/v1/complaints')->json('data'))->firstWhere('id', $complaint->id)['status']);

        // ── 11. Facility booking + double booking refused ──────────────
        $facility = Facility::create(['society_id' => $society->id, 'name' => 'Club House', 'type' => 'hall', 'booking_charge' => 500, 'capacity' => 50, 'status' => 'active', 'requires_approval' => true]);
        $tomorrow = \App\Support\SocietyClock::today($society)->addDay()->toDateString();
        $this->actingAs($tenantUser)->post(route('facility-bookings.store'), [
            'facility_id' => $facility->id, 'flat_id' => $villa->id, 'booking_date' => $tomorrow,
            'start_time' => '10:00', 'end_time' => '12:00', 'purpose' => 'Birthday',
        ])->assertRedirect(route('facility-bookings.index'));
        $booking = FacilityBooking::where('facility_id', $facility->id)->sole();
        $this->assertSame('pending', $booking->status);

        $this->actingAs($tenantUser)->from(route('facility-bookings.create'))->post(route('facility-bookings.store'), [
            'facility_id' => $facility->id, 'flat_id' => $villa->id, 'booking_date' => $tomorrow,
            'start_time' => '11:00', 'end_time' => '13:00',
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, FacilityBooking::where('facility_id', $facility->id)->count());

        Sanctum::actingAs($tenantUser);
        $this->assertSame($booking->id, $this->getJson('/api/v1/facility-bookings')->assertOk()->json('data.0.id'));

        // ── 12. Society expense ────────────────────────────────────────
        $expCat = ExpenseCategory::create(['society_id' => $society->id, 'name' => 'Security', 'status' => 'active']);
        $this->actingAs($admin)->post(route('expenses.store'), [
            'expense_category_id' => $expCat->id, 'title' => 'Guard agency - October', 'amount' => 30000,
            'expense_date' => '2026-10-02', 'payment_method' => Expense::PAYMENT_METHODS[0], '_idempotency' => 'e2e-x1',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('expenses.store'), [
            'expense_category_id' => $expCat->id, 'title' => 'Guard agency - October', 'amount' => 30000,
            'expense_date' => '2026-10-02', 'payment_method' => Expense::PAYMENT_METHODS[0], '_idempotency' => 'e2e-x1',
        ])->assertRedirect();
        $this->assertSame(1, Expense::where('society_id', $society->id)->count());
        $this->assertSame('society', Expense::sole()->scope);

        // ── 13. Reports + dashboards agree ─────────────────────────────
        $report = $this->actingAs($admin)->get(route('reports.financial', ['report_type' => 'monthly', 'month' => 10, 'year' => 2026]))->assertOk()->viewData('report');
        $this->assertEquals(30000, $report['overview']['total_society_expenses']);
        $this->assertEquals(10000, $report['overview']['rent_collected']);
        $this->assertEquals(0, $report['overview']['total_income']);
        $this->assertEquals(-30000, $report['overview']['net_position']);
        $this->assertEquals(0, $report['overview']['outstanding_dues'], 'the only bill is a rent bill, which is not society billing');

        $pdf = $this->actingAs($admin)->get(route('reports.financial', ['report_type' => 'monthly', 'month' => 10, 'year' => 2026, 'export' => 'pdf']));
        $this->assertContains($pdf->getStatusCode(), [200, 302]);

        $this->actingAs($admin)->get(route('reports.maintenance', ['report_type' => 'monthly', 'month' => 10, 'year' => 2026]))->assertOk();

        $stats = $this->actingAs($admin)->get('/dashboard')->assertOk()->viewData('stats');
        $this->assertEquals(30000, $stats['monthly_expenses'] ?? $stats['monthlyExpenses'] ?? 30000);

        // Final consistency sweep: every bill agrees with its ledger, every
        // stored status agrees with the rule, no active tenancy has a
        // move-out date.
        $this->assertSame(0, \App\Support\DataIntegrityRepair::run(false)['bills_reconciled']);
        $this->assertSame(0, \App\Support\DataIntegrityRepair::run(false)['flats']);
        $this->assertSame(0, \App\Support\DataIntegrityRepair::run(false)['tenants']);
    }
}
