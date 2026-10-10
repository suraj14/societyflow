<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two real bugs found by sweeping every route-model-bound method across
 * the core controllers for the same patterns already fixed once
 * elsewhere in this project (cross-society IDOR via a bare permission
 * check with no model scoping; "exempt Super Admin explicitly" missed on
 * an inline society comparison). Grouped here rather than split across
 * per-controller files because both are "same bug class, found by the
 * same sweep", not independent features.
 */
class QueryScopingBugSweepTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Same bug class as FacilityBookingController::updateStatus() (fixed
     * earlier this session) - its sibling editStatus() checked only
     * can('manage_facilities') with no model scoping at all, so an Admin
     * in any society could view another society's booking details by id.
     */
    public function test_facility_booking_edit_status_screen_is_society_scoped(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminB = $this->makeUserWithRole($societyB, 'Admin');
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);
        $adminB->givePermissionTo('manage_facilities');

        $flatA = $this->makeFlat($societyA, $this->makeBuilding($societyA));
        $residentA = $this->makeResidentUser($societyA, $flatA);
        $facility = Facility::create(['society_id' => $societyA->id, 'name' => 'Hall', 'type' => 'hall']);
        $bookingA = FacilityBooking::create([
            'society_id' => $societyA->id, 'facility_id' => $facility->id, 'user_id' => $residentA->id,
            'flat_id' => $flatA->id, 'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00', 'status' => 'pending',
        ]);

        $this->actingAs($adminB)->get(route('facility-bookings.edit-status', $bookingA))->assertForbidden();
    }

    /**
     * Admin\RentController::edit()/update()/destroy()/receipt() compared
     * $rent->flat->society_id against getSocietyId(), which returns null
     * for Super Admin (no society_id of their own) - so every one of
     * these fail-closed and blocked Super Admin from managing any rent
     * record at all, the exact "exempt Super Admin explicitly" gap
     * CLAUDE.md §3 already documents being fixed elsewhere.
     */
    public function test_super_admin_can_edit_and_view_a_rent_record_in_any_society(): void
    {
        $society = $this->makeSociety();
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin', ['society_id' => null]);
        $flat = $this->makeFlat($society, $this->makeBuilding($society));

        $rent = MaintenanceBill::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'bill_number' => 'RENT-TEST-1',
            'month' => 'January', 'year' => 2026, 'maintenance_amount' => 5000, 'total_amount' => 5000,
            'paid_amount' => 0, 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(5)->toDateString(),
            'bill_type' => 'rent',
        ]);
        Payment::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'maintenance_bill_id' => $rent->id,
            'amount' => 5000, 'payment_method' => 'cash', 'status' => 'success',
            'payment_date' => now()->toDateString(), 'due_date' => now()->addDays(5)->toDateString(),
            'bill_type' => 'rent',
        ]);

        $this->actingAs($superAdmin)->get(route('admin.rents.edit', $rent))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.rents.receipt', $rent))->assertOk();
    }

    public function test_admin_still_cannot_edit_another_societys_rent_record(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminB = $this->makeUserWithRole($societyB, 'Admin');
        $flatA = $this->makeFlat($societyA, $this->makeBuilding($societyA));

        $rentA = MaintenanceBill::create([
            'society_id' => $societyA->id, 'flat_id' => $flatA->id, 'bill_number' => 'RENT-TEST-2',
            'month' => 'January', 'year' => 2026, 'maintenance_amount' => 5000, 'total_amount' => 5000,
            'paid_amount' => 0, 'bill_date' => now()->toDateString(), 'due_date' => now()->addDays(5)->toDateString(),
            'bill_type' => 'rent',
        ]);

        $this->actingAs($adminB)->get(route('admin.rents.edit', $rentA))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
