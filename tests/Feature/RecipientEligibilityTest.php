<?php

namespace Tests\Feature;

use App\Events\NoticePublished;
use App\Jobs\DispatchEventNotification;
use App\Listeners\SendNoticePublishedSms;
use App\Models\EmailLog;
use App\Models\Notice;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DynamicEmailService;
use App\Services\SmsService;
use App\Support\EventAudience;
use App\Support\RecipientEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Only people who can legitimately be written to receive a society notification: a valid address, an ACTIVE account of this society, and
 * not a Super Admin (for a society notice). Enforced at the point of sending, so every listener, job, retry and command is covered.
 */
class RecipientEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal');
        $this->other = $this->makeSociety('Other');
    }

    private function inactive(string $role = 'Tenant', array $over = []): User
    {
        return $this->makeUserWithRole($this->society, $role, ['status' => 'inactive'] + $over);
    }

    // ── the rule ────────────────────────────────────────────────────────────

    public function test_the_rule_skips_invalid_addresses_inactive_accounts_and_super_admins_but_not_account_mail(): void
    {
        $active = $this->makeUserWithRole($this->society, 'Tenant');
        $off = $this->inactive();
        $super = $this->makeUserWithRole($this->society, 'Super Admin');
        $super->update(['society_id' => null]);

        $this->assertNull(RecipientEligibility::emailSkipReason($active->email, $this->society->id, 'notice_published'));
        $this->assertNull(RecipientEligibility::emailSkipReason(strtoupper($active->email), $this->society->id, 'notice_published'), 'case does not matter');
        $this->assertStringContainsString('not valid', RecipientEligibility::emailSkipReason('not-an-email', $this->society->id));
        $this->assertStringContainsString('not valid', RecipientEligibility::emailSkipReason('', $this->society->id));
        $this->assertStringContainsString('not active', RecipientEligibility::emailSkipReason($off->email, $this->society->id, 'notice_published'));
        $this->assertStringContainsString('Super Admin', RecipientEligibility::emailSkipReason($super->email, $this->society->id, 'notice_published'));
        $this->assertNull(RecipientEligibility::emailSkipReason($super->email, $this->society->id, 'password_reset'), 'an account e-mail may still reach a Super Admin');
        $this->assertNull(RecipientEligibility::emailSkipReason($super->email, $this->society->id, 'user_registered'));
        $this->assertNull(RecipientEligibility::emailSkipReason('stranger@example.test', $this->society->id, 'notice_published'), 'an address with no account here is not judged');
        $this->assertNull(RecipientEligibility::emailSkipReason($off->email, null, 'notice_published'), 'with no society there is no account to judge');
    }

    // ── at the point of sending ─────────────────────────────────────────────

    public function test_an_event_e_mail_goes_to_the_active_person_and_the_others_are_logged_as_skipped_not_sent(): void
    {
        Mail::fake();
        $ok = $this->makeUserWithRole($this->society, 'Tenant');
        $off = $this->inactive();
        $super = $this->makeUserWithRole($this->society, 'Super Admin');
        $super->update(['society_id' => null]);
        $recipients = [
            ['email' => $ok->email, 'name' => 'Ok', 'role' => 'Tenant'], ['email' => $off->email, 'name' => 'Off', 'role' => 'Tenant'],
            ['email' => 'not-an-email', 'name' => 'Bad', 'role' => 'Tenant'], ['email' => $super->email, 'name' => 'Super', 'role' => 'Super Admin'],
        ];

        DynamicEmailService::sendByEvent('notice_published', $recipients, ['notice_title' => 'T', 'notice_content' => 'C', 'view_url' => '#'], $this->society->id);

        $this->assertSame(['sent'], EmailLog::where('recipient_email', $ok->email)->pluck('status')->all());
        foreach ([$off->email, 'not-an-email', $super->email] as $skipped) {
            $row = EmailLog::where('recipient_email', $skipped)->first();
            $this->assertNotNull($row, "$skipped must leave a trace");
            $this->assertTrue($row->isSkipped(), "$skipped is logged as skipped, never as sent");
            $this->assertSame(3, (int) $row->retry_count, 'a skipped row offers no retry');
        }
        $this->assertStringContainsString('not active', EmailLog::where('recipient_email', $off->email)->value('error_message'));
    }

    public function test_the_queued_job_treats_a_skipped_recipient_as_final_and_does_not_retry(): void
    {
        Mail::fake();
        $off = $this->inactive();

        (new DispatchEventNotification('notice_published', ['email' => $off->email, 'name' => 'Off', 'role' => 'Tenant'], ['notice_title' => 'T', 'notice_content' => 'C', 'view_url' => '#'], $this->society->id, "notice_published:1:{$off->email}"))->handle();

        $this->assertSame(1, EmailLog::where('recipient_email', $off->email)->count());
        $this->assertTrue(EmailLog::where('recipient_email', $off->email)->first()->isSkipped());
        $this->assertSame(0, EmailLog::where('status', 'sent')->count());
    }

    public function test_the_same_notification_to_the_same_address_is_sent_once(): void
    {
        Mail::fake();
        $ok = $this->makeUserWithRole($this->society, 'Tenant');
        $job = fn () => new DispatchEventNotification('notice_published', ['email' => $ok->email, 'name' => 'Ok', 'role' => 'Tenant'], ['notice_title' => 'T', 'notice_content' => 'C', 'view_url' => '#'], $this->society->id, "notice_published:7:{$ok->email}");

        $job()->handle();
        $job()->handle();

        $this->assertSame(1, EmailLog::where('recipient_email', $ok->email)->where('status', 'sent')->count());
    }

    // ── SMS and audiences ───────────────────────────────────────────────────

    public function test_a_notice_sms_goes_only_to_active_people_with_a_valid_number_once_per_number(): void
    {
        foreach (['sms_enabled' => true, 'notification_sms_enabled' => true] as $k => $v) {
            SystemSetting::set('sms', $k, $v, 'boolean', $this->society->id);
        }
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'SAIDIG', 'string', $this->society->id);
        SmsTemplate::create(['society_id' => $this->society->id, 'name' => 'notice', 'purpose' => 'notice', 'audience' => 'resident', 'dlt_message_id' => '228199', 'is_active' => true,
            'body' => 'SocietyFlow: New notice {{notice_title}} published by your society. Please check the SocietyFlow app for details. Thank you.']);
        $a = $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9811111111']);
        $twin = $this->makeUserWithRole($this->society, 'Apartment Owner', ['phone' => '+91 98111 11111']);       // the same phone
        $off = $this->inactive('Tenant', ['phone' => '9822222222']);
        $bad = $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '12345']);
        $stranger = $this->makeUserWithRole($this->other, 'Tenant', ['phone' => '9833333333']);
        $numbers = [];
        SmsService::$transportOverride = function (string $url) use (&$numbers) { parse_str((string) parse_url($url, PHP_URL_QUERY), $q); $numbers[] = $q['numbers'] ?? null; return [['return' => true, 'request_id' => 'r'], 200]; };
        $notice = (new Notice())->forceFill(['id' => 91, 'society_id' => $this->society->id, 'title' => 'Water cut', 'content' => 'x', 'target_audience' => null]);

        try {
            (new SendNoticePublishedSms())->handle(new NoticePublished($notice));
        } finally {
            SmsService::$transportOverride = null;
        }

        $this->assertSame(['9811111111'], $numbers, 'one SMS: the active person, once, to a valid number of this society');
    }

    public function test_an_event_audience_never_includes_a_deactivated_owner_or_another_society(): void
    {
        $owner = $this->makeUserWithRole($this->society, 'Apartment Owner');
        $off = $this->inactive('Apartment Owner');
        $this->makeUserWithRole($this->other, 'Apartment Owner');
        $event = (object) ['society_id' => $this->society->id, 'is_role_based' => false, 'visible_roles' => null, 'visible_users' => null];

        $ids = EventAudience::users($event)->pluck('id')->all();
        $this->assertSame([$owner->id], $ids);
        $this->assertNotContains($off->id, $ids);

        $byRole = (object) ['society_id' => $this->society->id, 'is_role_based' => true, 'visible_roles' => ['Apartment Owner'], 'visible_users' => null];
        $this->assertSame([$owner->id], EventAudience::users($byRole)->pluck('id')->all());
        $chosen = (object) ['society_id' => $this->society->id, 'is_role_based' => false, 'visible_roles' => null, 'visible_users' => [$owner->id, $off->id]];
        $this->assertSame([$owner->id], EventAudience::users($chosen)->pluck('id')->all(), 'a hand-picked list does not bring an inactive person back');
    }

    // ── the status report shows what the log really says ────────────────────

    public function test_the_status_report_compares_the_last_login_failure_with_when_the_settings_were_saved(): void
    {
        SystemSetting::set('email', 'mail_host', 'smtp.example.test', 'string', $this->society->id);
        $row = EmailLog::create(['email_template_id' => null, 'society_id' => $this->society->id, 'recipient_email' => 'x@example.test', 'trigger_action' => 'notice_published', 'channel' => 'email',
            'status' => 'failed', 'error_message' => 'Failed to authenticate on SMTP server with username "u" using the following authenticators: "LOGIN"']);
        DB::table('email_logs')->where('id', $row->id)->update(['created_at' => now()->subDays(10)]);

        $this->artisan('messaging:status', ['--society' => $this->society->id])
            ->expectsOutputToContain('last real SMTP failure: login rejected on')
            ->assertExitCode(0);
    }
}
