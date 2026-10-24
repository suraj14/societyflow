<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Failed-password lockout on the app's login (/api/v1/auth/login) and the web login, which share App\Support\LoginThrottle.
 * The API login used to have only the general 60-requests-a-minute limit, so a password could be guessed without any lockout.
 */
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-2026!';

    private Society $society;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Alpha');
        $this->user = $this->makeUserWithRole($this->society, 'Owner', ['email' => 'asha@example.test', 'password' => Hash::make(self::PASSWORD)]);
    }

    private function apiLogin(string $email, string $password, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    private function webLogin(string $email, string $password, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from(route('login'))->post(route('login'), ['email' => $email, 'password' => $password]);
    }

    private function failApi(int $times, string $email = 'asha@example.test', string $ip = '10.0.0.1'): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->apiLogin($email, 'wrong-password', $ip)->assertStatus(422);
        }
    }

    // ── the app's login ─────────────────────────────────────────────────────

    public function test_the_app_login_locks_out_after_five_failures_even_for_the_right_password(): void
    {
        $this->failApi(5);

        $locked = $this->apiLogin('asha@example.test', self::PASSWORD)->assertStatus(429);
        $locked->assertJsonPath('success', false);
        $this->assertGreaterThan(0, $locked->json('retry_after'));
        $this->assertSame((string) $locked->json('retry_after'), $locked->headers->get('Retry-After'));
        $this->assertStringContainsString('Too many login attempts', $locked->json('message'));
        $this->assertSame(0, $this->user->tokens()->count(), 'no token is issued while locked out');
    }

    public function test_the_lockout_is_per_address_and_client_not_global(): void
    {
        $this->failApi(5);
        $other = $this->makeUserWithRole($this->society, 'Tenant', ['email' => 'ravi@example.test', 'password' => Hash::make(self::PASSWORD)]);

        $this->apiLogin('ravi@example.test', self::PASSWORD)->assertOk()->assertJsonPath('success', true);
        $this->apiLogin('asha@example.test', self::PASSWORD, '10.0.0.2')->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_the_address_is_matched_whatever_its_letter_case(): void
    {
        $this->failApi(3, 'asha@example.test');
        $this->failApi(2, 'ASHA@Example.Test');

        $this->apiLogin('Asha@example.test', self::PASSWORD)->assertStatus(429);
    }

    public function test_a_successful_login_clears_the_failures(): void
    {
        $this->failApi(4);
        $this->apiLogin('asha@example.test', self::PASSWORD)->assertOk();

        $this->failApi(4);   // would have been the 8th failure; the count started again
        $this->apiLogin('asha@example.test', self::PASSWORD)->assertOk();
    }

    public function test_the_limit_follows_the_societys_security_setting(): void
    {
        SystemSetting::set('security', 'login_attempt_limit', 3, 'integer', $this->society->id);

        $this->failApi(3);

        $this->apiLogin('asha@example.test', self::PASSWORD)->assertStatus(429);
    }

    public function test_the_lockout_lifts_after_the_minute(): void
    {
        $this->failApi(5);
        $this->apiLogin('asha@example.test', self::PASSWORD)->assertStatus(429);

        $this->travel(61)->seconds();

        $this->apiLogin('asha@example.test', self::PASSWORD)->assertOk();
    }

    public function test_guesses_spread_over_several_minutes_hit_the_longer_limit(): void
    {
        SystemSetting::set('security', 'login_attempt_limit', 3, 'integer', $this->society->id);

        // three failures a minute never trips the one-minute limit, but twelve within a quarter of an hour do
        for ($round = 0; $round < 4; $round++) {
            $this->failApi(3);
            $this->travel(61)->seconds();
        }

        $locked = $this->apiLogin('asha@example.test', self::PASSWORD)->assertStatus(429);
        $this->assertGreaterThan(61, $locked->json('retry_after'), 'the long window, not the one-minute window');
    }

    public function test_an_unknown_address_is_limited_and_answers_like_a_wrong_password(): void
    {
        $first = $this->apiLogin('nobody@example.test', 'wrong-password')->assertStatus(422);
        $known = $this->apiLogin('asha@example.test', 'wrong-password')->assertStatus(422);
        $this->assertSame($known->json('errors'), $first->json('errors'), 'the same body for an unknown address and a wrong password');

        $this->failApi(4, 'nobody@example.test');

        $this->apiLogin('nobody@example.test', 'wrong-password')->assertStatus(429);
    }

    public function test_an_unknown_address_still_costs_a_password_check(): void
    {
        // the unknown address and the wrong password both spend a hash check, so timing does not tell them apart
        Hash::partialMock()->shouldReceive('check')->twice()->andReturn(false);

        $this->apiLogin('nobody@example.test', 'wrong-password')->assertStatus(422);
        $this->apiLogin('asha@example.test', 'wrong-password')->assertStatus(422);
    }

    public function test_an_inactive_account_is_still_refused_once_the_password_is_right(): void
    {
        $this->user->update(['status' => 'inactive']);

        $this->apiLogin('asha@example.test', self::PASSWORD)->assertStatus(403);
        $this->assertSame(0, $this->user->tokens()->count());
    }

    // ── the web login: same rules ───────────────────────────────────────────

    public function test_the_web_login_locks_out_the_same_way(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->webLogin('asha@example.test', 'wrong-password')->assertSessionHasErrors('email');
        }

        $this->webLogin('asha@example.test', self::PASSWORD)->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_the_web_and_app_logins_share_one_counter(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->webLogin('asha@example.test', 'wrong-password');
        }
        $this->failApi(2);

        $this->apiLogin('asha@example.test', self::PASSWORD)->assertStatus(429);
    }

    public function test_the_web_login_accepts_the_right_password_before_the_limit(): void
    {
        $this->webLogin('asha@example.test', 'wrong-password');

        $this->webLogin('asha@example.test', self::PASSWORD)->assertRedirect();
        $this->assertAuthenticatedAs($this->user);
    }
}
