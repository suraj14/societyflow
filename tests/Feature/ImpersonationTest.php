<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Login as Society Admin" (brief §7).
 *
 * The target admin is never taken from the request - it is resolved
 * server-side from societies.admin_id - so there is no user_id parameter to
 * forge in the first place. These tests exercise that design rather than
 * merely checking it rejects a bad id.
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function societyWithAdmin(string $status = 'active'): array
    {
        $society = $this->makeSociety('Impersonation Society');
        $admin   = $this->makeUserWithRole($society, 'Admin', ['status' => $status]);
        $society->update(['admin_id' => $admin->id]);

        return [$society->fresh(), $admin];
    }

    public function test_super_admin_can_login_as_the_societys_admin(): void
    {
        [$society, $admin] = $this->societyWithAdmin();
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        $response = $this->actingAs($superAdmin)
            ->post(route('super-admin.societies.login-as', $society));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(session('impersonating'));
        $this->assertSame($superAdmin->id, session('impersonator_id'));
    }

    public function test_a_non_super_admin_cannot_impersonate(): void
    {
        [$society, ] = $this->societyWithAdmin();
        $regularAdmin = $this->makeUserWithRole($this->makeSociety('Other'), 'Admin');

        $this->actingAs($regularAdmin)
            ->post(route('super-admin.societies.login-as', $society))
            ->assertForbidden();

        // Still authenticated as themself - the attempt did not touch the session.
        $this->assertAuthenticatedAs($regularAdmin);
    }

    public function test_an_inactive_admin_cannot_be_impersonated(): void
    {
        [$society, $admin] = $this->societyWithAdmin(status: 'inactive');
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        $this->actingAs($superAdmin)
            ->post(route('super-admin.societies.login-as', $society))
            ->assertRedirect(route('super-admin.societies.index'));

        // Must still be the Super Admin - the borrow never happened.
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_a_society_with_no_admin_assigned_cannot_be_impersonated(): void
    {
        $society    = $this->makeSociety('No Admin Society');
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        $this->actingAs($superAdmin)
            ->post(route('super-admin.societies.login-as', $society))
            ->assertRedirect(route('super-admin.societies.index'));

        $this->assertAuthenticatedAs($superAdmin);
    }

    /**
     * A second borrow is blocked twice over: the route itself requires the
     * Super Admin role, and the borrowed session no longer holds it (403 at
     * the middleware), and the controller's own session-flag check would
     * refuse it even if role middleware were ever loosened. This test proves
     * the outer layer; test_the_controllers_own_nested_guard_also_refuses_it
     * proves the inner one directly.
     */
    public function test_nested_impersonation_is_refused_by_role_middleware(): void
    {
        [$societyA, ] = $this->societyWithAdmin();
        [$societyB, ] = $this->societyWithAdmin();
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        $this->actingAs($superAdmin)
            ->post(route('super-admin.societies.login-as', $societyA));

        $this->post(route('super-admin.societies.login-as', $societyB))
            ->assertForbidden();

        // Still the FIRST admin, not switched to the second society.
        $this->assertSame($societyA->admin_id, auth()->id());
    }

    /**
     * Proves the controller's own session-flag check independently of the
     * route middleware, by calling start() directly as a still-Super-Admin
     * session that merely has the impersonating flag already set - the
     * state a compromised or misconfigured route would produce.
     */
    public function test_the_controllers_own_nested_guard_also_refuses_it(): void
    {
        [$societyA, $adminA] = $this->societyWithAdmin();
        [$societyB, ] = $this->societyWithAdmin();
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        session(['impersonator_id' => $superAdmin->id, 'impersonating' => true]);

        $this->actingAs($superAdmin)
            ->post(route('super-admin.societies.login-as', $societyB))
            ->assertRedirect(route('super-admin.societies.index'));

        $this->assertAuthenticatedAs($superAdmin);
        $this->assertNotSame($societyB->admin_id, auth()->id());
    }

    public function test_returning_restores_the_original_super_admin(): void
    {
        [$society, $admin] = $this->societyWithAdmin();
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        $this->actingAs($superAdmin)
            ->post(route('super-admin.societies.login-as', $society));

        $this->assertAuthenticatedAs($admin);

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('super-admin.societies.index'));

        $this->assertAuthenticatedAs($superAdmin);
        $this->assertNull(session('impersonator_id'));
        $this->assertNull(session('impersonating'));
    }

    /** The banner and its own stop button are what "return" actually is. */
    public function test_the_impersonation_banner_appears_while_borrowed_and_not_otherwise(): void
    {
        [$society, $admin] = $this->societyWithAdmin();
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        // Before impersonating: the admin's own dashboard carries no banner.
        $plain = $this->actingAs($admin)->get('/dashboard')->getContent();
        $this->assertStringNotContainsString('Return to Super Admin', $plain);

        $this->actingAs($superAdmin)->post(route('super-admin.societies.login-as', $society));

        $borrowed = $this->get('/dashboard')->getContent();
        $this->assertStringContainsString('Return to Super Admin', $borrowed);
        $this->assertStringContainsString($admin->name, $borrowed);
    }

    public function test_start_and_stop_are_both_written_to_the_audit_log(): void
    {
        [$society, $admin] = $this->societyWithAdmin();
        $superAdmin = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        $this->actingAs($superAdmin)->post(route('super-admin.societies.login-as', $society));
        $this->post(route('impersonation.stop'));

        $this->assertDatabaseHas('activity_logs', [
            'action'  => 'impersonation_started',
            'user_id' => $superAdmin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action'  => 'impersonation_ended',
            'user_id' => $superAdmin->id,
        ]);

        $started = ActivityLog::where('action', 'impersonation_started')->first();
        $this->assertSame($admin->id, $started->new_values['target_admin_id']);

        // No credential of any kind may appear in the log payload.
        $dump = json_encode($started->new_values);
        $this->assertStringNotContainsString($admin->password, $dump);
    }

    /**
     * Stopping without ever having started must not error, and must not
     * grant access to whoever happens to be signed in.
     */
    public function test_stopping_without_an_active_impersonation_is_a_no_op(): void
    {
        $society = $this->makeSociety('Solo Society');
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)
            ->post(route('impersonation.stop'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /**
     * The target is resolved from societies.admin_id server-side; the
     * request carries no user id at all, so there is nothing to forge.
     */
    public function test_the_request_cannot_name_a_different_target_user(): void
    {
        [$society, $admin] = $this->societyWithAdmin();
        $attackerTarget = $this->makeUserWithRole($society, 'Admin', ['status' => 'active']);
        $superAdmin     = $this->makeUserWithRole($this->makeSociety('SA Home'), 'Super Admin');

        $this->actingAs($superAdmin)
            ->post(route('super-admin.societies.login-as', $society), [
                'user_id'  => $attackerTarget->id,
                'admin_id' => $attackerTarget->id,
            ]);

        // Whatever was posted, the borrowed identity is societies.admin_id.
        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($attackerTarget->id, auth()->id());
    }
}
