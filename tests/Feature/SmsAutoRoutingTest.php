<?php

namespace Tests\Feature;

use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Services\Fast2SmsService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SMS routing is automatic: a template with a Fast2SMS Message ID (or the default Message ID) plus a Sender ID goes on
 * the DLT route; anything else goes out as Quick SMS with the rendered text. Both are the documented GET /dev/bulkV2.
 * The transport is a mock - nothing here reaches Fast2SMS.
 */
class SmsAutoRoutingTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        SystemSetting::set('sms', 'fast2sms_api_key', 'test-key-not-real', 'encrypted', $this->society->id);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $this->society->id);
        SmsService::$transportOverride = function (string $url, array $data, array $headers) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->calls[] = ['url' => $url, 'q' => $q, 'headers' => $headers, 'path' => parse_url($url, PHP_URL_PATH)];

            return [['return' => true, 'request_id' => 'rq1', 'message' => ['Message sent successfully']], 200];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function set(string $key, $value): void
    {
        SystemSetting::set('sms', $key, $value, 'string', $this->society->id);
    }

    private function template(array $over = []): SmsTemplate
    {
        return SmsTemplate::create($over + ['society_id' => $this->society->id, 'name' => 'Login OTP', 'purpose' => 'login_otp', 'audience' => 'resident',
            'body' => 'Your OTP is {{otp}}. Valid for 5 minutes.', 'dlt_message_id' => '223577']);
    }

    public function test_a_template_with_a_message_id_and_a_sender_goes_on_the_dlt_route(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template();

        $r = SmsService::sendOtp('98765 43210', '4821', $this->society->id);

        $this->assertTrue($r['success']);
        $c = $this->calls[0];
        $this->assertSame('/dev/bulkV2', $c['path']);
        $this->assertSame(['route' => 'dlt', 'sender_id' => 'BOOKMZ', 'message' => '223577', 'numbers' => '9876543210', 'variables_values' => '4821'], $c['q']);
        $this->assertSame(['authorization: test-key-not-real'], $c['headers']);
    }

    public function test_variables_are_piped_in_the_order_they_appear_in_the_template(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $t = $this->template(['purpose' => 'new_bill', 'body' => 'Hi {{name}}, bill {{amount}} due {{due_date}}', 'dlt_message_id' => '300001']);

        $r = SmsService::sendByTemplate('new_bill', '9876543210', ['due_date' => '5 Nov', 'name' => 'Asha', 'amount' => '1,200'], $this->society->id, 'x');

        $this->assertTrue($r['success']);
        $this->assertSame('Asha|1,200|5 Nov', $this->calls[0]['q']['variables_values']);
        $this->assertSame('300001', $this->calls[0]['q']['message']);
        $this->assertSame(3, $t->variableCount());
    }

    public function test_a_template_without_a_message_id_is_sent_as_quick_sms_with_the_rendered_text(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template(['dlt_message_id' => null]);

        SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertSame(['route' => 'q', 'message' => 'Your OTP is 4821. Valid for 5 minutes.', 'numbers' => '9876543210'], $this->calls[0]['q']);
    }

    public function test_the_default_message_id_is_the_fallback_when_the_template_has_none(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->set('fast2sms_template_id', '223577');
        $this->template(['dlt_message_id' => null]);

        SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertSame('dlt', $this->calls[0]['q']['route']);
        $this->assertSame('223577', $this->calls[0]['q']['message']);
    }

    public function test_a_message_id_without_a_sender_id_is_refused_and_never_downgraded_to_quick_sms(): void
    {
        $this->template();                                   // Message ID 223577, but no Sender ID is saved

        $r = SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertFalse($r['success']);
        $this->assertSame('sender_missing', $r['error_code']);
        $this->assertStringContainsString('no Sender ID', $r['message']);
        $this->assertSame([], $this->calls, 'an incomplete DLT setup is reported, not sent as Quick SMS to look successful');
    }

    public function test_with_no_template_at_all_the_login_otp_still_goes_out_as_quick_sms(): void
    {
        SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertSame('q', $this->calls[0]['q']['route']);
        $this->assertStringContainsString('4821', $this->calls[0]['q']['message']);
    }

    public function test_the_advanced_otp_service_option_still_uses_the_otp_endpoint(): void
    {
        $this->set('fast2sms_route', 'otp');
        $this->set('fast2sms_otp_id', '6fd92db0ae');

        SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertSame('/dev/otp/send', $this->calls[0]['path']);
    }

    public function test_a_notification_uses_the_template_for_its_purpose_and_never_the_otp_one(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->set('notification_sms_enabled', true);
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society->id);
        $this->template();                                                       // OTP template, Message ID 223577
        $this->template(['name' => 'Notice', 'purpose' => 'notice', 'body' => '{{notice_title}}', 'dlt_message_id' => '223999']);

        SmsService::sendDltNotification('notification', '9876543210', 'legacy text', $this->society->id, false, 'notice', ['notice_title' => 'Water cut 2-4 PM']);

        $this->assertSame('223999', $this->calls[0]['q']['message']);
        $this->assertSame('Water cut 2-4 PM', $this->calls[0]['q']['variables_values']);
    }

    public function test_a_notification_with_no_template_is_not_sent(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society->id);
        $this->template();

        $r = SmsService::sendDltNotification('notification', '9876543210', 'Water cut', $this->society->id);

        $this->assertFalse($r['success']);
        $this->assertSame([], $this->calls, 'the OTP template is never reused for notices');
    }

    public function test_a_telecom_dlt_template_id_is_refused_before_any_request(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template(['dlt_message_id' => '1707168880000012345']);

        $r = SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('Message ID', $r['message']);
        $this->assertSame([], $this->calls);
    }

    public function test_the_kill_switch_stops_the_request_before_the_network(): void
    {
        config(['messaging.enabled' => false]);
        SmsService::$transportOverride = null;       // the real path: it must not reach cURL
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template();

        $r = SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertFalse($r['success']);
        $this->assertFalse($r['sent']);
        $this->assertSame(SmsService::STATUS_SKIPPED_DISABLED, $r['status']);
    }

    public function test_render_fills_named_and_numbered_placeholders_in_order(): void
    {
        $named = Fast2SmsService::render('Hi {{name}}, {{amount}}', ['amount' => '12', 'name' => 'Asha']);
        $this->assertSame(['Hi Asha, 12', ['Asha', '12'], []], [$named['text'], $named['variables'], $named['missing']]);
        $listed = Fast2SmsService::render('A={{1}} B={{2}}', ['x', 'y']);
        $this->assertSame(['A=x B=y', ['x', 'y'], []], [$listed['text'], $listed['variables'], $listed['missing']]);
        $this->assertSame('a/b|c', Fast2SmsService::pipe(['a|b', 'c']), 'a pipe inside a value would split it');
    }

    public function test_templates_are_scoped_to_their_society(): void
    {
        $other = $this->makeSociety('Beta');
        SmsTemplate::create(['society_id' => $other->id, 'name' => 'Theirs', 'purpose' => 'login_otp', 'audience' => 'resident', 'body' => 'THEIRS {{otp}}', 'dlt_message_id' => '999999']);
        $this->set('fast2sms_sender_id', 'BOOKMZ');

        SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertSame('q', $this->calls[0]['q']['route'], 'another society\'s template is never used');
        $this->assertStringNotContainsString('THEIRS', $this->calls[0]['q']['message']);
    }
}
