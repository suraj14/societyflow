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
}
