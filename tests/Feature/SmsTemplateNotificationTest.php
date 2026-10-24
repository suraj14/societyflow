<?php

namespace Tests\Feature;

use App\Models\AccessPass;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The SMS templates managed in Message Templates drive the real notifications: the named values each event supplies
 * (App\Support\TemplateVariables) are filled in the order they appear in the wording, the template's Message ID (or the
 * default) and the Sender ID decide DLT vs Quick SMS, and a message with a missing value or an incomplete DLT setup is
 * refused and logged - never sent with a placeholder or silently downgraded. The transport is a mock.
 */
class SmsTemplateNotificationTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        $this->society = $this->makeSociety('Alpha Heights');
        $this->set('sms_enabled', true, 'boolean');
        $this->set('notification_sms_enabled', true, 'boolean');
        $this->set('fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted');
        SmsService::$transportOverride = function (string $url, array $data, array $headers) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->requests[] = ['q' => $q, 'path' => parse_url($url, PHP_URL_PATH)];

            return [['return' => true, 'request_id' => 'rq' . count($this->requests), 'message' => ['SMS sent successfully.']], 200];
        };
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

    private function template(string $purpose, string $body, ?string $messageId = '223577', array $over = []): SmsTemplate
    {
        return SmsTemplate::create($over + ['society_id' => $this->society->id, 'name' => ucfirst($purpose) . ' template', 'purpose' => $purpose, 'audience' => 'resident',
            'body' => $body, 'dlt_message_id' => $messageId, 'is_active' => true]);
    }

    private function admin()
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_notices', 'guard_name' => 'web']);
        $admin = $this->makeUserWithRole($this->society, 'Admin', ['phone' => null]);
        $admin->givePermissionTo('manage_notices');

        return $admin;
    }

    private function publishNotice(string $title = 'Water Supply Interruption'): void
    {
        $this->actingAs($this->admin())->post('/notices', ['title' => $title, 'content' => 'Water will be shut off 10am-2pm.', 'type' => 'general', 'priority' => 'medium', 'status' => 'published', 'send_email' => 1, 'send_sms' => 1])->assertRedirect();   // the boxes now decide the channels (NoticeChannelChoiceAndResendTest)
    }

    private function sentTo(string $phone): array
    {
        return array_values(array_filter($this->requests, fn ($r) => ($r['q']['numbers'] ?? null) === $phone));
    }

    // ── notice published ────────────────────────────────────────────────────

    public function test_a_published_notice_fills_the_templates_variables_from_the_real_event_in_wording_order(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('notice', 'Hi {{resident_name}}, {{notice_title}} - {{society_name}}');
        $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Tara Tenant', 'phone' => '9876543210']);

        $this->publishNotice();

        $sent = $this->sentTo('9876543210');
        $this->assertCount(1, $sent);
        $this->assertSame('/dev/bulkV2', $sent[0]['path']);
        $this->assertSame(['route' => 'dlt', 'sender_id' => 'BOOKMZ', 'message' => '223577', 'numbers' => '9876543210', 'variables_values' => 'Tara Tenant|Water Supply Interruption|Alpha Heights'], $sent[0]['q']);
        $this->assertDatabaseHas('email_logs', ['channel' => 'sms', 'recipient_phone' => '9876543210', 'trigger_action' => 'notification', 'status' => 'sent']);
    }

    public function test_a_template_without_any_message_id_goes_out_as_quick_sms_with_the_rendered_text(): void
    {
        $this->template('notice', 'Hi {{resident_name}}, {{notice_title}} - {{society_name}}', null);
        $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Tara Tenant', 'phone' => '9876543210']);

        $this->publishNotice();

        $sent = $this->sentTo('9876543210');
        $this->assertSame(['route' => 'q', 'message' => 'Hi Tara Tenant, Water Supply Interruption - Alpha Heights', 'numbers' => '9876543210'], $sent[0]['q']);
    }

    public function test_the_default_message_id_fills_in_for_a_template_that_has_none(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->set('fast2sms_template_id', '445566');
        $this->template('notice', '{{notice_title}}', null);
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->publishNotice();

        $this->assertSame('445566', $this->sentTo('9876543210')[0]['q']['message']);
    }

    public function test_the_templates_own_message_id_beats_the_default(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->set('fast2sms_template_id', '445566');
        $this->template('notice', '{{notice_title}}', '778899');
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->publishNotice();

        $this->assertSame('778899', $this->sentTo('9876543210')[0]['q']['message']);
    }

    public function test_a_message_id_without_a_sender_is_refused_and_logged_never_sent_as_quick_sms(): void
    {
        $this->template('notice', '{{notice_title}}', '223577');   // no Sender ID saved
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->publishNotice();

        $this->assertSame([], $this->requests, 'nothing went out - not even as Quick SMS');
        $this->assertDatabaseHas('email_logs', ['channel' => 'sms', 'recipient_phone' => '9876543210', 'status' => 'failed']);
        $this->assertStringContainsString('no Sender ID', \App\Models\EmailLog::where('recipient_phone', '9876543210')->value('error_message'));
    }

    public function test_an_inactive_template_is_ignored(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('notice', '{{notice_title}}', '223577', ['is_active' => false]);
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->publishNotice();

        $this->assertSame([], $this->requests, 'no active template and no legacy notification template: nothing is sent');
    }

    public function test_only_the_societys_own_template_is_used(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $other = $this->makeSociety('Beta');
        SmsTemplate::create(['society_id' => $other->id, 'name' => 'Theirs', 'purpose' => 'notice', 'audience' => 'resident', 'body' => 'THEIRS {{notice_title}}', 'dlt_message_id' => '999999', 'is_active' => true]);
        $this->template('notice', 'Ours {{notice_title}}', '223577');
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->publishNotice();

        $this->assertSame('223577', $this->sentTo('9876543210')[0]['q']['message']);
    }

    public function test_turning_the_integration_off_stops_notifications_too_and_keeps_their_settings(): void
    {
        $this->set('sms_enabled', false, 'boolean');             // the master "Integration active" switch
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('notice', '{{notice_title}}');
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->publishNotice();

        $this->assertSame([], $this->requests, 'integration off means nothing is sent, even with notifications switched on');
        $this->assertTrue((bool) SystemSetting::get('sms', 'notification_sms_enabled', null, $this->society->id), 'the notification choice is kept');
        $this->assertSame(1, SmsTemplate::count());
    }

    public function test_notifications_stay_off_until_the_sms_notification_switch_is_on(): void
    {
        $this->set('notification_sms_enabled', false, 'boolean');
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('notice', '{{notice_title}}');
        $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);

        $this->publishNotice();

        $this->assertSame([], $this->requests);
    }

    // ── a missing value is never sent as a placeholder ──────────────────────

    public function test_a_missing_value_refuses_the_message_instead_of_sending_a_blank_or_a_placeholder(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('notice', '{{notice_title}} {{resident_name}}');

        $result = SmsService::sendDltNotification('notification', '9876543210', 'legacy', $this->society->id, false, 'notice', ['notice_title' => 'Water cut']);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_variables', $result['error_code']);
        $this->assertStringContainsString('{{resident_name}}', $result['message']);
        $this->assertSame([], $this->requests);
        $this->assertDatabaseHas('email_logs', ['channel' => 'sms', 'recipient_phone' => '9876543210', 'status' => 'failed']);
    }

    // ── gate pass revoked ───────────────────────────────────────────────────

    public function test_revoking_a_gate_pass_uses_the_gate_pass_template(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('gate_pass', 'Hi {{resident_name}}: pass for {{person_name}} was {{pass_status}} ({{society_name}})', '300300');
        $resident = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Rita Resident', 'phone' => '9988776655']);
        $admin = $this->makeUserWithRole($this->society, 'Admin', ['phone' => null]);
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society));
        $pass = AccessPass::create(['society_id' => $this->society->id, 'flat_id' => $flat->id, 'person_name' => 'Ramesh Kumar', 'person_phone' => '9123456780', 'pass_type' => 'recurring_staff',
            'access_scope' => 'property', 'token' => AccessPass::generateToken(), 'allowed_days' => [1, 2, 3], 'valid_from' => now()->toDateString(), 'status' => 'active', 'created_by' => $resident->id]);

        $this->actingAs($admin)->post(route('access-passes.revoke', $pass))->assertRedirect();

        $sent = $this->sentTo('9988776655');
        $this->assertCount(1, $sent);
        $this->assertSame('300300', $sent[0]['q']['message']);
        $this->assertSame('Rita Resident|Ramesh Kumar|revoked|Alpha Heights', $sent[0]['q']['variables_values']);
    }

    // ── society document published ──────────────────────────────────────────

    public function test_an_announced_document_uses_the_document_template(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('document', '{{document_title}} {{document_version}} is published - {{society_name}}', '400400');
        $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Tara Tenant', 'phone' => '9876543210']);
        $document = new \App\Models\SocietyDocument(['society_id' => $this->society->id, 'title' => 'Parking Rules', 'version' => 'v2', 'visible_to_guards' => false]);
        $document->setRelation('society', $this->society);

        (new \App\Listeners\SendSocietyDocumentPublishedSms())->handle(new \App\Events\SocietyDocumentPublished($document, ['sms']));

        $sent = $this->sentTo('9876543210');
        $this->assertCount(1, $sent);
        $this->assertSame('400400', $sent[0]['q']['message']);
        $this->assertSame('Parking Rules|v2|Alpha Heights', $sent[0]['q']['variables_values']);
    }

    // ── login OTP ───────────────────────────────────────────────────────────

    public function test_a_login_otp_template_fills_the_code_and_the_society_name(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('login_otp', '{{otp}} is your {{society_name}} login code', '223577');

        $result = SmsService::sendOtp('98765 43210', '4821', $this->society->id);

        $this->assertTrue($result['success']);
        $this->assertSame(['route' => 'dlt', 'sender_id' => 'BOOKMZ', 'message' => '223577', 'numbers' => '9876543210', 'variables_values' => '4821|Alpha Heights'], $this->requests[0]['q']);
    }

    public function test_a_login_otp_without_a_template_uses_the_default_message_id_with_just_the_code(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->set('fast2sms_template_id', '223577');

        SmsService::sendOtp('9876543210', '4821', $this->society->id);

        $this->assertSame(['route' => 'dlt', 'sender_id' => 'BOOKMZ', 'message' => '223577', 'numbers' => '9876543210', 'variables_values' => '4821'], $this->requests[0]['q']);
    }

    public function test_the_otp_never_reaches_the_log_or_the_delivery_record(): void
    {
        $this->set('fast2sms_sender_id', 'BOOKMZ');
        $this->template('login_otp', '{{otp}} is your login code', '223577');
        $logged = [];
        \Illuminate\Support\Facades\Log::shouldReceive('info', 'warning', 'error', 'notice', 'debug')->andReturnUsing(function ($msg, $ctx = []) use (&$logged) { $logged[] = $msg . ' ' . json_encode($ctx); });

        SmsService::sendOtp('9876543210', '482913', $this->society->id);

        $this->assertStringNotContainsString('482913', implode("\n", $logged));
        $this->assertStringNotContainsString('TEST-KEY-1234567890', implode("\n", $logged));
        $this->assertSame(0, \App\Models\EmailLog::where('error_message', 'like', '%482913%')->count());
    }
}
