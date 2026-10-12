<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\SmsService;
use App\Support\ChannelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings -> SMS -> "Send Test SMS": one request built from the SAVED settings, and an answer that says which of
 * the distinct things happened - switched off, not configured, incomplete DLT setup, provider rejection, network
 * failure, or "Fast2SMS accepted it" (never "delivered"). The provider is a mock; nothing here sends an SMS.
 */
class SmsTestSendTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'unit-test-key-0123456789abcdef';

    private $society;
    private $admin;
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->set('sms_enabled', true, 'boolean');
        $this->set('fast2sms_api_key', self::KEY, 'encrypted');
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function set(string $key, $value, string $type = 'string'): void
    {
        SystemSetting::set('sms', $key, $value, $type, $this->society->id);
    }

    private function respond(array $body, int $status = 200): void
    {
        SmsService::$transportOverride = function (string $url, array $data, array $headers) use ($body, $status) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->requests[] = ['q' => $q, 'headers' => $headers, 'path' => parse_url($url, PHP_URL_PATH)];

            return [$body, $status];
        };
    }

    private function test(array $over = [], $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->postJson(route('settings.sms.test'), $over + ['phone' => '9876543210', 'message' => 'Test message from SocietyFlow']);
    }

    // ── not attempted ───────────────────────────────────────────────────────

    public function test_a_switched_off_integration_says_so_and_sends_nothing(): void
    {
        $this->set('sms_enabled', false, 'boolean');
        $this->respond(['return' => true]);

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertTrue($r->json('skipped'));
        $this->assertStringContainsString('turned off', $r->json('message'));
        $this->assertSame([], $this->requests);
    }

    public function test_without_an_api_key_it_says_the_key_is_missing(): void
    {
        $this->set('fast2sms_api_key', '', 'encrypted');
        $this->respond(['return' => true]);

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('API key is not saved', $r->json('message'));
        $this->assertSame([], $this->requests);
    }

    public function test_with_messaging_switched_off_on_the_server_nothing_is_sent(): void
    {
        $this->assertFalse(\App\Support\ExternalMessaging::enabled(), 'the suite runs with the kill switch on');
        // no mock: the real transport must stop at the kill switch, before any request exists

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertTrue($r->json('skipped'));
        $this->assertStringContainsString('switched off on this server', $r->json('message'));
        $this->assertStringContainsString('no message was sent', $r->json('message'));
        $this->assertNull(ChannelStatus::get('sms', $this->society->id), 'a skipped test records no result');
    }

    // ── accepted ────────────────────────────────────────────────────────────

    public function test_with_a_sender_and_message_id_the_text_fills_the_dlt_template_and_the_reply_is_shown(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->set('fast2sms_template_id', '223577');
        $this->respond(['return' => true, 'request_id' => 'SAdkkjmXInIlYHY', 'message' => ['SMS sent successfully.']]);

        $r = $this->test(['phone' => '+91 98765 43210', 'message' => 'Asha|Block A'])->assertOk();

        $this->assertTrue($r->json('success'));
        $this->assertSame(['route' => 'dlt', 'sender_id' => 'BOOKMZ', 'message' => '223577', 'numbers' => '9876543210', 'variables_values' => 'Asha|Block A'], $this->requests[0]['q']);
        $this->assertSame('/dev/bulkV2', $this->requests[0]['path']);
        $this->assertSame(['authorization: ' . self::KEY], $this->requests[0]['headers']);
        $this->assertMatchesRegularExpression('/Fast2SMS accepted the test SMS for \*+3210\./', $r->json('message'), 'only the last four digits are shown');
        $this->assertStringContainsString('not a delivery confirmation', $r->json('message'));
        $this->assertStringNotContainsStringIgnoringCase('delivered.', $r->json('message'));
        $this->assertStringContainsString('SAdkkjmXInIlYHY', (string) $r->json('raw'), "Fast2SMS's own reply is shown");
        $this->assertTrue(ChannelStatus::get('sms', $this->society->id)['ok']);
        $this->assertStringNotContainsString(self::KEY, $r->getContent());
    }

    public function test_without_a_message_id_it_goes_out_as_quick_sms_with_the_typed_text(): void
    {
        $this->respond(['return' => true, 'request_id' => 'q1', 'message' => ['SMS sent successfully.']]);

        $r = $this->test(['message' => 'Hello from SocietyFlow'])->assertOk();

        $this->assertTrue($r->json('success'));
        $this->assertSame(['route' => 'q', 'message' => 'Hello from SocietyFlow', 'numbers' => '9876543210'], $this->requests[0]['q']);
    }

    // ── an incomplete or invalid DLT setup is reported, never sent as Quick SMS ──

    public function test_a_message_id_without_a_sender_id_is_refused_before_any_request(): void
    {
        $this->set('fast2sms_template_id', '223577');
        $this->respond(['return' => true]);

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('no Sender ID', $r->json('message'));
        $this->assertSame([], $this->requests, 'not even as Quick SMS');
    }

    public function test_a_dlt_template_id_saved_as_the_message_id_is_refused(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->set('fast2sms_template_id', '1707168880000012345');
        $this->respond(['return' => true]);

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('DLT Template ID', $r->json('message'));
        $this->assertSame([], $this->requests);
    }

    public function test_an_invalid_number_is_refused_with_a_plain_reason(): void
    {
        $this->respond(['return' => true]);

        $r = $this->test(['phone' => 'abcdefghij'])->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('valid 10-digit', $r->json('message'));
        $this->assertSame([], $this->requests);
    }

    public function test_the_request_itself_is_validated(): void
    {
        $this->test(['phone' => '123'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->test(['message' => str_repeat('x', 321)])->assertStatus(422)->assertJsonValidationErrors('message');
        $this->assertSame([], $this->requests);
    }

    // ── a provider rejection, a network failure, a malformed answer ─────────

    public function test_a_rejected_key_is_explained_and_fast2smss_words_are_kept(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->respond(['return' => false, 'status_code' => 412, 'message' => 'Invalid Authentication, Check Authorization Key'], 401);

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('Fast2SMS did not accept the API key', $r->json('message'));
        $this->assertStringContainsString('Invalid Authentication', $r->json('details'));
        $this->assertStringContainsString('Invalid Authentication', (string) $r->json('raw'));
        $status = ChannelStatus::get('sms', $this->society->id);
        $this->assertFalse($status['ok']);
        $this->assertStringContainsString('Invalid Authentication', $status['detail']);
        $this->assertStringNotContainsString(self::KEY, $r->getContent());
    }

    public function test_another_provider_rejection_is_shown_in_plain_words(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->set('fast2sms_template_id', '223577');
        $this->respond(['return' => false, 'status_code' => 400, 'message' => ['Invalid Sender ID']], 400);

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('sender ID', $r->json('message'));
        $this->assertStringContainsString('Invalid Sender ID', $r->json('details'));
    }

    public function test_a_timeout_says_it_could_not_reach_fast2sms(): void
    {
        $this->respond(['transport_error' => 'Operation timed out after 20001 milliseconds'], 0);

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertStringContainsString('Could not reach Fast2SMS', $r->json('message'));
        $this->assertFalse(ChannelStatus::get('sms', $this->society->id)['ok']);
    }

    public function test_a_malformed_reply_is_a_failure_not_a_success(): void
    {
        $this->respond(['raw' => '<html>Bad gateway</html>'], 200);

        $r = $this->test()->assertOk();

        $this->assertFalse($r->json('success'), 'HTTP 200 alone is not acceptance');
        $this->assertFalse(ChannelStatus::get('sms', $this->society->id)['ok']);
    }

    // ── who may test, and with whose settings ───────────────────────────────

    public function test_another_societys_admin_uses_only_their_own_settings(): void
    {
        $other = $this->makeUserWithRole($this->makeSociety('Beta'), 'Admin');
        $this->respond(['return' => true]);

        $r = $this->test([], $other)->assertOk();

        $this->assertFalse($r->json('success'), "Beta has no key of its own; Alpha's is never used");
        $this->assertSame([], $this->requests);
    }

    public function test_residents_and_staff_cannot_send_a_test(): void
    {
        foreach (['Tenant', 'Staff', 'Guard'] as $role) {
            $this->test([], $this->makeUserWithRole($this->society, $role))->assertForbidden();
        }
        $this->assertSame([], $this->requests);
    }
}
