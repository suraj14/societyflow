<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Support\ChannelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Settings -> Email / SMS / OTP / WhatsApp: configure once, test with the saved values. Provider HTTP is faked with the
 * response shapes confirmed against a live Fast2SMS account (see Fast2SmsAccount's docblock); nothing here touches the
 * network.
 */
class CommunicationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'live-key-abcdefghijklmnopqrstuvwxyz0123456789';

    private User $admin;
    private int $society;
    private array $providerCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $s = $this->makeSociety('Royal Eden');
        $this->society = $s->id;
        $this->admin = $this->makeUserWithRole($s, 'Admin', ['phone' => '9876500001']);
    }

    protected function tearDown(): void
    {
        Fast2SmsAccount::$transportOverride = null;
        SmsService::$transportOverride = null;
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function fakeAccount(array $routes): void
    {
        Fast2SmsAccount::$transportOverride = function (string $method, string $url) use ($routes) {
            $this->providerCalls[] = "{$method} " . parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY);
            foreach ($routes as $needle => $response) {
                if (str_contains($url, $needle)) {
                    return $response;
                }
            }
            return [['message' => 'unexpected'], 404];
        };
    }

    private function waNumber(string $pnid, string $number = '+919000000001', array $templates = []): array
    {
        return ['waba_id' => 4001001001001001, 'phone_number_id' => (int) $pnid, 'number' => $number, 'verified_name' => 'Royal Eden',
            'name_status' => 'APPROVED', 'quality_rating' => 'GREEN', 'messaging_limit' => 'TIER_1K', 'platform_type' => 'CLOUD_API',
            'connection_status' => 'CONNECTED', 'templates' => $templates];
    }

    private function waTemplate(string $name, int $vars, string $body, string $pnid = '7001001001001001', string $status = 'Approved'): array
    {
        return ['message_id' => random_int(10000, 99999), 'template_id' => 'meta_' . $name, 'template_name' => $name, 'phone_number_id' => (int) $pnid,
            'sender_number' => '+919000000001', 'category' => 'UTILITY', 'status' => $status, 'language' => 'en', 'var_count' => $vars,
            'components' => [['type' => 'BODY', 'text' => $body]]];
    }

    private function saveWhatsAppKey(): void
    {
        SystemSetting::set('whatsapp', 'whatsapp_api_key', self::KEY, 'encrypted', $this->society);
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $this->society);
    }

    /** Key + Phone Number ID saved, and these templates entered by hand (the shape waTemplate() describes). */
    private function syncWhatsApp(array $templates): void
    {
        $this->saveWhatsAppKey();
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '7001001001001001', 'string', $this->society);
        foreach ($templates as $t) {
            WhatsAppTemplate::create(['society_id' => $this->society, 'external_id' => 'manual-' . $t['template_name'], 'message_id' => (string) $t['message_id'], 'name' => $t['template_name'],
                'friendly_name' => $t['template_name'], 'status' => 'LOCAL', 'source' => 'manual', 'language' => $t['language'], 'var_count' => $t['var_count'], 'body_text' => $t['components'][0]['text']]);
        }
    }

    // ── Settings structure ──────────────────────────────────────────────────

    public function test_communication_settings_are_exactly_email_sms_and_whatsapp(): void
    {
        $html = $this->actingAs($this->admin)->get('/settings?tab=email')->assertOk()->getContent();

        $this->assertStringContainsString('Email Settings', $html);
        $this->assertStringContainsString('SMS / OTP', $html);
        $this->assertStringContainsString('WhatsApp', $html);
        $this->assertStringNotContainsString("activeTab = 'email-templates'", $html);
        $this->assertStringNotContainsString('Communication Center', $html);
    }

    // ── Email ───────────────────────────────────────────────────────────────

    private function saveEmail(array $overrides = []): void
    {
        $this->actingAs($this->admin)->post(route('settings.email.update'), array_merge([
            'mail_driver' => 'smtp', 'mail_host' => 'smtp.gmail.com', 'mail_port' => 587, 'mail_encryption' => 'tls',
            'mail_username' => 'office@royaleden.in', 'mail_password' => 'app-password-secret',
            'mail_from_address' => 'office@royaleden.in', 'mail_from_name' => 'Royal Eden Office',
        ], $overrides))->assertRedirect('/settings?tab=email');
    }

    public function test_email_status_moves_from_not_configured_to_saved_to_connected(): void
    {
        $this->actingAs($this->admin)->get('/settings?tab=email')->assertSee('Email not configured');

        $this->saveEmail();
        $html = $this->actingAs($this->admin)->get('/settings?tab=email')->assertOk()->getContent();
        $this->assertStringContainsString('Configuration saved', $html);
        $this->assertStringNotContainsString('app-password-secret', $html, 'the SMTP password is never rendered');

        Mail::fake();
        config(['messaging.enabled' => true]);   // the enabled path; mail is faked
        $this->actingAs($this->admin)->postJson(route('settings.email.test'), ['test_email' => 'me@royaleden.in'])
            ->assertOk()->assertJson(['success' => true]);
        $this->actingAs($this->admin)->get('/settings?tab=email')->assertSee('Email connected');

        // Changing the settings drops the old "Connected".
        $this->saveEmail(['mail_from_name' => 'Royal Eden RWA']);
        $this->assertNull(ChannelStatus::get('email', $this->society));
    }

    public function test_email_test_failure_is_plain_with_hidden_details_and_no_password(): void
    {
        // The suite runs with external messaging OFF (phpunit.xml). This test is about the enabled path: mail is faked or goes to a closed loopback port.
        config(['messaging.enabled' => true]);
        $this->saveEmail(['mail_host' => '127.0.0.1', 'mail_port' => 1, 'mail_encryption' => 'null']);

        $r = $this->actingAs($this->admin)->postJson(route('settings.email.test'), ['test_email' => 'me@royaleden.in'])->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertSame('Test email failed. Please check your SMTP settings.', $r->json('message'));
        $this->assertNotEmpty($r->json('details'));
        $this->assertStringNotContainsString('app-password-secret', $r->getContent());
        $this->assertFalse(ChannelStatus::get('email', $this->society)['ok']);
    }

    public function test_email_test_without_settings_says_not_set_up(): void
    {
        // The suite runs with external messaging OFF (phpunit.xml). This test is about the enabled path: mail is faked or goes to a closed loopback port.
        config(['messaging.enabled' => true]);
        $r = $this->actingAs($this->admin)->postJson(route('settings.email.test'), ['test_email' => 'me@royaleden.in'])->assertOk();
        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('not set up', $r->json('message'));
    }

    // ── SMS ─────────────────────────────────────────────────────────────────

    private function saveSmsKey(): void
    {
        SystemSetting::set('sms', 'fast2sms_api_key', self::KEY, 'encrypted', $this->society);
    }

    public function test_a_saved_dlt_template_id_is_never_sent_as_a_message_id(): void
    {
        $this->saveSmsKey();
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->society);
        SystemSetting::set('sms', 'fast2sms_template_id', '1707168880000012345', 'string', $this->society);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'ROYALE', 'string', $this->society);
        $called = false;
        SmsService::$transportOverride = function () use (&$called) { $called = true; return [['return' => true], 200]; };

        $result = SmsService::sendDltNotification('notification', '9876543210', 'Hello', $this->society);

        $this->assertFalse($result['success']);
        $this->assertFalse($called, 'no request is made with an identifier Fast2SMS cannot accept');
        $this->actingAs($this->admin)->get('/settings?tab=sms')->assertSee('needs to be chosen again');
    }

    public function test_test_sms_reports_provider_acceptance_and_records_status(): void
    {
        $this->saveSmsKey();
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->society);
        SystemSetting::set('sms', 'fast2sms_route', 'dlt', 'string', $this->society);
        SystemSetting::set('sms', 'fast2sms_template_id', '182001', 'string', $this->society);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'ROYALE', 'string', $this->society);
        $sent = null;
        SmsService::$transportOverride = function ($url, $data) use (&$sent) { parse_str((string) parse_url($url, PHP_URL_QUERY), $sent); // Fast2SMS SMS is a documented GET: values are in the query
             return [['return' => true, 'request_id' => 'abc'], 200]; };

        $r = $this->actingAs($this->admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210', 'test_value' => '4321'])->assertOk();

        $this->assertTrue($r->json('success'));
        $this->assertStringContainsString('Fast2SMS accepted the test SMS', $r->json('message'));
        $this->assertStringContainsString('not a delivery confirmation', $r->json('message'));
        $this->assertSame('4321', $sent['variables_values']);
        $this->assertTrue(ChannelStatus::get('sms', $this->society)['ok']);
    }

    // ── WhatsApp ────────────────────────────────────────────────────────────

    public function test_test_whatsapp_sends_the_chosen_template_with_typed_or_sample_values(): void
    {
        $this->syncWhatsApp([$this->waTemplate('visitor_alert', 2, 'Hi {{1}}, {{2}} is here')]);
        $tpl = WhatsAppTemplate::where('name', 'visitor_alert')->first();
        $url = null;
        WhatsAppService::$transportOverride = function ($u) use (&$url) { $url = $u; return [['status' => true, 'message' => 'Message sent successfully', 'request_id' => 'r2'], 200]; };
        $send = fn (array $extra = []) => $this->actingAs($this->admin)->postJson(route('settings.whatsapp.test-template'), ['phone' => '9876543210', 'template_id' => $tpl->id] + $extra)->assertOk();

        // typed values
        $r = $send(['variables' => 'Asha|Block A']);
        $this->assertTrue($r->json('success'));
        $this->assertStringContainsString('not a delivery confirmation', $r->json('message'));
        $this->assertStringContainsString('r2', (string) $r->json('raw'), 'Fast2SMS\'s own reply is shown');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('Asha|Block A', $q['variables_values']);
        $this->assertSame('919876543210', $q['numbers']);
        $this->assertSame((string) $tpl->message_id, $q['message_id']);

        // no values typed: sample values
        $send();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('Test 1|Test 2', $q['variables_values']);

        // wrong number of values is refused before any request
        $url = null;
        $bad = $send(['variables' => 'only one']);
        $this->assertFalse($bad->json('success'));
        $this->assertStringContainsString('needs 2 value(s)', $bad->json('message'));
        $this->assertNull($url, 'nothing was sent');

        // a provider rejection is explained and shown raw (the same values were accepted a moment ago; the duplicate-submit
        // guard that stops an identical resend within seconds is exercised in WhatsAppProviderContractTest)
        \Illuminate\Support\Facades\Cache::flush();
        WhatsAppService::$transportOverride = fn () => [['error' => ['message' => '(#132001) Template name does not exist in the translation']], 400];
        $fail = $send(['variables' => 'Asha|Block A']);
        $this->assertFalse($fail->json('success'));
        $this->assertStringContainsString('does not recognise that template', $fail->json('message'));
        $this->assertStringContainsString('132001', $fail->json('details'));
        $this->assertStringContainsString('132001', (string) $fail->json('raw'));
    }

    public function test_another_societys_admin_cannot_test_with_or_see_our_templates(): void
    {
        $this->syncWhatsApp([$this->waTemplate('visitor_alert', 2, 'Hi {{1}} {{2}}')]);
        $tpl = WhatsAppTemplate::where('name', 'visitor_alert')->first();
        $other = $this->makeUserWithRole($this->makeSociety('Other'), 'Admin');
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $other->society_id);

        $this->actingAs($other)->postJson(route('settings.whatsapp.test-template'), ['phone' => '9876543210', 'template_id' => $tpl->id])
            ->assertJson(['success' => false]);
        $this->actingAs($other)->get('/settings?tab=whatsapp')->assertOk()->assertDontSee('Visitor Alert');
    }

    public function test_residents_cannot_use_any_communication_settings_action(): void
    {
        $tenant = $this->makeUserWithRole(\App\Models\Society::find($this->society), 'Tenant');

        foreach (['settings.sms.update', 'settings.sms.test', 'settings.whatsapp.update',
                  'settings.whatsapp.test-template', 'settings.email.test'] as $route) {
            $this->actingAs($tenant)->postJson(route($route), [])->assertForbidden();
        }
    }

    // ── OTP / notification independence (SMS audit) ─────────────────────────

    public function test_a_notification_is_never_sent_through_the_otp_template_when_none_is_chosen(): void
    {
        $this->saveSmsKey();
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->society);
        // Legacy shape: only the (single) OTP template exists.
        SystemSetting::set('sms', 'fast2sms_template_id', '182001', 'string', $this->society);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'ROYALE', 'string', $this->society);
        $called = false;
        SmsService::$transportOverride = function () use (&$called) { $called = true; return [['return' => true], 200]; };

        $result = SmsService::sendDltNotification('notification', '9876543210', 'Water supply off 2-4 PM', $this->society);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No notification SMS template', $result['message']);
        $this->assertFalse($called, 'no request is made: the OTP template is not approved for notification text');
    }
}
