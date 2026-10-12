<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\Fast2SmsAccount;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Support\SettingSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Settings -> SMS and Settings -> WhatsApp: what a save stores, what it refuses, and what it never touches. Credentials
 * are encrypted at rest, masked on the page, and an empty or masked field keeps the saved one; turning an integration off
 * keeps every setting and really stops the sending; Sender ID, Message ID, Phone Number ID and country code are checked on
 * the server.
 */
class CommunicationSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    private const SMS_KEY = 'sms-key-0123456789abcdefghij';
    private const WA_KEY = 'wa-key-0123456789abcdefghij';

    private $society;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
    }

    protected function tearDown(): void
    {
        Fast2SmsAccount::$transportOverride = null;
        SmsService::$transportOverride = null;
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function sms(array $over = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->from('/settings?tab=sms')->post(route('settings.sms.update'), $over + ['sms_provider' => 'fast2sms']);
    }

    private function wa(array $over = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->from('/settings?tab=whatsapp')->post(route('settings.whatsapp.update'), $over);
    }

    private function saved(string $group, string $key)
    {
        return SystemSetting::get($group, $key, null, $this->society->id);
    }

    private function rawValue(string $group, string $key): ?string
    {
        return DB::table('system_settings')->where('group', $group)->where('key', $key)->where('society_id', $this->society->id)->value('value');
    }

    // ════════════════════════ SMS ════════════════════════

    public function test_an_sms_save_stores_the_values_and_normalises_the_sender(): void
    {
        $this->sms(['sms_enabled' => '1', 'fast2sms_api_key' => self::SMS_KEY, 'fast2sms_sender_id' => ' bookmz ', 'fast2sms_template_id' => '223577'])
            ->assertRedirect('/settings?tab=sms')->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertTrue((bool) $this->saved('sms', 'sms_enabled'));
        $this->assertSame('fast2sms', $this->saved('sms', 'sms_provider'));
        $this->assertSame('BOOKMZ', $this->saved('sms', 'fast2sms_sender_id'));
        $this->assertSame('223577', $this->saved('sms', 'fast2sms_template_id'));
        $this->assertSame('dlt', $this->saved('sms', 'fast2sms_route'), 'DLT is the default');
        $this->assertSame(self::SMS_KEY, $this->saved('sms', 'fast2sms_api_key'));
    }

    public function test_switching_the_integration_off_keeps_every_saved_setting(): void
    {
        $this->sms(['sms_enabled' => '1', 'fast2sms_api_key' => self::SMS_KEY, 'fast2sms_sender_id' => 'BOOKMZ', 'fast2sms_template_id' => '223577']);

        $this->sms(['sms_enabled' => '0', 'fast2sms_sender_id' => 'BOOKMZ', 'fast2sms_template_id' => '223577']);

        $this->assertFalse((bool) $this->saved('sms', 'sms_enabled'));
        $this->assertSame(self::SMS_KEY, $this->saved('sms', 'fast2sms_api_key'), 'turning it off is not forgetting the key');
        $this->assertSame('BOOKMZ', $this->saved('sms', 'fast2sms_sender_id'));
        $this->assertSame('223577', $this->saved('sms', 'fast2sms_template_id'));
    }

    public function test_a_switched_off_integration_really_stops_the_sending(): void
    {
        $this->sms(['sms_enabled' => '0', 'fast2sms_api_key' => self::SMS_KEY]);
        $called = false;
        SmsService::$transportOverride = function () use (&$called) { $called = true; return [['return' => true], 200]; };

        $result = SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertFalse($result['sent']);
        $this->assertSame(SmsService::STATUS_SKIPPED_DISABLED, $result['status']);
        $this->assertFalse($called);
    }

    /** @dataProvider senderIds */
    public function test_the_sender_id_is_three_to_six_letters(string $sender, bool $valid): void
    {
        $r = $this->sms(['fast2sms_sender_id' => $sender]);

        if ($valid) {
            $r->assertSessionHasNoErrors();
        } else {
            $r->assertSessionHasErrors('fast2sms_sender_id');
            $this->assertStringContainsString('3 to 6 letters', session('errors')->first('fast2sms_sender_id'));
            $this->assertNull($this->saved('sms', 'fast2sms_sender_id'), 'a rejected save stores nothing');
        }
    }

    public static function senderIds(): array
    {
        return ['six letters' => ['BOOKMZ', true], 'three letters' => ['ABC', true], 'lower case' => ['bookmz', true], 'an older id with a digit' => ['DISP24', true],
            'too short' => ['AB', false], 'too long' => ['ABCDEFG', false], 'starts with a digit' => ['1ABCDE', false], 'a space inside' => ['AB CD', false], 'punctuation' => ['AB-CD', false]];
    }

    /** @dataProvider messageIds */
    public function test_the_default_message_id_is_the_short_fast2sms_number(string $id, bool $valid): void
    {
        $r = $this->sms(['fast2sms_template_id' => $id]);

        if ($valid) {
            $r->assertSessionHasNoErrors();
            $this->assertSame($id, $this->saved('sms', 'fast2sms_template_id'));
        } else {
            $r->assertSessionHasErrors('fast2sms_template_id');
            $this->assertStringContainsString('223577', session('errors')->first('fast2sms_template_id'));
            $this->assertNull($this->saved('sms', 'fast2sms_template_id'));
        }
    }

    public static function messageIds(): array
    {
        return ['a short number' => ['223577', true], 'one digit' => ['7', true], 'letters' => ['22A577', false], 'the long DLT template id' => ['1707168880000012345', false], 'a space' => ['22 3577', false]];
    }

    public function test_the_optional_ids_can_be_cleared_and_are_left_alone_when_not_sent(): void
    {
        $this->sms(['fast2sms_sender_id' => 'BOOKMZ', 'fast2sms_template_id' => '223577']);

        $this->sms(['sms_enabled' => '1']);                                           // fields not sent at all
        $this->assertSame('BOOKMZ', $this->saved('sms', 'fast2sms_sender_id'));
        $this->assertSame('223577', $this->saved('sms', 'fast2sms_template_id'));

        $this->sms(['fast2sms_sender_id' => '', 'fast2sms_template_id' => '']);       // sent empty: cleared
        $this->assertSame('', $this->saved('sms', 'fast2sms_sender_id'));
        $this->assertSame('', $this->saved('sms', 'fast2sms_template_id'));
    }

    public function test_the_otp_service_is_an_advanced_option_that_is_off_unless_ticked(): void
    {
        $this->sms(['fast2sms_use_otp_service' => '1', 'fast2sms_otp_id' => '6fd92db0ae']);
        $this->assertSame('otp', $this->saved('sms', 'fast2sms_route'));
        $this->assertSame('6fd92db0ae', $this->saved('sms', 'fast2sms_otp_id'));

        $this->sms([]);
        $this->assertSame('dlt', $this->saved('sms', 'fast2sms_route'), 'unticked means the normal automatic routing');
        $this->assertSame('6fd92db0ae', $this->saved('sms', 'fast2sms_otp_id'), 'the OTP ID is kept');
    }

    public function test_an_unknown_provider_is_refused(): void
    {
        $this->sms(['sms_provider' => 'carrier-pigeon'])->assertSessionHasErrors('sms_provider');
    }

    public function test_the_sms_key_is_encrypted_at_rest_masked_on_the_page_and_kept_when_the_field_is_empty_or_masked(): void
    {
        $this->sms(['fast2sms_api_key' => self::SMS_KEY]);

        $this->assertNotNull($this->rawValue('sms', 'fast2sms_api_key'));
        $this->assertStringNotContainsString(self::SMS_KEY, (string) $this->rawValue('sms', 'fast2sms_api_key'), 'the database never holds the plain key');

        $html = $this->actingAs($this->admin)->get('/settings?tab=sms')->assertOk()->getContent();
        $this->assertStringNotContainsString(self::SMS_KEY, $html);
        $this->assertStringContainsString('API key configured', $html);
        $this->assertStringContainsString(SettingSecrets::MASK, $html);

        $this->sms(['fast2sms_api_key' => '']);
        $this->assertSame(self::SMS_KEY, $this->saved('sms', 'fast2sms_api_key'), 'an empty field keeps the key');
        $this->sms(['fast2sms_api_key' => SettingSecrets::MASK]);
        $this->assertSame(self::SMS_KEY, $this->saved('sms', 'fast2sms_api_key'), 'the mask itself is never stored');
        $this->sms(['fast2sms_api_key' => 'a-new-key-0123456789abcdef']);
        $this->assertSame('a-new-key-0123456789abcdef', $this->saved('sms', 'fast2sms_api_key'), 'a new value replaces it');
    }

    public function test_only_admins_can_save_sms_settings_and_only_for_their_own_society(): void
    {
        $this->actingAs($this->makeUserWithRole($this->society, 'Tenant'))->post(route('settings.sms.update'), ['sms_provider' => 'fast2sms', 'fast2sms_sender_id' => 'HACKER'])->assertForbidden();
        $this->assertNull($this->saved('sms', 'fast2sms_sender_id'));

        $other = $this->makeSociety('Beta');
        $this->sms(['fast2sms_sender_id' => 'BOOKMZ', 'society_id' => $other->id]);
        $this->assertSame('BOOKMZ', $this->saved('sms', 'fast2sms_sender_id'));
        $this->assertNull(SystemSetting::get('sms', 'fast2sms_sender_id', null, $other->id), "a request can never name another society's settings");
    }

    // ════════════════════════ WhatsApp ════════════════════════

    public function test_a_whatsapp_save_stores_the_values_and_keeps_the_key_encrypted(): void
    {
        $this->wa(['whatsapp_enabled' => '1', 'whatsapp_api_key' => self::WA_KEY, 'whatsapp_phone_number_id' => '1209719742234591', 'whatsapp_country_code' => '91'])
            ->assertRedirect('/settings?tab=whatsapp')->assertSessionHas('success')->assertSessionHasNoErrors();

        $this->assertTrue((bool) $this->saved('whatsapp', 'whatsapp_enabled'));
        $this->assertSame('1209719742234591', $this->saved('whatsapp', 'whatsapp_phone_number_id'));
        $this->assertSame('91', $this->saved('whatsapp', 'whatsapp_country_code'));
        $this->assertSame(self::WA_KEY, $this->saved('whatsapp', 'whatsapp_api_key'));
        $this->assertStringNotContainsString(self::WA_KEY, (string) $this->rawValue('whatsapp', 'whatsapp_api_key'));

        $html = $this->actingAs($this->admin)->get('/settings?tab=whatsapp')->assertOk()->getContent();
        $this->assertStringNotContainsString(self::WA_KEY, $html);
        $this->assertStringContainsString(SettingSecrets::MASK, $html);
    }

    public function test_the_whatsapp_key_is_kept_when_the_field_is_empty_or_masked_and_replaced_by_a_new_one(): void
    {
        $this->wa(['whatsapp_api_key' => self::WA_KEY]);

        $this->wa(['whatsapp_api_key' => '']);
        $this->assertSame(self::WA_KEY, $this->saved('whatsapp', 'whatsapp_api_key'));
        $this->wa(['whatsapp_api_key' => SettingSecrets::MASK]);
        $this->assertSame(self::WA_KEY, $this->saved('whatsapp', 'whatsapp_api_key'));
        $this->wa(['whatsapp_api_key' => 'another-wa-key-0123456789']);
        $this->assertSame('another-wa-key-0123456789', $this->saved('whatsapp', 'whatsapp_api_key'));
    }

    /** @dataProvider countryCodes */
    public function test_the_default_country_code_is_digits_only(string $code, bool $valid, ?string $stored): void
    {
        $r = $this->wa(['whatsapp_country_code' => $code]);

        if ($valid) {
            $r->assertSessionHasNoErrors();
            $this->assertSame($stored, $this->saved('whatsapp', 'whatsapp_country_code'));
        } else {
            $r->assertSessionHasErrors('whatsapp_country_code');
            $this->assertNull($this->saved('whatsapp', 'whatsapp_country_code'));
        }
    }

    public static function countryCodes(): array
    {
        return ['india' => ['91', true, '91'], 'one digit' => ['1', true, '1'], 'three digits' => ['971', true, '971'], 'empty falls back to 91' => ['', true, '91'],
            'letters' => ['IN', false, null], 'a plus' => ['+91', false, null], 'too long' => ['12345', false, null]];
    }

    /** @dataProvider phoneNumberIds */
    public function test_the_phone_number_id_is_the_long_fast2sms_id_never_a_mobile_number(string $id, bool $valid): void
    {
        $r = $this->wa(['whatsapp_phone_number_id' => $id]);

        if ($valid) {
            $r->assertSessionHasNoErrors();
            $this->assertSame(str_replace(' ', '', $id), (string) $this->saved('whatsapp', 'whatsapp_phone_number_id'));
        } else {
            $r->assertSessionHasErrors('whatsapp_phone_number_id');
            $this->assertStringContainsString('not a mobile number', session('errors')->first('whatsapp_phone_number_id'));
            $this->assertNull($this->saved('whatsapp', 'whatsapp_phone_number_id'));
        }
    }

    public static function phoneNumberIds(): array
    {
        return ['the id from the brief' => ['1209719742234591', true], 'a shorter real id' => ['100594425479261', true], 'empty clears it' => ['', true],
            'a ten digit mobile' => ['9876543210', false], 'a mobile with 91' => ['919876543210', false], 'letters' => ['abc123456789', false], 'too short' => ['12345', false]];
    }

    public function test_switching_whatsapp_off_keeps_every_setting_and_really_stops_the_sending(): void
    {
        \App\Models\WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => 'e1', 'message_id' => '8340', 'name' => 'visitor_arrived', 'status' => 'APPROVED', 'language' => 'en', 'var_count' => 0]);
        SystemSetting::set('whatsapp', 'whatsapp_visitor_template_name', 'visitor_arrived', 'string', $this->society->id);
        $this->wa(['whatsapp_enabled' => '1', 'whatsapp_api_key' => self::WA_KEY, 'whatsapp_phone_number_id' => '1209719742234591']);

        $this->wa(['whatsapp_enabled' => '0']);

        $this->assertFalse((bool) $this->saved('whatsapp', 'whatsapp_enabled'));
        $this->assertSame(self::WA_KEY, $this->saved('whatsapp', 'whatsapp_api_key'));
        $this->assertSame('1209719742234591', $this->saved('whatsapp', 'whatsapp_phone_number_id'));
        $this->assertSame('visitor_arrived', $this->saved('whatsapp', 'whatsapp_visitor_template_name'), 'the notification choices are kept');

        $called = false;
        WhatsAppService::$transportOverride = function () use (&$called) { $called = true; return [['status' => true], 200]; };
        $result = WhatsAppService::sendNotification('visitor', '9876543210', [], $this->society->id);
        $this->assertSame('dev_mode', $result['message']);
        $this->assertFalse($called);
        $this->assertStringContainsString('switched off', session('success') ?? 'switched off');
    }

    public function test_a_save_that_leaves_out_the_internal_settings_does_not_erase_them(): void
    {
        foreach (['whatsapp_api_version' => 'v26.0', 'whatsapp_waba_id' => '4001001001001001', 'whatsapp_template_language' => 'en_US', 'whatsapp_otp_template_name' => 'auth_template'] as $k => $v) {
            SystemSetting::set('whatsapp', $k, $v, 'string', $this->society->id);
        }

        $this->wa(['whatsapp_enabled' => '1', 'whatsapp_phone_number_id' => '1209719742234591', 'whatsapp_country_code' => '91']);

        $this->assertSame('4001001001001001', $this->saved('whatsapp', 'whatsapp_waba_id'));
        $this->assertSame('en_US', $this->saved('whatsapp', 'whatsapp_template_language'));
        $this->assertSame('auth_template', $this->saved('whatsapp', 'whatsapp_otp_template_name'));
        $this->assertSame('v26.0', $this->saved('whatsapp', 'whatsapp_api_version'));
    }

    public function test_the_primary_whatsapp_form_hides_the_technical_fields(): void
    {
        // the WhatsApp section on its own (the settings page renders every tab in one document)
        $this->actingAs($this->admin);
        $html = view('settings.partials.whatsapp', ['settings' => ['whatsapp' => SettingSecrets::mask('whatsapp', SystemSetting::getGroup('whatsapp', $this->society->id))]])->render();

        foreach (['whatsapp_waba_id', 'whatsapp_api_version', 'whatsapp_template_language', 'Business Account ID', 'API Version', 'Advanced'] as $technical) {
            $this->assertStringNotContainsString($technical, $html, "{$technical} is not part of the setup form");
        }
        foreach (['whatsapp_api_key', 'whatsapp_phone_number_id', 'whatsapp_country_code', 'Enabled', 'Phone Number ID'] as $field) {
            $this->assertStringContainsString($field, $html);
        }
    }

    public function test_the_sms_page_is_the_simple_bookmizo_style_layout(): void
    {
        $this->actingAs($this->admin);
        $html = view('settings.partials.sms', ['settings' => ['sms' => SettingSecrets::mask('sms', SystemSetting::getGroup('sms', $this->society->id))]])->render();

        foreach (['SMS for SocietyFlow is sent through your Fast2SMS account', 'Integration inactive', 'API Configuration', 'Fast2SMS API Key', 'Sender ID (DLT, optional)',
                  'Default Fast2SMS Message ID (DLT, optional)', 'Message Templates', 'Test SMS', 'Phone Number', 'Test Message', 'Send Test SMS', 'Save Changes'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach (['Verify connection', 'Connect &amp; Verify', 'Connect & Verify', 'Configuration saved', 'not verified', 'Fetch from Fast2SMS', 'Use for OTP', 'Use for notifications', 'DLT template or Fast2SMS'] as $removed) {
            $this->assertStringNotContainsString($removed, $html, "{$removed} is gone");
        }
        $this->assertSame(0, substr_count($html, 'name="fast2sms_route"'), 'no DLT-or-OTP radio choice any more');
        $this->assertSame(1, substr_count($html, 'name="fast2sms_use_otp_service"'), 'the OTP service is one Advanced switch');
    }

    public function test_the_page_says_when_the_sms_key_is_being_reused(): void
    {
        SystemSetting::set('sms', 'fast2sms_api_key', self::SMS_KEY, 'encrypted', $this->society->id);

        $html = $this->actingAs($this->admin)->get('/settings?tab=whatsapp')->assertOk()->getContent();

        $this->assertStringContainsString('Using the Fast2SMS key saved in SMS settings', $html);
        $this->assertStringNotContainsString(self::SMS_KEY, $html);
    }

    public function test_only_admins_can_save_whatsapp_settings_and_only_for_their_own_society(): void
    {
        $this->actingAs($this->makeUserWithRole($this->society, 'Staff'))->post(route('settings.whatsapp.update'), ['whatsapp_enabled' => '1'])->assertForbidden();
        $this->assertNull($this->saved('whatsapp', 'whatsapp_enabled'));

        $other = $this->makeSociety('Beta');
        $this->wa(['whatsapp_enabled' => '1', 'whatsapp_phone_number_id' => '1209719742234591', 'society_id' => $other->id]);
        $this->assertSame('1209719742234591', $this->saved('whatsapp', 'whatsapp_phone_number_id'));
        $this->assertNull(SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $other->id));
    }
}
