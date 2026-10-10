<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\WhatsAppService;
use App\Support\SettingSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * WhatsApp (Fast2SMS WABA): configuration, masking, and the real request
 * shape - built against Fast2SMS's own published API reference
 * (docs.fast2sms.com/reference/send{authentication,text}template), not
 * guessed.
 */
class WhatsAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'WA-TESTKEY-0123456789ABCDEFGH';

    private function admin()
    {
        return $this->makeUserWithRole($this->makeSociety(), 'Admin');
    }

    private function save(\App\Models\User $admin, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->post(route('settings.whatsapp.update'), array_merge([
            'whatsapp_enabled'          => '1',
            'whatsapp_phone_number_id'  => '100594425479261',
            'whatsapp_api_version'      => 'v26.0',
            'whatsapp_template_language'=> 'en_US',
            'whatsapp_otp_template_name'=> 'auth_template',
        ], $overrides));
    }

    private function fakeTransport(array $body, int $status, ?array &$sent = null): void
    {
        WhatsAppService::$transportOverride = function (string $url, array $data, array $headers) use ($body, $status, &$sent) {
            $sent = ['url' => $url, 'data' => $data, 'headers' => $headers];
            return [$body, $status];
        };
    }

    protected function tearDown(): void
    {
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    // ── Settings save / mask / preserve ─────────────────────────────────────

    public function test_saving_configures_whatsapp(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY])->assertSessionHas('success');

        $this->assertSame(self::KEY, SystemSetting::get('whatsapp', 'whatsapp_api_key', null, $admin->society_id));
        $this->assertSame('100594425479261', SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $admin->society_id));
    }

    public function test_the_key_is_encrypted_at_rest(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $raw = SystemSetting::where('group', 'whatsapp')->where('key', 'whatsapp_api_key')
            ->where('society_id', $admin->society_id)->value('value');

        $this->assertNotSame(self::KEY, $raw);
        $this->assertStringNotContainsString(self::KEY, (string) $raw);
    }

    public function test_submitting_the_mask_preserves_the_existing_key(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $this->save($admin, ['whatsapp_api_key' => SettingSecrets::MASK]);

        $this->assertSame(self::KEY, SystemSetting::get('whatsapp', 'whatsapp_api_key', null, $admin->society_id));
    }

    public function test_the_key_never_appears_in_the_settings_page(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $html = $this->actingAs($admin)->get('/settings?tab=whatsapp')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::KEY, $html);
        $this->assertStringContainsString('API key configured', $html);
    }

    public function test_the_key_is_scoped_to_its_society(): void
    {
        $a = $this->admin();
        $this->save($a, ['whatsapp_api_key' => self::KEY]);

        $b = $this->admin();
        $this->assertNull(SystemSetting::get('whatsapp', 'whatsapp_api_key', null, $b->society_id));
    }

    // ── The real request shape ──────────────────────────────────────────────

    public function test_otp_uses_the_authentication_template_shape(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $this->fakeTransport(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.test']]], 200, $sent);

        $result = WhatsAppService::sendOtp('9876543210', '482913', $admin->society_id);

        $this->assertTrue($result['success']);
        $this->assertSame('https://www.fast2sms.com/dev/whatsapp/v26.0/100594425479261/messages', $sent['url']);
        $this->assertContains('authorization: ' . self::KEY, $sent['headers']);
        $this->assertSame('whatsapp', $sent['data']['messaging_product']);
        $this->assertSame('individual', $sent['data']['recipient_type']);
        $this->assertSame('919876543210', $sent['data']['to']);
        $this->assertSame('template', $sent['data']['type']);
        $this->assertSame('auth_template', $sent['data']['template']['name']);
        $this->assertSame('en_US', $sent['data']['template']['language']['code']);
        $this->assertSame('482913', $sent['data']['template']['components'][0]['parameters'][0]['text']);
        $this->assertSame('button', $sent['data']['template']['components'][1]['type']);
        $this->assertSame('482913', $sent['data']['template']['components'][1]['parameters'][0]['text']);
    }

    public function test_a_notification_uses_its_own_configured_template(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY, 'whatsapp_visitor_template_name' => 'visitor_arrival']);

        $this->fakeTransport(['messages' => [['id' => 'wamid.test']]], 200, $sent);

        $result = WhatsAppService::sendNotification('visitor', '9876543210', ['Ramesh Kumar', 'Flat 12B'], $admin->society_id);

        $this->assertTrue($result['success']);
        $this->assertSame('visitor_arrival', $sent['data']['template']['name']);
        $this->assertSame('Ramesh Kumar', $sent['data']['template']['components'][0]['parameters'][0]['text']);
        $this->assertSame('Flat 12B', $sent['data']['template']['components'][0]['parameters'][1]['text']);
    }

    public function test_a_notification_with_no_template_configured_fails_honestly(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $this->fakeTransport(['messages' => [['id' => 'wamid.test']]], 200, $sent);

        $result = WhatsAppService::sendNotification('visitor', '9876543210', ['x'], $admin->society_id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No WhatsApp template', $result['message']);
        $this->assertNull($sent, 'no request may be sent without a template name');
    }

    public function test_session_message_uses_the_session_endpoint(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $this->fakeTransport(['status' => 'success'], 200, $sent);

        $result = WhatsAppService::sendSessionMessage('9876543210', 'Your visitor has arrived.', $admin->society_id);

        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('https://www.fast2sms.com/dev/whatsapp-session?', $sent['url']);
        $this->assertStringContainsString('phone_number_id=100594425479261', $sent['url']);
        $this->assertStringContainsString('to=919876543210', $sent['url']);
        $this->assertSame('text', $sent['data']['type']);
        $this->assertSame('Your visitor has arrived.', $sent['data']['text']);
    }

    public function test_disabled_whatsapp_sends_nothing(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY, 'whatsapp_enabled' => '0']);

        $this->fakeTransport(['messages' => [['id' => 'x']]], 200, $sent);

        $result = WhatsAppService::sendOtp('9876543210', '1234', $admin->society_id);

        $this->assertSame('dev_mode', $result['message']);
        $this->assertNull($sent);
    }

    public function test_a_provider_rejection_is_reported_not_claimed_as_success(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $this->fakeTransport(['error' => ['message' => 'Template name does not exist in the translation']], 400);

        $result = WhatsAppService::sendOtp('9876543210', '1234', $admin->society_id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Template name does not exist', $result['message']);
        $this->assertStringNotContainsString(self::KEY, $result['message']);
    }

    // ── Settings → Test WhatsApp button ─────────────────────────────────────

    public function test_test_button_reports_disabled_rather_than_success(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY, 'whatsapp_enabled' => '0']);

        $resp = $this->actingAs($admin)->postJson(route('settings.whatsapp.test'), ['phone' => '9876543210'])->assertOk();

        $this->assertFalse($resp->json('success'));
        $this->assertStringContainsString('turned off', $resp->json('message'));
    }

    public function test_test_button_reports_missing_template_rather_than_success(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY, 'whatsapp_otp_template_name' => '']);

        $resp = $this->actingAs($admin)->postJson(route('settings.whatsapp.test'), ['phone' => '9876543210'])->assertOk();

        $this->assertFalse($resp->json('success'));
        $this->assertStringContainsString('No WhatsApp template', $resp->json('message'));
    }

    public function test_test_button_uses_the_saved_otp_template_and_reports_real_success(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $this->fakeTransport(['messages' => [['id' => 'wamid.test']]], 200, $sent);

        $resp = $this->actingAs($admin)->postJson(route('settings.whatsapp.test'), ['phone' => '9876543210'])->assertOk();

        $this->assertTrue($resp->json('success'));
        $this->assertSame('auth_template', $sent['data']['template']['name']);
        $this->assertStringNotContainsString(self::KEY, $resp->getContent());
    }

    public function test_logs_never_contain_the_key_the_phone_or_the_otp(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['whatsapp_api_key' => self::KEY]);

        $logged = [];
        Log::shouldReceive('info', 'warning', 'error')->andReturnUsing(function ($msg, $ctx = []) use (&$logged) {
            $logged[] = $msg . ' ' . json_encode($ctx);
        });

        $this->fakeTransport(['error' => ['message' => 'Invalid authorization']], 401);

        WhatsAppService::sendOtp('9876543210', '482913', $admin->society_id);

        $all = implode("\n", $logged);
        $this->assertStringNotContainsString(self::KEY, $all);
        $this->assertStringNotContainsString('9876543210', $all);
        $this->assertStringNotContainsString('482913', $all);
    }
}
