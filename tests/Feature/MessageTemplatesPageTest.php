<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\SmsService;
use App\Services\WhatsAppTemplateSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Message Templates: one catalogue of SMS, WhatsApp and Email templates with channel/purpose filters, provider
 * identifier, approval status, variables, current usage and on/off - and the only place the template catalogue and the
 * "which template for which notification" mapping appear. Settings -> SMS and Settings -> WhatsApp keep API
 * configuration, connection checks and tests, and link here. Reading the page never contacts a provider.
 */
class MessageTemplatesPageTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $this->admin = $this->makeUserWithRole($this->a, 'Admin');
    }

    protected function tearDown(): void
    {
        Fast2SmsAccount::$transportOverride = null;
        SmsService::$transportOverride = null;
        WhatsAppTemplateSyncService::$transportOverride = null;
        parent::tearDown();
    }

    private function sms(string $key, $value, string $type = 'string', ?Society $s = null): void
    {
        SystemSetting::set('sms', $key, $value, $type, ($s ?? $this->a)->id);
    }

    private function wa(array $over = [], ?Society $s = null): WhatsAppTemplate
    {
        return WhatsAppTemplate::create($over + ['society_id' => ($s ?? $this->a)->id, 'external_id' => 'ext-' . uniqid(), 'name' => 'tpl_' . uniqid(), 'status' => 'APPROVED', 'language' => 'en', 'category' => 'UTILITY', 'var_count' => 2, 'body_text' => 'Hello {{1}}, {{2}}']);
    }

    private function page(array $query = [], $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->get(route('admin.message-templates.index', $query));
    }

    // ── access ──────────────────────────────────────────────────────────────

    public function test_only_admins_reach_the_page(): void
    {
        $this->page()->assertOk()->assertSee('Message Templates');
        $this->page([], $this->makeUserWithRole($this->a, 'Tenant'))->assertForbidden();
        $this->page([], $this->makeUserWithRole($this->a, 'Staff'))->assertForbidden();
        $this->page([], $this->makeUserWithRole($this->a, 'Guard'))->assertForbidden();
        auth()->logout();
        $this->get(route('admin.message-templates.index'))->assertRedirect();
    }

    public function test_a_super_admin_sees_the_platform_level_view_without_error(): void
    {
        $super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);
        $this->wa(['name' => 'alpha_only'], $this->a);

        $r = $this->page([], $super)->assertOk();

        $r->assertDontSee('alpha_only');
    }

    // ── SMS rows: the OTP and notification identifiers stay apart ───────────

    public function test_sms_rows_show_the_otp_id_and_the_notification_message_id_separately(): void
    {
        $this->sms('fast2sms_api_key', 'k', 'encrypted');
        $this->sms('fast2sms_route', 'otp');
        $this->sms('fast2sms_otp_id', 'OTPID-111');
        $this->sms('fast2sms_notification_template_id', '182002');

        $html = $this->page(['channel' => 'sms'])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/OTP ID.{0,200}OTPID-111/s', $html);
        $this->assertMatchesRegularExpression('/Message ID.{0,200}182002/s', $html);
        $this->assertStringNotContainsString('182002', substr($html, strpos($html, 'Login OTP'), 900), 'the notification Message ID is not on the OTP row');
    }

    public function test_an_otp_template_is_never_shown_as_the_notification_template(): void
    {
        $this->sms('fast2sms_api_key', 'k', 'encrypted');
        $this->sms('fast2sms_route', 'dlt');
        $this->sms('fast2sms_template_id', '182001');           // OTP only

        $page = $this->page(['channel' => 'sms'])->assertOk();

        $page->assertSee('182001');
        $this->assertSame(1, substr_count($page->getContent(), 'Not set'), 'only the notification row is "Not set"');
    }

    public function test_a_telecom_dlt_template_id_saved_as_a_message_id_is_flagged(): void
    {
        $this->sms('fast2sms_api_key', 'k', 'encrypted');
        $this->sms('fast2sms_route', 'dlt');
        $this->sms('fast2sms_template_id', '1707168880000012345');

        $this->page(['channel' => 'sms'])->assertOk()->assertSee('Wrong ID type');
    }

    public function test_no_key_is_reported_as_such(): void
    {
        $this->page(['channel' => 'sms'])->assertOk()->assertSee('API key missing');
    }

    // ── WhatsApp ────────────────────────────────────────────────────────────

    public function test_whatsapp_rows_show_status_language_variables_usage_and_the_unmapped_warning(): void
    {
        $this->wa(['name' => 'visitor_ok', 'friendly_name' => 'Visitor approved', 'language' => 'en_US', 'var_count' => 3]);
        $this->wa(['name' => 'old_one', 'status' => 'REJECTED']);
        SystemSetting::set('whatsapp', 'whatsapp_visitor_template_name', 'visitor_ok', 'string', $this->a->id);

        $r = $this->page(['channel' => 'whatsapp'])->assertOk();

        $r->assertSee('Visitor approved')->assertSee('en_US')->assertSee('Rejected')->assertSee('No WhatsApp template is chosen for');
        $html = $r->getContent();
        $row = substr($html, strpos($html, 'Visitor approved'), 3000);
        $this->assertStringContainsString('Visitor approved', $row);
        $this->assertMatchesRegularExpression('#<td class="px-3 py-3 text-gray-700">\s*3\s*</td>#', $row, 'variables column');
    }

    public function test_whatsapp_templates_of_another_society_are_not_listed(): void
    {
        $this->wa(['name' => 'mine_tpl', 'friendly_name' => 'Mine Visible'], $this->a);
        $this->wa(['name' => 'theirs_tpl', 'friendly_name' => 'Theirs Hidden'], $this->b);

        $this->page(['channel' => 'whatsapp'])->assertOk()->assertSee('Mine Visible')->assertDontSee('Theirs Hidden');
    }

    public function test_the_whatsapp_catalog_and_mapping_live_here_not_on_the_settings_page(): void
    {
        $this->wa(['name' => 'visitor_ok', 'friendly_name' => 'Visitor approved']);

        $settings = $this->actingAs($this->admin)->get('/settings?tab=whatsapp')->assertOk();
        $settings->assertDontSee('Notifications on WhatsApp')->assertDontSee('Use this template')->assertSee('Manage templates');
        $settings->assertSee(route('admin.message-templates.index', ['channel' => 'whatsapp']), false);

        $this->page(['channel' => 'whatsapp'])->assertOk()->assertSee('Use a template')->assertSee('Sync approved templates');
    }

    public function test_the_sms_template_picker_lives_here_and_the_settings_page_only_links_to_it(): void
    {
        $settings = $this->actingAs($this->admin)->get('/settings?tab=sms')->assertOk();
        $settings->assertDontSee('Use for notifications')->assertDontSee('Fetch from Fast2SMS')->assertSee('Manage approved templates');
        $settings->assertSee(route('admin.message-templates.index', ['channel' => 'sms']), false);

        $this->page(['channel' => 'sms'])->assertOk()->assertSee('Use for OTP')->assertSee('Use for notifications', false)->assertSee('Fetch from Fast2SMS');
    }

    // ── Email ───────────────────────────────────────────────────────────────

    public function test_email_templates_show_society_and_system_rows_but_not_another_societys(): void
    {
        EmailTemplate::create(['society_id' => $this->a->id, 'name' => 'Alpha welcome', 'slug' => 'alpha-welcome-' . uniqid(), 'subject' => 'Welcome', 'body' => 'x', 'type' => 'custom', 'status' => 'active', 'trigger_event' => 'user_registered']);
        EmailTemplate::create(['society_id' => $this->b->id, 'name' => 'Beta private template', 'slug' => 'beta-priv-' . uniqid(), 'subject' => 'S', 'body' => 'x', 'type' => 'custom', 'status' => 'active']);
        EmailTemplate::create(['society_id' => null, 'name' => 'Platform default', 'slug' => 'platform-default-' . uniqid(), 'subject' => 'S', 'body' => 'x', 'type' => 'system', 'status' => 'active']);

        $this->page(['channel' => 'email'])->assertOk()
            ->assertSee('Alpha welcome')->assertSee('Your template')
            ->assertSee('Platform default')->assertSee('System template')
            ->assertDontSee('Beta private template');
    }

    // ── filters ─────────────────────────────────────────────────────────────

    public function test_channel_and_purpose_filters_narrow_the_catalogue(): void
    {
        $this->wa(['name' => 'wa_one']);
        EmailTemplate::create(['society_id' => $this->a->id, 'name' => 'Notice mail', 'slug' => 'notice-mail-' . uniqid(), 'subject' => 'S', 'body' => 'x', 'type' => 'custom', 'status' => 'active', 'trigger_event' => 'notice_published']);

        $sms = $this->page(['channel' => 'sms'])->assertOk()->getContent();
        $this->assertStringNotContainsString('data-channel="whatsapp"', $sms);
        $this->assertStringNotContainsString('data-channel="email"', $sms);
        $this->assertStringContainsString('data-channel="sms"', $sms);

        $notices = $this->page(['purpose' => 'notices'])->assertOk()->getContent();
        $this->assertStringContainsString('Notice mail', $notices);
        $this->assertStringNotContainsString('Login OTP', $notices);

        // the only SMS templates are OTP and notices, so a bills filter on SMS is empty (system email templates do cover bills)
        $this->page(['purpose' => 'bills', 'channel' => 'sms'])->assertOk()->assertSee('No templates match this filter.');
        $this->page(['channel' => 'bogus', 'purpose' => 'bogus'])->assertOk()->assertSee('Message Templates');
    }

    // ── safety ──────────────────────────────────────────────────────────────

    public function test_secrets_never_reach_the_page_and_reading_it_contacts_no_provider(): void
    {
        config(['messaging.enabled' => true]);
        $called = false;
        $stub = function () use (&$called) { $called = true; return [['return' => true], 200]; };
        Fast2SmsAccount::$transportOverride = $stub;
        SmsService::$transportOverride = $stub;
        WhatsAppTemplateSyncService::$transportOverride = $stub;
        $this->sms('fast2sms_api_key', 'super-secret-sms-key', 'encrypted');
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'super-secret-wa-key', 'encrypted', $this->a->id);

        $html = $this->page()->assertOk()->getContent();

        $this->assertStringNotContainsString('super-secret-sms-key', $html);
        $this->assertStringNotContainsString('super-secret-wa-key', $html);
        $this->assertFalse($called, 'viewing the catalogue makes no provider request');
    }
}
