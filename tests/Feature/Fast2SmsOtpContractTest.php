<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fast2SMS OTP, against the official reference (docs.fast2sms.com, read for this change):
 *
 *   Send   POST https://www.fast2sms.com/dev/otp/send   header Authorization: <key>
 *          body  mobile (^[0-9]{10}$, required), otp_id (required), otp (optional, caller-supplied, <=10 chars),
 *                otp_expiry (minutes, 1-10080), otp_length (4-10), variables_values, udf1-3
 *          200 {return:true, status_code:200, request_id, message}   400 {return:false, message:"Invalid OTP ID"}
 *          401 {return:false, message:"Invalid Authorization key"}
 *   Verify POST https://www.fast2sms.com/dev/otp/verify  {mobile, otp}   200 verified / 400 invalid|expired|attempts / 404 not found
 *
 * SocietyFlow uses the documented caller-supplied-OTP mode: the app generates the code, stores it, hands the SAME
 * code to Fast2SMS as `otp` for delivery, and verifies it itself (with attempt limits, expiry, single use and
 * rate limits). It never asks Fast2SMS to verify a code it did not generate. Provider-side verification is the
 * other documented mode and is deliberately not used. The provider is a recording stub: nothing leaves the process.
 */
class Fast2SmsOtpContractTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'unit-test-key-not-real';
    private const PHONE = '9222222222';

    private $society;
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society));
        $this->makeResidentUser($this->society, $flat, ['phone' => self::PHONE]);

        foreach (['sms_enabled' => [true, 'boolean'], 'fast2sms_route' => ['otp', 'string'], 'fast2sms_otp_id' => ['6fd92db0ae', 'string'], 'fast2sms_api_key' => [self::KEY, 'encrypted']] as $k => [$v, $type]) {
            SystemSetting::set('sms', $k, $v, $type, $this->society->id);
        }
        $this->provider(200, ['return' => true, 'status_code' => 200, 'request_id' => 'req123abc', 'message' => ['OTP sent successfully']]);
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function provider(int $status, array $body): void
    {
        SmsService::$transportOverride = function (string $url, array $data, array $headers) use ($status, $body) {
            $this->calls[] = ['url' => $url, 'data' => $data, 'headers' => $headers];

            return [$body, $status];
        };
    }

    private function sentOtp(): string
    {
        return (string) collect($this->calls)->last()['data']['otp'];
    }

    // ── the documented send request ─────────────────────────────────────────

    public function test_the_send_request_matches_the_documented_contract(): void
    {
        $r = SmsService::sendOtp(self::PHONE, '4821', $this->society->id);

        $call = $this->calls[0];
        $this->assertSame('https://www.fast2sms.com/dev/otp/send', $call['url']);
        $this->assertContains('authorization: ' . self::KEY, $call['headers']);
        $this->assertContains('Content-Type: application/json', $call['headers']);
        $this->assertSame(['mobile', 'otp', 'otp_expiry', 'otp_id'], collect($call['data'])->keys()->sort()->values()->all(), 'exactly the documented fields, no extras');
        $this->assertMatchesRegularExpression('/^[0-9]{10}$/', $call['data']['mobile']);
        $this->assertSame('6fd92db0ae', $call['data']['otp_id']);
        $this->assertSame('4821', $call['data']['otp']);
        $this->assertLessThanOrEqual(10, strlen($call['data']['otp']), 'documented maximum');
        $this->assertSame(MobileOtp::TTL_MINUTES, $call['data']['otp_expiry'], 'one lifetime for the provider and the app check');
        $this->assertTrue($r['sent']);
        $this->assertSame('req123abc', $r['request_id'], 'the provider reference is kept for support');
    }

    public function test_a_malformed_number_is_refused_before_any_request(): void
    {
        foreach (['12345', 'abcdefghij', '', '98765432101234'] as $bad) {
            $r = SmsService::sendOtp($bad, '4821', $this->society->id);
            $this->assertFalse($r['success'], "'$bad'");
            $this->assertSame('invalid_number', $r['error_code']);
            $this->assertFalse($r['sent']);
        }
        $this->assertSame([], $this->calls, 'no request is made for a number the provider would reject');
    }

    public function test_country_code_forms_are_normalised_to_ten_digits(): void
    {
        SmsService::sendOtp('+91 92222 22222', '4821', $this->society->id);
        SmsService::sendOtp('919222222222', '4821', $this->society->id);

        $this->assertSame(['9222222222', '9222222222'], array_column(array_column($this->calls, 'data'), 'mobile'));
    }

    // ── documented responses ────────────────────────────────────────────────

    public function test_documented_failures_become_stable_plain_reasons(): void
    {
        $this->provider(401, ['return' => false, 'status_code' => 401, 'message' => 'Invalid Authorization key']);
        $r = SmsService::sendOtp(self::PHONE, '4821', $this->society->id);
        $this->assertSame(['failed', false, 'invalid_api_key', 401], [$r['status'], $r['sent'], $r['error_code'], $r['provider_status']]);

        $this->provider(400, ['return' => false, 'status_code' => 400, 'message' => 'Invalid OTP ID']);
        $r = SmsService::sendOtp(self::PHONE, '4821', $this->society->id);
        $this->assertSame(['failed', 'invalid_otp_id'], [$r['status'], $r['error_code']]);

        $this->provider(502, ['message' => 'Bad gateway']);
        $this->assertSame('provider_unavailable', SmsService::sendOtp(self::PHONE, '4821', $this->society->id)['error_code']);
    }

    public function test_http_200_without_return_true_is_not_a_success(): void
    {
        foreach ([['return' => false, 'message' => 'Something odd'], ['raw' => '<html>maintenance</html>'], []] as $body) {
            $this->provider(200, $body);
            $r = SmsService::sendOtp(self::PHONE, '4821', $this->society->id);
            $this->assertFalse($r['success']);
            $this->assertFalse($r['sent']);
        }
    }

    public function test_the_api_key_never_appears_in_a_failure_message(): void
    {
        $this->provider(401, ['return' => false, 'message' => 'Invalid Authorization key ' . self::KEY . ' authorization: ' . self::KEY]);

        $r = SmsService::sendOtp(self::PHONE, '4821', $this->society->id);

        $this->assertStringNotContainsString(self::KEY, json_encode($r));
    }

    public function test_the_provider_is_never_asked_to_verify(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $this->sentOtp()])->assertOk();

        foreach ($this->calls as $c) {
            $this->assertStringNotContainsString('/otp/verify', $c['url']);
        }
    }

    // ── one coherent flow: the code that was delivered is the code that verifies ──

    public function test_the_code_handed_to_the_provider_is_the_code_the_app_accepts(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();
        $delivered = $this->sentOtp();

        $stored = MobileOtp::where('phone', self::PHONE)->latest('id')->value('otp');
        $this->assertNotSame($delivered, $stored, 'stored as a keyed hash, not as the code');
        $this->assertSame(MobileOtp::hashCode(self::PHONE, 'login', $delivered), $stored);
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $delivered])->assertOk()->assertJsonPath('success', true);
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();
        $code = $this->sentOtp();

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $code])->assertOk();
        $again = $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $code]);

        $again->assertStatus(422)->assertJsonPath('success', false);
        $this->assertNull($again->json('token'));
    }

    public function test_a_wrong_code_counts_attempts_and_five_wrong_ones_lock_the_code_even_for_the_right_answer(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();
        $code = $this->sentOtp();
        $wrong = $code === '1111' ? '2222' : '1111';

        for ($i = 4; $i >= 0; $i--) {
            $r = $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $wrong]);
            $r->assertStatus(422);
            $this->assertStringContainsString('not valid or has expired', $r->json('message'));   // one answer for every kind of bad code
        }

        $late = $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $code]);
        $this->assertGreaterThanOrEqual(422, $late->getStatusCode());
        $this->assertNull($late->json('token'), 'the right code no longer works once the attempts are used up');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();
        $code = $this->sentOtp();

        $this->travel(MobileOtp::TTL_MINUTES + 1)->minutes();

        $r = $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $code]);
        $r->assertStatus(422);
        $this->assertStringContainsString('expired', $r->json('message'));
    }

    public function test_requesting_a_new_code_invalidates_the_previous_one(): void
    {
        $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();
        $first = $this->sentOtp();
        $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();
        $second = $this->sentOtp();

        if ($first !== $second) {
            $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $first])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/verify-otp', ['phone' => self::PHONE, 'otp' => $second])->assertOk();
    }

    public function test_resend_is_limited_to_three_requests_per_window(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();
        }
        $this->assertCount(3, $this->calls);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertStatus(429);
        $this->assertCount(3, $this->calls, 'the throttled request reaches no provider');
    }

    public function test_outside_dev_the_response_never_contains_the_code(): void
    {
        $this->app['env'] = 'production';

        $r = $this->postJson('/api/v1/auth/send-otp', ['phone' => self::PHONE])->assertOk();

        $this->assertArrayNotHasKey('otp', $r->json());
        $this->assertStringNotContainsString($this->sentOtp(), (string) $r->getContent());
    }
}
