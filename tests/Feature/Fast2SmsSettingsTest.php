<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\SmsService;
use App\Support\SettingSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The Fast2SMS key: saved, kept, replaced, encrypted, never shown.
 *
 * These pin the bug the admin reported: a re-save with the mask in the
 * field silently overwrote the working key with the mask itself.
 */
class Fast2SmsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'F2S-TESTKEY-0123456789ABCDEFGH';

    private function admin()
    {
        return $this->makeUserWithRole($this->makeSociety(), 'Admin');
    }

    private function save(\App\Models\User $admin, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->post(route('settings.sms.update'), array_merge([
            'sms_provider'         => 'fast2sms',
            'sms_enabled'          => '1',
            'fast2sms_route'       => 'otp',
            'fast2sms_sender_id'   => 'DISP24',
            'fast2sms_template_id' => '223577',
            'fast2sms_otp_id'      => '6fd92db0ae',
        ], $overrides));
    }

    private function storedKey(\App\Models\User $admin): ?string
    {
        return SystemSetting::get('sms', 'fast2sms_api_key', null, $admin->society_id);
    }


    /** Capture every provider request; answer with the given body and status. */
    private function fakeTransport(array $body, int $status, ?array &$sent = null): void
    {
        SmsService::$transportOverride = function (string $url, array $data, array $headers) use ($body, $status, &$sent) {
            $sent = ["url" => $url, "data" => $data, "headers" => $headers];
            return [$body, $status];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    // A. New key saves.
    public function test_a_new_key_is_saved(): void
    {
        $admin = $this->admin();

        $this->save($admin, ['fast2sms_api_key' => self::KEY])->assertSessionHas('success');

        $this->assertSame(self::KEY, $this->storedKey($admin));
    }

    // B. The masked field keeps the existing key.
    public function test_submitting_the_mask_preserves_the_existing_key(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $this->save($admin, ['fast2sms_api_key' => SettingSecrets::MASK]);

        $this->assertSame(self::KEY, $this->storedKey($admin), 'the mask must not overwrite the key');
    }

    // B2. A blank field also preserves it.
    public function test_a_blank_key_field_preserves_the_existing_key(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $this->save($admin, ['fast2sms_api_key' => '']);

        $this->assertSame(self::KEY, $this->storedKey($admin));
    }

    // C. A genuinely new key replaces the old one.
    public function test_a_new_key_replaces_the_old_one(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $this->save($admin, ['fast2sms_api_key' => 'NEW-REPLACEMENT-KEY-XYZ123456']);

        $this->assertSame('NEW-REPLACEMENT-KEY-XYZ123456', $this->storedKey($admin));
    }

    // D. Encrypted at rest.
    public function test_the_key_is_encrypted_at_rest(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $raw = SystemSetting::where('group', 'sms')
            ->where('key', 'fast2sms_api_key')
            ->where('society_id', $admin->society_id)
            ->value('value');

        $this->assertNotSame(self::KEY, $raw);
        $this->assertStringNotContainsString(self::KEY, (string) $raw);
    }

    // D2. Re-saving never double-encrypts.
    public function test_re_saving_an_unchanged_key_does_not_double_encrypt(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $this->save($admin, ['fast2sms_api_key' => SettingSecrets::MASK]);
        $this->save($admin, ['fast2sms_api_key' => SettingSecrets::MASK]);

        $this->assertSame(self::KEY, $this->storedKey($admin));
    }

    // E. Never returned to the browser.
    public function test_the_key_never_appears_in_the_settings_page(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $html = $this->actingAs($admin)->get('/settings?tab=sms')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::KEY, $html);
    }

    // F. Reload shows the configured state, without the value.
    public function test_reload_shows_configured_without_revealing_the_key(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $html = $this->actingAs($admin)->get('/settings?tab=sms')->assertOk()->getContent();

        $this->assertStringContainsString('API key configured', $html);
        $this->assertStringContainsString(SettingSecrets::MASK, $html);
        $this->assertStringNotContainsString(self::KEY, $html);
    }

    public function test_an_unconfigured_key_says_so(): void
    {
        $admin = $this->admin();

        $html = $this->actingAs($admin)->get('/settings?tab=sms')->assertOk()->getContent();

        $this->assertStringContainsString('API key not configured', $html);
    }

    // Society scope: a key saved for one society is not visible to another.
    public function test_the_key_is_scoped_to_its_society(): void
    {
        $a = $this->admin();
        $this->save($a, ['fast2sms_api_key' => self::KEY]);

        $b = $this->admin();

        $this->assertNull($this->storedKey($b));
    }

    // Phone normalisation.
    public function test_phone_numbers_are_normalised_to_ten_digits(): void
    {
        $this->assertSame('9876543210', SmsService::normalizePhone('+91 98765 43210'));
        $this->assertSame('9876543210', SmsService::normalizePhone('919876543210'));
        $this->assertSame('9876543210', SmsService::normalizePhone('09876543210'));
        $this->assertSame('9876543210', SmsService::normalizePhone('9876543210'));
    }

    public function test_phone_is_masked_to_its_last_four_digits(): void
    {
        $masked = SmsService::maskPhone('+91 98765 43210');

        // Only the final four digits may be visible; nothing else of the number.
        $this->assertStringEndsWith('3210', $masked);
        $this->assertStringNotContainsString('9876', $masked);
        $this->assertSame(0, substr_count($masked, '9') + substr_count($masked, '8') + substr_count($masked, '7'));
    }

    // Test SMS: uses the saved configuration and reports real outcomes.
    public function test_test_sms_reports_a_provider_rejection_as_a_failure(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $this->fakeTransport(['return' => false, 'message' => ['Invalid authorization key']], 401);

        $resp = $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210'])
            ->assertOk();

        // Settings now shows a plain reason; the provider's own words are kept
        // under "View technical details" (`details`), not in the headline.
        $this->assertFalse($resp->json('success'));
        $this->assertStringContainsString('did not accept the API key', $resp->json('message'));
        $this->assertStringContainsString('Fast2SMS request failed', $resp->json('details'));
        $this->assertStringContainsString('Invalid authorization key', $resp->json('details'));
        $this->assertStringNotContainsString(self::KEY, $resp->getContent());
    }

    public function test_test_sms_reports_disabled_sms_rather_than_success(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY, 'sms_enabled' => '0']);

        $resp = $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210'])
            ->assertOk();

        $this->assertFalse($resp->json('success'));
        $this->assertStringContainsString('turned off', $resp->json('message'));
    }

    public function test_test_sms_uses_the_saved_key_in_the_authorization_header(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $this->fakeTransport(['return' => true, 'message' => ['sent']], 200, $sent);

        $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210'])
            ->assertOk()
            ->assertJson(['success' => true]);

        // Smart OTP endpoint, exact payload: no DLT field may reach it.
        $this->assertSame('https://www.fast2sms.com/dev/otp/send', $sent['url']);
        $this->assertContains('authorization: ' . self::KEY, $sent['headers']);
        $this->assertContains('Content-Type: application/json', $sent['headers']);
        $this->assertContains('Accept: application/json', $sent['headers']);
        $this->assertSame(
            // otp_expiry is the app's own code lifetime (5 min, MobileOtp::TTL_MINUTES) - it was 10 while the app expired codes at 5
            ['mobile' => '9876543210', 'otp_id' => '6fd92db0ae', 'otp' => '1234', 'otp_expiry' => \App\Models\MobileOtp::TTL_MINUTES],
            $sent['data']
        );
    }

    public function test_dlt_route_uses_the_bulk_api_with_dlt_fields_and_never_the_otp_id(): void
    {
        $admin = $this->admin();
        $this->save($admin, [
            'fast2sms_api_key'     => self::KEY,
            'fast2sms_route'       => 'dlt',
            'fast2sms_sender_id'   => 'DISP24',
            'fast2sms_template_id' => '223577',
        ]);

        $this->fakeTransport(['return' => true], 200, $sent);

        $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('https://www.fast2sms.com/dev/bulkV2', $sent['url']);
        $this->assertSame('dlt', $sent['data']['route']);
        $this->assertSame('DISP24', $sent['data']['sender_id']);
        $this->assertSame('223577', $sent['data']['message']);
        $this->assertStringNotContainsString('6fd92db0ae', json_encode($sent));
    }

    public function test_otp_route_without_an_otp_id_gives_a_clear_configuration_error(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY, 'fast2sms_otp_id' => '']);

        $this->fakeTransport(['return' => true], 200, $sent);

        $resp = $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210'])
            ->assertOk();

        $this->assertFalse($resp->json('success'));
        $this->assertStringContainsString('OTP ID is not set', $resp->json('message'));
        $this->assertNull($sent, 'no provider request may be sent without an OTP ID');
    }

    public function test_the_otp_id_is_stored_as_plain_config_and_shown_on_the_form(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_otp_id' => '6fd92db0ae']);

        $raw = SystemSetting::where('group', 'sms')
            ->where('key', 'fast2sms_otp_id')
            ->where('society_id', $admin->society_id)
            ->value('value');
        $this->assertSame('6fd92db0ae', $raw, 'the OTP ID is config, not a secret, so it is not encrypted');

        $html = $this->actingAs($admin)->get('/settings?tab=sms')->assertOk()->getContent();
        $this->assertStringContainsString('name="fast2sms_otp_id"', $html);
        $this->assertStringContainsString('value="6fd92db0ae"', $html);
    }

    public function test_the_provider_request_uses_the_current_saved_key_after_a_change(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);
        $this->save($admin, ['fast2sms_api_key' => 'SECOND-KEY-AAAAAAAAAAAAAAAAAA']);

        $this->fakeTransport(['return' => true], 200, $sent);

        $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210']);

        $this->assertContains('authorization: SECOND-KEY-AAAAAAAAAAAAAAAAAA', $sent['headers']);
        $this->assertNotContains('authorization: ' . self::KEY, $sent['headers']);
    }

    public function test_a_missing_key_reports_sms_not_configured(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => '', 'sms_enabled' => '1']);

        $this->fakeTransport(['return' => true], 200, $sent);

        $result = SmsService::sendOtp('9876543210', '1234', $admin->society_id);

        $this->assertSame('dev_mode', $result['message']);
        $this->assertNull($sent, 'no provider request may be sent without a key');
    }

    public function test_a_transport_timeout_is_reported_not_swallowed(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        SmsService::$transportOverride = function () {
            throw new \RuntimeException('Operation timed out after 15000 milliseconds');
        };

        $resp = $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210'])
            ->assertOk();

        $this->assertFalse($resp->json('success'));
        $this->assertStringContainsString('could not be reached', $resp->json('message'));
        $this->assertStringContainsString('Fast2SMS request failed', $resp->json('details'));
    }

    /** Logs must never carry the key, the OTP, or a full phone number. */
    public function test_logs_never_contain_the_key_or_the_full_phone_number(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => self::KEY]);

        $logged = [];
        Log::shouldReceive('info', 'warning', 'error')->andReturnUsing(function ($msg, $ctx = []) use (&$logged) {
            $logged[] = $msg . ' ' . json_encode($ctx);
        });

        $this->fakeTransport(['return' => false, 'message' => 'Invalid authorization key'], 401);

        $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210']);

        $all = implode("\n", $logged);
        $this->assertStringNotContainsString(self::KEY, $all);
        $this->assertStringNotContainsString('9876543210', $all);
        $this->assertStringNotContainsString('1234', $all, 'the test OTP must not be logged');
    }

    /** Bulk SMS keys get the same protection, not just Fast2SMS. */
    public function test_a_masked_bulksms_password_is_also_preserved(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['bulksmsplans_api_password' => 'BULK-PASS-AAAAAAAAAAAAAAAA']);

        $this->save($admin, ['bulksmsplans_api_password' => SettingSecrets::MASK]);

        $this->assertSame(
            'BULK-PASS-AAAAAAAAAAAAAAAA',
            SystemSetting::get('sms', 'bulksmsplans_api_password', null, $admin->society_id)
        );
    }
}
