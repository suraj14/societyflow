<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Resident;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin > Apartment Assignments, over HTTP. The sibling of
 * VillaAssignmentHttpTest: the two controllers were written from the same
 * template and had the same three defects, found by that suite and fixed in
 * both - a 500 whenever the legacy 'Apartment Owner' role is not in the roles
 * table, an unscoped `unassign`/`reassign` that let an Admin act on another
 * society's owner by posting their id, and Super Admin being refused
 * everything because they have no society of their own.
 */
class ApartmentAssignmentHttpTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private array $A;
    private array $B;

    protected function setUp(): void
    {
        parent::setUp();

        $pad = $this->makeSociety('Padding');
        for ($i = 0; $i < 4; $i++) {
            Owner::create(['society_id' => $pad->id, 'name' => "Pad $i", 'email' => "pad$i@test.local", 'phone' => '9000000000', 'status' => 'active']);
        }

        $this->a = $this->makeSociety('Society A');
        $this->b = $this->makeSociety('Society B');
        $this->A = $this->world($this->a, 'A');
        $this->B = $this->world($this->b, 'B');
    }

    private function world(Society $society, string $tag): array
    {
        $building = $this->makeBuilding($society, "Tower $tag");
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => "Owner $tag", 'email' => strtolower($tag) . '.owner@test.local',
            'phone' => '9' . random_int(100000000, 999999999), 'status' => 'active',
        ]);

        return [
            'admin' => $this->makeUserWithRole($society, 'Admin'),
            'owner' => $owner,
            'login' => $this->makeUserWithRole($society, 'Owner', ['name' => "Owner Login $tag", 'owner_id' => $owner->id]),
            'f1'    => $this->makeFlat($society, $building, "$tag-101"),
            'f2'    => $this->makeFlat($society, $building, "$tag-102"),
        ];
    }

    private function assign(User $by, User $user, Flat $flat)
    {
        return $this->actingAs($by)->post(route('admin.apartment-assignments.assign'), ['user_id' => $user->id, 'apartment_id' => $flat->id]);
    }

    public function test_the_screen_renders_for_an_admin_even_without_the_legacy_apartment_owner_role(): void
    {
        // Only 'Owner' exists in the roles table here - the state that used to be a 500.
        $res = $this->actingAs($this->A['admin'])->get(route('admin.apartment-assignments.index'))->assertOk();

        $this->assertSame([$this->A['login']->id], $res->viewData('unassignedApartmentOwners')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$this->A['f1']->id, $this->A['f2']->id], $res->viewData('availableApartments')->pluck('id')->all());
        $res->assertDontSee('Owner Login B');
    }

    public function test_assign_unassign_and_reassign_work_and_use_the_owner_record_id(): void
    {
        $this->assertNotSame($this->A['owner']->id, $this->A['login']->id);

        $this->assign($this->A['admin'], $this->A['login'], $this->A['f1'])->assertSessionHas('success');
        $this->assertSame($this->A['owner']->id, $this->A['f1']->fresh()->owner_id, 'flats.owner_id is owners.id');
        $this->assertSame($this->A['f1']->id, $this->A['login']->fresh()->ownedFlat->id);

        $res = $this->actingAs($this->A['admin'])->get(route('admin.apartment-assignments.index'))->assertOk();
        $this->assertSame([], $res->viewData('unassignedApartmentOwners')->all());
        $this->assertSame([$this->A['login']->id], $res->viewData('assignedApartmentOwners')->pluck('id')->all());

        $this->actingAs($this->A['admin'])->post(route('admin.apartment-assignments.reassign'), [
            'current_user_id' => $this->A['login']->id, 'new_apartment_id' => $this->A['f2']->id,
        ])->assertSessionHas('success');
        $this->assertNull($this->A['f1']->fresh()->owner_id);
        $this->assertSame($this->A['owner']->id, $this->A['f2']->fresh()->owner_id);

        $this->actingAs($this->A['admin'])->post(route('admin.apartment-assignments.unassign'), ['user_id' => $this->A['login']->id])
            ->assertSessionHas('success');
        $this->assertNull($this->A['f2']->fresh()->owner_id);
        $this->assertSame('inactive', Resident::where('user_id', $this->A['login']->id)->sole()->status);
    }

    public function test_an_admin_cannot_unassign_or_move_an_owner_of_another_society(): void
    {
        $this->assign($this->B['admin'], $this->B['login'], $this->B['f1'])->assertSessionHas('success');
        $ownerId = $this->B['f1']->fresh()->owner_id;

        $r1 = $this->actingAs($this->A['admin'])->post(route('admin.apartment-assignments.unassign'), ['user_id' => $this->B['login']->id]);
        $this->assertContains($r1->getStatusCode(), [302, 403, 404]);
        $this->assertSame($ownerId, $this->B['f1']->fresh()->owner_id, "Society B's apartment must stay assigned");
        $this->assertSame('active', Resident::where('user_id', $this->B['login']->id)->sole()->status);

        $r2 = $this->actingAs($this->A['admin'])->post(route('admin.apartment-assignments.reassign'), [
            'current_user_id' => $this->B['login']->id, 'new_apartment_id' => $this->A['f1']->id,
        ]);
        $this->assertContains($r2->getStatusCode(), [302, 403, 404]);
        $this->assertSame($ownerId, $this->B['f1']->fresh()->owner_id);
        $this->assertNull($this->A['f1']->fresh()->owner_id, 'and Society A gained nothing');
    }

    public function test_assign_refuses_a_foreign_apartment_and_a_foreign_owner(): void
    {
        $this->assign($this->A['admin'], $this->A['login'], $this->B['f1'])->assertSessionHas('error');
        $this->assertNull($this->B['f1']->fresh()->owner_id);

        $this->assign($this->A['admin'], $this->B['login'], $this->A['f1'])->assertSessionHas('error');
        $this->assertNull($this->A['f1']->fresh()->owner_id);
        $this->assertSame(0, Resident::count());
    }

    public function test_only_admins_can_use_it(): void
    {
        foreach (['Tenant', 'Owner', 'Guard', 'Staff', 'Accountant'] as $role) {
            $user = $this->makeUserWithRole($this->a, $role);
            $this->actingAs($user)->get(route('admin.apartment-assignments.index'))->assertForbidden();
            $this->assign($user, $this->A['login'], $this->A['f1'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.apartment-assignments.unassign'), ['user_id' => $this->A['login']->id])->assertForbidden();
            $this->assertNull($this->A['f1']->fresh()->owner_id, "$role changed an assignment");
        }
    }

    public function test_super_admin_sees_every_society_and_can_assign_within_one(): void
    {
        $super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);

        $res = $this->actingAs($super)->get(route('admin.apartment-assignments.index'))->assertOk();
        $this->assertEqualsCanonicalizing([$this->A['login']->id, $this->B['login']->id], $res->viewData('unassignedApartmentOwners')->pluck('id')->all());

        $this->assign($super, $this->B['login'], $this->A['f1'])->assertSessionHas('error');   // owner must match the apartment's society
        $this->assertNull($this->A['f1']->fresh()->owner_id);

        $this->assign($super, $this->B['login'], $this->B['f2'])->assertSessionHas('success');
        $this->assertSame($this->B['owner']->id, $this->B['f2']->fresh()->owner_id);

        $this->actingAs($super)->post(route('admin.apartment-assignments.reassign'), [
            'current_user_id' => $this->B['login']->id, 'new_apartment_id' => $this->B['f1']->id,
        ])->assertSessionHas('success');
        $this->assertSame($this->B['owner']->id, $this->B['f1']->fresh()->owner_id);

        $this->actingAs($super)->post(route('admin.apartment-assignments.unassign'), ['user_id' => $this->B['login']->id])->assertSessionHas('success');
        $this->assertNull($this->B['f1']->fresh()->owner_id);
    }
}
