<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * AuthController::forgotPassword() used to be a stub (a TODO comment and a
 * fake "sent" message, no email ever went out) and there was no
 * reset-password route at all - even a real email would have had nowhere to
 * land. This also covers the concrete real-world failure it was found from:
 * an Owner (Lokesh Suman, Villa 159/Royal Eden) whose account was active
 * with a correct role, but whose randomly-generated password nobody knew,
 * so "forgot password" and an Admin-driven reset were the only legitimate
 * ways back in.
 */
class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_reset_link_through_the_laravel_broker(): void
    {
        Notification::fake();

        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, 'Owner', ['email' => 'lokesh@test.local']);

        $response = $this->post('/forgot-password', ['email' => 'lokesh@test.local']);

        $response->assertRedirect();
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_gives_the_same_response_for_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', ['email' => 'nobody@test.local']);

        // No validation error naming the email as unknown - enumeration-safe.
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
    }

    public function test_user_can_reset_password_with_a_valid_token_and_then_login(): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, 'Owner', ['email' => 'lokesh@test.local']);

        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'lokesh@test.local',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ]);

        $response->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('brand-new-password-1', $user->fresh()->password));

        $login = $this->post('/login', ['email' => 'lokesh@test.local', 'password' => 'brand-new-password-1']);
        $this->assertAuthenticatedAs($user->fresh());
        $login->assertRedirect();
    }

    public function test_old_password_no_longer_works_after_reset(): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, 'Owner', ['email' => 'lokesh@test.local']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'lokesh@test.local',
            'password' => 'brand-new-password-1',
            'password_confirmation' => 'brand-new-password-1',
        ]);

        $this->post('/login', ['email' => 'lokesh@test.local', 'password' => 'correct-horse-battery-staple']);
        $this->assertGuest();
    }

    public function test_admin_can_reset_an_owners_password_and_owner_can_login_with_it(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Lokesh Suman', 'email' => 'lokesh@test.local',
            'phone' => '9000000040', 'status' => 'active', 'property_type' => 'villa',
        ]);
        $ownerUser = $this->makeUserWithRole($society, 'Owner', ['email' => $owner->email, 'owner_id' => $owner->id]);

        $response = $this->actingAs($admin)->post("/owners/{$owner->id}/reset-password", [
            'password' => 'admin-set-password-1',
            'password_confirmation' => 'admin-set-password-1',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('admin-set-password-1', $ownerUser->fresh()->password));

        auth()->logout();
        $login = $this->post('/login', ['email' => $owner->email, 'password' => 'admin-set-password-1']);
        $this->assertAuthenticatedAs($ownerUser->fresh());
    }

    public function test_owner_cannot_reset_another_users_password(): void
    {
        $society = $this->makeSociety();

        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Lokesh Suman', 'email' => 'lokesh@test.local',
            'phone' => '9000000041', 'status' => 'active', 'property_type' => 'villa',
        ]);
        $ownerUser = $this->makeUserWithRole($society, 'Owner', ['email' => $owner->email, 'owner_id' => $owner->id]);

        $otherOwner = Owner::create([
            'society_id' => $society->id, 'name' => 'Other Owner', 'email' => 'other@test.local',
            'phone' => '9000000042', 'status' => 'active', 'property_type' => 'villa',
        ]);

        $response = $this->actingAs($ownerUser)->post("/owners/{$otherOwner->id}/reset-password", [
            'password' => 'attacker-password-1',
            'password_confirmation' => 'attacker-password-1',
        ]);

        $response->assertForbidden();
    }

    public function test_tenant_cannot_reset_another_users_password(): void
    {
        $society = $this->makeSociety();
        $tenantUser = $this->makeUserWithRole($society, 'Tenant');

        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Lokesh Suman', 'email' => 'lokesh@test.local',
            'phone' => '9000000043', 'status' => 'active', 'property_type' => 'villa',
        ]);

        $response = $this->actingAs($tenantUser)->post("/owners/{$owner->id}/reset-password", [
            'password' => 'attacker-password-1',
            'password_confirmation' => 'attacker-password-1',
        ]);

        $response->assertForbidden();
    }

    public function test_assigning_a_tenant_to_the_owners_property_does_not_disable_the_owner_or_change_their_role(): void
    {
        $society = $this->makeSociety('Royal Eden');
        $villaArea = VillaArea::create(['society_id' => $society->id, 'name' => 'Suman Sadan']);

        $flat = Flat::create([
            'society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $villaArea->id,
            'flat_number' => '159', 'floor' => 0, 'status' => 'vacant',
        ]);

        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Lokesh Suman', 'email' => 'lokesh@test.local',
            'phone' => '9000000044', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $villaArea->id, 'villa_no' => '159',
        ]);
        $ownerUser = $this->makeUserWithRole($society, 'Owner', ['email' => $owner->email, 'owner_id' => $owner->id]);

        $tenantUser = $this->makeUserWithRole($society, 'Tenant', ['email' => 'piyus@test.local']);
        $tenant = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'user_id' => $tenantUser->id,
            'name' => 'Piyus Chudarri', 'email' => $tenantUser->email, 'phone' => '9000000045',
            'move_in_date' => now(), 'status' => 'active',
        ]);
        $tenantUser->update(['tenant_id' => $tenant->id]);

        $ownerUser->refresh();
        $this->assertSame('active', $ownerUser->status);
        $this->assertTrue($ownerUser->hasRole('Owner'));
        $this->assertFalse($ownerUser->hasRole('Tenant'));
        $this->assertSame($owner->id, $ownerUser->owner_id);

        // Tenant login is independent and unaffected.
        $tenantUser->refresh();
        $this->assertSame('active', $tenantUser->status);
        $this->assertTrue($tenantUser->hasRole('Tenant'));
        $login = $this->post('/login', ['email' => $tenantUser->email, 'password' => 'correct-horse-battery-staple']);
        $this->assertAuthenticatedAs($tenantUser);
    }

    /**
     * Covers the shared /users/{user}/reset-password endpoint - the same
     * AdminPasswordResetService OwnerController::resetPassword() uses -
     * across every normal role, not just Owner.
     */
    public function test_admin_can_reset_the_password_of_every_normal_role_and_the_user_can_login(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        foreach (['Tenant', 'Staff', 'Guard', 'Accountant'] as $role) {
            $target = $this->makeUserWithRole($society, $role, ['email' => strtolower($role) . '@test.local']);

            $response = $this->actingAs($admin)->post("/users/{$target->id}/reset-password", [
                'password' => 'new-password-for-' . strtolower($role),
                'password_confirmation' => 'new-password-for-' . strtolower($role),
            ]);

            $response->assertRedirect();

            $fresh = $target->fresh();
            $this->assertTrue(Hash::check('new-password-for-' . strtolower($role), $fresh->password));
            $this->assertNotSame('new-password-for-' . strtolower($role), $fresh->password, 'password must never be stored as plaintext');

            // Unchanged by the reset: role, email, society.
            $this->assertTrue($fresh->hasRole($role));
            $this->assertSame(strtolower($role) . '@test.local', $fresh->email);
            $this->assertSame($society->id, $fresh->society_id);

            auth()->logout();
            $login = $this->post('/login', ['email' => $fresh->email, 'password' => 'new-password-for-' . strtolower($role)]);
            $this->assertAuthenticatedAs($fresh);
            auth()->logout();
        }
    }

    public function test_resetting_a_tenants_password_leaves_their_property_assignment_untouched(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, '101');

        $tenantUser = $this->makeUserWithRole($society, 'Tenant', ['email' => 'piyus@test.local']);
        $tenant = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $flat->id, 'user_id' => $tenantUser->id,
            'name' => 'Piyus Chudarri', 'email' => $tenantUser->email, 'phone' => '9000000050',
            'move_in_date' => now(), 'status' => 'active',
        ]);
        $tenantUser->update(['tenant_id' => $tenant->id]);

        $this->actingAs($admin)->post("/users/{$tenantUser->id}/reset-password", [
            'password' => 'new-tenant-password-1',
            'password_confirmation' => 'new-tenant-password-1',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame($flat->id, $tenant->flat_id);
        $this->assertSame('active', $tenant->status);
        $this->assertSame($tenantUser->id, $tenant->user_id);
    }

    public function test_admin_cannot_reset_a_users_password_from_another_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $admin = $this->makeUserWithRole($societyA, 'Admin');
        $otherSocietyUser = $this->makeUserWithRole($societyB, 'Tenant');

        $response = $this->actingAs($admin)->post("/users/{$otherSocietyUser->id}/reset-password", [
            'password' => 'cross-society-password-1',
            'password_confirmation' => 'cross-society-password-1',
        ]);

        $response->assertForbidden();
        $this->assertFalse(Hash::check('cross-society-password-1', $otherSocietyUser->fresh()->password));
    }

    public function test_guard_cannot_reset_another_users_password(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        $tenantUser = $this->makeUserWithRole($society, 'Tenant');

        $response = $this->actingAs($guard)->post("/users/{$tenantUser->id}/reset-password", [
            'password' => 'attacker-password-2',
            'password_confirmation' => 'attacker-password-2',
        ]);

        $response->assertForbidden();
    }

    public function test_ordinary_admin_cannot_reset_a_super_admin_password(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $superAdmin = $this->makeUserWithRole($this->makeSociety(), 'Super Admin', ['society_id' => null]);

        $response = $this->actingAs($admin)->post("/users/{$superAdmin->id}/reset-password", [
            'password' => 'escalation-attempt-1',
            'password_confirmation' => 'escalation-attempt-1',
        ]);

        $response->assertForbidden();
    }

    public function test_target_users_sanctum_tokens_are_revoked_on_reset(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $tenantUser = $this->makeUserWithRole($society, 'Tenant');
        $tenantUser->createToken('mobile-app');

        $this->assertSame(1, $tenantUser->tokens()->count());

        $this->actingAs($admin)->post("/users/{$tenantUser->id}/reset-password", [
            'password' => 'new-password-revoke-1',
            'password_confirmation' => 'new-password-revoke-1',
        ])->assertRedirect();

        $this->assertSame(0, $tenantUser->tokens()->count());
    }
}
