<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * flats.owner_id points at owners.id, and nothing may write a users.id there.
 *
 * The column is `foreign key("owner_id") references "owners"("id")`. It was
 * originally constrained to `users` and corrected in 2026_01_23 / 2026_01_27,
 * but the assignment controllers were never updated - they still wrote
 * $user->id.
 *
 * That does not fail loudly. Both tables are integer-keyed from 1, so a user
 * id almost always matches *some* owner row: the foreign key is satisfied and
 * the flat is silently attributed to a different person. These tests pin the
 * identity, not merely the presence of a value.
 */
class OwnerIdRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Owner Link Society');
        $this->admin   = $this->makeUserWithRole($this->society, 'Admin');

        $this->makeRole('Apartment Owner');
        $this->makeRole('Villa Owner');
    }

    /**
     * Build an owner whose id deliberately cannot coincide with the user's id.
     *
     * If owners.id and users.id happen to match, a test asserting the wrong
     * value still passes. Padding the owners table removes that coincidence,
     * so assertSame() below actually means something.
     */
    private function ownerWithUser(string $role, string $propertyType = 'apartment'): array
    {
        // Burn a few owner ids so the real one is far from any user id.
        for ($i = 0; $i < 5; $i++) {
            Owner::create([
                'society_id' => $this->society->id,
                'name'       => 'Padding ' . $i,
                'email'      => 'pad' . uniqid() . '@test.local',
                'phone'      => '9000000000',
                'status'     => 'active',
            ]);
        }

        $owner = Owner::create([
            'society_id'    => $this->society->id,
            'property_type' => $propertyType,
            'name'          => 'Real Owner',
            'email'         => 'real' . uniqid() . '@test.local',
            'phone'         => '9876543210',
            'status'        => 'active',
        ]);

        $user = $this->makeUserWithRole($this->society, $role, ['owner_id' => $owner->id]);

        $this->assertNotSame(
            $owner->id,
            $user->id,
            'fixture is useless if owners.id equals users.id'
        );

        return [$owner, $user];
    }

    private function villa(Society $society, string $number = 'V-1'): Flat
    {
        return Flat::create([
            'society_id'    => $society->id,
            'property_type' => 'villa',
            'flat_number'   => $number,
            'villa_name'    => 'Villa ' . $number,
            'status'        => 'vacant',
        ]);
    }

    private function apartment(Society $society, string $number = 'A-1'): Flat
    {
        return Flat::create([
            'society_id'    => $society->id,
            'building_id'   => $this->makeBuilding($society, 'Tower ' . uniqid())->id,
            'property_type' => 'apartment',
            'flat_number'   => $number,
            'floor'         => 1,
            'status'        => 'vacant',
        ]);
    }

    // ------------------------------------------------------------ the schema

    public function test_flats_owner_id_references_the_owners_table(): void
    {
        $owner = Owner::create([
            'society_id' => $this->society->id,
            'name'       => 'Schema Owner',
            'email'      => 'schema' . uniqid() . '@test.local',
            'phone'      => '9876543210',
            'status'     => 'active',
        ]);

        $flat = $this->apartment($this->society);
        $flat->update(['owner_id' => $owner->id]);

        $this->assertTrue($flat->fresh()->ownerModel->is($owner));
    }

    // ------------------------------------------------- apartment assignment

    public function test_assigning_an_apartment_stores_the_owner_id_not_the_user_id(): void
    {
        [$owner, $user] = $this->ownerWithUser('Apartment Owner');
        $flat = $this->apartment($this->society);

        $this->actingAs($this->admin)
            ->post(route('admin.apartment-assignments.assign'), [
                'user_id'      => $user->id,
                'apartment_id' => $flat->id,
            ]);

        $this->assertSame(
            $owner->id,
            $flat->fresh()->owner_id,
            'the flat must carry the owners.id, never the users.id'
        );
    }

    // ----------------------------------------------------- villa assignment

    public function test_assigning_a_villa_stores_the_owner_id_not_the_user_id(): void
    {
        [$owner, $user] = $this->ownerWithUser('Villa Owner', 'villa');
        $villa = $this->villa($this->society);

        $this->actingAs($this->admin)
            ->post(route('admin.villa-assignments.assign'), [
                'user_id'  => $user->id,
                'villa_id' => $villa->id,
            ]);

        $this->assertSame($owner->id, $villa->fresh()->owner_id);
    }

    /** The villa screen must not accept an apartment. */
    public function test_villa_assignment_rejects_an_apartment(): void
    {
        [, $user] = $this->ownerWithUser('Villa Owner', 'villa');
        $flat = $this->apartment($this->society);

        $this->actingAs($this->admin)
            ->post(route('admin.villa-assignments.assign'), [
                'user_id'  => $user->id,
                'villa_id' => $flat->id,
            ]);

        $this->assertNull($flat->fresh()->owner_id);
    }

    /** ...and the apartment screen must not accept a villa. */
    public function test_apartment_assignment_rejects_a_villa(): void
    {
        [, $user] = $this->ownerWithUser('Apartment Owner');
        $villa = $this->villa($this->society);

        $this->actingAs($this->admin)
            ->post(route('admin.apartment-assignments.assign'), [
                'user_id'      => $user->id,
                'apartment_id' => $villa->id,
            ]);

        $this->assertNull($villa->fresh()->owner_id);
    }

    // -------------------------------------------------------- property transfer

    public function test_reassignment_moves_ownership_and_stores_the_owner_id(): void
    {
        [$owner, $user] = $this->ownerWithUser('Villa Owner', 'villa');

        $from = $this->villa($this->society, 'V-FROM');
        $to   = $this->villa($this->society, 'V-TO');

        $from->update(['owner_id' => $owner->id, 'status' => 'occupied']);

        $this->actingAs($this->admin)
            ->post(route('admin.villa-assignments.reassign'), [
                'current_user_id' => $user->id,
                'new_villa_id'    => $to->id,
            ]);

        $this->assertNull($from->fresh()->owner_id, 'the old villa must be released');
        $this->assertSame($owner->id, $to->fresh()->owner_id);
    }

    public function test_unassignment_clears_ownership(): void
    {
        [$owner, $user] = $this->ownerWithUser('Villa Owner', 'villa');
        $villa = $this->villa($this->society);
        $villa->update(['owner_id' => $owner->id, 'status' => 'occupied']);

        $this->actingAs($this->admin)
            ->post(route('admin.villa-assignments.unassign'), ['user_id' => $user->id]);

        $this->assertNull($villa->fresh()->owner_id);
    }

    // ------------------------------------------------------------ duplicates

    public function test_an_already_owned_villa_cannot_be_reassigned(): void
    {
        [$firstOwner, ]   = $this->ownerWithUser('Villa Owner', 'villa');
        [, $secondUser]   = $this->ownerWithUser('Villa Owner', 'villa');

        $villa = $this->villa($this->society);
        $villa->update(['owner_id' => $firstOwner->id, 'status' => 'occupied']);

        $this->actingAs($this->admin)
            ->post(route('admin.villa-assignments.assign'), [
                'user_id'  => $secondUser->id,
                'villa_id' => $villa->id,
            ]);

        $this->assertSame($firstOwner->id, $villa->fresh()->owner_id);
    }

    // -------------------------------------------------------- society isolation

    public function test_a_villa_from_another_society_cannot_be_assigned(): void
    {
        [, $user] = $this->ownerWithUser('Villa Owner', 'villa');

        $other = $this->makeSociety('Other Society');
        $villa = $this->villa($other, 'V-FOREIGN');

        $this->actingAs($this->admin)
            ->post(route('admin.villa-assignments.assign'), [
                'user_id'  => $user->id,
                'villa_id' => $villa->id,
            ]);

        $this->assertNull($villa->fresh()->owner_id);
    }

    /** An owner from another society must not be attached to a local flat. */
    public function test_assignment_rejects_a_user_from_another_society(): void
    {
        $other = $this->makeSociety('Other Society');

        $foreignOwner = Owner::create([
            'society_id' => $other->id,
            'name'       => 'Foreign Owner',
            'email'      => 'foreign' . uniqid() . '@test.local',
            'phone'      => '9876543210',
            'status'     => 'active',
        ]);
        $foreignUser = $this->makeUserWithRole($other, 'Villa Owner', ['owner_id' => $foreignOwner->id]);

        $villa = $this->villa($this->society);

        $this->actingAs($this->admin)
            ->post(route('admin.villa-assignments.assign'), [
                'user_id'  => $foreignUser->id,
                'villa_id' => $villa->id,
            ]);

        $this->assertNull($villa->fresh()->owner_id);
    }

    // ------------------------------------------------ cross-society FK injection

    /**
     * `exists:owners,id` proves an owner exists, not that it is ours. Paired
     * with a society_id taken from the session, a posted foreign owner id
     * produced a flat stamped society A but owned from society B.
     */
    public function test_a_flat_cannot_be_given_an_owner_from_another_society(): void
    {
        $other = $this->makeSociety('Other Society');
        $foreignOwner = Owner::create([
            'society_id' => $other->id,
            'name'       => 'Foreign Owner',
            'email'      => 'foreign' . uniqid() . '@test.local',
            'phone'      => '9876543210',
            'status'     => 'active',
        ]);

        $building = $this->makeBuilding($this->society, 'Local Tower');

        $this->actingAs($this->admin)->post('/flats', [
            'building_id' => $building->id,
            'flat_number' => 'X-1',
            'floor'       => 1,
            'type'        => '2BHK',
            'status'      => 'vacant',
            'owner_id'    => $foreignOwner->id,
        ])->assertSessionHasErrors('owner_id');

        $this->assertDatabaseMissing('flats', ['flat_number' => 'X-1']);
    }

    /** The same for the parent building. */
    public function test_a_flat_cannot_be_parented_to_another_societys_building(): void
    {
        $other = $this->makeSociety('Other Society');
        $foreignBuilding = $this->makeBuilding($other, 'Foreign Tower');

        $this->actingAs($this->admin)->post('/flats', [
            'building_id' => $foreignBuilding->id,
            'flat_number' => 'X-2',
            'floor'       => 1,
            'type'        => '2BHK',
            'status'      => 'vacant',
        ])->assertSessionHasErrors('building_id');

        $this->assertDatabaseMissing('flats', ['flat_number' => 'X-2']);
    }

    /** A legitimate local building is still accepted. */
    public function test_a_flat_in_the_admins_own_society_is_created(): void
    {
        $building = $this->makeBuilding($this->society, 'Own Tower');

        $this->actingAs($this->admin)->post('/flats', [
            'building_id' => $building->id,
            'flat_number' => 'OK-1',
            'floor'       => 1,
            'type'        => '2BHK',
            'status'      => 'vacant',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('flats', [
            'flat_number' => 'OK-1',
            'society_id'  => $this->society->id,
        ]);
    }

    // --------------------------------------------------------- relationships

    /**
     * Flat::ownerUser() resolved owner_id against the users table, so it
     * returned whichever user happened to share that integer.
     */
    public function test_owner_user_relationship_resolves_through_the_owner(): void
    {
        [$owner, $user] = $this->ownerWithUser('Apartment Owner');

        $flat = $this->apartment($this->society);
        $flat->update(['owner_id' => $owner->id]);

        $resolved = $flat->fresh()->ownerUser;

        $this->assertNotNull($resolved, 'the flat should reach its owner login account');
        $this->assertSame($user->id, $resolved->id);
        $this->assertSame($user->email, $resolved->email);
    }

    /** A flat owned by nobody must not borrow an unrelated user. */
    public function test_an_unowned_flat_has_no_owner_user(): void
    {
        $flat = $this->apartment($this->society);

        $this->assertNull($flat->ownerUser);
        $this->assertNull($flat->ownerModel);
    }

    /**
     * An owner need not have a login account, and the property still has an
     * owner. Display must come from the Owner record, or such a villa reads
     * "Not Assigned" while plainly being owned.
     */
    public function test_a_property_owned_by_an_accountless_owner_still_reports_its_owner(): void
    {
        $owner = Owner::create([
            'society_id'    => $this->society->id,
            'property_type' => 'villa',
            'name'          => 'Accountless Owner',
            'email'         => 'noaccount' . uniqid() . '@test.local',
            'phone'         => '9876543210',
            'status'        => 'active',
        ]);

        $villa = $this->villa($this->society);
        $villa->update(['owner_id' => $owner->id]);
        $villa = $villa->fresh();

        $this->assertNull($villa->ownerUser, 'this owner deliberately has no account');
        $this->assertSame('Accountless Owner', $villa->ownerModel->name);

        $html = $this->actingAs($this->admin)
            ->get(route('villas.show', $villa))->assertOk()->getContent();

        $this->assertStringContainsString('Accountless Owner', $html);
        $this->assertStringNotContainsString('Not Assigned', $html);
    }

    /** No row anywhere may hold an owner_id that is not a real owner. */
    public function test_no_flat_holds_a_dangling_owner_id(): void
    {
        [$owner, $user] = $this->ownerWithUser('Apartment Owner');
        $flat = $this->apartment($this->society);

        $this->actingAs($this->admin)
            ->post(route('admin.apartment-assignments.assign'), [
                'user_id'      => $user->id,
                'apartment_id' => $flat->id,
            ]);

        $dangling = Flat::whereNotNull('owner_id')
            ->whereNotIn('owner_id', Owner::query()->select('id'))
            ->count();

        $this->assertSame(0, $dangling);
    }
}
