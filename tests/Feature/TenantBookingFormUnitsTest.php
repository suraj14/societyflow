<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Which units does the booking form offer a tenant? The ones they ACTUALLY
 * live in (active tenancy / active residency) - the same set store() enforces.
 * Found by the browser audit: the form offered Piyus the villa he had left
 * (via a closed residency row) and not the one he rents, and a tenant created
 * from Admin -> Tenants (no residency row at all) was offered nothing, so
 * tenants of rented units could not book a facility.
 */
class TenantBookingFormUnitsTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private Flat $current;   // villa 159, on_rent: what he rents now
    private Flat $left;      // villa 160: what he rented before
    private User $tenantUser;
    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Permission::firstOrCreate(['name' => 'book_facility', 'guard_name' => 'web']);
        $this->society = $this->makeSociety('Eden');
        $area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'A', 'status' => 'active']);
        $villa = fn (string $no, string $status) => Flat::create(['society_id' => $this->society->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => $no, 'status' => $status]);
        $this->current = $villa('159', 'vacant');
        $this->left = $villa('160', 'occupied');

        $this->tenantUser = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Piyus']);
        $this->tenantUser->givePermissionTo('book_facility');
        $tenant = Tenant::create(['society_id' => $this->society->id, 'flat_id' => $this->current->id, 'user_id' => $this->tenantUser->id, 'name' => 'Piyus', 'email' => 'p@t.local', 'phone' => '0000000001', 'move_in_date' => '2026-09-22', 'status' => 'active']);
        $this->tenantUser->update(['tenant_id' => $tenant->id]);   // also makes the flat on_rent

        $this->facility = Facility::create([
            'society_id' => $this->society->id, 'name' => 'Clubhouse', 'type' => 'clubhouse', 'status' => 'active', 'requires_approval' => false,
            'opening_time' => '08:00', 'closing_time' => '22:00', 'advance_booking_days' => 60, 'max_booking_hours' => 6, 'booking_charge' => 0,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
    }

    private function legacyRow(Flat $flat, string $status): Resident
    {
        return Resident::create(['society_id' => $this->society->id, 'flat_id' => $flat->id, 'user_id' => $this->tenantUser->id, 'name' => 'Piyus', 'type' => 'tenant', 'move_in_date' => '2026-09-22', 'status' => $status]);
    }

    private function offered(string $route = 'facility-bookings.create', array $params = []): array
    {
        return $this->actingAs($this->tenantUser)->get(route($route, $params))->assertOk()->viewData('properties')->pluck('id')->all();
    }

    private function book(Flat $flat)
    {
        return $this->actingAs($this->tenantUser)->post(route('facility-bookings.store'), [
            'facility_id' => $this->facility->id, 'flat_id' => $flat->id, 'booking_date' => now()->addDays(10)->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00', 'purpose' => 'Party', 'expected_guests' => 5,
        ]);
    }

    public function test_a_tenant_with_only_a_tenancy_is_offered_the_unit_they_rent(): void
    {
        $this->assertSame(0, Resident::where('user_id', $this->tenantUser->id)->count(), 'precondition: no residency row, like tenants created from Admin -> Tenants');
        $this->assertSame('on_rent', $this->current->fresh()->status, 'precondition: an on_rent unit');

        $this->assertSame([$this->current->id], $this->offered());
    }

    public function test_a_tenant_who_moved_is_offered_only_the_current_unit_not_the_one_they_left(): void
    {
        $this->legacyRow($this->left, 'inactive');   // the closed history row

        $this->assertSame([$this->current->id], $this->offered());
    }

    public function test_the_edit_form_offers_the_same_units(): void
    {
        $this->legacyRow($this->left, 'inactive');
        $this->book($this->current)->assertRedirect(route('facility-bookings.index'));
        $booking = FacilityBooking::sole();
        $booking->update(['status' => 'pending']);

        $this->assertSame([$this->current->id], $this->offered('facility-bookings.edit', [$booking]));
    }

    public function test_the_tenant_can_actually_book_the_unit_the_form_offers_and_not_the_one_they_left(): void
    {
        $this->legacyRow($this->left, 'inactive');

        $this->book($this->left)->assertSessionHas('error');
        $this->assertSame(0, FacilityBooking::count(), 'refused for the unit they left');

        $this->book($this->current)->assertRedirect(route('facility-bookings.index'))->assertSessionHas('success');
        $this->assertSame($this->current->id, (int) FacilityBooking::sole()->flat_id);
    }

    public function test_a_tenant_never_sees_another_residents_unit(): void
    {
        $someoneElse = Flat::create(['society_id' => $this->society->id, 'villa_area_id' => $this->current->villa_area_id, 'property_type' => 'villa', 'flat_number' => '999', 'status' => 'vacant']);

        $this->assertNotContains($someoneElse->id, $this->offered());
    }
}
