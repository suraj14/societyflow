<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Resident;
use App\Models\Society;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin > Villa Assignments (/admin/villa-assignments), exercised through the
 * real routes, middleware and views - P1-77. Until now nothing hit this
 * controller over HTTP at all, which is how its two query-time relations
 * (whereHas/whereDoesntHave('ownedVilla'), broken until P1-75c) and an
 * unscoped unassign went unnoticed.
 *
 * Ids are pushed apart on purpose (padding owners/users) because
 * flats.owner_id references owners.id, NOT users.id (CLAUDE.md §5): a test
 * where the two integers happen to be equal proves nothing about which table
 * was consulted.
 */
class VillaAssignmentHttpTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private array $A;
    private array $B;

    protected function setUp(): void
    {
        parent::setUp();

        // Padding so owners.id and users.id never coincide.
        $pad = $this->makeSociety('Padding');
        for ($i = 0; $i < 4; $i++) {
            Owner::create(['society_id' => $pad->id, 'name' => "Pad $i", 'email' => "pad$i@test.local", 'phone' => '9000000000', 'status' => 'active']);
        }

        $this->a = $this->makeSociety('Society A');
        $this->b = $this->makeSociety('Society B');
        $this->A = $this->world($this->a, 'A');
        $this->B = $this->world($this->b, 'B');
    }

    /** One society's worth of fixtures: two vacant villas, one apartment, one owner with a login. */
    private function world(Society $society, string $tag): array
    {
        $area = VillaArea::create(['society_id' => $society->id, 'name' => "Area $tag", 'status' => 'active']);
        $villa = fn (int $n) => Flat::create([
            'society_id' => $society->id, 'villa_area_id' => $area->id, 'property_type' => 'villa',
            'flat_number' => "$tag-$n", 'villa_name' => "Villa $tag$n", 'status' => 'vacant',
        ]);
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => "Owner $tag", 'email' => strtolower($tag) . '.owner@test.local',
            'phone' => '9' . random_int(100000000, 999999999), 'status' => 'active', 'property_type' => 'villa',
        ]);

        return [
            'admin'     => $this->makeUserWithRole($society, 'Admin', ['name' => "Admin $tag"]),
            'owner'     => $owner,
            'login'     => $this->makeUserWithRole($society, 'Owner', ['name' => "Owner Login $tag", 'owner_id' => $owner->id]),
            'v1'        => $villa(1),
            'v2'        => $villa(2),
            'apartment' => $this->makeFlat($society, $this->makeBuilding($society, "Tower $tag"), "Apt-$tag"),
        ];
    }

    private function assign(User $by, User $user, Flat $villa)
    {
        return $this->actingAs($by)->post(route('admin.villa-assignments.assign'), ['user_id' => $user->id, 'villa_id' => $villa->id]);
    }

    // ================================================================= index

    public function test_the_screen_lists_unassigned_owners_and_available_villas_of_the_admins_society_only(): void
    {
        $res = $this->actingAs($this->A['admin'])->get(route('admin.villa-assignments.index'))->assertOk();

        $this->assertSame([$this->A['login']->id], $res->viewData('unassignedVillaOwners')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$this->A['v1']->id, $this->A['v2']->id], $res->viewData('availableVillas')->pluck('id')->all());
        $this->assertSame([], $res->viewData('assignedVillaOwners')->all());

        $res->assertSee('Owner Login A')->assertSee('Villa A1')->assertSee('Villa A2')
            ->assertDontSee('Owner Login B')->assertDontSee('Villa B1')->assertDontSee('Society B');
    }

    public function test_an_owner_moves_from_the_unassigned_to_the_assigned_list_once_given_a_villa(): void
    {
        // This is the query-time relation that silently matched nobody before P1-75c.
        $this->assign($this->A['admin'], $this->A['login'], $this->A['v1'])->assertSessionHas('success');

        $res = $this->actingAs($this->A['admin'])->get(route('admin.villa-assignments.index'))->assertOk();

        $this->assertSame([], $res->viewData('unassignedVillaOwners')->all(), 'no longer unassigned');
        $this->assertSame([$this->A['login']->id], $res->viewData('assignedVillaOwners')->pluck('id')->all());
        $this->assertSame($this->A['v1']->id, $res->viewData('assignedVillaOwners')->first()->ownedVilla->id);
        $this->assertSame([$this->A['v2']->id], $res->viewData('availableVillas')->pluck('id')->all(), 'only the other villa is still available');
        $res->assertSee('Assigned Villa Owners (1)');
    }

    public function test_an_empty_society_renders_without_error(): void
    {
        $empty = $this->makeSociety('Empty');
        $admin = $this->makeUserWithRole($empty, 'Admin');

        $this->actingAs($admin)->get(route('admin.villa-assignments.index'))->assertOk()
            ->assertSee('Unassigned Villa Owners (0)')->assertSee('Available Villas (0)');
    }

    // ============================================================ permissions

    public function test_only_admins_can_use_the_screen_every_other_role_and_guest_is_refused(): void
    {
        foreach (['Tenant', 'Owner', 'Guard', 'Staff', 'Accountant'] as $role) {
            $user = $this->makeUserWithRole($this->a, $role);

            $this->actingAs($user)->get(route('admin.villa-assignments.index'))->assertForbidden();
            $this->assign($user, $this->A['login'], $this->A['v1'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.villa-assignments.unassign'), ['user_id' => $this->A['login']->id])->assertForbidden();
            $this->actingAs($user)->post(route('admin.villa-assignments.reassign'), ['current_user_id' => $this->A['login']->id, 'new_villa_id' => $this->A['v2']->id])->assertForbidden();

            $this->assertNull($this->A['v1']->fresh()->owner_id, "$role changed an assignment");
        }

        $this->app['auth']->forgetGuards();
        $this->get(route('admin.villa-assignments.index'))->assertRedirect(route('login'));
        $this->post(route('admin.villa-assignments.assign'), ['user_id' => 1, 'villa_id' => 1])->assertRedirect(route('login'));
    }

    // ================================================================== assign

    public function test_assign_attributes_the_villa_to_the_owner_record_not_the_user_id(): void
    {
        $this->assertNotSame($this->A['owner']->id, $this->A['login']->id, 'fixture must keep the two id spaces apart');

        $this->assign($this->A['admin'], $this->A['login'], $this->A['v1'])
            ->assertSessionHas('success', 'Villa Villa A1 has been assigned to Owner Login A successfully!');

        $villa = $this->A['v1']->fresh();
        $this->assertSame($this->A['owner']->id, $villa->owner_id, 'flats.owner_id is owners.id');
        $this->assertSame('occupied', $villa->status);

        $resident = Resident::where('user_id', $this->A['login']->id)->sole();
        $this->assertSame($villa->id, $resident->flat_id);
        $this->assertSame($this->a->id, $resident->society_id);
        $this->assertSame('owner', $resident->type);
        $this->assertSame('active', $resident->status);
        $this->assertSame($villa->id, $this->A['login']->fresh()->ownedVilla->id, 'and the account now resolves its villa');
    }

    public function test_assign_requires_both_ids_and_they_must_exist(): void
    {
        $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.assign'), [])
            ->assertSessionHasErrors(['user_id', 'villa_id']);
        $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.assign'), ['user_id' => 999999, 'villa_id' => 999999])
            ->assertSessionHasErrors(['user_id', 'villa_id']);
        $this->assertSame(0, Resident::count());
    }

    /** @dataProvider refusedAssignments */
    public function test_assign_refuses_what_it_should_and_changes_nothing(string $case): void
    {
        $admin = $this->A['admin'];
        $user  = $this->A['login'];
        $villa = $this->A['v1'];
        $expect = null;

        switch ($case) {
            case 'user is not an owner':
                $user = $this->makeUserWithRole($this->a, 'Tenant');
                $expect = 'User must have the Owner role to be assigned a villa.';
                break;
            case 'villa already taken':
                $other = Owner::create(['society_id' => $this->a->id, 'name' => 'Other', 'email' => 'other@test.local', 'phone' => '9000000009', 'status' => 'active']);
                $villa->update(['owner_id' => $other->id]);
                $expect = 'This villa is already assigned to another owner.';
                break;
            case 'unit is an apartment':
                $villa = $this->A['apartment'];
                $expect = 'Selected unit is not a villa.';
                break;
            case 'villa belongs to another society':
                $villa = $this->B['v1'];
                $expect = 'Villa must belong to the same society.';
                break;
            case 'owner has no owner record':
                $user = $this->makeUserWithRole($this->a, 'Owner', ['owner_id' => null]);
                $expect = 'has no owner record yet';
                break;
            case 'owner account belongs to another society':
                $user = $this->B['login'];
                $expect = 'Owner must belong to the same society.';
                break;
        }

        $before = [Resident::count(), $villa->fresh()->owner_id, $villa->fresh()->status];

        $res = $this->assign($admin, $user, $villa)->assertSessionHas('error');
        $this->assertStringContainsString($expect, session('error'));
        $this->assertSame($before, [Resident::count(), $villa->fresh()->owner_id, $villa->fresh()->status], "$case changed data");
    }

    /** @return array<string, array{string}> */
    public static function refusedAssignments(): array
    {
        return array_combine(
            ['user is not an owner', 'villa already taken', 'unit is an apartment', 'villa belongs to another society', 'owner has no owner record', 'owner account belongs to another society'],
            array_map(fn ($c) => [$c], ['user is not an owner', 'villa already taken', 'unit is an apartment', 'villa belongs to another society', 'owner has no owner record', 'owner account belongs to another society'])
        );
    }

    // ================================================================ unassign

    public function test_unassign_frees_the_villa_and_closes_the_residency(): void
    {
        $this->assign($this->A['admin'], $this->A['login'], $this->A['v1']);

        $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.unassign'), ['user_id' => $this->A['login']->id])
            ->assertSessionHas('success');

        $villa = $this->A['v1']->fresh();
        $this->assertNull($villa->owner_id);
        $this->assertSame('vacant', $villa->status);
        $resident = Resident::where('user_id', $this->A['login']->id)->sole();
        $this->assertSame('inactive', $resident->status);
        $this->assertNotNull($resident->move_out_date);
        $this->assertNull($this->A['login']->fresh()->ownedVilla);

        $res = $this->actingAs($this->A['admin'])->get(route('admin.villa-assignments.index'))->assertOk();
        $this->assertSame([$this->A['login']->id], $res->viewData('unassignedVillaOwners')->pluck('id')->all(), 'back in the unassigned list');
    }

    public function test_unassign_without_a_villa_or_with_a_missing_user_is_refused(): void
    {
        $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.unassign'), ['user_id' => $this->A['login']->id])
            ->assertSessionHas('error', 'User does not have an assigned villa.');
        $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.unassign'), [])->assertSessionHasErrors('user_id');
        $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.unassign'), ['user_id' => 999999])->assertSessionHasErrors('user_id');
    }

    public function test_an_admin_cannot_unassign_a_villa_in_another_society(): void
    {
        $this->assign($this->B['admin'], $this->B['login'], $this->B['v1'])->assertSessionHas('success');
        $villaB = $this->B['v1']->fresh();
        $this->assertNotNull($villaB->owner_id);

        $response = $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.unassign'), ['user_id' => $this->B['login']->id]);

        $this->assertContains($response->getStatusCode(), [302, 403, 404]);
        $this->assertSame($villaB->owner_id, $this->B['v1']->fresh()->owner_id, "Society B's villa must stay assigned");
        $this->assertSame('occupied', $this->B['v1']->fresh()->status);
        $this->assertSame('active', Resident::where('user_id', $this->B['login']->id)->sole()->status, "Society B's residency must stay open");
    }

    // ================================================================ reassign

    public function test_reassign_moves_the_owner_to_the_new_villa_and_frees_the_old_one(): void
    {
        $this->assign($this->A['admin'], $this->A['login'], $this->A['v1']);

        $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.reassign'), [
            'current_user_id' => $this->A['login']->id, 'new_villa_id' => $this->A['v2']->id,
        ])->assertSessionHas('success', 'Villa reassigned successfully! Owner Login A moved from Villa A1 to Villa A2.');

        $this->assertNull($this->A['v1']->fresh()->owner_id);
        $this->assertSame('vacant', $this->A['v1']->fresh()->status);
        $this->assertSame($this->A['owner']->id, $this->A['v2']->fresh()->owner_id, 'owners.id again, not users.id');
        $this->assertSame('occupied', $this->A['v2']->fresh()->status);
        $this->assertSame($this->A['v2']->id, Resident::where('user_id', $this->A['login']->id)->sole()->flat_id);
        $this->assertSame($this->A['v2']->id, $this->A['login']->fresh()->ownedVilla->id);
    }

    /** @dataProvider refusedReassignments */
    public function test_reassign_refuses_what_it_should_and_leaves_everything_as_it_was(string $case): void
    {
        $this->assign($this->A['admin'], $this->A['login'], $this->A['v1']);
        $current = $this->A['login'];
        $target  = $this->A['v2'];

        switch ($case) {
            case 'owner has no villa':
                $current = $this->makeUserWithRole($this->a, 'Owner', ['owner_id' => $this->A['owner']->id]);
                $this->A['v1']->update(['owner_id' => null]);
                $expect = 'User does not have an assigned villa.';
                break;
            case 'target already taken':
                $other = Owner::create(['society_id' => $this->a->id, 'name' => 'Other', 'email' => 'other@test.local', 'phone' => '9000000009', 'status' => 'active']);
                $target->update(['owner_id' => $other->id]);
                $expect = 'The new villa is already assigned to another owner.';
                break;
            case 'target in another society':
                $target = $this->B['v1'];
                $expect = 'Villa must belong to the same society.';
                break;
            case 'target is an apartment':
                $target = $this->A['apartment'];
                $expect = 'Selected unit is not a villa.';
                break;
        }

        $snapshot = fn () => [Flat::orderBy('id')->get(['id', 'owner_id', 'status'])->toArray(), Resident::orderBy('id')->get(['user_id', 'flat_id', 'status'])->toArray()];
        $before = $snapshot();

        $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.reassign'), [
            'current_user_id' => $current->id, 'new_villa_id' => $target->id,
        ])->assertSessionHas('error');
        $this->assertStringContainsString($expect, session('error'));
        $this->assertSame($before, $snapshot(), "$case changed data");
    }

    /** @return array<string, array{string}> */
    public static function refusedReassignments(): array
    {
        $cases = ['owner has no villa', 'target already taken', 'target in another society', 'target is an apartment'];

        return array_combine($cases, array_map(fn ($c) => [$c], $cases));
    }

    public function test_an_admin_cannot_move_another_societys_owner_into_their_own_villa(): void
    {
        $this->assign($this->B['admin'], $this->B['login'], $this->B['v1'])->assertSessionHas('success');

        $response = $this->actingAs($this->A['admin'])->post(route('admin.villa-assignments.reassign'), [
            'current_user_id' => $this->B['login']->id, 'new_villa_id' => $this->A['v1']->id,
        ]);

        $this->assertContains($response->getStatusCode(), [302, 403, 404]);
        $this->assertNotNull($this->B['v1']->fresh()->owner_id, "Society B's villa must stay assigned");
        $this->assertNull($this->A['v1']->fresh()->owner_id, 'and Society A gained nothing');
    }

    // ============================================================== Super Admin

    public function test_super_admin_sees_every_society_and_can_assign_within_one(): void
    {
        $super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);

        $res = $this->actingAs($super)->get(route('admin.villa-assignments.index'))->assertOk();
        $this->assertEqualsCanonicalizing([$this->A['login']->id, $this->B['login']->id], $res->viewData('unassignedVillaOwners')->pluck('id')->all());
        $this->assertCount(4, $res->viewData('availableVillas'));

        // An owner may only receive a villa of their own society, whoever is acting.
        $this->assign($super, $this->B['login'], $this->A['v1'])->assertSessionHas('error');
        $this->assertNull($this->A['v1']->fresh()->owner_id);

        $this->assign($super, $this->B['login'], $this->B['v2'])->assertSessionHas('success');
        $this->assertSame($this->B['owner']->id, $this->B['v2']->fresh()->owner_id);

        $this->actingAs($super)->post(route('admin.villa-assignments.reassign'), [
            'current_user_id' => $this->B['login']->id, 'new_villa_id' => $this->B['v1']->id,
        ])->assertSessionHas('success');
        $this->assertSame($this->B['owner']->id, $this->B['v1']->fresh()->owner_id);
        $this->assertNull($this->B['v2']->fresh()->owner_id);

        $this->actingAs($super)->post(route('admin.villa-assignments.unassign'), ['user_id' => $this->B['login']->id])->assertSessionHas('success');
        $this->assertNull($this->B['v1']->fresh()->owner_id);
    }
}
