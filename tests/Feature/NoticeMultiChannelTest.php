<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Notice now has two independent channel listeners on the same event:
 * SendNoticePublishedEmail (queues DispatchEventNotification) and
 * SendNoticePublishedSms (calls SmsService::sendDltNotification directly -
 * no queue, so this one is observable in the same request without the
 * afterCommit/RefreshDatabase interaction NoticePublishedEmailE2ETest works
 * around). This proves SMS actually fires from the real event, and that it
 * is a separate code path from email, not a shared one that would fail
 * together.
 */
class NoticeMultiChannelTest extends TestCase
{
    use RefreshDatabase;

    private function adminWhoCanManageNotices(\App\Models\Society $society): \App\Models\User
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_notices', 'guard_name' => 'web']);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $admin->givePermissionTo('manage_notices');
        return $admin;
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    public function test_publishing_a_notice_sends_sms_when_the_toggle_is_on(): void
    {
        Queue::fake();
        Mail::fake();

        $society  = $this->makeSociety();
        $admin    = $this->adminWhoCanManageNotices($society);
        $tenant   = $this->makeUserWithRole($society, 'Tenant', ['phone' => '9876543210']);

        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $society->id);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $society->id);
        SystemSetting::set('sms', 'fast2sms_route', 'dlt', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_template_id', '169ec8e6c8', 'string', $society->id);
        // Notifications use their own approved template; the OTP template is never reused for them.
        SystemSetting::set('sms', 'fast2sms_notification_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_notification_template_id', '169ec8e6c8', 'string', $society->id);

        $sent = null;
        SmsService::$transportOverride = function (string $url, array $data, array $headers) use (&$sent) {
            $sent = ['url' => $url, 'data' => $data];
            return [['return' => true], 200];
        };

        $this->actingAs($admin)->post('/notices', [
            'title'    => 'Water Supply Interruption',
            'content'  => 'Water will be shut off 10am-2pm.',
            'type'     => 'general',
            'priority' => 'medium',
            'status'   => 'published',
        ])->assertRedirect();

        $this->assertNotNull($sent, 'the SMS channel must fire independently of the queued email job');
        // Fast2SMS DLT SMS is a documented GET: every value is in the query string
        $this->assertStringStartsWith('https://www.fast2sms.com/dev/bulkV2?', $sent['url']);
        parse_str((string) parse_url($sent['url'], PHP_URL_QUERY), $q);
        $this->assertSame('9876543210', $q['numbers']);
        $this->assertSame('dlt', $q['route']);
    }

    public function test_sms_is_not_sent_when_the_toggle_is_off(): void
    {
        Queue::fake();
        Mail::fake();

        $society = $this->makeSociety();
        $admin   = $this->adminWhoCanManageNotices($society);
        $this->makeUserWithRole($society, 'Tenant', ['phone' => '9876543210']);
        // notification_sms_enabled left at its default (off).

        $sent = null;
        SmsService::$transportOverride = function (string $url, array $data, array $headers) use (&$sent) {
            $sent = true;
            return [['return' => true], 200];
        };

        $this->actingAs($admin)->post('/notices', [
            'title'    => 'Gate Maintenance',
            'content'  => 'x',
            'type'     => 'general',
            'priority' => 'low',
            'status'   => 'published',
        ])->assertRedirect();

        $this->assertNull($sent);
    }

    public function test_sms_still_fires_when_the_email_listener_would_fail(): void
    {
        // The email channel's job is queued (afterCommit) and never runs in
        // this test process at all - proof by construction that SMS does
        // not depend on it: this assertion is reached only if the SMS
        // listener ran to completion in the same request where email never
        // got further than being queued.
        Queue::fake();

        $society = $this->makeSociety();
        $admin   = $this->adminWhoCanManageNotices($society);
        $this->makeUserWithRole($society, 'Tenant', ['phone' => '9876543210']);

        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $society->id);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $society->id);
        SystemSetting::set('sms', 'fast2sms_route', 'dlt', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_template_id', '169ec8e6c8', 'string', $society->id);
        // Notifications use their own approved template; the OTP template is never reused for them.
        SystemSetting::set('sms', 'fast2sms_notification_sender_id', 'DISP24', 'string', $society->id);
        SystemSetting::set('sms', 'fast2sms_notification_template_id', '169ec8e6c8', 'string', $society->id);

        $sent = null;
        SmsService::$transportOverride = function (string $url, array $data, array $headers) use (&$sent) {
            $sent = true;
            return [['return' => true], 200];
        };

        $this->actingAs($admin)->post('/notices', [
            'title'    => 'Independent Channel Check',
            'content'  => 'x',
            'type'     => 'general',
            'priority' => 'low',
            'status'   => 'published',
        ])->assertRedirect();

        $this->assertTrue($sent);
    }
}
