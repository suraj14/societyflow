<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UtilityBill;
use App\Support\UserFlatResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The utility bills module was entirely unreachable: the controller
 * authorized against a UtilityBill policy that was never written or
 * registered, so Gate found neither policy nor ability and denied every
 * show/edit/update/destroy for every role - Super Admin included.
 *
 * Also covers UserFlatResolver, which replaced per-controller ownership
 * logic that compared flats.owner_id (a foreign key to owners.id) against
 * users.id. That matched nothing for most owners and, where a users.id
 * happened to equal a real owners.id, matched a different person's flats.
 */
class UtilityBillAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeBill(Society $society, Flat $flat): UtilityBill
    {
        return UtilityBill::create([
            'society_id'  => $society->id,
            'flat_id'     => $flat->id,
            'bill_type'   => 'Electricity',
            'bill_amount' => 1200,
            'bill_date'   => now()->toDateString(),
            'due_date'    => now()->addDays(15)->toDateString(),
            'status'      => 'unpaid',
        ]);
    }

    public function test_admin_can_view_a_utility_bill_in_their_society(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $bill    = $this->makeBill($society, $flat);

        $this->actingAs($admin)->get("/utility-bills/{$bill->id}")->assertOk();
    }

    public function test_admin_can_open_the_edit_screen(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $bill    = $this->makeBill($society, $flat);

        $this->actingAs($admin)->get("/utility-bills/{$bill->id}/edit")->assertOk();
    }

    public function test_admin_cannot_view_a_bill_from_another_society(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $flatB    = $this->makeFlat($societyB, $this->makeBuilding($societyB));
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');
        $billB    = $this->makeBill($societyB, $flatB);

        $this->actingAs($adminA)->get("/utility-bills/{$billB->id}")->assertForbidden();
    }

    public function test_resident_can_view_a_bill_for_their_own_flat(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $resident->assignRole($this->makeRole('Tenant')->name);

        $bill = $this->makeBill($society, $flat);

        $this->actingAs($resident)->get("/utility-bills/{$bill->id}")->assertOk();
    }

    public function test_resident_cannot_view_a_bill_for_someone_elses_flat(): void
    {
        $society   = $this->makeSociety();
        $building  = $this->makeBuilding($society);
        $ownFlat   = $this->makeFlat($society, $building, '101');
        $otherFlat = $this->makeFlat($society, $building, '102');

        $resident = $this->makeResidentUser($society, $ownFlat);
        $resident->assignRole($this->makeRole('Tenant')->name);

        $bill = $this->makeBill($society, $otherFlat);

        $this->actingAs($resident)->get("/utility-bills/{$bill->id}")->assertForbidden();
    }

    public function test_resident_cannot_delete_a_bill(): void
    {
        $society  = $this->makeSociety();
        $flat     = $this->makeFlat($society, $this->makeBuilding($society));
        $resident = $this->makeResidentUser($society, $flat);
        $resident->assignRole($this->makeRole('Tenant')->name);

        $bill = $this->makeBill($society, $flat);

        $this->actingAs($resident)->delete("/utility-bills/{$bill->id}")->assertForbidden();
        $this->assertDatabaseHas('utility_bills', ['id' => $bill->id]);
    }

    // ── UserFlatResolver ─────────────────────────────────────────────────────

    public function test_resolver_finds_flats_owned_through_the_owners_table(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '301');

        $owner = Owner::create([
            'society_id'    => $society->id,
            'name'          => 'Real Owner',
            'email'         => 'realowner@test.local',
            'status'        => 'active',
            'property_type' => 'apartment',
            'building_id'   => $building->id,
            'flat_no'       => '301',
        ]);

        $flat->update(['owner_id' => $owner->id]);

        $user = User::create([
            'society_id' => $society->id,
            'owner_id'   => $owner->id,
            'name'       => 'Real Owner',
            'email'      => 'realowneruser@test.local',
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);

        $this->assertContains($flat->id, UserFlatResolver::flatIdsFor($user));
    }

    /**
     * flats.owner_id is unpopulated for most rows in real data, so ownership
     * has to fall back to the address the owner record itself carries.
     */
    public function test_resolver_falls_back_to_the_owner_record_address(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '302');
        // flat->owner_id deliberately left null.

        $owner = Owner::create([
            'society_id'    => $society->id,
            'name'          => 'Unlinked Owner',
            'email'         => 'unlinked@test.local',
            'status'        => 'active',
            'property_type' => 'apartment',
            'building_id'   => $building->id,
            'flat_no'       => '302',
        ]);

        $user = User::create([
            'society_id' => $society->id,
            'owner_id'   => $owner->id,
            'name'       => 'Unlinked Owner',
            'email'      => 'unlinkeduser@test.local',
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);

        $this->assertContains($flat->id, UserFlatResolver::flatIdsFor($user));
    }

    /** Tenancy was ignored entirely, so tenant-only accounts saw nothing. */
    public function test_resolver_finds_flats_held_through_tenancy(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '303');

        $user = User::create([
            'society_id' => $society->id,
            'name'       => 'Renter',
            'email'      => 'renter@test.local',
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);

        $tenant = Tenant::create([
            'society_id'   => $society->id,
            'flat_id'      => $flat->id,
            'user_id'      => $user->id,
            'name'         => 'Renter',
            'email'        => 'renter@test.local',
            'move_in_date' => now()->toDateString(),
            'status'       => 'active',
        ]);

        $user->update(['tenant_id' => $tenant->id]);

        $this->assertContains($flat->id, UserFlatResolver::flatIdsFor($user->fresh()));
    }

    /** A users.id that collides with an unrelated owners.id must not match. */
    public function test_resolver_does_not_match_flats_by_user_id_collision(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '304');

        $strangerOwner = Owner::create([
            'society_id' => $society->id,
            'name'       => 'Stranger',
            'email'      => 'stranger@test.local',
            'status'     => 'active',
        ]);

        $flat->update(['owner_id' => $strangerOwner->id]);

        // A user with no owner link at all must never see that flat.
        $unrelated = User::create([
            'society_id' => $society->id,
            'name'       => 'Unrelated',
            'email'      => 'unrelated@test.local',
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);

        $this->assertNotContains($flat->id, UserFlatResolver::flatIdsFor($unrelated));
    }

    /**
     * A flat with no owner_id belongs to nobody.
     *
     * Most unset owner_id values are exactly this - a unit nobody owns yet,
     * not a link waiting to be repaired. The resolver must not hand such a
     * flat to a user merely because they own something else nearby.
     */
    public function test_resolver_does_not_claim_an_unowned_flat(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);

        $owner = Owner::create([
            'society_id' => $society->id,
            'name'       => 'Real Owner',
            'email'      => 'realowner' . uniqid() . '@test.local',
            'status'     => 'active',
        ]);

        $owned   = $this->makeFlat($society, $building, '401');
        $unowned = $this->makeFlat($society, $building, '402');
        $owned->update(['owner_id' => $owner->id]);

        $user = User::create([
            'society_id' => $society->id,
            'owner_id'   => $owner->id,
            'name'       => 'Real Owner',
            'email'      => 'realuser' . uniqid() . '@test.local',
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);

        $resolved = UserFlatResolver::flatIdsFor($user);

        $this->assertContains($owned->id, $resolved);
        $this->assertNotContains($unowned->id, $resolved, 'an unowned flat must stay unowned');
        $this->assertNull($unowned->fresh()->owner_id);
    }

    /**
     * An owner need not have a login account, and the flat is still owned.
     *
     * This is why display reads the Owner record: ownerUser is null here,
     * but the property plainly has an owner.
     */
    public function test_a_flat_owned_by_an_accountless_owner_is_still_owned(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '403');

        $owner = Owner::create([
            'society_id' => $society->id,
            'name'       => 'No Account Owner',
            'email'      => 'noaccount' . uniqid() . '@test.local',
            'status'     => 'active',
        ]);

        $flat->update(['owner_id' => $owner->id]);
        $flat = $flat->fresh();

        $this->assertSame($owner->id, $flat->owner_id);
        $this->assertSame('No Account Owner', $flat->ownerModel->name);
        $this->assertNull($flat->ownerUser, 'this owner deliberately has no login account');

        // And nobody resolves it, because no user is linked to that owner.
        $bystander = User::create([
            'society_id' => $society->id,
            'name'       => 'Bystander',
            'email'      => 'bystander' . uniqid() . '@test.local',
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);

        $this->assertNotContains($flat->id, UserFlatResolver::flatIdsFor($bystander));
    }

    /**
     * Deleting an owner detaches everything that pointed at it, and the
     * resolver then finds nothing for that user.
     *
     * This test previously asserted the opposite for users.owner_id: the
     * column had no foreign key, so a deleted owner left a dangling id
     * behind. 2026_09_15_000001 added the constraint it was always meant to
     * have, so both sides now clear. Ownership of the *account* is untouched
     * - only the link goes.
     */
    public function test_resolver_survives_an_owner_record_that_no_longer_exists(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '404');

        $owner = Owner::create([
            'society_id' => $society->id,
            'name'       => 'Doomed Owner',
            'email'      => 'doomed' . uniqid() . '@test.local',
            'status'     => 'active',
        ]);

        $flat->update(['owner_id' => $owner->id]);

        $user = User::create([
            'society_id' => $society->id,
            'owner_id'   => $owner->id,
            'name'       => 'Doomed Owner',
            'email'      => 'doomeduser' . uniqid() . '@test.local',
            'password'   => bcrypt('x'),
            'status'     => 'active',
        ]);

        $owner->delete();

        // users.owner_id is ON DELETE SET NULL on both engines after
        // 2026_09_15_000001.
        $this->assertNull($user->fresh()->owner_id, 'the account link is cleared, not left dangling');

        // flats.owner_id only has its constraint on SQLite. On MySQL the
        // migrations that declared it did so inside a table rebuild that runs
        // for SQLite alone, so the foreign key was never created there and a
        // deleted owner leaves flats.owner_id dangling. Filed as P1-16;
        // asserted per engine so neither behaviour drifts unnoticed.
        if (DB::getDriverName() === 'sqlite') {
            $this->assertNull($flat->fresh()->owner_id, 'the flat is released with its owner');
        } else {
            $this->assertNotNull(
                $flat->fresh()->owner_id,
                'if MySQL now clears this, flats.owner_id gained its constraint - close P1-16'
            );
        }

        // The account itself survives; it simply owns nothing now.
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame([], UserFlatResolver::flatIdsFor($user->fresh()));
    }
}
