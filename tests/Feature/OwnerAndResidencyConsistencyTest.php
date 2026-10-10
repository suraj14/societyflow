<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\VillaArea;
use App\Support\PropertyOccupancy;
use App\Support\UserFlatResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two real-data inconsistencies, reproduced with fixtures of the same shape:
 *
 * 1. An owner whose own record names a villa (villa_area_id + villa_no) while
 *    flats.owner_id was never filled in. Owner Management and Property &
 *    Occupancy already treat that as "owned"; the assignment screens listed
 *    the owner as unassigned and the villa as Available, so the villa could be
 *    handed to somebody else.
 *
 * 2. A tenant moved from one unit to another by editing the tenancy. The
 *    legacy residents row for the old unit was left active, so the tenant
 *    resolved to BOTH units (the old unit's bills and bookings stayed visible).
 */
class OwnerAndResidencyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private VillaArea $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Eden');
        $this->area = VillaArea::create(['society_id' => $this->society->id, 'name' => 'Size 25x50', 'status' => 'active']);
    }

    private function villa(string $no, array $over = []): Flat
    {
        return Flat::create(array_merge([
            'society_id' => $this->society->id, 'villa_area_id' => $this->area->id, 'property_type' => 'villa',
            'flat_number' => $no, 'status' => 'vacant',
        ], $over));
    }

    private function ownerWithAddress(string $name, string $villaNo): array
    {
        $owner = Owner::create([
            'society_id' => $this->society->id, 'name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)) . '@t.local',
            'phone' => '9' . random_int(100000000, 999999999), 'status' => 'active',
            'property_type' => 'villa', 'villa_area_id' => $this->area->id, 'villa_no' => $villaNo,
        ]);
        $user = $this->makeUserWithRole($this->society, 'Owner', ['name' => $name, 'owner_id' => $owner->id]);

        return [$owner, $user];
    }

    // ----------------------------------------------------------------- 1. owner / villa

    public function test_all_screens_agree_the_owner_whose_record_names_a_villa_owns_it(): void
    {
        $villa = $this->villa('160', ['villa_name' => 'Suman Sadan']);
        [$owner, $user] = $this->ownerWithAddress('Suraj Suman', '160');
        $this->assertNull($villa->owner_id, 'precondition: owner_id never populated');

        // The canonical resolvers.
        $this->assertSame($owner->id, $villa->resolvedOwner()->id);
        $this->assertSame([$villa->id], UserFlatResolver::ownedFlatIds($user)->all());
        $this->assertSame(PropertyOccupancy::OWNER, $villa->occupancyState());

        // The assignment screen now says the same.
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $response = $this->actingAs($admin)->get(route('admin.villa-assignments.index'));
        $response->assertOk();

        $available = $response->viewData('availableVillas')->firstWhere('id', $villa->id);
        $this->assertSame($owner->id, $available->declaredOwner->id);
        $unassigned = $response->viewData('unassignedVillaOwners')->firstWhere('id', $user->id);
        $this->assertSame($villa->id, $unassigned->declaredUnit->id);
        $response->assertSee('Recorded owner: Suraj Suman');
        $response->assertSee('Owner record names Suman Sadan');
    }

    public function test_a_villa_recorded_as_someone_elses_cannot_be_assigned_to_a_different_owner(): void
    {
        $villa = $this->villa('160');
        $this->ownerWithAddress('Suraj Suman', '160');
        [$otherOwner, $other] = $this->ownerWithAddress('Naresh Suman', '158');
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $this->actingAs($admin)->post(route('admin.villa-assignments.assign'), ['user_id' => $other->id, 'villa_id' => $villa->id])
            ->assertSessionHas('error');

        $this->assertNull($villa->fresh()->owner_id, 'nothing was written');
        $this->assertSame([], UserFlatResolver::ownedFlatIds($other)->all());
    }

    public function test_the_recorded_owner_can_confirm_the_link_explicitly(): void
    {
        $villa = $this->villa('160');
        [$owner, $user] = $this->ownerWithAddress('Suraj Suman', '160');
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $this->actingAs($admin)->post(route('admin.villa-assignments.assign'), ['user_id' => $user->id, 'villa_id' => $villa->id])
            ->assertSessionHas('success');

        $this->assertSame($owner->id, $villa->fresh()->owner_id);
    }

    public function test_reassign_cannot_move_an_owner_into_a_villa_recorded_as_someone_elses(): void
    {
        $mine = $this->villa('158');
        [$owner, $user] = $this->ownerWithAddress('Naresh Suman', '158');
        $mine->update(['owner_id' => $owner->id]);
        $theirs = $this->villa('160');
        $this->ownerWithAddress('Suraj Suman', '160');
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $this->actingAs($admin)->post(route('admin.villa-assignments.reassign'), ['current_user_id' => $user->id, 'new_villa_id' => $theirs->id])
            ->assertSessionHas('error');

        $this->assertSame($owner->id, $mine->fresh()->owner_id, 'the original villa stays assigned');
        $this->assertNull($theirs->fresh()->owner_id);
    }

    public function test_a_genuinely_unowned_villa_is_still_available_and_assignable(): void
    {
        $free = $this->villa('165');
        [$owner, $user] = $this->ownerWithAddress('New Owner', '999');   // address matches no villa
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $response = $this->actingAs($admin)->get(route('admin.villa-assignments.index'));
        $this->assertNull($response->viewData('availableVillas')->firstWhere('id', $free->id)->declaredOwner);
        $response->assertSee('Available');

        $this->actingAs($admin)->post(route('admin.villa-assignments.assign'), ['user_id' => $user->id, 'villa_id' => $free->id])
            ->assertSessionHas('success');
        $this->assertSame($owner->id, $free->fresh()->owner_id);
    }

    public function test_occupancy_totals_count_the_recorded_owner_as_owner_occupied(): void
    {
        $villa = $this->villa('160');
        $this->villa('165');                                           // nobody
        $this->ownerWithAddress('Suraj Suman', '160');

        $states = PropertyOccupancy::statesFor(Flat::where('society_id', $this->society->id)->get());
        $counts = collect($states)->countBy('state')->all();

        $this->assertSame(1, $counts[PropertyOccupancy::OWNER] ?? 0);
        $this->assertSame(1, $counts[PropertyOccupancy::VACANT] ?? 0);
        $this->assertSame($villa->id, collect($states)->filter(fn ($s) => $s['state'] === PropertyOccupancy::OWNER)->keys()->first());
    }

    public function test_one_person_shows_one_photo_in_owner_management_user_management_and_the_mobile_api(): void
    {
        $this->makeRole('Admin');
        [$owner, $user] = $this->ownerWithAddress('Suraj Suman', '160');
        $path = 'owners/canonical-photo.jpg';
        $owner->update(['profile_image' => $path]);
        $user->update(['avatar' => $path]);
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $ownerPage = $this->actingAs($admin)->get(route('owners.show', $owner))->assertOk()->getContent();
        $userPage  = $this->actingAs($admin)->get(route('users.edit', $user))->assertOk()->getContent();
        $this->assertStringContainsString('storage/' . $path, $ownerPage, 'Owner Management shows the photo');
        $this->assertStringContainsString('storage/' . $path, $userPage, 'User Management shows the SAME photo');

        \Laravel\Sanctum\Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.avatar', '/storage/' . $path);
    }

    public function test_the_same_rule_protects_apartments(): void
    {
        $building = $this->makeBuilding($this->society);
        $flat = $this->makeFlat($this->society, $building, '202');
        $recorded = Owner::create(['society_id' => $this->society->id, 'name' => 'Recorded', 'email' => 'rec@t.local', 'phone' => '9000000071', 'status' => 'active', 'property_type' => 'apartment', 'building_id' => $building->id, 'flat_no' => '202']);
        $otherOwner = Owner::create(['society_id' => $this->society->id, 'name' => 'Other', 'email' => 'oth@t.local', 'phone' => '9000000072', 'status' => 'active', 'property_type' => 'apartment']);
        $other = $this->makeUserWithRole($this->society, 'Owner', ['owner_id' => $otherOwner->id]);
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $page = $this->actingAs($admin)->get(route('admin.apartment-assignments.index'));
        $this->assertSame($recorded->id, $page->viewData('availableApartments')->firstWhere('id', $flat->id)->declaredOwner->id);

        $this->actingAs($admin)->post(route('admin.apartment-assignments.assign'), ['user_id' => $other->id, 'apartment_id' => $flat->id])
            ->assertSessionHas('error');
        $this->assertNull($flat->fresh()->owner_id);
    }

    // ----------------------------------------------------------------- 2. stale residency

    private function tenantOf(Flat $flat, $user): Tenant
    {
        return Tenant::create([
            'society_id' => $this->society->id, 'flat_id' => $flat->id, 'user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'phone' => '9000000061', 'move_in_date' => now()->subDays(20), 'status' => 'active',
        ]);
    }

    private function residencyAt(Flat $flat, $user): Resident
    {
        return Resident::create([
            'society_id' => $this->society->id, 'flat_id' => $flat->id, 'user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'phone' => '9000000061', 'type' => 'tenant', 'move_in_date' => now()->subDays(20), 'status' => 'active',
        ]);
    }

    public function test_moving_a_tenant_to_another_unit_closes_the_residency_of_the_unit_they_left(): void
    {
        $old = $this->villa('160');
        $new = $this->villa('159');
        $user = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Piyus']);
        $tenant = $this->tenantOf($old, $user);
        $stale = $this->residencyAt($old, $user);
        $this->assertEqualsCanonicalizing([$old->id], UserFlatResolver::flatIdsFor($user));

        $tenant->update(['flat_id' => $new->id]);

        $this->assertSame([$new->id], UserFlatResolver::flatIdsFor($user->fresh()), 'only the current unit resolves');
        $stale->refresh();
        $this->assertSame('inactive', $stale->status);
        $this->assertNotNull($stale->move_out_date);
        $this->assertSame($old->id, $stale->flat_id, 'history row is kept, not deleted or re-pointed');
        $this->assertSame(1, Resident::where('user_id', $user->id)->count());
    }

    public function test_the_admin_edit_screen_and_move_out_do_the_same(): void
    {
        $old = $this->villa('160');
        $new = $this->villa('159');
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $user = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Piyus']);
        $tenant = $this->tenantOf($old, $user);
        $stale = $this->residencyAt($old, $user);

        // Move-out (the unit link is cleared).
        $this->actingAs($admin)->post(route('admin.tenants.move-out', $tenant))->assertSessionHas('success');
        $this->assertSame('inactive', $stale->fresh()->status);
        $this->assertSame([], UserFlatResolver::flatIdsFor($user->fresh()));

        // A different tenant's residency at the same unit is untouched.
        $bystander = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Bystander']);
        $bystanderRow = $this->residencyAt($new, $bystander);
        $tenant->update(['flat_id' => $new->id, 'status' => 'active']);   // re-placed elsewhere
        $this->assertSame('active', $bystanderRow->fresh()->status);
    }

    public function test_a_residency_for_the_unit_the_tenant_still_occupies_is_left_alone(): void
    {
        $unit = $this->villa('160');
        $other = $this->villa('159');
        $user = $this->makeUserWithRole($this->society, 'Tenant');
        $tenant = $this->tenantOf($unit, $user);
        $row = $this->residencyAt($unit, $user);

        $tenant->update(['monthly_rent' => 12000]);                // unrelated edit

        $this->assertSame('active', $row->fresh()->status);
        $this->assertSame([$unit->id], UserFlatResolver::flatIdsFor($user->fresh()));
        $this->assertNotNull($other);
    }

    public function test_the_audit_reports_a_contradiction_and_stops_reporting_it_once_closed(): void
    {
        $old = $this->villa('160');
        $new = $this->villa('159');
        $user = $this->makeUserWithRole($this->society, 'Tenant');
        $tenant = $this->tenantOf($new, $user);
        $row = $this->residencyAt($old, $user);                    // exactly the shape found in the dev data

        $found = \App\Support\DataIntegrityRepair::residentsContradictingTenancy();
        $this->assertCount(1, $found);

        $row->update(['status' => 'inactive']);                    // the reviewed repair
        $this->assertCount(0, \App\Support\DataIntegrityRepair::residentsContradictingTenancy());
        $this->assertNotNull($tenant);
    }
}
