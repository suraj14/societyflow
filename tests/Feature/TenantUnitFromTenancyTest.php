<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use App\Support\MobileUserPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which unit is a tenant's? The active TENANCY decides - not the legacy
 * `residents` row, which is not closed when a tenancy moves. Found when a
 * tenant moved from villa 160 to villa 159 was shown (and could pay rent for)
 * villa 160, and - once that row was correctly closed - was told "No apartment
 * assigned" while renting 159. Covers the web rent page, rent payment, the
 * receipt, API complaints and the mobile session payload.
 */
class TenantUnitFromTenancyTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private Flat $current;   // villa 159: what he rents now
    private Flat $left;      // villa 160: what he rented before
    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeRole('Tenant');
        $this->society = $this->makeSociety();
        $area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'Area', 'status' => 'active']);
        $villa = fn (string $no) => Flat::create(['society_id' => $this->society->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => $no, 'status' => 'vacant']);
        $this->current = $villa('159');
        $this->left = $villa('160');

        $this->tenantUser = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Piyus']);
        $tenant = Tenant::create(['society_id' => $this->society->id, 'flat_id' => $this->current->id, 'user_id' => $this->tenantUser->id, 'name' => 'Piyus', 'email' => 'piyus@t.local', 'phone' => '0000000001', 'move_in_date' => '2026-09-22', 'status' => 'active']);
        $this->tenantUser->update(['tenant_id' => $tenant->id]);
    }

    private function rentBill(Flat $flat, string $month = 'September'): MaintenanceBill
    {
        return MaintenanceBill::create([
            'society_id' => $this->society->id, 'flat_id' => $flat->id, 'month' => $month, 'year' => 2026, 'bill_type' => 'rent',
            'bill_date' => '2026-09-22', 'due_date' => '2026-10-22', 'maintenance_amount' => 10000, 'notes' => 'Monthly Rent',
        ]);
    }

    private function legacyRow(Flat $flat, string $status): Resident
    {
        return Resident::create(['society_id' => $this->society->id, 'flat_id' => $flat->id, 'user_id' => $this->tenantUser->id, 'name' => 'Piyus', 'type' => 'tenant', 'move_in_date' => '2026-09-22', 'status' => $status]);
    }

    // ------------------------------------------------------------ rent page

    public function test_rent_page_works_with_a_tenancy_and_no_residency_row_at_all(): void
    {
        $mine = $this->rentBill($this->current);

        $response = $this->actingAs($this->tenantUser)->get(route('tenant.rent'))->assertOk();

        $response->assertSessionMissing('error');
        $response->assertSee('159');
        $this->assertSame([$mine->id], $response->viewData('rentRecords')->pluck('id')->all());
    }

    public function test_a_closed_residency_for_the_old_unit_does_not_blank_the_page(): void
    {
        $this->legacyRow($this->left, 'inactive');   // the repaired state
        $mine = $this->rentBill($this->current);

        $response = $this->actingAs($this->tenantUser)->get(route('tenant.rent'))->assertOk();
        $this->assertSame([$mine->id], $response->viewData('rentRecords')->pluck('id')->all());
    }

    public function test_a_stale_active_residency_does_not_show_the_unit_he_left(): void
    {
        $this->legacyRow($this->left, 'active');     // the unrepaired state
        $old = $this->rentBill($this->left);
        $mine = $this->rentBill($this->current, 'October');

        $response = $this->actingAs($this->tenantUser)->get(route('tenant.rent'))->assertOk();

        $this->assertSame([$mine->id], $response->viewData('rentRecords')->pluck('id')->all(), "villa 160's rent must not appear");
        $this->assertSame($this->current->id, $response->viewData('flat')->id);
    }

    public function test_no_active_tenancy_means_no_unit_and_nothing_is_created(): void
    {
        Tenant::where('user_id', $this->tenantUser->id)->update(['status' => 'inactive']);
        $this->legacyRow($this->left, 'active');
        $before = [Tenant::count(), Resident::count(), Flat::count()];

        $this->actingAs($this->tenantUser)->get(route('tenant.rent'))
            ->assertRedirect(route('tenant.dashboard'))->assertSessionHas('error');

        $this->assertSame($before, [Tenant::count(), Resident::count(), Flat::count()], 'viewing a page writes nothing');
    }

    // ------------------------------------------------------- pay / receipt

    public function test_he_can_pay_and_open_the_receipt_for_the_unit_he_rents(): void
    {
        $mine = $this->rentBill($this->current);

        $this->actingAs($this->tenantUser)->postJson(route('tenant.rent.payment'), [
            'rent_id' => $mine->id, 'payment_date' => '2026-10-10', 'payment_amount' => 5000, 'payment_method' => 'cash',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, $mine->payments()->where('status', 'pending')->count());
        $this->actingAs($this->tenantUser)->get(route('tenant.rent.receipt', $mine->id))->assertOk();
    }

    public function test_he_cannot_pay_or_open_the_receipt_for_the_unit_he_left_even_with_a_stale_row(): void
    {
        $this->legacyRow($this->left, 'active');
        $old = $this->rentBill($this->left);

        $this->actingAs($this->tenantUser)->postJson(route('tenant.rent.payment'), [
            'rent_id' => $old->id, 'payment_date' => '2026-10-10', 'payment_amount' => 1000, 'payment_method' => 'cash',
        ])->assertStatus(403);
        $this->assertSame(0, $old->payments()->count(), 'nothing was recorded');

        $this->actingAs($this->tenantUser)->get(route('tenant.rent.receipt', $old->id))->assertStatus(404);   // the controller maps its 403 to 404
    }

    public function test_he_cannot_reach_another_societys_rent(): void
    {
        $other = $this->makeSociety('Other');
        $area = VillaArea::create(['society_id' => $other->id, 'name' => 'A', 'status' => 'active']);
        $flat = Flat::create(['society_id' => $other->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '159', 'status' => 'vacant']);
        $bill = MaintenanceBill::create(['society_id' => $other->id, 'flat_id' => $flat->id, 'month' => 'September', 'year' => 2026, 'bill_type' => 'rent', 'bill_date' => '2026-09-22', 'due_date' => '2026-10-22', 'maintenance_amount' => 500]);

        $this->actingAs($this->tenantUser)->postJson(route('tenant.rent.payment'), [
            'rent_id' => $bill->id, 'payment_date' => '2026-10-10', 'payment_amount' => 100, 'payment_method' => 'cash',
        ])->assertStatus(403);
    }

    // ------------------------------------------------------ API and mobile

    public function test_an_api_complaint_is_filed_against_the_rented_unit(): void
    {
        $this->legacyRow($this->left, 'active');   // stale
        \Laravel\Sanctum\Sanctum::actingAs($this->tenantUser);

        $this->postJson('/api/v1/complaints', ['title' => 'Leak', 'description' => 'Water leak'])->assertStatus(201);

        $this->assertSame($this->current->id, (int) Complaint::where('created_by', $this->tenantUser->id)->value('flat_id'));
    }

    public function test_a_tenant_with_no_residency_row_still_gets_a_unit_on_their_complaint(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->tenantUser);

        $this->postJson('/api/v1/complaints', ['title' => 'Light', 'description' => 'Light out'])->assertStatus(201);

        $this->assertSame($this->current->id, (int) Complaint::where('created_by', $this->tenantUser->id)->value('flat_id'));
    }

    public function test_the_mobile_session_payload_names_the_rented_unit_after_the_old_row_is_closed(): void
    {
        $this->legacyRow($this->left, 'inactive');

        $payload = MobileUserPayload::make($this->tenantUser->fresh());

        $this->assertSame('159', $payload['flat_number']);
        $this->assertSame('159', $payload['villa_number']);
        $this->assertTrue($payload['is_tenant']);
    }

    public function test_the_mobile_payload_ignores_a_stale_active_residency_for_the_unit_he_left(): void
    {
        $this->legacyRow($this->left, 'active');   // the unrepaired state: web already uses the tenancy, the app must agree

        $payload = MobileUserPayload::make($this->tenantUser->fresh());

        $this->assertSame('159', $payload['flat_number']);
        $this->assertSame('159', $payload['villa_number']);
    }
}
