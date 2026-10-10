<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opening a page must never write ownership or tenancy data.
 *
 * DashboardController (and Tenant\RentController) used to "auto-fix" an
 * account with no property by INVENTING one on page load:
 *   - an Owner with no unit got a brand-new flat (random floor, bedrooms,
 *     bathrooms and area) assigned to them, plus a Resident row;
 *   - a Tenant with no tenancy got a Tenant record with a random rent
 *     (15,000-25,000) and deposit, placed on the FIRST vacant flat of the
 *     society, plus a Resident row.
 * That is how a tenant ended up with a stale residents row on a vacant villa
 * they never rented (Royal Eden: tenant on Villa 159, residents row on Villa
 * 160), and how an owner whose unit simply had not been assigned yet would
 * have been given a made-up one.
 *
 * It also linked a tenant login to any tenant sharing a display name - across
 * every society - and persisted tenant_id. Identity is linked by stable ids
 * or a verified unique email inside the SAME society, never by a name.
 */
class DashboardDoesNotFabricateDataTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(): array
    {
        return [
            'flats'     => Flat::count(),
            'buildings' => Building::count(),
            'owners'    => Owner::count(),
            'tenants'   => Tenant::count(),
            'residents' => Resident::count(),
            'vacant'    => Flat::where('status', 'vacant')->count(),
        ];
    }

    private function villaSociety(): Society
    {
        $society = $this->makeSociety('Fabrication Society ' . uniqid());
        $society->update(['property_type' => 'villa']);

        return $society;
    }

    // ------------------------------------------------------------- owners

    public function test_an_owner_with_no_property_sees_an_honest_empty_state_and_nothing_is_created(): void
    {
        $this->makeRole('Owner');
        $society = $this->villaSociety();
        $owner   = Owner::create(['society_id' => $society->id, 'name' => 'Suraj', 'email' => 'suraj@test.local', 'phone' => '9000000001', 'status' => 'active', 'property_type' => 'villa']);
        $user    = $this->makeUserWithRole($society, 'Owner', ['owner_id' => $owner->id]);
        $before  = $this->snapshot();

        $res = $this->actingAs($user)->followingRedirects()->get('/dashboard');

        $res->assertOk();
        $this->assertSame($before, $this->snapshot(), 'a dashboard view must not create flats, buildings, residents or owners');
        $this->assertNull(Flat::where('owner_id', $owner->id)->first(), 'no unit was invented for the owner');
        $res->assertDontSee('Tower A');
    }

    public function test_an_owner_who_really_owns_a_unit_still_sees_it_and_nothing_extra_is_created(): void
    {
        $this->makeRole('Owner');
        $society = $this->villaSociety();
        $owner   = Owner::create(['society_id' => $society->id, 'name' => 'Real Owner', 'email' => 'real@test.local', 'phone' => '9000000002', 'status' => 'active', 'property_type' => 'villa']);
        $user    = $this->makeUserWithRole($society, 'Owner', ['owner_id' => $owner->id]);
        $villa   = Flat::create(['society_id' => $society->id, 'property_type' => 'villa', 'flat_number' => '158', 'status' => 'occupied', 'owner_id' => $owner->id]);
        $before  = $this->snapshot();

        $this->actingAs($user)->followingRedirects()->get('/dashboard')->assertOk()->assertSee('158');

        $this->assertSame($before, $this->snapshot());
    }

    // ------------------------------------------------------------- tenants

    public function test_a_tenant_with_no_tenancy_is_not_given_a_made_up_one_on_the_first_vacant_flat(): void
    {
        $this->makeRole('Tenant');
        $society = $this->villaSociety();
        $vacant  = Flat::create(['society_id' => $society->id, 'property_type' => 'villa', 'flat_number' => '160', 'status' => 'vacant']);
        $user    = $this->makeUserWithRole($society, 'Tenant', ['name' => 'Piyus']);
        $before  = $this->snapshot();

        $this->actingAs($user)->followingRedirects()->get('/dashboard')->assertOk();
        $this->actingAs($user)->followingRedirects()->get(route('tenant.rent'))->assertOk();

        $this->assertSame($before, $this->snapshot(), 'no tenancy, resident row or flat may be created by viewing a page');
        $this->assertSame('vacant', $vacant->fresh()->status, 'the vacant villa must stay vacant');
        $this->assertNull($user->fresh()->tenant_id);
        $this->assertSame(0, Resident::where('user_id', $user->id)->count());
    }

    public function test_the_tenant_rent_page_for_an_unassigned_tenant_explains_instead_of_inventing(): void
    {
        $this->makeRole('Tenant');
        $society = $this->villaSociety();
        $user    = $this->makeUserWithRole($society, 'Tenant');

        $this->actingAs($user)->followingRedirects()->get(route('tenant.rent'))
            ->assertOk()->assertSee('No apartment assigned to your account');
    }

    public function test_a_tenant_is_never_linked_to_another_societys_tenant_with_the_same_name(): void
    {
        $this->makeRole('Tenant');
        $mine   = $this->villaSociety();
        $theirs = $this->villaSociety();
        $theirFlat = Flat::create(['society_id' => $theirs->id, 'property_type' => 'villa', 'flat_number' => 'ZZ-7731', 'status' => 'on_rent']);
        $theirTenant = Tenant::create([
            'society_id' => $theirs->id, 'flat_id' => $theirFlat->id, 'name' => 'Same Name', 'email' => 'someone.else@test.local',
            'phone' => '9000000003', 'move_in_date' => '2026-01-01', 'status' => 'active',
        ]);
        $user = $this->makeUserWithRole($mine, 'Tenant', ['name' => 'Same Name', 'email' => 'me@test.local']);

        $res = $this->actingAs($user)->followingRedirects()->get('/dashboard');

        $res->assertOk()->assertDontSee('ZZ-7731');
        $this->assertNull($user->fresh()->tenant_id, 'a shared display name proves nothing');
        $this->assertNull($theirTenant->fresh()->user_id);
    }

    public function test_a_tenant_in_the_same_society_with_the_same_name_but_a_different_email_is_not_linked_either(): void
    {
        $this->makeRole('Tenant');
        $society = $this->villaSociety();
        $flat = Flat::create(['society_id' => $society->id, 'property_type' => 'villa', 'flat_number' => '159', 'status' => 'on_rent']);
        $other = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'name' => 'Common Name', 'email' => 'other.person@test.local',
            'phone' => '9000000004', 'move_in_date' => '2026-01-01', 'status' => 'active',
        ]);
        $user = $this->makeUserWithRole($society, 'Tenant', ['name' => 'Common Name', 'email' => 'different.person@test.local']);

        $this->actingAs($user)->followingRedirects()->get('/dashboard')->assertOk();

        $this->assertNull($user->fresh()->tenant_id);
    }

    public function test_a_tenant_whose_verified_email_matches_their_own_societys_tenant_record_is_linked(): void
    {
        $this->makeRole('Tenant');
        $society = $this->villaSociety();
        $flat = Flat::create(['society_id' => $society->id, 'property_type' => 'villa', 'flat_number' => '159', 'status' => 'on_rent']);
        $tenant = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'name' => 'Piyus', 'email' => 'piyus@test.local',
            'phone' => '9000000005', 'move_in_date' => '2026-01-01', 'status' => 'active',
        ]);
        $user = $this->makeUserWithRole($society, 'Tenant', ['name' => 'Piyus P', 'email' => 'piyus@test.local']);
        $before = $this->snapshot();

        $this->actingAs($user)->followingRedirects()->get('/dashboard')->assertOk()->assertSee('159');

        $this->assertSame($tenant->id, $user->fresh()->tenant_id);
        $this->assertSame($before, $this->snapshot());
    }
}
