<?php

namespace Tests\Feature;

use App\Services\EmailConfigurationService;
use App\Services\Fast2SmsAccount;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Support\ExternalMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Runs the real notification job synchronously for one recipient. */
class DispatchEventNotificationSyncHelper
{
    public static function run(int $societyId, string $key): void
    {
        (new \App\Jobs\DispatchEventNotification(
            'notice_published',
            ['email' => 'resident@example.invalid', 'name' => 'Resident', 'role' => 'Tenant'],
            ['user_name' => 'Resident', 'notice_title' => 'Water cut', 'notice_content' => 'x', 'view_url' => '#'],
            $societyId,
            $key,
        ))->handle();
    }
}

/**
 * EXTERNAL_MESSAGING_ENABLED=false must make it impossible to reach a real
 * SMTP / SMS / WhatsApp provider even when a society has working credentials
 * stored - the case that made MAIL_MAILER=log useless: the mail layer applies
 * the society's own SMTP settings over the configured driver at send time.
 */
class ExternalMessagingKillSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config(['messaging.enabled' => true]);
        SmsService::$transportOverride = null;
        WhatsAppService::$transportOverride = null;
        Fast2SmsAccount::$transportOverride = null;

        parent::tearDown();
    }

    public function test_a_societys_stored_smtp_settings_do_not_override_the_log_driver_when_disabled(): void
    {
        config(['messaging.enabled' => false, 'mail.default' => 'log']);

        EmailConfigurationService::applyMailConfiguration([
            'mail_driver' => 'smtp', 'mail_host' => 'smtp.example.invalid', 'mail_port' => 587,
            'mail_username' => 'someone', 'mail_password' => 'secret', 'mail_from_address' => 'x@example.invalid',
        ]);

        $this->assertSame('log', config('mail.default'));
        $this->assertNotSame('smtp.example.invalid', config('mail.mailers.smtp.host'));
    }

    public function test_when_enabled_the_societys_settings_are_still_applied(): void
    {
        config(['messaging.enabled' => true, 'mail.default' => 'log']);

        EmailConfigurationService::applyMailConfiguration([
            'mail_driver' => 'smtp', 'mail_host' => 'smtp.example.invalid', 'mail_port' => 587,
            'mail_username' => 'someone', 'mail_password' => 'secret', 'mail_from_address' => 'x@example.invalid',
        ]);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.invalid', config('mail.mailers.smtp.host'));
    }

    public function test_sms_whatsapp_and_fast2sms_make_no_network_call_when_disabled(): void
    {
        config(['messaging.enabled' => false]);

        // Resolve every provider through its real (non-override) transport.
        $curl = new \ReflectionMethod(SmsService::class, 'curlPost');
        $curl->setAccessible(true);
        $handler = fn ($body, $code) => ['success' => true, 'message' => 'would have been sent'];

        // A URL on the reserved .invalid TLD can never resolve: if the switch
        // failed, this would return a transport error, not our suppression text.
        $sms = $curl->invoke(null, 'https://provider.example.invalid/send', ['to' => '9000000000'], [], $handler);
        $this->assertFalse($sms['success']);
        $this->assertSame(ExternalMessaging::SUPPRESSED_MESSAGE, $sms['message']);

        $wa = new \ReflectionMethod(WhatsAppService::class, 'curlPost');
        $wa->setAccessible(true);
        $whatsapp = $wa->invoke(null, 'https://graph.example.invalid/messages', ['to' => '9000000000'], [], $handler);
        $this->assertFalse($whatsapp['success']);
        $this->assertSame(ExternalMessaging::SUPPRESSED_MESSAGE, $whatsapp['message']);

        $req = new \ReflectionMethod(Fast2SmsAccount::class, 'request');
        $req->setAccessible(true);
        [$body, $code] = $req->invoke(null, 'GET', 'https://fast2sms.example.invalid/wallet', 'key');
        $this->assertSame(0, $code);
        $this->assertSame(ExternalMessaging::SUPPRESSED_MESSAGE, $body['transport_error']);
    }

    public function test_an_installed_test_transport_still_works_when_disabled(): void
    {
        config(['messaging.enabled' => false]);
        SmsService::$transportOverride = fn () => [['ok' => true], 200];

        $curl = new \ReflectionMethod(SmsService::class, 'curlPost');
        $curl->setAccessible(true);
        $result = $curl->invoke(null, 'https://provider.example.invalid/send', [], [], fn ($b, $c) => ['success' => true, 'message' => 'via override']);

        $this->assertTrue($result['success'], 'tests that fake the transport never need the network');
    }

    public function test_a_society_with_real_smtp_settings_still_only_logs_through_the_real_notification_job(): void
    {
        config(['messaging.enabled' => false, 'mail.default' => 'log']);
        $society = $this->makeSociety();
        foreach (['mail_driver' => 'smtp', 'mail_host' => 'smtp.example.invalid', 'mail_port' => 587, 'mail_username' => 'u', 'mail_password' => 'p', 'mail_encryption' => 'tls', 'mail_from_address' => 'x@example.invalid', 'mail_from_name' => 'X'] as $k => $v) {
            \App\Models\SystemSetting::set('email', $k, $v, 'string', $society->id);
        }

        // Real job, real mailer (no Mail::fake): an SMTP attempt to .invalid would fail the send.
        DispatchEventNotificationSyncHelper::run($society->id, 'killswitch:1');

        $this->assertSame('log', config('mail.default'), 'the society SMTP override did not take over');
        // The kill switch is off, so the delivery log must say "blocked" - this used to assert 'sent', which pinned the
        // over-claim (a message that never left this computer reported as sent). See App\Support\MailDelivery.
        $this->assertTrue(\App\Models\EmailLog::where('notification_key', 'killswitch:1')->where('status', 'blocked')->exists());
        $this->assertFalse(\App\Models\EmailLog::where('notification_key', 'killswitch:1')->where('status', 'sent')->exists());
    }

    public function test_even_a_path_that_sets_its_own_smtp_mailer_cannot_send_while_disabled(): void
    {
        $smtp = ['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'smtp.example.invalid', 'port' => 587, 'username' => 'u', 'password' => 'p', 'timeout' => 2]];
        $send = fn () => \Illuminate\Support\Facades\Mail::raw('x', fn ($m) => $m->to('a@example.invalid')->from('b@example.invalid')->subject('s'));

        // Control: with the switch on this configuration really tries SMTP and fails to connect.
        config(['messaging.enabled' => true] + $smtp);
        app()->forgetInstance('mail.manager');
        try {
            $send();
            $this->fail('control: the SMTP attempt should have failed on the unresolvable host');
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString('control:', $e->getMessage());
        }

        // Switch off: the same configuration is cancelled at MessageSending - no exception, no connection.
        config(['messaging.enabled' => false] + $smtp);
        app()->forgetInstance('mail.manager');
        $send();
        $this->assertTrue(true, 'cancelled without touching the network');
    }

    public function test_sms_and_whatsapp_test_buttons_report_not_sent_and_never_call_the_provider(): void
    {
        config(['messaging.enabled' => false]);
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        \App\Models\SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $society->id);
        \App\Models\SystemSetting::set('sms', 'fast2sms_api_key', 'fake-key', 'encrypted', $society->id);
        \App\Models\SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $society->id);
        \App\Models\SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $society->id);
        $template = \App\Models\WhatsAppTemplate::create(['society_id' => $society->id, 'external_id' => 'ext-1', 'message_id' => '8340', 'name' => 'visitor_arrived', 'status' => 'APPROVED', 'language' => 'en', 'var_count' => 0]);
        $this->assertNull(SmsService::$transportOverride);
        $this->assertNull(WhatsAppService::$transportOverride);
        $this->assertNull(Fast2SmsAccount::$transportOverride);

        $sms = $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210', 'message' => 'Hello'])->assertOk();
        $wa = $this->actingAs($admin)->postJson(route('settings.whatsapp.test-template'), ['phone' => '9876543210', 'template_id' => $template->id])->assertOk();

        foreach ([$sms, $wa] as $r) {
            $this->assertFalse($r->json('success'));
            $this->assertTrue($r->json('skipped'), 'messaging is off, so nothing was attempted');
            $this->assertStringContainsString('Not sent', (string) $r->json('message'));
            $this->assertStringNotContainsStringIgnoringCase('delivered', (string) $r->json('message'));
        }
        $this->assertDatabaseMissing('email_logs', ['channel' => 'whatsapp', 'trigger_action' => 'test']);
    }

    public function test_the_test_email_button_never_claims_a_send_that_was_only_logged(): void
    {
        config(['messaging.enabled' => false]);
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_settings', 'guard_name' => 'web']);
        $admin->givePermissionTo('manage_settings');

        $response = $this->actingAs($admin)->postJson(route('settings.email.test'), ['test_email' => 'someone@example.invalid']);

        $this->assertNotSame(500, $response->getStatusCode());
        if ($response->getStatusCode() === 200) {
            $response->assertJson(['success' => false]);
            $this->assertStringContainsString('Not sent', (string) $response->json('message'));
        }
    }

    /**
     * REQUIREMENT CHANGED (2026-10-16, owner's instruction): this used to pin the default as ON "so existing environments are
     * unchanged". The new rule is the opposite - external messaging is OFF unless explicitly enabled, in every new environment
     * and in production - so the test now pins that. An environment that wants real delivery must say so (EXTERNAL_MESSAGING_ENABLED=true).
     */
    public function test_the_kill_switch_defaults_to_off_and_must_be_enabled_explicitly(): void
    {
        $this->assertMatchesRegularExpression("/env\('EXTERNAL_MESSAGING_ENABLED', false\)/", file_get_contents(config_path('messaging.php')));
        $this->assertMatchesRegularExpression('/^EXTERNAL_MESSAGING_ENABLED=false\s*$/m', file_get_contents(base_path('.env.example')));
        $this->assertFalse((bool) config('messaging.enabled'), 'the test suite itself runs with messaging disabled');
    }
}

