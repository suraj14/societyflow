<?php

namespace Tests\Feature;

use App\Events\NoticePublished;
use App\Jobs\DispatchEventNotification;
use App\Models\ActivityLog;
use App\Models\EmailLog;
use App\Models\Notice;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The notice form's "Send Email" / "Send SMS" boxes decide which external channels a notice uses (in-app is always on), both on first
 * publication and on a deliberate resend. Editing a published notice sends nothing. Nothing here reaches a provider: jobs are faked or run
 * against the blocked/simulated transports, SMS goes to a test double that sits behind the allow-list.
 */
class NoticeChannelChoiceAndResendTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $other;
    private $admin;
    private $owner;
    /** @var list<string> numbers the SMS test double saw */
    private array $sms = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal Eden');
        $this->other = $this->makeSociety('Elsewhere');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin', ['phone' => '9800000001']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_notices', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('manage_notices');
        $this->owner = $this->makeUserWithRole($this->society, 'Owner', ['email' => 'owner@example.test', 'phone' => '9887313518']);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->society->id);
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'SAIDIG', 'string', $this->society->id);
        SmsTemplate::create(['society_id' => $this->society->id, 'name' => 'notice', 'purpose' => 'notice', 'audience' => 'resident', 'dlt_message_id' => '228199', 'is_active' => true,
            'body' => 'SocietyFlow: New notice {{notice_title}} published by your society. Please check the SocietyFlow app for details. Thank you.']);
        SmsService::$transportOverride = function (string $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->sms[] = $q['numbers'] ?? '?';

            return [['return' => true, 'request_id' => 'r'], 200];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function notice(bool $email, bool $sms, string $status = 'published'): Notice
    {
        return Notice::create(['society_id' => $this->society->id, 'created_by' => $this->admin->id, 'title' => 'Water cut', 'content' => 'x', 'type' => 'general',
            'priority' => 'medium', 'status' => $status, 'publish_date' => now(), 'send_email' => $email, 'send_sms' => $sms]);
    }

    private function emailJobs(): int
    {
        return Bus::dispatched(DispatchEventNotification::class)->count();
    }

    // ── first publication honours the boxes ─────────────────────────────────

    /** @return array<string, array{bool, bool}> */
    public static function boxCombinations(): array
    {
        return ['neither' => [false, false], 'email only' => [true, false], 'sms only' => [false, true], 'both' => [true, true]];
    }

    /** @dataProvider boxCombinations */
    public function test_each_channel_follows_its_own_box_and_in_app_is_always_sent(bool $email, bool $sms): void
    {
        Bus::fake();

        NoticePublished::dispatch($this->notice($email, $sms));

        $this->assertSame($email, $this->emailJobs() > 0, 'e-mail jobs exist only when the e-mail box is ticked');
        $this->assertSame($sms, in_array('9887313518', $this->sms, true), 'SMS goes out only when the SMS box is ticked');
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->owner->id)->count(), 'in-app does not depend on the boxes');
    }

    public function test_a_ticked_box_still_obeys_the_global_switch_and_the_allow_list(): void
    {
        // delivery off (the test default): the e-mail is logged as blocked and never as sent (the SMS double stands in for the network, so it is not asserted here)
        NoticePublished::dispatch($this->notice(true, true));
        $this->assertSame(0, EmailLog::where('channel', 'email')->where('status', 'sent')->count());

        // delivery on with a one-number list: only that number is reached
        $this->sms = [];
        \Illuminate\Support\Facades\Cache::flush();   // the first dispatch's duplicate-window memory
        config(['messaging.enabled' => true, 'messaging.allowlist' => '9887313518,owner@example.test']);
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9811111111']);
        NoticePublished::dispatch($this->notice(true, true));
        $this->assertSame(['9887313518'], $this->sms);
    }

    // ── the form ────────────────────────────────────────────────────────────

    public function test_a_new_notice_defaults_both_boxes_on_and_an_unticked_choice_survives_a_validation_error(): void
    {
        $this->assertTrue((new Notice())->send_email);
        $this->assertTrue((new Notice())->send_sms);

        $page = $this->actingAs($this->admin)->get(route('notices.create'))->assertOk()->getContent();
        $this->assertSame(2, preg_match_all('/name="send_(email|sms)" value="1" checked/', $page));

        $again = $this->actingAs($this->admin)->withSession(['_old_input' => ['title' => 'x']])->get(route('notices.create'))->getContent();
        $this->assertSame(0, preg_match_all('/name="send_(email|sms)" value="1" checked/', $again), 'old input without the boxes means they were unticked');
    }

    public function test_storing_and_editing_keep_the_explicit_selection(): void
    {
        Bus::fake();
        $base = ['title' => 'Lift', 'content' => 'Lift is down', 'type' => 'general', 'priority' => 'medium', 'status' => 'published'];

        $this->actingAs($this->admin)->post(route('notices.store'), $base)->assertSessionHasNoErrors();
        $stored = Notice::where('title', 'Lift')->firstOrFail();
        $this->assertFalse((bool) $stored->send_email);
        $this->assertFalse((bool) $stored->send_sms);
        $this->assertSame(0, $this->emailJobs());
        $this->assertSame([], $this->sms, 'both boxes left unticked: no external send');

        $page = $this->actingAs($this->admin)->get(route('notices.edit', $stored))->assertOk()->getContent();
        $this->assertSame(0, preg_match_all('/name="send_(email|sms)" value="1" checked/', $page), 'the edit form shows what was saved');
    }

    // ── editing never re-broadcasts ─────────────────────────────────────────

    public function test_editing_an_already_published_notice_sends_nothing(): void
    {
        Bus::fake();
        $notice = $this->notice(true, true);

        $this->actingAs($this->admin)->put(route('notices.update', $notice), ['title' => 'Water cut (revised)', 'content' => 'x', 'type' => 'general', 'priority' => 'medium',
            'status' => 'published', 'send_email' => 1, 'send_sms' => 1])->assertSessionHasNoErrors();

        $this->assertSame('Water cut (revised)', $notice->fresh()->title);
        $this->assertSame(0, $this->emailJobs());
        $this->assertSame([], $this->sms);
    }

    public function test_publishing_a_draft_notifies_by_its_boxes(): void
    {
        Bus::fake();
        $draft = $this->notice(false, true, 'draft');

        $this->actingAs($this->admin)->put(route('notices.update', $draft), ['title' => 'Water cut', 'content' => 'x', 'type' => 'general', 'priority' => 'medium',
            'status' => 'published', 'send_sms' => 1])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->emailJobs());
        $this->assertContains('9887313518', $this->sms);
    }

    // ── explicit resend ─────────────────────────────────────────────────────

    private function resendPayload(array $over = []): array
    {
        return array_merge(['channels' => ['email'], 'confirm' => 1, 'token' => 'tok-' . uniqid()], $over);
    }

    public function test_resend_uses_only_the_chosen_channels_with_its_own_keys_and_is_audited(): void
    {
        Bus::fake();
        $notice = $this->notice(true, true);

        $this->actingAs($this->admin)->post(route('notices.resend', $notice), $this->resendPayload(['token' => 'abc-1']))->assertSessionHasNoErrors()->assertSessionHas('success');

        Bus::assertDispatched(DispatchEventNotification::class, fn ($j) => str_starts_with($j->notificationKey, "notice_resend:{$notice->id}:abc-1:"));
        $this->assertSame([], $this->sms, 'e-mail only was chosen');
        $log = ActivityLog::where('action', 'notice.resend')->where('model_id', $notice->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(['email'], $log->new_values['channels']);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_resend_by_sms_reaches_the_audience_once_and_leaves_old_log_rows_alone(): void
    {
        $notice = $this->notice(true, true);
        $old = EmailLog::create(['society_id' => $this->society->id, 'channel' => 'email', 'recipient_email' => 'owner@example.test', 'trigger_action' => 'notice_published',
            'notification_key' => "notice_published:{$notice->id}:owner@example.test", 'status' => 'sent']);
        $before = (array) DB::table('email_logs')->where('id', $old->id)->first();

        $this->actingAs($this->admin)->post(route('notices.resend', $notice), $this->resendPayload(['channels' => ['sms']]))->assertSessionHasNoErrors();

        $this->assertContains('9887313518', $this->sms);
        $this->assertSame($before, (array) DB::table('email_logs')->where('id', $old->id)->first(), 'history is not rewritten');
    }

    public function test_the_same_submission_acts_once_and_needs_confirmation_and_a_channel(): void
    {
        Bus::fake();
        $notice = $this->notice(true, true);
        $payload = $this->resendPayload(['token' => 'same-1']);

        $this->actingAs($this->admin)->post(route('notices.resend', $notice), $payload)->assertSessionHas('success');
        $count = $this->emailJobs();
        $this->actingAs($this->admin)->post(route('notices.resend', $notice), $payload)->assertSessionHas('error');
        $this->assertSame($count, $this->emailJobs(), 'a double submit sends nothing more');

        $this->actingAs($this->admin)->post(route('notices.resend', $notice), $this->resendPayload(['confirm' => null]))->assertSessionHasErrors('confirm');
        $this->actingAs($this->admin)->post(route('notices.resend', $notice), $this->resendPayload(['channels' => []]))->assertSessionHasErrors('channels');
        $this->actingAs($this->admin)->post(route('notices.resend', $notice), $this->resendPayload(['channels' => ['whatsapp']]))->assertSessionHasErrors('channels.0');
        $this->assertSame($count, $this->emailJobs());
    }

    public function test_only_a_published_notice_can_be_resent(): void
    {
        Bus::fake();
        $draft = $this->notice(true, true, 'draft');

        $this->actingAs($this->admin)->post(route('notices.resend', $draft), $this->resendPayload())->assertSessionHas('error');

        $this->assertSame(0, $this->emailJobs());
    }

    public function test_resend_is_for_this_societys_administrators_only(): void
    {
        Bus::fake();
        $notice = $this->notice(true, true);
        $stranger = $this->makeUserWithRole($this->other, 'Admin');
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $this->actingAs($stranger)->post(route('notices.resend', $notice), $this->resendPayload())->assertForbidden();
        $this->actingAs($tenant)->post(route('notices.resend', $notice), $this->resendPayload())->assertForbidden();

        $this->assertSame(0, $this->emailJobs());
        $this->assertSame(0, ActivityLog::where('action', 'notice.resend')->count());
    }

    public function test_a_resend_with_delivery_off_is_blocked_not_sent(): void
    {
        $notice = $this->notice(true, true);

        $this->actingAs($this->admin)->post(route('notices.resend', $notice), $this->resendPayload(['channels' => ['email', 'sms']]))->assertSessionHasNoErrors();

        $this->assertSame(0, EmailLog::where('channel', 'email')->where('status', 'sent')->count(), 'switch off: nothing may be recorded as sent');
    }

    public function test_the_resend_button_shows_for_a_published_notice_to_an_admin_only(): void
    {
        $notice = $this->notice(true, true);
        $tenant = $this->makeUserWithRole($this->society, 'Tenant');

        $this->actingAs($this->admin)->get(route('notices.show', $notice))->assertOk()->assertSee('Send this notice again');
        $this->actingAs($tenant)->get(route('notices.show', $notice))->assertDontSee('Send this notice again');
    }
}
