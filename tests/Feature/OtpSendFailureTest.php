<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Regression: outside local/dev, send-otp answered success:true even when
 * no SMS was sent (Fast2SMS rejected the key, or SMS was not configured),
 * so the app sat on the OTP screen waiting for a code that never came.
 * Found on the dev box, where society 1's saved key and the .env fallback
 * key are both rejected by Fast2SMS.
 */
class OtpSendFailureTest extends TestCase
{
    use RefreshDatabase;

    private string $phone = '9811112222';
    private int $societyId;

    protected function setUp(): void
    {
        parent::setUp();
        $society = $this->makeSociety();
        $this->societyId = $society->id;
        $this->makeResidentUser($society, $this->makeFlat($society, $this->makeBuilding($society)), ['phone' => $this->phone]);
        $this->app['env'] = 'production';
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function configureSms(): void
    {
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->societyId);
        SystemSetting::set('sms', 'fast2sms_api_key', 'KEY-abc', 'encrypted', $this->societyId);
        SystemSetting::set('sms', 'fast2sms_route', 'otp', 'string', $this->societyId);
        SystemSetting::set('sms', 'fast2sms_otp_id', 'otpid12345', 'string', $this->societyId);
    }

    public function test_unconfigured_sms_is_reported_as_a_failure_in_production(): void
    {
        $r = $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone])->assertStatus(503);

        $r->assertJson(['success' => false, 'code' => 'sms_not_configured']);
        $r->assertJsonMissingPath('otp');
        $this->assertSame(0, MobileOtp::where('phone', $this->phone)->where('is_used', false)->count(), 'an OTP that was never sent cannot be used');
        $this->assertSame(0, RateLimiter::attempts('otp-send:' . $this->phone), 'our failure does not use up the user\'s attempts');
    }

    public function test_a_provider_rejection_is_reported_as_a_failure_in_production(): void
    {
        $this->configureSms();
        SmsService::$transportOverride = fn () => [['return' => false, 'status_code' => 412, 'message' => 'Invalid Authentication, Check Authorization Key'], 401];

        $r = $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone])->assertStatus(503);

        $r->assertJson(['success' => false, 'code' => 'sms_failed']);
        $r->assertJsonMissingPath('otp');
        $this->assertStringNotContainsString('KEY-abc', $r->getContent());
    }

    public function test_an_accepted_send_succeeds_without_exposing_the_otp(): void
    {
        $this->configureSms();
        $sent = null;
        SmsService::$transportOverride = function ($url, $data) use (&$sent) { $sent = ['url' => $url, 'data' => $data]; return [['return' => true, 'request_id' => 'r1'], 200]; };

        $r = $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone])->assertOk();

        $r->assertJson(['success' => true]);
        $r->assertJsonMissingPath('otp');
        $this->assertSame('https://www.fast2sms.com/dev/otp/send', $sent['url']);
        $code = $sent['data']['otp'];
        $row = MobileOtp::where('phone', $this->phone)->where('is_used', false)->first();
        $this->assertSame(1, preg_match('/^[1-9][0-9]{3}$/', $code), 'four digits');
        $this->assertNotSame($code, $row->otp, 'the table never holds the code as typed');
        $this->assertSame(64, strlen($row->otp));

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $this->phone, 'otp' => $code])
            ->assertOk()->assertJsonPath('success', true);
    }
}
