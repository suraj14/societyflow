<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logout is a state change, so it must be a POST protected by CSRF and must
 * never be triggerable by a link, image tag or prefetch (GET). The GET route
 * that remains is only a friendly redirect for stale bookmarks.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_logout_does_not_end_the_session_or_touch_auth_state(): void
    {
        $user = $this->makeUserWithRole($this->makeSociety(), 'Admin');
        $this->actingAs($user);
        $before = $user->fresh()->remember_token;

        $this->get('/logout')->assertRedirect(route('login'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($before, $user->fresh()->remember_token);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_get_logout_for_a_guest_is_a_harmless_redirect(): void
    {
        $this->get('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_post_logout_ends_the_session_rotates_the_token_and_blocks_further_access(): void
    {
        $user = $this->makeUserWithRole($this->makeSociety(), 'Admin');
        $user->forceFill(['remember_token' => 'old-remember-token'])->save();
        $this->actingAs($user)->withSession(['marker' => 'x']);
        $oldToken = csrf_token();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');
        $this->assertGuest();
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token, 'remember-me cookie must be invalidated');
        $this->assertNull(session('marker'), 'old session data must be gone');
        $this->assertNotSame($oldToken, csrf_token(), 'CSRF token must be regenerated');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_repeated_logout_is_safe(): void
    {
        $user = $this->makeUserWithRole($this->makeSociety(), 'Tenant');
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        // Second tap (or another tab with the same, now dead, session).
        $second = $this->post(route('logout'));
        $this->assertContains($second->getStatusCode(), [302, 419]);
        $this->assertNotSame(500, $second->getStatusCode());
        $this->assertGuest();
    }

    public function test_post_logout_without_a_valid_csrf_token_is_refused_and_keeps_the_session(): void
    {
        $user = $this->makeUserWithRole($this->makeSociety(), 'Admin');
        $this->actingAs($user);

        $this->enforceCsrf();
        $response = $this->from('/dashboard')->post(route('logout'), ['_token' => 'forged']);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($user, null);

        // A request carrying the real token is accepted and does log out.
        $this->post(route('logout'), ['_token' => csrf_token()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** Laravel's CSRF middleware no-ops under PHPUnit; run the application's real class with that switch off. */
    private function enforceCsrf(): void
    {
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_api_logout_revokes_only_the_presented_token_and_cannot_be_repeated(): void
    {
        $user = $this->makeUserWithRole($this->makeSociety(), 'Tenant');
        $keep = $user->createToken('other-device')->plainTextToken;
        $use  = $user->createToken('this-device')->plainTextToken;

        $this->withToken($use)->postJson('/api/v1/auth/logout')->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, $user->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($use)->postJson('/api/v1/auth/logout')->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->withToken($keep)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_api_logout_rejects_get(): void
    {
        $user = $this->makeUserWithRole($this->makeSociety(), 'Tenant');
        $token = $user->createToken('d')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/logout')->assertStatus(405);
        $this->assertSame(1, $user->tokens()->count());
    }
}
