<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The whole life of a one-time code, server side: how it is made, kept, sent, spent and refused - and that nothing about
 * it leaks (in production responses, in the table, in logs, or by telling callers which numbers are registered).
 * The SMS transport is a recording stub; nothing here contacts Fast2SMS.
 */
class OtpLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '9811113333';
    private $society;
    private $user;
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society));
        $this->user = $this->makeResidentUser($this->society, $flat, ['phone' => self::PHONE]);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'KEY-abc', 'encrypted', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_route', 'otp', 'string', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_otp_id', 'otpid12345', 'string', $this->society->id);
        SmsService::$transportOverride = function ($url, $data) {
            $this->sent[] = ['url' => $url, 'otp' => $data['otp'] ?? null, 'mobile' => $data['mobile'] ?? null];

            return [['return' => true, 'request_id' => 'r' . count($this->sent)], 200];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function asEnvironment(string $env, bool $flag = true): void
    {
        $this->app['env'] = $env;
        config(['otp.expose_dev_code' => $flag]);
    }

    private function send(string $phone = self::PHONE)
    {
        return $this->postJson('/api/v1/auth/send-otp', ['phone' => $phone]);
    }

    private function verify(string $code, string $phone = self::PHONE)
    {
        return $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'otp' => $code]);
    }

    private function lastSentCode(): string
    {
        return (string) end($this->sent)['otp'];
    }

    // ── nothing development-only can switch on in production ────────────────

    public function test_production_and_staging_never_return_the_code_even_with_the_flag_on(): void
    {
        foreach (['production', 'staging'] as $env) {
            $this->asEnvironment($env, true);
            RateLimiter::clear('otp-send:' . self::PHONE);

            $r = $this->send()->assertOk();
            $this->assertArrayNotHasKey('otp', $r->json(), $env);
            $this->assertStringNotContainsString($this->lastSentCode(), $r->getContent(), "$env: not even inside a message");

            $forgot = $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email])->assertOk();
            $this->assertArrayNotHasKey('otp', $forgot->json(), "$env forgot-password");
        }
        $this->assertFalse(MobileOtp::exposesDevCode());
    }

    public function test_a_local_server_returns_the_code_only_when_it_opted_in(): void
    {
        $this->asEnvironment('local', false);
        $this->assertFalse(MobileOtp::exposesDevCode());
        $this->assertArrayNotHasKey('otp', $this->send()->assertOk()->json());

        $this->asEnvironment('local', true);
        RateLimiter::clear('otp-send:' . self::PHONE);
        $r = $this->send()->assertOk();
        $this->assertTrue(MobileOtp::exposesDevCode());
        $this->assertSame($this->lastSentCode(), $r->json('otp'), 'the code returned is the code that was sent');
    }

    public function test_the_default_is_off_and_production_checks_cannot_be_fooled_by_the_flag(): void
    {
        $this->assertFalse((bool) (require base_path('config/otp.php'))['expose_dev_code'] && !env('OTP_EXPOSE_DEV_CODE'), 'off unless the variable says true');
        $this->app['env'] = 'production';
        config(['otp.expose_dev_code' => true]);
        $this->assertFalse(MobileOtp::exposesDevCode());
    }

    public function test_an_unsent_code_is_never_handed_out_in_production_instead(): void
    {
        $this->asEnvironment('production');
        SmsService::$transportOverride = fn () => [['return' => false, 'message' => 'Invalid Authentication'], 401];

        $r = $this->send()->assertStatus(503);

        $r->assertJson(['code' => 'sms_failed']);
        $this->assertArrayNotHasKey('otp', $r->json());
        $this->assertStringNotContainsString('Dev OTP', $r->getContent());
        $this->assertSame(0, MobileOtp::where('is_used', false)->count(), 'the unsent code is retired');
    }

    public function test_a_provider_timeout_does_not_leave_a_code_that_looks_delivered(): void
    {
        $this->asEnvironment('production');
        SmsService::$transportOverride = fn () => [['message' => 'Operation timed out'], 0];

        $r = $this->send()->assertStatus(503);

        $this->assertFalse($r->json('success'));
        $this->assertSame(0, MobileOtp::where('is_used', false)->count());
        $this->assertSame(0, RateLimiter::attempts('otp-send:' . self::PHONE), 'our failure does not use up the person\'s attempts');
    }

    // ── what is stored ──────────────────────────────────────────────────────

    public function test_the_code_is_four_digits_random_and_stored_only_as_a_keyed_hash(): void
    {
        $this->send()->assertOk();
        $code = $this->lastSentCode();
        $row = MobileOtp::firstOrFail();

        $this->assertMatchesRegularExpression('/^[1-9][0-9]{3}$/', $code);
        $this->assertSame(64, strlen($row->otp));
        $this->assertNotSame($code, $row->otp);
        $this->assertSame(hash_hmac('sha256', self::PHONE . '|login|' . $code, config('app.key')), $row->otp);
        $this->assertArrayNotHasKey('otp', $row->toArray(), 'never serialised');
        $this->assertStringNotContainsString($code, json_encode(\Illuminate\Support\Facades\DB::table('mobile_otps')->get()), 'not anywhere in the table');

        $seen = [];
        for ($i = 0; $i < 60; $i++) {
            [, $c] = MobileOtp::issue('9000000000', 'login');
            $seen[$c] = true;
        }
        $this->assertGreaterThan(40, count($seen), 'drawn from the whole range, not a fixed or tiny set');
    }

    public function test_the_hash_depends_on_phone_and_purpose(): void
    {
        $this->assertNotSame(MobileOtp::hashCode('9811113333', 'login', '1234'), MobileOtp::hashCode('9811113334', 'login', '1234'));
        $this->assertNotSame(MobileOtp::hashCode('9811113333', 'login', '1234'), MobileOtp::hashCode('9811113333', 'password_reset', '1234'));
    }

    // ── spending a code ─────────────────────────────────────────────────────

    public function test_a_valid_code_signs_in_once_and_cannot_be_replayed(): void
    {
        $this->send()->assertOk();
        $code = $this->lastSentCode();

        $this->verify($code)->assertOk()->assertJsonPath('success', true);
        $again = $this->verify($code)->assertStatus(422);

        $this->assertNull($again->json('token'));
        $this->assertSame(1, MobileOtp::where('is_used', true)->count());
    }

    public function test_the_same_code_presented_twice_at_once_is_spent_by_exactly_one_request(): void
    {
        [, $code] = MobileOtp::issue(self::PHONE, 'login');

        $results = [MobileOtp::check(self::PHONE, 'login', $code), MobileOtp::check(self::PHONE, 'login', $code)];

        $this->assertSame([true, false], $results);

        // and the conditional UPDATE itself refuses a row somebody else already spent
        [$row, $code2] = MobileOtp::issue(self::PHONE, 'login');
        MobileOtp::whereKey($row->id)->update(['is_used' => true]);
        $this->assertFalse(MobileOtp::check(self::PHONE, 'login', $code2));
    }

    public function test_wrong_expired_superseded_and_unrequested_codes_all_get_the_same_answer(): void
    {
        $this->send()->assertOk();
        $first = $this->lastSentCode();
        $wrong = $first === '1111' ? '2222' : '1111';

        $messages = [];
        $messages['wrong'] = $this->verify($wrong)->assertStatus(422)->json('message');

        \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(1));
        RateLimiter::clear('otp-send:' . self::PHONE);
        $this->send()->assertOk();                       // supersedes the first
        $messages['superseded'] = $this->verify($first === $this->lastSentCode() ? $wrong : $first)->assertStatus(422)->json('message');

        $this->travel(MobileOtp::TTL_MINUTES + 1)->minutes();
        $messages['expired'] = $this->verify($this->lastSentCode())->assertStatus(422)->json('message');

        $messages['never asked'] = $this->verify('4321', '9800000000')->assertStatus(422)->json('message');

        $this->assertCount(1, array_unique($messages), 'nothing distinguishes the cases: ' . json_encode($messages));
    }

    public function test_five_wrong_guesses_kill_the_code_even_for_the_right_answer(): void
    {
        $this->send()->assertOk();
        $code = $this->lastSentCode();
        $wrong = $code === '1111' ? '2222' : '1111';

        for ($i = 0; $i < 5; $i++) {
            $this->verify($wrong)->assertStatus(422);
        }

        $late = $this->verify($code);
        $this->assertGreaterThanOrEqual(422, $late->getStatusCode());
        $this->assertNull($late->json('token'));
        $this->assertSame(5, (int) MobileOtp::first()->attempts);
    }

    public function test_the_expiry_is_exactly_the_documented_lifetime(): void
    {
        [, $code] = MobileOtp::issue(self::PHONE, 'login');

        $this->travel(MobileOtp::TTL_MINUTES * 60 - 5)->seconds();
        $this->assertTrue(MobileOtp::check(self::PHONE, 'login', $code), 'still valid a moment before');

        [, $code2] = MobileOtp::issue(self::PHONE, 'login');
        $this->travel(MobileOtp::TTL_MINUTES * 60 + 5)->seconds();
        $this->assertFalse(MobileOtp::check(self::PHONE, 'login', $code2));
    }

    public function test_a_login_code_cannot_reset_a_password_and_the_other_way_round(): void
    {
        [, $loginCode] = MobileOtp::issue(self::PHONE, 'login');
        $this->assertFalse(MobileOtp::check(self::PHONE, 'password_reset', $loginCode));

        // (issuing a code for one purpose retires the other's, so ask again)
        [, $resetCode] = MobileOtp::issue(self::PHONE, 'password_reset');
        $this->assertFalse(MobileOtp::check(self::PHONE, 'login', $resetCode));

        $res = $this->postJson('/api/v1/auth/reset-password', ['email' => $this->user->email, 'otp' => $loginCode, 'password' => 'a-new-Strong-pass-9', 'password_confirmation' => 'a-new-Strong-pass-9']);
        $res->assertStatus(422);
    }

    // ── asking again ────────────────────────────────────────────────────────

    public function test_a_new_code_invalidates_the_old_one(): void
    {
        $this->send()->assertOk();
        $first = $this->lastSentCode();
        RateLimiter::clear('otp-send:' . self::PHONE);
        do {
            $this->send()->assertOk();
            $second = $this->lastSentCode();
            RateLimiter::clear('otp-send:' . self::PHONE);
        } while ($second === $first);   // a repeat draw would make the point moot

        $this->verify($first)->assertStatus(422);
        $this->verify($second)->assertOk();
    }

    public function test_resend_waits_for_the_cooldown_and_tells_the_app_how_long(): void
    {
        config(['otp.resend_cooldown_seconds' => 30]);

        $this->send()->assertOk();
        $r = $this->send()->assertStatus(429);

        $this->assertSame('otp_cooldown', $r->json('code'));
        $this->assertLessThanOrEqual(30, $r->json('retry_after'));
        $this->assertGreaterThan(0, $r->json('retry_after'));
        $this->assertCount(1, $this->sent, 'a repeated tap sends one SMS, not two');

        $this->travel(31)->seconds();
        $this->send()->assertOk();
        $this->assertCount(2, $this->sent);
    }

    public function test_a_failed_send_does_not_start_the_cooldown(): void
    {
        $this->asEnvironment('production');
        config(['otp.resend_cooldown_seconds' => 30]);
        SmsService::$transportOverride = fn () => [['return' => false, 'message' => 'down'], 500];
        $this->send()->assertStatus(503);

        SmsService::$transportOverride = function ($u, $d) { $this->sent[] = ['otp' => $d['otp']]; return [['return' => true], 200]; };
        $this->send()->assertOk();   // not told to wait: the first attempt never reached the person
        $this->assertCount(1, $this->sent);
    }

    public function test_each_number_is_limited_to_a_few_codes_in_ten_minutes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->send()->assertOk();
        }
        $this->send()->assertStatus(429);
    }

    public function test_one_address_cannot_walk_a_list_of_numbers(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->send('98000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT))->assertOk();
        }
        $this->send('9800000099')->assertStatus(429);
    }

    // ── nothing says which numbers are registered ───────────────────────────

    public function test_a_registered_and_an_unknown_number_get_the_same_answer_to_send(): void
    {
        $known = $this->send(self::PHONE)->assertOk()->json();
        $unknown = $this->send('9899999999')->assertOk()->json();

        unset($known['otp'], $unknown['otp']);
        $this->assertSame(array_keys($known), array_keys($unknown));
        $this->assertSame('If this number is registered, a code has been sent to 98******99.', $unknown['message']);
        $this->assertCount(1, $this->sent, 'no SMS for the unknown number');
        $this->assertSame(1, MobileOtp::count());
    }

    public function test_forgot_password_answers_alike_for_known_and_unknown_emails_and_sends_one_sms(): void
    {
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email])->assertOk()->json();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@test.local'])->assertOk()->json();
        unset($known['otp']);

        $this->assertSame($known, $unknown);
        $this->assertCount(1, $this->sent);
    }

    // ── nothing leaks into logs ─────────────────────────────────────────────

    public function test_neither_the_code_nor_the_key_reaches_the_log(): void
    {
        $lines = [];
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Log\Events\MessageLogged::class, function ($e) use (&$lines) {
            $lines[] = $e->level . ' ' . $e->message . ' ' . json_encode($e->context);
        });
        $this->asEnvironment('production');
        SmsService::$transportOverride = function ($u, $d) use (&$captured) { $captured = $d['otp']; return [['return' => false, 'message' => 'rejected ' . $d['otp'] . ' KEY-abc'], 401]; };

        $this->send()->assertStatus(503);

        $blob = implode("\n", $lines);
        $this->assertNotSame('', (string) $captured);
        $this->assertStringNotContainsString('KEY-abc', $blob);
        $this->assertStringNotContainsString((string) $captured, $blob, 'a code the provider echoed back is not logged');
    }

    // ── password reset by code ──────────────────────────────────────────────

    public function test_reset_by_code_sets_the_password_signs_in_and_revokes_the_old_tokens(): void
    {
        $old = $this->user->createToken('mobile-app')->plainTextToken;
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->user->email])->assertOk();
        $code = $this->lastSentCode();

        $res = $this->postJson('/api/v1/auth/reset-password', ['email' => $this->user->email, 'otp' => $code, 'password' => 'a-new-Strong-pass-9', 'password_confirmation' => 'a-new-Strong-pass-9'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertNotEmpty($res->json('token'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-new-Strong-pass-9', $this->user->fresh()->password));
        $this->assertSame(1, $this->user->tokens()->count(), 'only the new session survives');

        $replay = $this->postJson('/api/v1/auth/reset-password', ['email' => $this->user->email, 'otp' => $code, 'password' => 'another-Strong-pass-1', 'password_confirmation' => 'another-Strong-pass-1']);
        $replay->assertStatus(422);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('another-Strong-pass-1', $this->user->fresh()->password), 'a spent reset code changes nothing');
    }
}
