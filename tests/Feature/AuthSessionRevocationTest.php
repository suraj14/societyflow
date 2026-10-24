<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\User;
use App\Services\AdminPasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A password change ends the OTHER web sessions (and remember-me cookies) and API tokens of THAT user, and nobody else's. Laravel 10.50
 * AuthenticateSession keeps a hash of the password in the session and refuses a session whose hash no longer matches; it works the same
 * for the file, database and redis drivers because it compares on each request instead of looking sessions up.
 */
class AuthSessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Old-pass-9#aaaa';
    private const NEW = 'New-pass-7#bbbb';

    private function owner(array $over = []): User
    {
        return $this->makeUserWithRole($this->makeSociety('Alpha ' . uniqid()), 'Owner', ['password' => Hash::make(self::OLD)] + $over);
    }

    /** What a browser that signed in earlier would present: a session holding the password hash of that moment. */
    private function staleSessionOf(User $user): array
    {
        return ['password_hash_web' => $user->password];
    }

    public function test_a_session_whose_password_hash_no_longer_matches_is_signed_out(): void
    {
        $user = $this->owner();
        $stale = $this->staleSessionOf($user);

        app(AdminPasswordResetService::class)->reset($user, self::NEW);

        $this->flushSession();
        // be(), not actingAs(): the harness starts a fresh session whenever actingAs() meets a different password hash, and this test is exactly about keeping the stale one
        $this->withSession($stale)->be($user->fresh())->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_session_that_changed_the_password_stays_signed_in(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->put(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));

        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_an_unrelated_users_session_is_not_affected(): void
    {
        $changed = $this->owner();
        $bystander = $this->owner();
        $bystanderSession = $this->staleSessionOf($bystander);

        app(AdminPasswordResetService::class)->reset($changed, self::NEW);

        $this->flushSession();
        $this->withSession($bystanderSession)->actingAs($bystander->fresh())->get('/dashboard')->assertOk();
    }

    public function test_a_remember_me_cookie_issued_before_a_password_change_stops_working(): void
    {
        $user = $this->owner();
        $login = $this->post('/login', ['email' => $user->email, 'password' => self::OLD, 'remember' => '1']);
        $cookie = collect($login->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'a remember-me cookie was issued');

        // a new browser: no session, only the cookie
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->call('GET', '/dashboard', [], [$cookie->getName() => $cookie->getValue()])->assertOk();

        app(AdminPasswordResetService::class)->reset($user, self::NEW);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->call('GET', '/dashboard', [], [$cookie->getName() => $cookie->getValue()])->assertRedirect(route('login'));
    }

    public function test_the_reset_service_cycles_the_remember_token_and_deletes_only_that_users_tokens(): void
    {
        $user = $this->owner(['remember_token' => 'old-remember-token-value']);
        $other = $this->owner();
        $user->createToken('mobile-app');
        $other->createToken('mobile-app');

        app(AdminPasswordResetService::class)->reset($user, self::NEW);

        $this->assertNotSame('old-remember-token-value', $user->fresh()->remember_token);
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $user->id)->count());
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $other->id)->count());
    }

    // ── the Super Admin's "reset society admin password" screen ─────────────

    public function test_the_super_admin_reset_goes_through_the_shared_service_and_revokes_only_that_admins_tokens(): void
    {
        $society = $this->makeSociety('Reset Me');
        $admin = $this->makeUserWithRole($society, 'Admin', ['password' => Hash::make(self::OLD), 'remember_token' => 'old-remember-token-value']);
        $society->update(['admin_id' => $admin->id]);
        $otherSociety = $this->makeSociety('Elsewhere');
        $otherAdmin = $this->makeUserWithRole($otherSociety, 'Admin');
        $super = $this->makeUserWithRole($this->makeSociety('Home'), 'Super Admin', ['society_id' => null]);
        $admin->createToken('mobile-app');
        $admin->createToken('mobile-otp');
        $otherAdmin->createToken('mobile-app');
        $super->createToken('mobile-app');

        $this->actingAs($super)->post(route('super-admin.societies.reset-admin-password', $society), ['new_password' => 'Zq7#vL!m2Xp9Rt', 'new_password_confirmation' => 'Zq7#vL!m2Xp9Rt'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertTrue(Hash::check('Zq7#vL!m2Xp9Rt', $admin->fresh()->password));
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $admin->id)->count(), 'every token of the reset admin is gone');
        $this->assertNotSame('old-remember-token-value', $admin->fresh()->remember_token);
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $otherAdmin->id)->count(), 'another society\'s admin is untouched');
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $super->id)->count(), 'and so is the Super Admin');
        $this->assertTrue(Hash::check('x', Hash::make('x')));   // sanity: hashing works
        $this->assertFalse(Hash::check(self::OLD, $admin->fresh()->password));
    }

    public function test_a_non_super_admin_cannot_use_the_reset_screen(): void
    {
        $society = $this->makeSociety('Guarded');
        $admin = $this->makeUserWithRole($society, 'Admin', ['password' => Hash::make(self::OLD)]);
        $society->update(['admin_id' => $admin->id]);

        $this->actingAs($admin)->post(route('super-admin.societies.reset-admin-password', $society), ['new_password' => 'Zq7#vL!m2Xp9Rt', 'new_password_confirmation' => 'Zq7#vL!m2Xp9Rt'])->assertForbidden();
        $this->assertTrue(Hash::check(self::OLD, $admin->fresh()->password));
    }

    // ── impersonation keeps working with AuthenticateSession ────────────────

    public function test_impersonation_start_and_stop_survive_the_password_hash_check(): void
    {
        $society = $this->makeSociety('Borrowed');
        $admin = $this->makeUserWithRole($society, 'Admin');
        $society->update(['admin_id' => $admin->id]);
        $super = $this->makeUserWithRole($this->makeSociety('SA'), 'Super Admin', ['society_id' => null]);

        $this->actingAs($super)->post(route('super-admin.societies.login-as', $society))->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($admin);

        $this->post(route('impersonation.stop'))->assertRedirect(route('super-admin.societies.index'));
        $this->get(route('super-admin.dashboard'))->assertOk();
        $this->assertAuthenticatedAs($super);
    }

    public function test_a_remember_me_cookie_dies_after_a_profile_password_change_which_does_not_cycle_the_remember_token(): void
    {
        $user = $this->owner();
        $login = $this->post('/login', ['email' => $user->email, 'password' => self::OLD, 'remember' => '1']);
        $cookie = collect($login->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));

        $this->put(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->call('GET', '/dashboard', [], [$cookie->getName() => $cookie->getValue()])->assertRedirect(route('login'));
    }
}