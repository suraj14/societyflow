<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `email_logs` is the delivery log for every channel now, not just email
 * (2026_10_07_000005 widened it: email_template_id and recipient_email are
 * nullable, recipient_phone was added - a full-table rebuild on SQLite,
 * verified not to lose any of this project's existing rows at migration
 * time). This proves an SMS send is actually recorded there and that the
 * existing admin screen (`/email-logs`) - built before this round, not
 * rebuilt - renders a mixed channel log without error.
 */
class DeliveryLogsMultiChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    public function test_an_sms_send_is_recorded_in_the_shared_delivery_log(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $society->id);
        SystemSetting::set('sms', 'fast2sms_route', 'dlt', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_template_id', '169ec8e6c8', 'string', $society->id);
        // Notifications use their own approved template; the OTP template is never reused for them.
        SystemSetting::set('sms', 'fast2sms_notification_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_notification_template_id', '169ec8e6c8', 'string', $society->id);

        SmsService::$transportOverride = fn () => [['return' => true], 200];

        SmsService::sendDltNotification('notification', '9876543210', 'Test message', $society->id);

        $this->assertDatabaseHas('email_logs', [
            'channel'         => 'sms',
            'recipient_phone' => '9876543210',
            'trigger_action'  => 'notification',
            'status'          => 'sent',
            'email_template_id' => null,
            'recipient_email'   => null,
        ]);
    }

    public function test_a_failed_sms_send_is_recorded_with_its_error(): void
    {
        $society = $this->makeSociety();
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $society->id);
        SystemSetting::set('sms', 'fast2sms_route', 'dlt', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_template_id', '169ec8e6c8', 'string', $society->id);
        // Notifications use their own approved template; the OTP template is never reused for them.
        SystemSetting::set('sms', 'fast2sms_notification_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_notification_template_id', '169ec8e6c8', 'string', $society->id);

        SmsService::$transportOverride = fn () => [['return' => false, 'message' => 'Invalid Sender ID'], 400];

        SmsService::sendDltNotification('notification', '9876543210', 'Test message', $society->id);

        $this->assertDatabaseHas('email_logs', [
            'channel'  => 'sms',
            'status'   => 'failed',
        ]);
        $log = EmailLog::where('channel', 'sms')->latest('id')->first();
        $this->assertStringContainsString('Invalid Sender ID', $log->error_message);
    }

    public function test_the_delivery_logs_page_renders_a_mixed_channel_log_without_error(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        EmailLog::create([
            'society_id' => $society->id, 'channel' => 'email',
            'recipient_email' => 'owner@test.local', 'trigger_action' => 'notice_published', 'status' => 'sent',
        ]);
        EmailLog::create([
            'society_id' => $society->id, 'channel' => 'sms',
            'recipient_phone' => '9876543210', 'trigger_action' => 'notification', 'status' => 'sent',
        ]);

        $html = $this->actingAs($admin)->get('/admin/email-logs')->assertOk()->getContent();

        $this->assertStringContainsString('owner@test.local', $html);
        $this->assertStringContainsString('9876543210', $html);
    }

    public function test_the_delivery_logs_page_can_filter_by_channel(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        EmailLog::create([
            'society_id' => $society->id, 'channel' => 'email',
            'recipient_email' => 'owner@test.local', 'trigger_action' => 'notice_published', 'status' => 'sent',
        ]);
        EmailLog::create([
            'society_id' => $society->id, 'channel' => 'sms',
            'recipient_phone' => '9876543210', 'trigger_action' => 'notification', 'status' => 'sent',
        ]);

        $html = $this->actingAs($admin)->get('/admin/email-logs?channel=sms')->assertOk()->getContent();

        $this->assertStringContainsString('9876543210', $html);
        $this->assertStringNotContainsString('owner@test.local', $html);
    }
}
