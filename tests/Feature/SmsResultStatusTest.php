<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\MobileOtp;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SmsService result contract: every result carries `status` (sent | skipped_disabled | skipped_not_configured |
 * failed) and `sent` (true only when the provider accepted the request). `success`/`message` keep their historical
 * values for old callers, so a skip still reads success=true / 'dev_mode' there - but must never be reported as
 * sent, and the Delivery Log / UI say "Skipped - not sent". The provider is mocked; nothing leaves the process.
 */
class SmsResultStatusTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');

        // Safety net: whatever a test does, the provider transport is a recording stub that rejects the request.
        // Nothing in this class can reach the network (an earlier draft did, via a stale cached fake key).
        SmsService::$transportOverride = function (string $url, array $data) {
            $this->calls[] = ['url' => $url, 'message' => ($data['message'] ?? self::queryOf($url)['message'] ?? null), 'unexpected' => true];

            return [['return' => false, 'message' => ['stub: no provider in tests']], 401];
        };
    }

    /** Fast2SMS SMS is a documented GET: the values are in the URL's query string. */
    private static function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

        return $q;
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function guardTransport(): void
    {
        SmsService::$transportOverride = function (string $url, array $data) {
            $this->calls[] = ['url' => $url, 'message' => ($data['message'] ?? self::queryOf($url)['message'] ?? null), 'unexpected' => true];

            return [['return' => false, 'message' => ['stub: no provider in tests']], 401];
        };
    }

    private function set(string $key, $value, string $type = 'string'): void
    {
        SystemSetting::set('sms', $key, $value, $type, $this->society->id);
    }

    private function configure(bool $withNotificationTemplate = true): void
    {
        $this->set('fast2sms_api_key', 'test-key-not-real', 'encrypted');
        $this->set('sms_enabled', true, 'boolean');
        $this->set('notification_sms_enabled', true, 'boolean');
        $this->set('fast2sms_route', 'dlt');
        $this->set('fast2sms_template_id', '182001');          // the OTP template
        $this->set('fast2sms_sender_id', 'ROYALE');
        if ($withNotificationTemplate) {
            $this->set('fast2sms_notification_template_id', '182002');
            $this->set('fast2sms_notification_sender_id', 'ROYALE');
        }
    }

    private function mockProvider(array $body, int $status = 200): void
    {
        SmsService::$transportOverride = function (string $url, array $data) use ($body, $status) {
            $this->calls[] = ['url' => $url, 'message' => ($data['message'] ?? self::queryOf($url)['message'] ?? null)];

            return [$body, $status];
        };
    }

    // ── notifications ───────────────────────────────────────────────────────

    public function test_a_switched_off_notification_is_skipped_never_sent_and_writes_no_log_row(): void
    {
        $this->configure();
        $this->set('notification_sms_enabled', false, 'boolean');
        $this->mockProvider(['return' => true]);

        $r = SmsService::sendDltNotification('notification', '9876543210', 'Water off', $this->society->id);

        $this->assertSame('skipped_disabled', $r['status']);
        $this->assertFalse($r['sent']);
        $this->assertSame('dev_mode', $r['message'], 'legacy shape kept for old callers');
        $this->assertSame([], $this->calls);
        $this->assertSame(0, EmailLog::count(), 'turned off on purpose: nothing to show');
    }

    public function test_a_missing_api_key_is_skipped_not_configured_and_shows_as_skipped_in_the_log(): void
    {
        $this->set('notification_sms_enabled', true, 'boolean');
        $this->mockProvider(['return' => true]);

        $r = SmsService::sendDltNotification('notification', '9876543210', 'Water off', $this->society->id);

        $this->assertSame('skipped_not_configured', $r['status']);
        $this->assertFalse($r['sent']);
        $this->assertSame([], $this->calls);
        $log = EmailLog::sole();
        $this->assertTrue($log->isSkipped());
        $this->assertNotSame('sent', $log->status);
        $this->assertNull($log->sent_at);
        $this->assertFalse($log->shouldRetry(), 'a skip is not retried');
    }

    public function test_a_missing_notification_template_never_falls_back_to_the_otp_one_and_is_logged_as_skipped(): void
    {
        $this->configure(withNotificationTemplate: false);
        $this->mockProvider(['return' => true]);

        $r = SmsService::sendDltNotification('notification', '9876543210', 'Water off', $this->society->id);

        $this->assertFalse($r['success']);
        $this->assertSame('skipped_not_configured', $r['status']);
        $this->assertFalse($r['sent']);
        $this->assertSame([], $this->calls, 'the OTP template 182001 is never used for a notice');
        $this->assertTrue(EmailLog::sole()->isSkipped());
    }

    public function test_the_global_kill_switch_skips_without_calling_out_and_is_logged_as_skipped(): void
    {
        $this->configure();
        // Real transport on purpose: the kill switch (false suite-wide) must stop it before any curl call.
        SmsService::$transportOverride = null;
        $this->assertFalse(\App\Support\ExternalMessaging::enabled());

        $r = SmsService::sendDltNotification('notification', '9876543210', 'Water off', $this->society->id);

        $this->assertFalse($r['success']);
        $this->assertSame('skipped_disabled', $r['status']);
        $this->assertFalse($r['sent']);
        $this->assertStringContainsString('EXTERNAL_MESSAGING_ENABLED', $r['message']);
        $log = EmailLog::sole();
        $this->assertTrue($log->isSkipped());
        $this->assertNull($log->sent_at);
    }

    public function test_provider_acceptance_is_sent_and_logged_as_sent_using_the_notification_template(): void
    {
        $this->configure();
        $this->mockProvider(['return' => true, 'message' => ['SMS sent successfully.']]);

        $r = SmsService::sendDltNotification('notification', '9876543210', 'Water off', $this->society->id);

        $this->assertTrue($r['success']);
        $this->assertSame('sent', $r['status']);
        $this->assertTrue($r['sent']);
        $this->assertSame('182002', $this->calls[0]['message'], 'notification template, not the OTP one');
        $log = EmailLog::sole();
        $this->assertSame('sent', $log->status);
        $this->assertFalse($log->isSkipped());
    }

    public function test_a_provider_rejection_is_failed_not_skipped_and_not_sent(): void
    {
        $this->configure();
        $this->mockProvider(['return' => false, 'message' => ['Invalid template']], 200);

        $r = SmsService::sendDltNotification('notification', '9876543210', 'Water off', $this->society->id);

        $this->assertFalse($r['success']);
        $this->assertSame('failed', $r['status']);
        $this->assertFalse($r['sent']);
        $log = EmailLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertFalse($log->isSkipped(), 'a real failure stays a failure');
    }

    // ── OTP ─────────────────────────────────────────────────────────────────

    public function test_otp_statuses_for_disabled_unconfigured_accepted_and_rejected(): void
    {
        // off
        $this->mockProvider(['return' => true]);
        $off = SmsService::sendOtp('9876543210', '4821', $this->society->id);
        $this->assertSame(['skipped_disabled', false], [$off['status'], $off['sent']]);

        // on, no key
        $this->set('sms_enabled', true, 'boolean');
        $noKey = SmsService::sendOtp('9876543210', '4821', $this->society->id);
        $this->assertSame(['skipped_not_configured', false], [$noKey['status'], $noKey['sent']]);
        $this->assertSame([], $this->calls);

        // accepted
        $this->configure();
        $this->mockProvider(['return' => true]);
        $ok = SmsService::sendOtp('9876543210', '4821', $this->society->id);
        $this->assertSame(['sent', true], [$ok['status'], $ok['sent']]);
        $this->assertSame('182001', $this->calls[0]['message'], 'OTP uses the OTP template');

        // rejected
        $this->mockProvider(['return' => false, 'message' => ['Invalid']], 400);
        $bad = SmsService::sendOtp('9876543210', '4821', $this->society->id);
        $this->assertSame(['failed', false], [$bad['status'], $bad['sent']]);
    }

    public function test_otp_template_changes_do_not_leak_into_notifications(): void
    {
        $this->configure();
        $this->set('fast2sms_template_id', '999999');
        $this->mockProvider(['return' => true]);

        SmsService::sendDltNotification('notification', '9876543210', 'Water off', $this->society->id);
        SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertSame(['182002', '999999'], array_column($this->calls, 'message'));
    }

    // ── callers ─────────────────────────────────────────────────────────────

    public function test_the_test_sms_button_says_not_sent_and_why_for_each_skip(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        // kill switch (suite default) with everything configured: the REAL transport, which is stopped before curl
        $this->configure();
        SmsService::$transportOverride = null;
        $r = $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210', 'template' => 'otp'])->assertOk();
        $r->assertJson(['success' => false, 'skipped' => true, 'status' => 'skipped_disabled']);
        $this->assertStringStartsWith('Not sent:', $r->json('message'));
        $this->assertStringContainsString('switched off on this server', $r->json('message'));
        $this->assertStringNotContainsString('Test SMS sent', $r->json('message'));

        // messaging enabled, key missing: the stub is back, and it must never be reached
        $this->guardTransport();
        config(['messaging.enabled' => true]);
        $this->set('fast2sms_api_key', '', 'encrypted');   // cleared through the model so the settings cache is invalidated
        $r = $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210', 'template' => 'otp', 'route' => 'dlt'])->assertOk();
        $r->assertJson(['success' => false, 'skipped' => true, 'status' => 'skipped_not_configured']);
        $this->assertStringContainsString('API key is not saved', $r->json('message'));
    }

    public function test_otp_login_reports_a_skipped_send_as_not_set_up_and_never_as_sent(): void
    {
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society));
        $user = $this->makeResidentUser($this->society, $flat, ['phone' => '9333333333']);
        $this->app['env'] = 'production';      // dev environments hand the OTP back; production must not

        // SMS on and fully configured, but the kill switch is off: nothing is sent.
        $this->configure();
        SmsService::$transportOverride = null;   // real transport; the kill switch stops it before curl
        $r = $this->postJson('/api/v1/auth/send-otp', ['phone' => $user->phone]);

        $r->assertStatus(503)->assertJsonPath('success', false)->assertJsonPath('code', 'sms_not_configured');
        $this->assertStringNotContainsString('OTP sent successfully', (string) $r->getContent());
        $this->assertSame(0, MobileOtp::where('phone', $user->phone)->where('is_used', false)->count(), 'the unsent code is revoked');
        $this->assertSame([], $this->calls, 'nothing reached any transport');
    }

    public function test_the_delivery_log_screen_labels_skipped_rows_and_does_not_count_them_as_failures(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->set('notification_sms_enabled', true, 'boolean');
        SmsService::sendDltNotification('notification', '9876543210', 'Water off', $this->society->id);   // skipped: no key
        EmailLog::create(['society_id' => $this->society->id, 'channel' => 'sms', 'recipient_phone' => '9876543211', 'trigger_action' => 'notification',
            'status' => 'failed', 'error_message' => 'Provider said no', 'retry_count' => 0]);

        $page = $this->actingAs($admin)->get(route('admin.email-logs.index'))->assertOk();

        $page->assertSee('Skipped - not sent')->assertSee('Failed');
        $stats = $page->viewData('stats');
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertSame(0, $stats['sent']);
    }
}
