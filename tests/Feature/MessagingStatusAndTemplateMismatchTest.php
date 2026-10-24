<?php

namespace Tests\Feature;

use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Support\DltTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * 1. A template holding another notification's wording is refused when it is saved and again when it would be sent (a real society's
 *    "document" template carried the BILL text).
 * 2. `php artisan messaging:status` explains why messaging is on or off and what is configured - read-only, no secret ever printed.
 * 3. A blocked test send tells the person what to do about it.
 */
class MessagingStatusAndTemplateMismatchTest extends TestCase
{
    use RefreshDatabase;

    /** The bill wording exactly as a pasted telecom template carries it: {#VAR#} markers and a stray space before the full stop. */
    private const BILL_AS_PASTED = 'SocietyFlow: Your {#VAR#} bill is Rs. {#VAR#} . Please check the SocietyFlow app for due date and payment details. Thank you.';

    private function admin($society)
    {
        return $this->makeUserWithRole($society, 'Admin');
    }

    // ── 1. wording that belongs to another notification ─────────────────────

    public function test_the_registry_recognises_each_registered_text_even_as_pasted_with_stray_spaces(): void
    {
        $this->assertSame('bill', DltTemplates::purposeOfBody(self::BILL_AS_PASTED));
        $this->assertSame('bill', DltTemplates::purposeOfBody('SocietyFlow: Your {{bill_label}} bill is Rs. {{amount_plain}}. Please check the SocietyFlow app for due date and payment details. Thank you.'));
        $this->assertSame('complaint', DltTemplates::purposeOfBody('SocietyFlow: Complaint {{complaint_ref}} status updated to {{complaint_status}}. Please check the SocietyFlow app for details.'));
        $this->assertNull(DltTemplates::purposeOfBody('Something entirely different {{notice_title}}'));
        $this->assertNull(DltTemplates::purposeConflict('bill', self::BILL_AS_PASTED), 'the same purpose is not a conflict');
        $this->assertStringContainsString('Bill generated', (string) DltTemplates::purposeConflict('document', self::BILL_AS_PASTED));
    }

    public function test_the_form_refuses_bill_wording_saved_as_a_document_template_but_accepts_it_as_a_bill(): void
    {
        $society = $this->makeSociety();
        $admin = $this->admin($society);
        $base = ['name' => 'Doc SMS', 'audience' => 'resident', 'status' => 'active', 'dlt_message_id' => '228127'];
        $body = 'SocietyFlow: Your {{bill_label}} bill is Rs. {{amount_plain}}. Please check the SocietyFlow app for due date and payment details. Thank you.';

        $this->actingAs($admin)->post(route('admin.message-templates.sms.store'), $base + ['purpose' => 'document', 'body' => $body])->assertSessionHasErrors('body');
        $this->assertSame(0, SmsTemplate::count());

        $this->actingAs($admin)->post(route('admin.message-templates.sms.store'), $base + ['purpose' => 'bill', 'body' => $body])->assertSessionHasNoErrors();
        $this->assertSame('bill', SmsTemplate::first()->purpose);
    }

    public function test_a_legacy_template_with_another_notifications_wording_is_not_sent(): void
    {
        $society = $this->makeSociety();
        foreach (['sms_enabled' => true, 'notification_sms_enabled' => true] as $k => $v) {
            SystemSetting::set('sms', $k, $v, 'boolean', $society->id);
        }
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'SAIDIG', 'string', $society->id);
        // saved before the check existed: purpose "document", wording of the bill
        SmsTemplate::create(['society_id' => $society->id, 'name' => 'societyflow_bill_generated', 'purpose' => 'document', 'audience' => 'resident', 'body' => self::BILL_AS_PASTED, 'dlt_message_id' => '228127', 'is_active' => true]);
        $calls = 0;
        SmsService::$transportOverride = function () use (&$calls) { $calls++; return [['return' => true], 200]; };

        try {
            $r = SmsService::sendDltNotification('notification', '9876543210', '', $society->id, false, 'document', ['document_title' => 'Rules', 'document_version' => 'v2']);
        } finally {
            SmsService::$transportOverride = null;
        }

        $this->assertSame(0, $calls, 'nothing went to the provider');
        $this->assertFalse($r['sent']);
        $this->assertSame('template_purpose_mismatch', $r['error_code'] ?? null);
        $this->assertStringContainsString('another notification', $r['message']);
    }

    // ── 2. the status report ────────────────────────────────────────────────

    public function test_the_status_report_says_why_messaging_is_off_and_never_prints_a_secret(): void
    {
        $society = $this->makeSociety('Royal');
        foreach ([['sms', 'fast2sms_api_key', 'SECRET-SMS-KEY-9999'], ['email', 'mail_password', 'SECRET-MAIL-PASSWORD-9999'], ['whatsapp', 'whatsapp_api_key', 'SECRET-WA-KEY-9999']] as [$g, $k, $v]) {
            SystemSetting::set($g, $k, $v, 'encrypted', $society->id);
        }
        SystemSetting::set('email', 'mail_host', 'smtp.example.test', 'string', $society->id);
        SystemSetting::set('email', 'mail_port', 465, 'string', $society->id);
        SystemSetting::set('email', 'mail_encryption', 'tls', 'string', $society->id);
        SystemSetting::set('email', 'mail_from_address', 'noreply@example.test', 'string', $society->id);
        config(['messaging.enabled' => false]);

        $this->artisan('messaging:status', ['--society' => $society->id])
            ->expectsOutputToContain('OFF - nothing leaves this server')
            // one expectation per output line (Laravel matches each line to one expectation): host, port, sender and that the password is set - never its value
            ->expectsOutputToContain('smtp.example.test:465 (tls), from noreply@example.test, login NOT set, password set')
            ->expectsOutputToContain('API key set')
            ->expectsOutputToContain('Nothing was sent and no provider was contacted')
            ->doesntExpectOutputToContain('SECRET-SMS-KEY-9999')
            ->doesntExpectOutputToContain('SECRET-MAIL-PASSWORD-9999')
            ->doesntExpectOutputToContain('SECRET-WA-KEY-9999')
            ->assertExitCode(0);
    }

    public function test_the_status_report_warns_when_delivery_is_on_with_no_allowlist(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => null]);

        $this->artisan('messaging:status')->expectsOutputToContain('reaches EVERYONE')->assertExitCode(0);
    }

    public function test_the_status_report_flags_an_invalid_allowlist_without_echoing_it(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => '9876543210,']);

        $this->artisan('messaging:status')->expectsOutputToContain('Test allow-list: INVALID')->doesntExpectOutputToContain('9876543210')->assertExitCode(0);
    }

    public function test_the_status_report_counts_a_valid_allowlist_without_showing_it(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => 'me@example.com, 9876543210']);

        $this->artisan('messaging:status')->expectsOutputToContain('valid, 2 recipient(s) (contents not shown)')->doesntExpectOutputToContain('me@example.com')->assertExitCode(0);
    }

    public function test_the_status_report_says_an_empty_allowlist_means_nobody(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => '']);

        $this->artisan('messaging:status')->expectsOutputToContain('EMPTY - nobody can receive anything')->assertExitCode(0);
    }

    public function test_the_status_report_flags_bookmizo_whatsapp_mappings_and_wrong_sms_wording(): void
    {
        $society = $this->makeSociety('Royal');
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_visitor_template_name', 'bookmizo_refund_full', 'string', $society->id);
        WhatsAppTemplate::create(['society_id' => $society->id, 'external_id' => '906673405846175', 'message_id' => '30381', 'name' => 'bookmizo_refund_full', 'status' => 'APPROVED', 'language' => 'en',
            'var_count' => 5, 'purpose' => 'visitor', 'is_active' => true]);
        SmsTemplate::create(['society_id' => $society->id, 'name' => 'bill', 'purpose' => 'document', 'audience' => 'resident', 'body' => self::BILL_AS_PASTED, 'dlt_message_id' => '228127', 'is_active' => true]);

        $this->artisan('messaging:status', ['--society' => $society->id])
            ->expectsOutputToContain('is a BookMizo template, not a SocietyFlow one')
            ->expectsOutputToContain('WRONG WORDING for this type')
            ->expectsOutputToContain('no active template, so no SMS is sent for it')
            ->assertExitCode(0);
    }
    // ── 3. a blocked test send says what to do ──────────────────────────────

    public function test_a_blocked_test_send_is_marked_blocked_and_says_what_to_do(): void
    {
        config(['messaging.enabled' => false]);
        $society = $this->makeSociety();
        $admin = $this->admin($society);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_settings', 'guard_name' => 'web']);
        $admin->givePermissionTo('manage_settings');

        $r = $this->actingAs($admin)->postJson(route('settings.email.test'), ['test_email' => 'someone@example.test'])->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertTrue($r->json('blocked'));
        $this->assertSame('messaging_disabled', $r->json('reason'));
        $this->assertStringContainsString('messaging:status', $r->json('hint'));
        $this->assertStringContainsString('docs/LOCAL_MESSAGING_TESTING.md', $r->json('hint'));
    }
}
