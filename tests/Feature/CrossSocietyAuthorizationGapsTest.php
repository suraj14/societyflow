<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Resident;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three route-bound controllers had no society check at all, found by a
 * targeted audit of every show/edit/update/destroy accepting a route-model
 * id (brief §3/§4: "never trust IDs sent from the frontend").
 *
 * Each was confirmed by reading the method body before being counted as a
 * finding - a grep for "no scoping call" produces false positives for
 * controllers that scope through a policy or a differently-named helper, so
 * every case here was verified to be a real gap, not a naming mismatch.
 */
class CrossSocietyAuthorizationGapsTest extends TestCase
{
    use RefreshDatabase;

    private function twoSocietiesWithAdmins(): array
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');
        $adminB   = $this->makeUserWithRole($societyB, 'Admin');

        return [$societyA, $societyB, $adminA, $adminB];
    }

    // ------------------------------------------------------------ residents

    private function residentIn(Society $society): Resident
    {
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, 'R-' . uniqid());
        $user     = $this->makeResidentUser($society, $flat);

        return Resident::where('user_id', $user->id)->firstOrFail();
    }

    public function test_admin_cannot_view_a_resident_from_another_society(): void
    {
        [$societyA, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $residentB = $this->residentIn($societyB);

        $this->actingAs($adminA)
            ->get("/residents/{$residentB->id}")
            ->assertForbidden();
    }

    public function test_admin_cannot_update_a_resident_from_another_society(): void
    {
        [$societyA, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $residentB = $this->residentIn($societyB);

        $this->actingAs($adminA)
            ->put("/residents/{$residentB->id}", [
                'name'          => 'Hijacked',
                'email'         => $residentB->email,
                'phone'         => '9000000000',
                'flat_id'       => $residentB->flat_id,
                'move_in_date'  => now()->toDateString(),
                'status'        => 'active',
            ])
            ->assertForbidden();

        $this->assertSame('active', $residentB->fresh()->status);
        $this->assertNotSame('Hijacked', $residentB->fresh()->name);
    }

    public function test_admin_cannot_delete_a_resident_from_another_society(): void
    {
        [$societyA, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $residentB = $this->residentIn($societyB);

        $this->actingAs($adminA)
            ->delete("/residents/{$residentB->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('residents', ['id' => $residentB->id]);
    }

    /**
     * The update form previously trusted a posted society_id outright,
     * letting a resident be reassigned to a different society entirely.
     * Confirmed within the admin's own society, where authorization passes,
     * so this isolates the mass-assignment bug from the missing-auth bug.
     */
    public function test_the_posted_society_id_cannot_move_a_resident_to_another_society(): void
    {
        [$societyA, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $residentA = $this->residentIn($societyA);

        $this->actingAs($adminA)
            ->put("/residents/{$residentA->id}", [
                'name'         => $residentA->name,
                'email'        => $residentA->email,
                'phone'        => $residentA->phone,
                'flat_id'      => $residentA->flat_id,
                'society_id'   => $societyB->id, // forged
                'move_in_date' => now()->toDateString(),
                'status'       => 'active',
            ])
            ->assertRedirect();

        $this->assertSame($societyA->id, $residentA->fresh()->society_id);
    }

    /** A legitimate same-society update still works. */
    public function test_admin_can_still_update_a_resident_in_their_own_society(): void
    {
        [$societyA, , $adminA] = $this->twoSocietiesWithAdmins();
        $residentA = $this->residentIn($societyA);

        $this->actingAs($adminA)
            ->put("/residents/{$residentA->id}", [
                'name'         => 'Renamed Fine',
                'email'        => $residentA->email,
                'phone'        => $residentA->phone,
                'flat_id'      => $residentA->flat_id,
                'move_in_date' => now()->toDateString(),
                'status'       => 'active',
            ])
            ->assertRedirect(route('residents.index'));

        $this->assertSame('Renamed Fine', $residentA->fresh()->name);
    }

    // ----------------------------------------------------------------- villas

    private function villaIn(Society $society): Flat
    {
        return Flat::create([
            'society_id'    => $society->id,
            'property_type' => 'villa',
            'flat_number'   => 'V-' . uniqid(),
            'villa_name'    => 'Villa ' . uniqid(),
            'status'        => 'vacant',
        ]);
    }

    public function test_admin_cannot_view_a_villa_from_another_society(): void
    {
        [, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $villaB = $this->villaIn($societyB);

        $this->actingAs($adminA)
            ->get("/villas/{$villaB->id}")
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_a_villa_from_another_society(): void
    {
        [, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $villaB = $this->villaIn($societyB);

        $this->actingAs($adminA)
            ->delete("/villas/{$villaB->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('flats', ['id' => $villaB->id]);
    }

    public function test_admin_cannot_edit_the_form_for_a_villa_from_another_society(): void
    {
        [, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $villaB = $this->villaIn($societyB);

        $this->actingAs($adminA)
            ->get("/villas/{$villaB->id}/edit")
            ->assertForbidden();
    }

    /**
     * Assigning an owner through this form must store owners.id, not
     * users.id - villa.owner_id is a foreign key to owners.id (4e35ffa), and
     * update() previously did User::find($villa->owner_id), which read that
     * id against the wrong table.
     */
    public function test_updating_a_villas_owner_grants_the_role_to_the_correct_login_account(): void
    {
        [$societyA, , $adminA] = $this->twoSocietiesWithAdmins();
        $villa = $this->villaIn($societyA);
        $this->makeRole('Villa Owner');

        // Pad the owners table so owners.id cannot coincide with any user id.
        for ($i = 0; $i < 5; $i++) {
            \App\Models\Owner::create([
                'society_id' => $societyA->id,
                'name'       => 'Padding ' . $i,
                'email'      => 'pad' . uniqid() . '@test.local',
                'status'     => 'active',
            ]);
        }

        $owner = \App\Models\Owner::create([
            'society_id' => $societyA->id,
            'name'       => 'Real Villa Owner',
            'email'      => 'villaowner' . uniqid() . '@test.local',
            'status'     => 'active',
        ]);
        $ownerUser = $this->makeUserWithRole($societyA, 'Tenant', ['owner_id' => $owner->id]);
        $ownerUser->removeRole('Tenant'); // start without Villa Owner to prove it gets assigned

        $this->assertNotSame($owner->id, $ownerUser->id, 'fixture requires distinct ids');

        $villaArea = \App\Models\VillaArea::create([
            'society_id' => $societyA->id,
            'name'       => 'Green Valley ' . uniqid(),
            'status'     => 'active',
        ]);

        $this->actingAs($adminA)->put("/villas/{$villa->id}", [
            'villa_area_id'  => $villaArea->id,
            'owner_id'       => $owner->id,
            'flat_number'    => $villa->flat_number,
            'status'         => 'occupied',
            'has_garden'     => '0',
            'has_parking'    => '0',
        ])->assertRedirect(route('villas.index'));

        $this->assertSame($owner->id, $villa->fresh()->owner_id);
        $this->assertTrue($ownerUser->fresh()->hasRole('Villa Owner'));
    }

    // --------------------------------------------------------- facility bookings

    private function bookingIn(Society $society): FacilityBooking
    {
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, 'F-' . uniqid());
        $user     = $this->makeResidentUser($society, $flat);
        $facility = Facility::create([
            'society_id' => $society->id,
            'name'       => 'Clubhouse ' . uniqid(),
            'status'     => 'active',
        ]);

        return FacilityBooking::create([
            'society_id'   => $society->id,
            'facility_id'  => $facility->id,
            'user_id'      => $user->id,
            'flat_id'      => $flat->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '10:00',
            'end_time'     => '11:00',
            'status'       => 'pending',
        ]);
    }

    /**
     * This was the widest-reach of the four findings: show() had no check
     * of any kind, not even the user_id ownership check edit/update/destroy
     * already had - any authenticated user, any role, any society.
     */
    public function test_a_resident_cannot_view_a_booking_from_another_society(): void
    {
        [, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $bookingB = $this->bookingIn($societyB);

        $this->actingAs($adminA)
            ->get("/facility-bookings/{$bookingB->id}")
            ->assertForbidden();
    }

    /**
     * manage_facilities is a role permission with no society scope in
     * Spatie's model, so an Admin holding it could reach any society's
     * booking through edit/update/destroy despite failing the user_id
     * ownership check that gates ordinary residents.
     */
    public function test_an_admin_with_manage_facilities_cannot_delete_another_societys_booking(): void
    {
        [, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $bookingB = $this->bookingIn($societyB);

        $this->actingAs($adminA)
            ->delete("/facility-bookings/{$bookingB->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('facility_bookings', ['id' => $bookingB->id]);
    }

    public function test_an_admin_with_manage_facilities_cannot_edit_another_societys_booking(): void
    {
        [, $societyB, $adminA] = $this->twoSocietiesWithAdmins();
        $bookingB = $this->bookingIn($societyB);

        $this->actingAs($adminA)
            ->get("/facility-bookings/{$bookingB->id}/edit")
            ->assertForbidden();
    }

    /** A resident can still view their own society's booking. */
    public function test_a_resident_can_view_a_booking_in_their_own_society(): void
    {
        [$societyA, , $adminA] = $this->twoSocietiesWithAdmins();
        $bookingA = $this->bookingIn($societyA);

        $this->actingAs($adminA)
            ->get("/facility-bookings/{$bookingA->id}")
            ->assertOk();
    }
}
