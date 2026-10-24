<?php

namespace Tests\Feature;

use App\Events\NoticePublished;
use App\Jobs\DispatchEventNotification;
use App\Models\EmailLog;
use App\Models\Notice;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The reported case: an "Owner" of Royal Eden (society 6) published a notice and neither the owner's e-mail nor mobile received anything.
 * Found: (1) the e-mail was suppressed by an old "sent" row that only ever meant "written to the log" and (2) the test seam of the SMS and
 * WhatsApp senders skipped the allow-list, so a simulation showed sends the real path would refuse. Nothing here sends anything.
 */
class NoticeOwnerNotificationTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $other;
    private $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal Eden');
        $this->other = $this->makeSociety('Elsewhere');
        $this->owner = $this->makeUserWithRole($this->society, 'Owner', ['name' => 'Suraj suman', 'email' => 'SUMANSURAJ0@GMAIL.COM', 'phone' => '9887313518']);
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function notice(string $title = 'Water supply off 2-4 PM'): Notice
    {
        return Notice::create(['society_id' => $this->society->id, 'created_by' => $this->owner->id, 'title' => $title, 'content' => 'Please store water.',
            'type' => 'general', 'priority' => 'medium', 'status' => 'published', 'publish_date' => now(), 'send_email' => true, 'send_sms' => true]);
    }

    private function smsReady(): void
    {
        foreach (['sms_enabled' => true, 'notification_sms_enabled' => true] as $k => $v) {
            SystemSetting::set('sms', $k, $v, 'boolean', $this->society->id);
        }
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'SAIDIG', 'string', $this->society->id);
        SmsTemplate::create(['society_id' => $this->society->id, 'name' => 'notice', 'purpose' => 'notice', 'audience' => 'resident', 'dlt_message_id' => '228199', 'is_active' => true,
            'body' => 'SocietyFlow: New notice {{notice_title}} published by your society. Please check the SocietyFlow app for details. Thank you.']);
    }

    // â”€â”€ the owner is resolved, in this society only â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_the_owner_role_is_in_the_audience_of_every_channel_and_nobody_from_another_society_is(): void
    {
        Bus::fake();
        $this->smsReady();
        // another society's account on the very same number and an address that merely looks alike, plus a deactivated owner here
        $this->makeUserWithRole($this->other, 'Owner', ['email' => 'sumansuraj0@gmail.com.evil.test', 'phone' => '9887313518']);
        $off = $this->makeUserWithRole($this->society, 'Owner', ['status' => 'inactive', 'phone' => '9822222222']);
        $numbers = [];
        SmsService::$transportOverride = function (string $url) use (&$numbers) { parse_str((string) parse_url($url, PHP_URL_QUERY), $q); $numbers[] = $q['numbers'] ?? null; return [['return' => true, 'request_id' => 'r'], 200]; };

        NoticePublished::dispatch($this->notice());

        Bus::assertDispatched(DispatchEventNotification::class, fn ($j) => strtolower($j->recipient['email']) === 'sumansuraj0@gmail.com');
        Bus::assertNotDispatched(DispatchEventNotification::class, fn ($j) => $j->recipient['email'] === $off->email || str_contains($j->recipient['email'], 'evil.test'));
        $this->assertSame(['9887313518'], $numbers, 'one SMS to the owner of THIS society; the other society\'s account on the same number and the inactive owner get none');
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->owner->id)->count(), 'in-app entry for the owner');
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $off->id)->count());
    }

    public function test_the_owner_sees_the_in_app_notification_through_the_mobile_api(): void
    {
        Bus::fake();
        NoticePublished::dispatch($this->notice('Lift maintenance on Sunday'));

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/notifications')->assertOk()->assertSee('Lift maintenance on Sunday', false);
    }

    // â”€â”€ the e-mail was suppressed by a "sent" that was never a send â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function test_an_old_log_only_sent_row_does_not_silence_the_notification(): void
    {
        Mail::fake();
        $notice = $this->notice();
        $key = "notice_published:{$notice->id}:{$this->owner->email}";
        $old = EmailLog::create(['society_id' => $this->society->id, 'channel' => 'email', 'recipient_email' => $this->owner->email, 'trigger_action' => 'notice_published',
            'notification_key' => $key, 'status' => 'sent']);
        DB::table('email_logs')->where('id', $old->id)->update(['created_at' => '2026-10-16 11:21:11']);   // before sent meant accepted by a server

        (new DispatchEventNotification('notice_published', ['email' => $this->owner->email, 'name' => 'Suraj suman', 'role' => 'Owner'],
            ['notice_title' => $notice->title, 'notice_content' => 'x', 'view_url' => '#'], $this->society->id, $key))->handle();

        $this->assertSame(1, EmailLog::where('notification_key', $key)->where('id', '!=', $old->id)->where('status', 'sent')->count(), 'sent now, for real');
    }

    public function test_a_genuine_sent_row_still_prevents_a_second_e_mail(): void
    {
        Mail::fake();
        $notice = $this->notice();
        $key = "notice_published:{$notice->id}:{$this->owner->email}";
        $job = fn () => new DispatchEventNotification('notice_published', ['email' => $this->owner->email, 'name' => 'Suraj suman', 'role' => 'Owner'],
            ['notice_title' => $notice->title, 'notice_content' => 'x', 'view_url' => '#'], $this->society->id, $key);

        $job()->handle();
        $job()->handle();

        $this->assertSame(1, EmailLog::where('notification_key', $key)->where('status', 'sent')->count());
    }

    // â”€â”€ the allow-list holds beneath the test seam, and channels are independent â”€â”€

    public function test_the_allow_list_is_enforced_even_when_the_transport_is_a_test_double(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => '9887313518']);
        $this->smsReady();
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9811111111']);
        $numbers = [];
        SmsService::$transportOverride = function (string $url) use (&$numbers) { parse_str((string) parse_url($url, PHP_URL_QUERY), $q); $numbers[] = $q['numbers'] ?? null; return [['return' => true, 'request_id' => 'r'], 200]; };
        Bus::fake();

        NoticePublished::dispatch($this->notice());

        $this->assertSame(['9887313518'], $numbers, 'the tenant\'s number is not on the list: refused before the transport');
        $this->assertStringContainsString('allow-list', (string) EmailLog::where('recipient_phone', '9811111111')->value('error_message'), 'the refusal is logged with its reason, not dropped');
    }

    public function test_a_whatsapp_to_an_unlisted_number_is_refused_before_the_transport(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => '9887313518']);
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'WA-KEY-0123456789abcdefghij', 'encrypted', $this->society->id);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $this->society->id);
        WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => '1088260420753201', 'message_id' => '37332', 'name' => 'society_important_notice', 'status' => 'APPROVED', 'language' => 'en',
            'phone_number_id' => '1209719742234591', 'var_count' => 3, 'purpose' => 'notice', 'is_active' => true, 'source' => 'synced',
            'body_text' => 'Hello {{1}}, Important notice from {{2}}: {{3}}. Please check the SocietyFlow app for full details. Thank you.']);
        $sent = [];
        WhatsAppService::$transportOverride = function (string $url) use (&$sent) { parse_str((string) parse_url($url, PHP_URL_QUERY), $q); $sent[] = $q['numbers'] ?? null; return [['return' => true, 'message' => ['sent'], 'request_id' => 'r'], 200]; };

        $blocked = WhatsAppService::sendNotification('notice', '9811111111', ['resident_name' => 'A', 'notice_title' => 'T', 'society_name' => 'Royal Eden'], $this->society->id);
        $allowed = WhatsAppService::sendNotification('notice', '9887313518', ['resident_name' => 'A', 'notice_title' => 'T', 'society_name' => 'Royal Eden'], $this->society->id);

        $this->assertFalse($blocked['success']);
        $this->assertSame(['919887313518'], $sent);
        $this->assertTrue($allowed['success']);
    }

    public function test_a_failing_sms_does_not_stop_the_e_mail_or_the_in_app_entry(): void
    {
        Bus::fake();
        $this->smsReady();
        SmsService::$transportOverride = function () { throw new \RuntimeException('provider down'); };

        NoticePublished::dispatch($this->notice());

        Bus::assertDispatched(DispatchEventNotification::class);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->owner->id)->count());
    }
}
