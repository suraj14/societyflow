<?php

namespace Tests\Feature;

use App\Events\NoticePublished;
use App\Listeners\SendNoticePublishedSms;
use App\Models\EmailLog;
use App\Models\Notice;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "The same notification reaches the same person once": an event that fires twice, or a queue retry after a send that
 * did succeed, must not text or WhatsApp a resident twice - but a failed attempt can be retried, a different message (or
 * the same one after the window) goes out, repeated saves send nothing, and OTP resends are deliberate and unaffected.
 * Transports are mocks.
 */
class DuplicateSendTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private array $sms = [];
    private array $wa = [];
    private int $smsStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha Heights');
        foreach (['sms_enabled' => true, 'notification_sms_enabled' => true] as $k => $v) {
            SystemSetting::set('sms', $k, $v, 'boolean', $this->society->id);
        }
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'BOOKMZ', 'string', $this->society->id);
        SmsTemplate::create(['society_id' => $this->society->id, 'name' => 'Notice', 'purpose' => 'notice', 'audience' => 'resident', 'body' => '{{notice_title}}', 'dlt_message_id' => '223577', 'is_active' => true]);
        SmsService::$transportOverride = function (string $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->sms[] = $q;

            return $this->smsStatus === 200
                ? [['return' => true, 'request_id' => 'r' . count($this->sms), 'message' => ['ok']], 200]
                : [['return' => false, 'message' => ['Rejected']], $this->smsStatus];
        };

        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'WA-KEY-1234567890', 'encrypted', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_notice_template_name', 'notice_alert', 'string', $this->society->id);
        WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => 'e1', 'message_id' => '8340', 'name' => 'notice_alert', 'status' => 'APPROVED', 'language' => 'en', 'var_count' => 1, 'body_text' => '{{1}}']);
        SystemSetting::set('whatsapp', 'whatsapp_notice_variables', ['notice_title'], 'json', $this->society->id);
        WhatsAppService::$transportOverride = function (string $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->wa[] = $q;

            return [['status' => true, 'request_id' => 'w' . count($this->wa)], 200];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function notice(string $title = 'Water cut'): Notice
    {
        $author = $this->makeUserWithRole($this->society, 'Admin', ['phone' => null]);

        return Notice::create(['society_id' => $this->society->id, 'title' => $title, 'content' => 'x', 'type' => 'general', 'priority' => 'medium', 'status' => 'published', 'created_by' => $author->id]);
    }

    private function fire(Notice $notice): void
    {
        (new SendNoticePublishedSms())->handle(new NoticePublished($notice));
    }

    // ── SMS ─────────────────────────────────────────────────────────────────

    public function test_the_same_notice_event_firing_twice_texts_each_resident_once(): void
    {
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543211']);
        $notice = $this->notice();

        $this->fire($notice);
        $this->fire($notice);

        $this->assertCount(2, $this->sms, 'two residents, one SMS each');
        $this->assertSame(['9876543210', '9876543211'], array_column($this->sms, 'numbers'));
        $this->assertSame(2, EmailLog::where('channel', 'sms')->where('status', 'sent')->count(), 'the second attempt writes no extra delivery row');
        $this->assertSame(0, EmailLog::where('channel', 'sms')->where('status', 'failed')->count());
    }

    public function test_a_different_notice_goes_out_even_to_the_same_number(): void
    {
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->fire($this->notice('Water cut'));
        $this->fire($this->notice('Lift maintenance'));

        $this->assertSame(['Water cut', 'Lift maintenance'], array_column($this->sms, 'variables_values'));
    }

    public function test_a_failed_send_is_not_remembered_so_it_can_be_retried(): void
    {
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);
        $notice = $this->notice();

        $this->smsStatus = 400;
        $this->fire($notice);
        $this->assertSame(1, EmailLog::where('channel', 'sms')->where('status', 'failed')->count());

        $this->smsStatus = 200;
        $this->fire($notice);

        $this->assertCount(2, $this->sms, 'the retry after a failure is sent');
        $this->assertSame(1, EmailLog::where('channel', 'sms')->where('status', 'sent')->count());
    }

    public function test_the_same_message_is_sent_again_once_the_window_has_passed(): void
    {
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);
        $notice = $this->notice();

        $this->fire($notice);
        $this->travel(6)->minutes();
        $this->fire($notice);

        $this->assertCount(2, $this->sms);
    }

    public function test_the_same_notice_in_another_society_is_a_separate_message(): void
    {
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);
        $this->fire($this->notice());

        $other = $this->makeSociety('Beta');
        foreach (['sms_enabled', 'notification_sms_enabled'] as $k) {
            SystemSetting::set('sms', $k, true, 'boolean', $other->id);
        }
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $other->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'BOOKMZ', 'string', $other->id);
        SmsTemplate::create(['society_id' => $other->id, 'name' => 'Notice', 'purpose' => 'notice', 'audience' => 'resident', 'body' => '{{notice_title}}', 'dlt_message_id' => '223577', 'is_active' => true]);
        $this->makeUserWithRole($other, 'Tenant', ['phone' => '9876543210']);
        $author = $this->makeUserWithRole($other, 'Admin', ['phone' => null]);
        $this->fire(Notice::create(['society_id' => $other->id, 'title' => 'Water cut', 'content' => 'x', 'type' => 'general', 'priority' => 'medium', 'status' => 'published', 'created_by' => $author->id]));

        $this->assertCount(2, $this->sms);
    }

    public function test_the_login_otp_is_never_suppressed_a_resend_is_deliberate(): void
    {
        SystemSetting::set('sms', 'fast2sms_template_id', '223577', 'string', $this->society->id);

        SmsService::sendOtp('9876543210', '4821', $this->society->id);
        SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertCount(2, $this->sms);
    }

    public function test_saving_settings_repeatedly_sends_nothing(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        foreach (range(1, 3) as $i) {
            $this->actingAs($admin)->post(route('settings.sms.update'), ['sms_provider' => 'fast2sms', 'sms_enabled' => '1', 'fast2sms_sender_id' => 'BOOKMZ', 'fast2sms_template_id' => '223577'])->assertSessionHasNoErrors();
            $this->actingAs($admin)->post(route('settings.whatsapp.update'), ['whatsapp_enabled' => '1', 'whatsapp_phone_number_id' => '1209719742234591'])->assertSessionHasNoErrors();
        }

        $this->assertSame([], $this->sms);
        $this->assertSame([], $this->wa);
        $this->assertSame(0, EmailLog::count());
    }

    // ── WhatsApp ────────────────────────────────────────────────────────────

    public function test_the_same_whatsapp_notification_twice_goes_once_and_logs_once(): void
    {
        $values = ['resident_name' => 'Asha', 'notice_title' => 'Water cut', 'society_name' => 'Alpha Heights'];

        $first = WhatsAppService::sendNotification('notice', '9876543210', $values, $this->society->id);
        $second = WhatsAppService::sendNotification('notice', '9876543210', $values, $this->society->id);

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
        $this->assertSame('skipped_duplicate', $second['status']);
        $this->assertCount(1, $this->wa);
        $this->assertSame(1, EmailLog::where('channel', 'whatsapp')->count(), 'the suppressed copy leaves no delivery row');
    }

    public function test_a_failed_whatsapp_send_can_be_retried_and_a_different_message_goes_out(): void
    {
        $values = ['notice_title' => 'Water cut'];
        WhatsAppService::$transportOverride = fn () => [['status' => false, 'message' => 'Rejected'], 400];
        $this->assertFalse(WhatsAppService::sendNotification('notice', '9876543210', $values, $this->society->id)['success']);

        WhatsAppService::$transportOverride = function (string $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->wa[] = $q;

            return [['status' => true, 'request_id' => 'w'], 200];
        };
        $this->assertTrue(WhatsAppService::sendNotification('notice', '9876543210', $values, $this->society->id)['success'], 'the retry is sent');
        $this->assertTrue(WhatsAppService::sendNotification('notice', '9876543210', ['notice_title' => 'Lift maintenance'], $this->society->id)['success']);

        $this->assertSame(['Water cut', 'Lift maintenance'], array_column($this->wa, 'variables_values'));
    }
}
