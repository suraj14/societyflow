<?php

namespace Tests\Feature;

use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The standalone user create/edit screens.
 *
 * Both views were missing, so GET /users/create and /users/{id}/edit
 * returned 500 for Admin and Super Admin (found by browser sweep). The
 * controller also rejected every Super Admin submission because a Super
 * Admin has no society_id and the form offered no way to choose one, and
 * its early-return guards answered a normal form POST with raw JSON.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeRole('Staff');
        Event::fake([UserCreated::class]);
    }

    public function test_admin_can_open_create_and_edit_screens(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $target  = $this->makeUserWithRole($society, 'Staff');

        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('Create New User', false);
        $this->actingAs($admin)->get("/users/{$target->id}/edit")->assertOk()->assertSee($target->email, false);
    }

    public function test_admin_cannot_open_another_societys_user(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');
        $userB    = $this->makeUserWithRole($societyB, 'Staff');

        // edit() redirects back with an error; update()/destroy() must be a
        // proper 404, never a 500 and never raw JSON in a browser.
        $this->actingAs($adminA)->put("/users/{$userB->id}", [
            'name' => 'Hijacked', 'phone' => '9999999999', 'role' => 'Staff', 'status' => 'active',
        ])->assertNotFound();

        $this->actingAs($adminA)->delete("/users/{$userB->id}")->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $userB->id, 'name' => $userB->name]);
    }

    public function test_super_admin_creates_a_user_in_a_chosen_society(): void
    {
        $society    = $this->makeSociety();
        $superAdmin = $this->makeUserWithRole($society, 'Super Admin', ['society_id' => null]);

        $this->actingAs($superAdmin)->get('/users/create')->assertOk()->assertSee('Select Society', false);

        $this->actingAs($superAdmin)->post('/users', [
            'society_id' => $society->id,
            'name'  => 'Chosen Society User',
            'email' => 'chosen@test.local',
            'phone' => '9876500010',
            'role'  => 'Staff',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'chosen@test.local', 'society_id' => $society->id]);
        Event::assertDispatched(UserCreated::class);
    }

    public function test_super_admin_cannot_attach_a_user_to_a_nonexistent_society(): void
    {
        $society    = $this->makeSociety();
        $superAdmin = $this->makeUserWithRole($society, 'Super Admin', ['society_id' => null]);

        $this->actingAs($superAdmin)->post('/users', [
            'society_id' => 999999,
            'name' => 'Ghost', 'email' => 'ghost@test.local', 'phone' => '9876500011', 'role' => 'Staff',
        ])->assertSessionHasErrors('society_id');

        $this->assertDatabaseMissing('users', ['email' => 'ghost@test.local']);
    }

    /** An Admin's society is taken from their account, not the request. */
    public function test_admin_cannot_override_society_via_request(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');

        $this->actingAs($adminA)->post('/users', [
            'society_id' => $societyB->id, // must be ignored
            'name' => 'Sneaky', 'email' => 'sneaky@test.local', 'phone' => '9876500012', 'role' => 'Staff',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'sneaky@test.local', 'society_id' => $societyA->id]);
    }

    public function test_changing_own_role_from_the_form_redirects_with_an_error(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($admin)->from("/users/{$admin->id}/edit")->put("/users/{$admin->id}", [
            'name' => $admin->name, 'phone' => '9876500013', 'role' => 'Staff', 'status' => 'active',
        ]);

        $response->assertRedirect("/users/{$admin->id}/edit");
        $response->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    public function test_admin_updates_a_user_through_the_form(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $target  = $this->makeUserWithRole($society, 'Staff');

        $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => 'Renamed Person', 'phone' => '9876500014', 'role' => 'Staff', 'status' => 'inactive',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Renamed Person', 'status' => 'inactive']);
    }

    // ------------------------------------------------- Super Admin escalation
    //
    // The create/edit forms rendered Role::all() unfiltered regardless of who
    // was viewing, and store()/update() validated the submitted role only
    // against `exists:roles,name` - so an Admin (a society-level role) could
    // both see "Super Admin" in the dropdown AND successfully POST
    // role=Super Admin, for a brand new account or an existing user in their
    // own society, and grant platform-wide authority. Both the option list
    // and the server-side validation are pinned here.

    public function test_admin_does_not_see_super_admin_in_the_create_form(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $html = $this->actingAs($admin)->get('/users/create')->assertOk()->getContent();

        $this->assertStringNotContainsString('>Super Admin<', $html);
    }

    public function test_admin_cannot_create_a_super_admin_by_forged_request(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Sneaky Super', 'email' => 'sneakysuper@test.local',
            'phone' => '9876500020', 'role' => 'Super Admin',
        ]);

        $response->assertSessionHasErrors('role');
        $user = User::where('email', 'sneakysuper@test.local')->first();
        $this->assertNull($user);
    }

    public function test_admin_cannot_promote_an_existing_user_to_super_admin(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $target  = $this->makeUserWithRole($society, 'Staff');

        $response = $this->actingAs($admin)->put("/users/{$target->id}", [
            'name' => $target->name, 'phone' => '9876500021', 'role' => 'Super Admin', 'status' => 'active',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertFalse($target->fresh()->hasRole('Super Admin'));
        $this->assertTrue($target->fresh()->hasRole('Staff'));
    }

    /** Super Admin itself is unrestricted - the fix must not lock them out. */
    public function test_super_admin_can_still_create_a_super_admin(): void
    {
        $society    = $this->makeSociety();
        $superAdmin = $this->makeUserWithRole($society, 'Super Admin', ['society_id' => null]);

        $html = $this->actingAs($superAdmin)->get('/users/create')->assertOk()->getContent();
        $this->assertStringContainsString('>Super Admin<', $html);

        $this->actingAs($superAdmin)->post('/users', [
            'society_id' => $society->id,
            'name' => 'New Super Admin', 'email' => 'newsuper@test.local',
            'phone' => '9876500022', 'role' => 'Super Admin',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'newsuper@test.local')->firstOrFail();
        $this->assertTrue($user->hasRole('Super Admin'));
    }
}
