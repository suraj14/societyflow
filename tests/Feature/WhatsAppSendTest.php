<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WhatsApp sending through Fast2SMS: the integer message_id (never the long template_id), the saved Phone Number ID,
 * the default country code on the recipient, the template's variables in order, the API key (its own, or the SMS one
 * when left empty) and every way the provider can answer. The transport is a mock; nothing here messages anyone.
 */
class WhatsAppSendTest extends TestCase
{
    use RefreshDatabase;

    private const WA_KEY = 'wa-key-0123456789abcdefghij';
    private const SMS_KEY = 'sms-key-0123456789abcdefghij';

    private $society;
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $this->wa('whatsapp_enabled', true, 'boolean');
        $this->wa('whatsapp_api_key', self::WA_KEY, 'encrypted');
        $this->wa('whatsapp_phone_number_id', '1209719742234591');
        $this->wa('whatsapp_visitor_template_name', 'visitor_arrived');
        WhatsAppService::$transportOverride = function (string $url, array $data, array $headers) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->requests[] = ['url' => $url, 'q' => $q, 'data' => $data, 'headers' => $headers, 'path' => parse_url($url, PHP_URL_PATH)];

            return [['return' => true, 'message' => ['Whatsapp Message sent successfully.'], 'request_id' => 'wa' . count($this->requests)], 200];
        };
    }

    protected function tearDown(): void
    {
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function wa(string $key, $value, string $type = 'string'): void
    {
        SystemSetting::set('whatsapp', $key, $value, $type, $this->society->id);
    }

    private function template(array $over = []): WhatsAppTemplate
    {
        return WhatsAppTemplate::create($over + ['society_id' => $this->society->id, 'external_id' => '2012345678901234', 'message_id' => '8340', 'name' => 'visitor_arrived',
            'status' => 'APPROVED', 'language' => 'en', 'var_count' => 2, 'body_text' => 'Hi {{1}}, {{2}} is at the gate']);
    }

    private function send(array $values = ['resident_name' => 'Asha Rao', 'visitor_name' => 'Ravi', 'visit_date' => '12 Nov', 'society_name' => 'Alpha'], string $phone = '9876543210')
    {
        return WhatsAppService::sendNotification('visitor', $phone, $values, $this->society->id);
    }

    // ── the request ─────────────────────────────────────────────────────────

    public function test_the_request_uses_the_message_id_the_phone_number_id_and_the_templates_values_in_order(): void
    {
        $this->template();

        $r = $this->send();

        $this->assertTrue($r['success']);
        $this->assertSame('/dev/whatsapp', $this->requests[0]['path']);
        $this->assertSame(['message_id' => '8340', 'phone_number_id' => '1209719742234591', 'numbers' => '919876543210', 'variables_values' => 'Asha Rao|Ravi'], $this->requests[0]['q']);
        $this->assertStringNotContainsString('2012345678901234', $this->requests[0]['url'], 'the long Meta template_id is never sent');
        $this->assertSame(['authorization: ' . self::WA_KEY], $this->requests[0]['headers']);
    }

    public function test_the_saved_variable_mapping_decides_which_value_fills_which_slot(): void
    {
        $this->template();
        $this->wa('whatsapp_visitor_variables', ['visitor_name', 'society_name'], 'json');

        $this->send();

        $this->assertSame('Ravi|Alpha', $this->requests[0]['q']['variables_values']);
    }

    public function test_a_template_with_no_variables_sends_none(): void
    {
        $this->template(['var_count' => 0, 'body_text' => 'Your visitor is at the gate']);

        $this->send();

        $this->assertArrayNotHasKey('variables_values', $this->requests[0]['q']);
    }

    public function test_a_pipe_inside_a_value_cannot_split_it_and_an_empty_value_is_not_sent_blank(): void
    {
        $this->template();

        $this->send(['resident_name' => 'Asha | Rao', 'visitor_name' => '', 'visit_date' => 'x', 'society_name' => 'Alpha']);

        $this->assertSame('Asha / Rao|-', $this->requests[0]['q']['variables_values']);
    }

    public function test_a_template_whose_value_count_does_not_match_is_refused_before_any_request(): void
    {
        $this->template(['var_count' => 2]);

        $r = WhatsAppService::sendNotification('visitor', '9876543210', ['one', 'two', 'three'], $this->society->id);   // a list of 3 for a 2-variable template

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('needs 2 value(s)', $r['message']);
        $this->assertSame([], $this->requests);
        $this->assertDatabaseHas('email_logs', ['channel' => 'whatsapp', 'trigger_action' => 'visitor', 'status' => 'failed']);
    }
    // ── recipient formatting ────────────────────────────────────────────────

    /** @dataProvider recipients */
    public function test_the_recipient_gets_the_default_country_code(string $given, ?string $countryCode, string $expected): void
    {
        $this->template(['var_count' => 0, 'body_text' => 'Your visitor is at the gate']);
        if ($countryCode !== null) {
            $this->wa('whatsapp_country_code', $countryCode);
        }

        $this->send([], $given);

        $this->assertSame($expected, $this->requests[0]['q']['numbers']);
    }

    public static function recipients(): array
    {
        return [
            'ten digits, default 91'            => ['9876543210', null, '919876543210'],
            'spaces and a plus are removed'     => ['+91 98765 43210', null, '919876543210'],
            'a leading 91 is not doubled'       => ['919876543210', null, '919876543210'],
            'a leading zero is dropped'         => ['09876543210', null, '919876543210'],
            'another country code'              => ['5012345678', '971', '9715012345678'],
            'a number that already has its code' => ['971501234567', '971', '971501234567'],
        ];
    }

    // ── the API key ─────────────────────────────────────────────────────────

    public function test_an_empty_whatsapp_key_reuses_the_sms_key(): void
    {
        $this->template();
        $this->wa('whatsapp_api_key', '', 'encrypted');
        SystemSetting::set('sms', 'fast2sms_api_key', self::SMS_KEY, 'encrypted', $this->society->id);

        $this->send();

        $this->assertSame(['authorization: ' . self::SMS_KEY], $this->requests[0]['headers']);
    }

    public function test_a_whatsapp_key_of_its_own_beats_the_sms_key(): void
    {
        $this->template();
        SystemSetting::set('sms', 'fast2sms_api_key', self::SMS_KEY, 'encrypted', $this->society->id);

        $this->send();

        $this->assertSame(['authorization: ' . self::WA_KEY], $this->requests[0]['headers']);
    }

    public function test_another_societys_sms_key_is_never_borrowed(): void
    {
        $this->template();
        $this->wa('whatsapp_api_key', '', 'encrypted');
        SystemSetting::set('sms', 'fast2sms_api_key', self::SMS_KEY, 'encrypted', $this->makeSociety('Beta')->id);

        $r = $this->send();

        $this->assertSame('dev_mode', $r['message'], 'no key for this society: nothing is sent');
        $this->assertSame([], $this->requests);
    }

    // ── on / off ────────────────────────────────────────────────────────────

    public function test_a_switched_off_integration_sends_nothing(): void
    {
        $this->template();
        $this->wa('whatsapp_enabled', false, 'boolean');

        $r = $this->send();

        $this->assertSame('dev_mode', $r['message']);
        $this->assertSame([], $this->requests);
        $this->assertDatabaseCount('email_logs', 0);
    }

    public function test_without_a_phone_number_id_nothing_is_sent(): void
    {
        $this->template();
        $this->wa('whatsapp_phone_number_id', '');

        $r = $this->send();

        $this->assertSame('dev_mode', $r['message']);
        $this->assertSame([], $this->requests);
    }

    public function test_a_notification_without_a_template_is_not_sent_and_says_why(): void
    {
        $this->wa('whatsapp_visitor_template_name', '');

        $r = $this->send();

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('No active WhatsApp template configured', $r['message']);
        $this->assertSame([], $this->requests);
    }

    public function test_the_kill_switch_stops_it_before_the_network(): void
    {
        $this->template();
        WhatsAppService::$transportOverride = null;      // the real transport
        $this->assertFalse(\App\Support\ExternalMessaging::enabled());

        $r = $this->send();

        $this->assertFalse($r['success']);
        $this->assertTrue($r['skipped']);
        $this->assertSame(\App\Support\ExternalMessaging::SUPPRESSED_MESSAGE, $r['message']);
    }

    // ── what the provider can answer ────────────────────────────────────────

    public function test_a_rejection_shows_fast2smss_reason_without_the_key_and_is_logged_as_failed(): void
    {
        $this->template();
        WhatsAppService::$transportOverride = fn () => [['status' => false, 'message' => 'Invalid template ID ' . self::WA_KEY], 400];

        $r = $this->send();

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('Invalid template ID', $r['message']);
        $this->assertStringNotContainsString(self::WA_KEY, $r['message']);
        $this->assertStringNotContainsString(self::WA_KEY, (string) $r['raw']);
        $this->assertDatabaseHas('email_logs', ['channel' => 'whatsapp', 'trigger_action' => 'visitor', 'status' => 'failed']);
    }

    public function test_an_invalid_key_is_a_failure(): void
    {
        $this->template();
        WhatsAppService::$transportOverride = fn () => [['status' => false, 'message' => 'Invalid Authorization key'], 401];

        $r = $this->send();

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('Invalid Authorization key', $r['message']);
    }

    public function test_a_timeout_is_a_failure_not_a_success(): void
    {
        $this->template();
        WhatsAppService::$transportOverride = fn () => [['transport_error' => 'Operation timed out after 20001 milliseconds'], 0];

        $r = $this->send();

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('WhatsApp service unavailable', $r['message']);
        $this->assertDatabaseHas('email_logs', ['channel' => 'whatsapp', 'status' => 'failed']);
    }

    public function test_a_malformed_reply_is_not_treated_as_sent(): void
    {
        $this->template();
        WhatsAppService::$transportOverride = fn () => [['raw' => '<html>Bad gateway</html>'], 200];

        $r = $this->send();

        $this->assertFalse($r['success'], 'HTTP 200 alone is not acceptance - the reply must say status: true');
        $this->assertDatabaseHas('email_logs', ['channel' => 'whatsapp', 'status' => 'failed']);
    }

    public function test_an_accepted_message_is_logged_as_sent_with_no_claim_of_delivery(): void
    {
        $this->template();

        $r = $this->send();

        $this->assertTrue($r['success']);
        $this->assertSame('wa1', $r['request_id']);
        $this->assertDatabaseHas('email_logs', ['channel' => 'whatsapp', 'trigger_action' => 'visitor', 'status' => 'sent', 'recipient_phone' => '9876543210']);
        $this->assertDatabaseMissing('email_logs', ['channel' => 'whatsapp', 'status' => 'delivered']);
    }

    // ── a template without a message_id still works by name and language ────

    public function test_a_template_synced_before_message_ids_falls_back_to_its_name_and_language(): void
    {
        $this->template(['message_id' => null, 'language' => 'en_GB']);
        WhatsAppService::$transportOverride = function (string $url, array $data, array $headers) {
            $this->requests[] = ['url' => $url, 'data' => $data, 'headers' => $headers];

            return [['messages' => [['id' => 'wamid.1']]], 200];
        };

        $this->assertTrue($this->send()['success']);

        $this->assertSame('https://www.fast2sms.com/dev/whatsapp/v26.0/1209719742234591/messages', $this->requests[0]['url']);
        $template = $this->requests[0]['data']['template'];
        $this->assertSame('visitor_arrived', $template['name']);
        $this->assertSame('en_GB', $template['language']['code'], "the template's own language wins");
        $this->assertSame(['Asha Rao', 'Ravi'], array_column($template['components'][0]['parameters'], 'text'));
        $this->assertSame('919876543210', $this->requests[0]['data']['to']);
    }
}
