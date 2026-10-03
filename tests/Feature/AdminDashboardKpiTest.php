<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\VillaArea;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Admin dashboard shows exactly eight KPI cards, each traced to the
 * canonical source: PropertyOccupancy for units, active owners + tenants
 * for residents, the society-local day for visitors, Complaint::openTickets
 * for tickets, unpaid bill balances for dues, and success payments in the
 * society-local month for revenue - all scoped to the admin's own society.
 */
class AdminDashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function society(): array
    {
        $society = $this->makeSociety();
        $society->update(['property_type' => 'villa']);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $area  = VillaArea::create(['society_id' => $society->id, 'name' => 'Area', 'status' => 'active']);
        $villas = [];
        foreach (['158', '159', '160', '165', '72'] as $no) {
            $villas[$no] = Flat::create(['society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id, 'flat_number' => $no, 'floor' => 0, 'status' => 'vacant']);
        }
        $owners = [];
        foreach (['158', '159', '160'] as $no) {
            $owners[$no] = Owner::create(['society_id' => $society->id, 'name' => 'O' . $no, 'email' => "o{$no}" . uniqid() . '@t.local', 'phone' => '9000000' . $no, 'status' => 'active', 'property_type' => 'villa', 'villa_area_id' => $area->id, 'villa_no' => $no]);
        }
        // Inactive owner must not count as a resident or occupy.
        Owner::create(['society_id' => $society->id, 'name' => 'Gone', 'email' => 'gone' . uniqid() . '@t.local', 'phone' => '9000000001', 'status' => 'inactive', 'property_type' => 'villa', 'villa_area_id' => $area->id, 'villa_no' => '165']);
        $tenant = Tenant::create(['society_id' => $society->id, 'flat_id' => $villas['159']->id, 'name' => 'Piyus', 'email' => 'piyus' . uniqid() . '@t.local', 'phone' => '9000000061', 'move_in_date' => now(), 'status' => 'active']);
        // The owner of 160 also rents 72 (same email): counted once.
        Tenant::create(['society_id' => $society->id, 'flat_id' => $villas['72']->id, 'name' => 'O160', 'email' => $owners['160']->email, 'phone' => '9000000160', 'move_in_date' => now(), 'status' => 'active']);
        Tenant::create(['society_id' => $society->id, 'flat_id' => null, 'name' => 'Left', 'email' => 'left' . uniqid() . '@t.local', 'phone' => '9000000002', 'move_in_date' => now(), 'status' => 'inactive']);

        return compact('society', 'admin', 'area', 'villas', 'owners', 'tenant');
    }

    private function stats(array $f): array
    {
        return $this->actingAs($f['admin'])->get('/dashboard')->assertOk()->viewData('stats');
    }

    public function test_exactly_eight_kpi_cards_each_linking_somewhere(): void
    {
        $f = $this->society();
        $html = $this->actingAs($f['admin'])->get('/dashboard')->assertOk()->getContent();

        preg_match_all('/data-kpi="([a-z_]+)"/', $html, $m);
        $this->assertSame(
            ['total_properties', 'occupied_units', 'vacant_units', 'residents', 'visitors_today', 'open_tickets', 'pending_dues', 'monthly_revenue'],
            $m[1]
        );
        $this->assertSame(8, preg_match_all('/<a href="[^"]+" class="[^"]*" data-kpi=/', $html), 'every card is a link');
        // Buildings / Apartments / Villas are not primary cards any more.
        $this->assertStringNotContainsString('data-kpi="total_villas"', $html);
        $this->assertStringNotContainsString('Society Expenses This Month</p>', $html);
        // Responsive grid: two columns on phones, four on desktop.
        $this->assertSame(2, substr_count($html, 'grid grid-cols-2 lg:grid-cols-4'));
    }

    public function test_property_people_cards_follow_the_canonical_occupancy_rule(): void
    {
        $f = $this->society();
        $s = $this->stats($f);

        $this->assertSame(5, $s['total_units']);
        $this->assertSame(4, $s['occupied_units'], '158 + 160 owner, 159 + 72 tenant');
        $this->assertSame(2, $s['owner_occupied_units']);
        $this->assertSame(2, $s['tenant_occupied_units']);
        $this->assertSame(1, $s['vacant_units'], '165: inactive owner only');
        $this->assertSame($s['total_units'], $s['occupied_units'] + $s['vacant_units']);

        $this->assertSame(3, $s['active_owners']);
        $this->assertSame(2, $s['active_tenants']);
        $this->assertSame(4, $s['total_residents'], '3 owners + 2 tenants, one person in both tables');
    }

    public function test_operations_and_finance_cards_use_real_records(): void
    {
        $f = $this->society();
        $cat = ComplaintCategory::create(['society_id' => $f['society']->id, 'name' => 'Plumbing', 'status' => 'active']);
        foreach (['open', 'in_progress', 'resolved', 'closed'] as $st) {
            Complaint::create(['society_id' => $f['society']->id, 'flat_id' => $f['villas']['159']->id, 'complaint_category_id' => $cat->id, 'created_by' => $f['admin']->id, 'title' => $st, 'description' => 'x', 'priority' => 'low', 'status' => $st]);
        }

        // 10:30 IST on 1 Oct 2026 = 05:00 UTC.
        Carbon::setTestNow(Carbon::parse('2026-10-01 05:00:00', 'UTC'));
        $mk = fn (string $utc) => Visitor::create(['society_id' => $f['society']->id, 'flat_id' => $f['villas']['159']->id, 'host_user_id' => $f['admin']->id, 'visitor_name' => 'V', 'visitor_phone' => '9', 'visitor_type' => 'guest', 'purpose' => 'x', 'expected_entry_time' => $utc, 'approval_status' => 'pending', 'entry_status' => 'pending']);
        $mk('2026-09-30 19:00:00'); // 00:30 IST today
        $mk('2026-10-01 18:00:00'); // 23:30 IST today
        $mk('2026-09-30 18:00:00'); // yesterday IST

        $bill = fn (string $month, float $amt) => MaintenanceBill::create(['society_id' => $f['society']->id, 'flat_id' => $f['villas']['159']->id, 'month' => $month, 'year' => 2026, 'bill_date' => '2026-09-01', 'due_date' => '2026-09-10', 'maintenance_amount' => $amt, 'paid_amount' => 0, 'bill_type' => 'maintenance']);
        $b1 = $bill('August', 4000);
        $b2 = $bill('September', 6000);
        $pay = fn (MaintenanceBill $b, float $amt, string $date, string $status) => Payment::create(['society_id' => $f['society']->id, 'flat_id' => $b->flat_id, 'maintenance_bill_id' => $b->id, 'amount' => $amt, 'payment_method' => 'cash', 'status' => $status, 'payment_date' => $date, 'bill_type' => 'maintenance', 'due_date' => '2026-09-10']);
        $pay($b1, 4000, '2026-10-01', 'success');   // this month: revenue, b1 settled
        $pay($b2, 1000, '2026-09-28', 'success');   // last month: not revenue, reduces dues
        $pay($b2, 5000, '2026-10-01', 'pending');   // unverified claim: neither revenue nor dues reduction
        $pay($b2, 999, '2026-10-01', 'failed');

        $s = $this->stats($f);
        $this->assertSame(2, $s['visitors_today']);
        $this->assertSame(2, $s['open_tickets'], 'open + in_progress');
        $this->assertEquals(5000, $s['pending_dues'], '6000 billed - 1000 verified');
        $this->assertSame(1, $s['pending_bills_count']);
        $this->assertEquals(4000, $s['monthly_revenue'], 'success payments dated this month only');
    }

    public function test_an_admin_never_sees_another_societys_figures(): void
    {
        $f = $this->society();
        $other = $this->society();

        $s = $this->stats($f);
        $this->assertSame(5, $s['total_units']);
        $this->assertSame(4, $s['total_residents']);
        $this->assertSame(0, $s['open_tickets']);
        $this->assertEquals(0, $s['pending_dues']);
        $this->assertEquals(0, $s['monthly_revenue']);
        $this->assertSame(0, $s['visitors_today']);
        $this->assertSame(5, $this->stats($other)['total_units']);
    }

    public function test_empty_society_renders_zeros_without_errors(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $s = $this->actingAs($admin)->get('/dashboard')->assertOk()->viewData('stats');
        foreach (['total_units', 'occupied_units', 'vacant_units', 'total_residents', 'visitors_today', 'open_tickets', 'pending_dues', 'monthly_revenue'] as $k) {
            $this->assertEquals(0, $s[$k], $k);
        }
    }
}
