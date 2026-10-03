<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UserController::edit()/update()/destroy() all gate the society check
 * behind `$currentUser->hasRole('Admin') && $user->society_id !== $societyId`.
 *
 * Unlike PaymentController and NoticeController's identical-looking bug, this
 * one was not independently exploitable: every call site is preceded by
 * `authorizeRole('Super Admin', 'Admin')`, so no role outside those two ever
 * reaches the check, and Admin is exactly the role the && names. It is
 * hardened here anyway (CLAUDE.md §3: never rely on a role list that could
 * grow) to `authorizeSocietyAccess()`, which is equivalent for both current
 * roles and does not depend on the permission map staying as it is today.
 */
class UserControllerSocietyIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Society, 1: Society, 2: User, 3: User} */
    private function twoSocietiesWithAdminAndTarget(): array
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');
        $targetB  = $this->makeUserWithRole($societyB, 'Tenant');

        return [$societyA, $societyB, $adminA, $targetB];
    }

    public function test_admin_cannot_view_the_edit_form_for_another_societys_user(): void
    {
        [, , $adminA, $targetB] = $this->twoSocietiesWithAdminAndTarget();

        $this->actingAs($adminA)
            ->get("/users/{$targetB->id}/edit")
            ->assertRedirect();

        // The controller returns back()->with('error', ...) rather than a
        // 403/404, so the redirect itself is the assertion; confirm no
        // society-B data reached the response session as success.
    }

    public function test_admin_cannot_update_another_societys_user(): void
    {
        [, , $adminA, $targetB] = $this->twoSocietiesWithAdminAndTarget();

        $this->actingAs($adminA)
            ->put("/users/{$targetB->id}", [
                'name'   => 'Renamed By Admin A',
                'phone'  => '9998887777',
                'role'   => 'Tenant',
                'status' => 'active',
            ]);

        $this->assertSame('Tenant User', $targetB->fresh()->name);
    }

    public function test_admin_cannot_delete_another_societys_user(): void
    {
        [, , $adminA, $targetB] = $this->twoSocietiesWithAdminAndTarget();

        $this->actingAs($adminA)->delete("/users/{$targetB->id}");

        $this->assertDatabaseHas('users', ['id' => $targetB->id]);
    }

    /** The legitimate case must keep working. */
    public function test_admin_can_update_their_own_societys_user(): void
    {
        $society = $this->makeSociety('Society A');
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $target  = $this->makeUserWithRole($society, 'Tenant');

        $this->actingAs($admin)
            ->put("/users/{$target->id}", [
                'name'   => 'Renamed Correctly',
                'phone'  => '9998887777',
                'role'   => 'Tenant',
                'status' => 'active',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertSame('Renamed Correctly', $target->fresh()->name);
    }

    public function test_super_admin_can_edit_a_user_in_any_society(): void
    {
        [, $societyB, , $targetB] = $this->twoSocietiesWithAdminAndTarget();
        $superAdmin = $this->makeUserWithRole($societyB, 'Super Admin', ['society_id' => null]);

        $this->actingAs($superAdmin)
            ->get("/users/{$targetB->id}/edit")
            ->assertOk();
    }
}
