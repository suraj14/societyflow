<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Services\WhatsAppTemplateSyncService;
use App\Support\ChannelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Settings -> Email / SMS / OTP / WhatsApp: configure once, verify against
 * the provider, pick templates instead of typing IDs. Provider HTTP is
 * faked with the response shapes confirmed against a live Fast2SMS account
 * (see Fast2SmsAccount's docblock); nothing here touches the network.
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
        WhatsAppTemplateSyncService::$transportOverride = null;
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

    private function smsTemplatesResponse(): array
    {
        return [[
            'success' => true,
            'data' => [
                ['sender_id' => 'ROYALE', 'entity_id' => '1701111111111111111', 'entity_name' => 'Royal Eden RWA', 'entity_status' => 'Approved', 'templates' => [
                    ['message_id' => 182001, 'message' => 'Your Royal Eden login OTP is {#var#}. Do not share it.', 'status' => 'Approved', 'var_count' => 1],
                    ['message_id' => 182002, 'message' => 'Royal Eden: {#var#}', 'status' => 'Approved', 'var_count' => 1],
                    ['message_id' => 182003, 'message' => 'Dear {#var#}, your bill of {#var#} is due.', 'status' => 'Approved', 'var_count' => 2],
                    ['message_id' => 182004, 'message' => 'Pending one {#var#}', 'status' => 'Pending', 'var_count' => 1],
                ]],
            ],
        ], 200];
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

    private function connectWhatsApp(array $templates): void
    {
        $this->saveWhatsAppKey();
        $number = $this->waNumber('7001001001001001', '+919000000001', $templates);
        $this->fakeAccount([
            'whatsapp?type=number'   => [['success' => true, 'data' => [array_diff_key($number, ['templates' => 1])]], 200],
            'whatsapp?type=template' => [['success' => true, 'data' => [$number]], 200],
        ]);
        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.connect'))->assertOk()->assertJson(['success' => true]);
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

    public function test_sms_connect_verifies_the_key_and_lists_only_approved_templates(): void
    {
        $this->saveSmsKey();
        $this->fakeAccount([
            '/dev/wallet' => [['return' => true, 'wallet' => '512.30', 'sms_count' => 2300], 200],
            'dlt_manager?type=template' => $this->smsTemplatesResponse(),
        ]);

        $r = $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();

        $this->assertTrue($r->json('success'));
        $this->assertSame(['182001', '182002', '182003'], array_column($r->json('templates'), 'message_id'));
        $this->assertSame('ROYALE', $r->json('templates.0.sender_id'));
        $this->assertTrue(ChannelStatus::get('sms', $this->society)['ok']);
        $this->assertStringNotContainsString(self::KEY, $r->getContent());
    }

    public function test_sms_connect_with_a_rejected_key_is_explained(): void
    {
        $this->saveSmsKey();
        $this->fakeAccount(['/dev/wallet' => [['return' => false, 'status_code' => 412, 'message' => 'Invalid Authentication, Check Authorization Key'], 401]]);

        $r = $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('did not accept this API key', $r->json('message'));
        $this->assertStringContainsString('Invalid Authentication', $r->json('details'));
        $this->assertFalse(ChannelStatus::get('sms', $this->society)['ok']);
    }

    public function test_sms_verify_matches_a_message_id_and_explains_a_dlt_template_id(): void
    {
        $this->saveSmsKey();
        $this->fakeAccount(['dlt_manager?type=template' => $this->smsTemplatesResponse()]);

        $this->actingAs($this->admin)->postJson(route('settings.sms.verify'), ['template_ref' => '182001'])
            ->assertOk()->assertJson(['success' => true, 'template' => ['message_id' => '182001', 'sender_id' => 'ROYALE']]);

        $dlt = $this->actingAs($this->admin)->postJson(route('settings.sms.verify'), ['template_ref' => '1707168880000012345'])->assertOk();
        $this->assertFalse($dlt->json('success'));
        $this->assertStringContainsString('DLT Template ID', $dlt->json('message'));
        $this->assertCount(3, $dlt->json('templates'), 'the manager is offered the list to pick from');

        $this->actingAs($this->admin)->postJson(route('settings.sms.verify'), ['template_ref' => '999999'])
            ->assertOk()->assertJson(['success' => false]);

        $this->assertNull(SystemSetting::where('key', 'fast2sms_template_id')->value('value'), 'verifying never saves anything');
    }

    public function test_using_a_template_for_otp_saves_message_id_sender_and_route(): void
    {
        $this->saveSmsKey();
        $this->fakeAccount(['dlt_manager?type=template' => $this->smsTemplatesResponse()]);

        $this->actingAs($this->admin)->postJson(route('settings.sms.use-template'), ['purpose' => 'otp', 'message_id' => '182001'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('182001', SystemSetting::get('sms', 'fast2sms_template_id', null, $this->society));
        $this->assertSame('ROYALE', SystemSetting::get('sms', 'fast2sms_sender_id', null, $this->society));
        $this->assertSame('dlt', SystemSetting::get('sms', 'fast2sms_route', null, $this->society));
        $this->actingAs($this->admin)->get('/settings?tab=sms')->assertSee('Your Royal Eden login OTP is');

        // Two variables, or not in the account: refused.
        $this->actingAs($this->admin)->postJson(route('settings.sms.use-template'), ['purpose' => 'notification', 'message_id' => '182003'])
            ->assertJson(['success' => false]);
        $this->actingAs($this->admin)->postJson(route('settings.sms.use-template'), ['purpose' => 'notification', 'message_id' => '555555'])
            ->assertJson(['success' => false]);
        $this->assertNull(SystemSetting::get('sms', 'fast2sms_notification_template_id', null, $this->society));
    }

    public function test_notification_sms_use_their_own_template_and_sender(): void
    {
        $this->saveSmsKey();
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society);
        SystemSetting::set('sms', 'fast2sms_template_id', '182001', 'string', $this->society);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'ROYALE', 'string', $this->society);
        $this->fakeAccount(['dlt_manager?type=template' => $this->smsTemplatesResponse()]);
        $this->actingAs($this->admin)->postJson(route('settings.sms.use-template'), ['purpose' => 'notification', 'message_id' => '182002'])->assertJson(['success' => true]);

        $sent = null;
        SmsService::$transportOverride = function ($url, $data) use (&$sent) { parse_str((string) parse_url($url, PHP_URL_QUERY), $sent); // Fast2SMS SMS is a documented GET: values are in the query
             return [['return' => true], 200]; };

        SmsService::sendDltNotification('notification', '9876543210', 'Water supply off 2-4 PM', $this->society);

        $this->assertSame('182002', $sent['message'], 'notification template, not the OTP one');
        $this->assertSame('Water supply off 2-4 PM', $sent['variables_values']);
    }

    public function test_a_saved_dlt_template_id_is_never_sent_as_a_message_id(): void
    {
        $this->saveSmsKey();
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society);
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
        $this->assertStringContainsString('accepted it for delivery', $r->json('message'));
        $this->assertSame('4321', $sent['variables_values']);
        $this->assertTrue(ChannelStatus::get('sms', $this->society)['ok']);
    }

    // ── WhatsApp ────────────────────────────────────────────────────────────

    public function test_connect_fills_number_ids_and_syncs_this_numbers_templates(): void
    {
        $this->connectWhatsApp([
            $this->waTemplate('payment_completed', 1, 'Dear user, Your last payment completed successfully amount: {{1}} Thank you.'),
            $this->waTemplate('visitor_alert', 2, 'Hi {{1}}, {{2}} is at the gate.'),
            $this->waTemplate('promo', 1, 'Sale {{1}}', '7001001001001001', 'Rejected'),
        ]);

        $this->assertSame('7001001001001001', SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $this->society));
        $this->assertSame('4001001001001001', SystemSetting::get('whatsapp', 'whatsapp_waba_id', null, $this->society));
        $this->assertSame(3, WhatsAppTemplate::bySociety($this->society)->count());
        $this->assertSame(2, WhatsAppTemplate::bySociety($this->society)->approved()->count());
        $tpl = WhatsAppTemplate::where('name', 'payment_completed')->first();
        $this->assertSame(1, $tpl->var_count);
        $this->assertSame('en', $tpl->language);
        $this->assertStringContainsString('amount: {{1}}', $tpl->body_text);
        $this->assertTrue(ChannelStatus::get('whatsapp', $this->society)['ok']);

        $html = $this->actingAs($this->admin)->get('/settings?tab=whatsapp')->assertOk()->getContent();
        $this->assertStringContainsString('WhatsApp connected', $html);
        $this->assertStringContainsString('Payment Completed', $html);
        $this->assertStringNotContainsString('meta_payment_completed', $html, 'Meta template IDs stay internal');
        $this->assertStringNotContainsString(self::KEY, $html);
    }

    public function test_connect_with_several_numbers_asks_which_one(): void
    {
        $this->saveWhatsAppKey();
        $this->fakeAccount([
            'whatsapp?type=number' => [['success' => true, 'data' => [
                array_diff_key($this->waNumber('7001'), ['templates' => 1]),
                array_diff_key($this->waNumber('7002', '+919000000002'), ['templates' => 1]),
            ]], 200],
            'whatsapp?type=template' => [['success' => true, 'data' => [$this->waNumber('7002', '+919000000002', [$this->waTemplate('only_on_two', 1, 'X {{1}}', '7002')])]], 200],
        ]);

        $first = $this->actingAs($this->admin)->postJson(route('settings.whatsapp.connect'))->assertOk();
        $this->assertTrue($first->json('choose'));
        $this->assertCount(2, $first->json('numbers'));
        $this->assertEmpty(SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $this->society));

        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.connect'), ['phone_number_id' => '7002'])->assertJson(['success' => true]);
        $this->assertSame('7002', SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $this->society));
        $this->assertSame(['only_on_two'], WhatsAppTemplate::bySociety($this->society)->pluck('name')->all());
    }

    public function test_verify_template_resolves_ids_and_names_but_never_saves_the_pasted_value(): void
    {
        $this->connectWhatsApp([$this->waTemplate('payment_completed', 1, 'amount: {{1}}')]);
        $tpl = WhatsAppTemplate::where('name', 'payment_completed')->first();

        foreach (['meta_payment_completed', 'payment_completed', 'Payment Completed', (string) $tpl->message_id] as $ref) {
            $this->actingAs($this->admin)->postJson(route('settings.whatsapp.verify-template'), ['template_ref' => $ref])
                ->assertOk()->assertJson(['success' => true, 'template' => ['id' => $tpl->id]]);
        }

        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.verify-template'), ['template_ref' => 'no_such_template'])
            ->assertJson(['success' => false]);
        $this->assertNull(SystemSetting::get('whatsapp', 'whatsapp_payment_template_name', null, $this->society));
    }

    public function test_assigning_a_template_checks_variables_and_drives_the_real_send(): void
    {
        $this->connectWhatsApp([
            $this->waTemplate('payment_completed', 1, 'Dear user, amount: {{1}}'),
            $this->waTemplate('huge', 9, '{{1}}{{2}}{{3}}{{4}}{{5}}{{6}}{{7}}{{8}}{{9}}'),
        ]);
        $ok = WhatsAppTemplate::where('name', 'payment_completed')->first();
        $huge = WhatsAppTemplate::where('name', 'huge')->first();

        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.assign'), ['purpose' => 'payment', 'template_id' => $huge->id, 'variables' => array_fill(0, 9, 'amount')])
            ->assertJson(['success' => false]);
        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.assign'), ['purpose' => 'payment', 'template_id' => $ok->id, 'variables' => []])
            ->assertJson(['success' => false]);
        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.assign'), ['purpose' => 'payment', 'template_id' => $ok->id, 'variables' => ['amount']])
            ->assertJson(['success' => true]);

        $sent = null;
        $url = null;
        WhatsAppService::$transportOverride = function ($u) use (&$url) { $url = $u; return [['status' => true, 'request_id' => 'r1'], 200]; };

        $result = WhatsAppService::sendNotification('payment', '9876543210', ['resident_name' => 'Tara', 'amount' => "\u{20B9}1,200.00", 'society_name' => 'Royal Eden'], $this->society);

        $this->assertTrue($result['success']);
        // synced templates are sent by Fast2SMS's integer message_id (the simple API), with the mapped values piped
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame((string) $ok->message_id, $q['message_id']);
        $this->assertNotSame('meta_payment_completed', $q['message_id']);
        $this->assertSame("\u{20B9}1,200.00", $q['variables_values']);
        $this->assertDatabaseHas('email_logs', ['channel' => 'whatsapp', 'trigger_action' => 'payment', 'status' => 'sent']);
    }

    public function test_sync_repairs_assignments_saved_as_display_names(): void
    {
        $this->saveWhatsAppKey();
        SystemSetting::set('whatsapp', 'whatsapp_payment_template_name', 'Payment Completed', 'string', $this->society);
        SystemSetting::set('whatsapp', 'whatsapp_visitor_template_name', 'Something Unknown', 'string', $this->society);

        $this->connectWhatsApp([$this->waTemplate('payment_completed', 1, 'amount: {{1}}')]);

        $this->assertSame('payment_completed', SystemSetting::get('whatsapp', 'whatsapp_payment_template_name', null, $this->society));
        $this->assertSame('Something Unknown', SystemSetting::get('whatsapp', 'whatsapp_visitor_template_name', null, $this->society), 'unmatched values are left alone, never guessed');
        // the purpose -> template mapping lives on the Message Templates page now (the settings page only summarises it)
        $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->assertSee('Needs a new template');
    }

    public function test_saving_whatsapp_settings_keeps_template_assignments(): void
    {
        SystemSetting::set('whatsapp', 'whatsapp_payment_template_name', 'payment_completed', 'string', $this->society);

        $resp = $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), [
            'whatsapp_enabled' => '1', 'whatsapp_api_key' => self::KEY, 'whatsapp_phone_number_id' => '7001001001001001',
        ]);
        $resp->assertRedirect('/settings?tab=whatsapp');

        $this->assertSame('payment_completed', SystemSetting::get('whatsapp', 'whatsapp_payment_template_name', null, $this->society));
    }

    public function test_test_whatsapp_sends_the_chosen_template_with_sample_values(): void
    {
        $this->connectWhatsApp([$this->waTemplate('visitor_alert', 2, 'Hi {{1}}, {{2}} is here')]);
        $tpl = WhatsAppTemplate::where('name', 'visitor_alert')->first();
        $url = null;
        WhatsAppService::$transportOverride = function ($u) use (&$url) { $url = $u; return [['status' => true, 'request_id' => 'r2'], 200]; };

        $r = $this->actingAs($this->admin)->postJson(route('settings.whatsapp.test-template'), ['phone' => '9876543210', 'template_id' => $tpl->id])->assertOk();

        $this->assertTrue($r->json('success'));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('Test 1|Test 2', $q['variables_values']);
        $this->assertSame('919876543210', $q['numbers']);
        $this->assertSame((string) $tpl->message_id, $q['message_id']);

        WhatsAppService::$transportOverride = fn () => [['error' => ['message' => '(#132001) Template name does not exist in the translation']], 400];
        $fail = $this->actingAs($this->admin)->postJson(route('settings.whatsapp.test-template'), ['phone' => '9876543210', 'template_id' => $tpl->id])->assertOk();
        $this->assertFalse($fail->json('success'));
        $this->assertStringContainsString('does not recognise that template', $fail->json('message'));
        $this->assertStringContainsString('132001', $fail->json('details'));
    }

    public function test_another_societys_admin_cannot_test_with_or_see_our_templates(): void
    {
        $this->connectWhatsApp([$this->waTemplate('visitor_alert', 2, 'Hi {{1}} {{2}}')]);
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

        foreach (['settings.sms.connect', 'settings.sms.verify', 'settings.sms.use-template', 'settings.whatsapp.connect', 'settings.whatsapp.sync',
                  'settings.whatsapp.verify-template', 'settings.whatsapp.assign', 'settings.whatsapp.unassign', 'settings.whatsapp.test-template', 'settings.email.test'] as $route) {
            $this->actingAs($tenant)->postJson(route($route), [])->assertForbidden();
        }
    }

    // ── OTP / notification independence (SMS audit) ─────────────────────────

    public function test_a_notification_is_never_sent_through_the_otp_template_when_none_is_chosen(): void
    {
        $this->saveSmsKey();
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society);
        // Legacy shape: only the (single) OTP template exists.
        SystemSetting::set('sms', 'fast2sms_template_id', '182001', 'string', $this->society);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'ROYALE', 'string', $this->society);
        $called = false;
        SmsService::$transportOverride = function () use (&$called) { $called = true; return [['return' => true], 200]; };

        $result = SmsService::sendDltNotification('notification', '9876543210', 'Water supply off 2-4 PM', $this->society);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No notification SMS template', $result['message']);
        $this->assertFalse($called, 'no request is made: the OTP template is not approved for notification text');
        $this->actingAs($this->admin)->get('/settings?tab=sms')->assertSee('the OTP template is never reused');
    }

    public function test_choosing_the_otp_template_never_changes_the_notification_template_and_vice_versa(): void
    {
        $this->saveSmsKey();
        $this->fakeAccount(['dlt_manager?type=template' => $this->smsTemplatesResponse()]);
        $get = fn (string $k) => SystemSetting::get('sms', $k, null, $this->society);

        $this->actingAs($this->admin)->postJson(route('settings.sms.use-template'), ['purpose' => 'notification', 'message_id' => '182002'])->assertJson(['success' => true]);
        $notifBefore = [$get('fast2sms_notification_template_id'), $get('fast2sms_notification_sender_id')];
        $this->assertNull($get('fast2sms_template_id'), 'choosing a notification template does not fill the OTP one');

        $this->actingAs($this->admin)->postJson(route('settings.sms.use-template'), ['purpose' => 'otp', 'message_id' => '182001'])->assertJson(['success' => true]);
        $this->assertSame($notifBefore, [$get('fast2sms_notification_template_id'), $get('fast2sms_notification_sender_id')], 'choosing the OTP template leaves notifications alone');
        $this->assertSame('182001', $get('fast2sms_template_id'));
    }

    public function test_connect_and_verify_only_read_the_account_and_never_call_a_send_endpoint(): void
    {
        $this->saveSmsKey();
        $this->fakeAccount(['wallet' => [['return' => true, 'wallet' => 120.5], 200], 'dlt_manager?type=template' => $this->smsTemplatesResponse()]);
        $sent = false;
        SmsService::$transportOverride = function () use (&$sent) { $sent = true; return [['return' => true], 200]; };

        $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();
        $this->actingAs($this->admin)->postJson(route('settings.sms.verify'), ['template_ref' => '182001'])->assertOk();

        $this->assertFalse($sent, 'no SMS transport is touched');
        foreach ($this->providerCalls as $call) {
            $this->assertMatchesRegularExpression('#/dev/(wallet|dlt_manager)#', $call, 'only the balance and template-list endpoints are used');
            $this->assertStringNotContainsString('/bulkV2', $call);
            $this->assertStringNotContainsString('/otp/send', $call);
        }
        $this->assertNotEmpty($this->providerCalls);
    }
}
