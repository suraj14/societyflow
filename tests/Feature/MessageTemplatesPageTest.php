<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Message Templates: SMS | WhatsApp | Email, each tab loading the real records of the signed-in society. SMS cards show
 * name, type, recipient, how the message goes out (DLT Message ID / default / Quick SMS), the wording and its length,
 * on/off; WhatsApp cards show the provider-approved templates with the Fast2SMS message_id, language, variables and what
 * uses each; Email lists the existing templates and links to their editor. Settings -> SMS and Settings -> WhatsApp keep
 * the API configuration and the tests, and link here. Reading the page never contacts a provider.
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
        parent::tearDown();
    }

    private function sms(array $over = [], ?Society $s = null): SmsTemplate
    {
        return SmsTemplate::create($over + ['society_id' => ($s ?? $this->a)->id, 'name' => 'Notice alert', 'purpose' => 'notice', 'audience' => 'resident',
            'body' => 'Notice: {{notice_title}} - {{society_name}}', 'dlt_message_id' => '223577', 'is_active' => true]);
    }

    private function setting(string $key, $value, ?Society $s = null): void
    {
        SystemSetting::set('sms', $key, $value, 'string', ($s ?? $this->a)->id);
    }

    private function wa(array $over = [], ?Society $s = null): WhatsAppTemplate
    {
        return WhatsAppTemplate::create($over + ['society_id' => ($s ?? $this->a)->id, 'external_id' => 'ext-' . uniqid(), 'message_id' => (string) random_int(1000, 9999), 'name' => 'tpl_' . uniqid(),
            'status' => 'APPROVED', 'language' => 'en', 'category' => 'UTILITY', 'var_count' => 2, 'body_text' => 'Hello {{1}}, {{2}}']);
    }

    private function page(array $query = [], $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->get(route('admin.message-templates.index', $query));
    }

    // ── access ──────────────────────────────────────────────────────────────

    public function test_a_failed_save_re_renders_the_form_with_an_empty_string_body_not_null(): void
    {
        // The page script calls body.matchAll()/replace(): a null body (an emptied box comes back as null) threw in the browser
        // right after a rejected save and froze the counter and preview. Found by the browser run, invisible to server tests.
        $this->actingAs($this->admin)->from(route('admin.message-templates.sms.create'))
            ->post(route('admin.message-templates.sms.store'), ['name' => '', 'body' => '', 'purpose' => 'notice', 'audience' => 'resident'])
            ->assertSessionHasErrors(['name', 'body']);

        $html = $this->actingAs($this->admin)->withSession(['errors' => session('errors'), '_old_input' => ['name' => '', 'body' => null]])
            ->get(route('admin.message-templates.sms.create'))->assertOk()->getContent();

        $this->assertStringContainsString('\u0022body\u0022:\u0022\u0022', $html);
        $this->assertStringNotContainsString('\u0022body\u0022:null', $html);
    }

    public function test_only_admins_reach_the_page_and_the_sms_actions(): void
    {
        $this->page()->assertOk()->assertSee('Message Templates');
        $template = $this->sms();

        foreach (['Tenant', 'Staff', 'Guard'] as $role) {
            $user = $this->makeUserWithRole($this->a, $role);
            $this->page([], $user)->assertForbidden();
            $this->actingAs($user)->get(route('admin.message-templates.sms.create'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.message-templates.sms.store'), [])->assertForbidden();
            $this->actingAs($user)->get(route('admin.message-templates.sms.edit', $template))->assertForbidden();
            $this->actingAs($user)->put(route('admin.message-templates.sms.update', $template), [])->assertForbidden();
            $this->actingAs($user)->post(route('admin.message-templates.sms.toggle', $template))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.message-templates.sms.destroy', $template))->assertForbidden();
        }
        auth()->logout();
        $this->get(route('admin.message-templates.index'))->assertRedirect();
    }

    public function test_a_super_admin_sees_the_platform_level_view_without_error(): void
    {
        $super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);
        $this->sms(['name' => 'Alpha only']);
        $this->wa(['name' => 'alpha_only'], $this->a);

        foreach (['sms', 'whatsapp', 'email'] as $channel) {
            $r = $this->page(['channel' => $channel], $super)->assertOk();
            $r->assertDontSee('Alpha only')->assertDontSee('alpha_only');
        }
    }

    // ── tabs ────────────────────────────────────────────────────────────────

    public function test_sms_is_the_default_tab_and_every_tab_shows_its_count(): void
    {
        $this->sms();
        $this->sms(['name' => 'Second', 'purpose' => 'gate_pass']);
        $this->wa();

        $r = $this->page()->assertOk();
        $html = $r->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*SMS\s*<span[^>]*>2</', $html);
        $this->assertMatchesRegularExpression('/WhatsApp\s*<span[^>]*>1</', $html);
        $r->assertSee('New Template');
        $r->assertSee(route('admin.message-templates.sms.create'), false);

        // an unknown channel falls back to SMS rather than failing
        $this->page(['channel' => 'bogus'])->assertOk()->assertSee('Notice alert');
    }

    // ── SMS tab ─────────────────────────────────────────────────────────────

    public function test_sms_cards_show_the_name_type_recipient_route_wording_and_length(): void
    {
        $this->setting('fast2sms_sender_id', 'BOOKMZ');
        $this->sms(['name' => 'Notice alert', 'audience' => 'guard', 'body' => 'Notice: {{notice_title}}']);

        $html = $this->page(['channel' => 'sms'])->assertOk()->getContent();

        $this->assertStringContainsString('Notice alert', $html);
        $this->assertStringContainsString('Notice published', $html);
        $this->assertStringContainsString('Guard', $html);
        $this->assertStringContainsString('DLT: 223577', $html);
        $this->assertStringContainsString('Notice: {{notice_title}}', $html, 'the wording keeps its {{variable}} placeholders');
        $this->assertStringContainsString('24/320 characters', $html);
        $this->assertStringContainsString('1 variable', $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function test_the_route_label_follows_the_message_id_the_default_and_the_sender(): void
    {
        $this->sms(['name' => 'Own id', 'purpose' => 'notice', 'dlt_message_id' => '111111']);
        $this->sms(['name' => 'No id', 'purpose' => 'gate_pass', 'dlt_message_id' => null, 'body' => 'Pass for {{person_name}}']);

        // no sender yet: a Message ID without a sender is flagged, a template without one is Quick SMS
        $html = $this->page(['channel' => 'sms'])->getContent();
        $this->assertStringContainsString('DLT: 111111 - add a Sender ID', $html);
        $this->assertStringContainsString('Quick SMS', $html);

        $this->setting('fast2sms_sender_id', 'BOOKMZ');
        $this->setting('fast2sms_template_id', '223577');
        $html = $this->page(['channel' => 'sms'])->getContent();
        $this->assertStringContainsString('DLT: 111111', $html);
        $this->assertStringNotContainsString('add a Sender ID', $html);
        $this->assertStringContainsString('DLT: 223577 (default)', $html, 'the default only fills in for a template without its own ID');
    }

    public function test_one_short_line_names_the_types_without_an_active_template(): void
    {
        $this->sms(['purpose' => 'notice']);
        $this->sms(['purpose' => 'gate_pass', 'name' => 'Old pass', 'body' => 'Pass for {{person_name}}', 'is_active' => false]);

        $html = $this->page(['channel' => 'sms'])->getContent();

        $this->assertStringContainsString('No active SMS template for:', $html);
        $this->assertStringContainsString('Login OTP (codes go out as plain Quick SMS)', $html);
        $this->assertStringContainsString('Gate pass revoked (not sent by SMS)', $html);
        $this->assertStringContainsString('Society document published (not sent by SMS)', $html);
        $this->assertStringNotContainsString('Notice published (not sent by SMS)', $html, 'notices have an active template');
    }

    public function test_sms_templates_of_another_society_are_never_listed(): void
    {
        $this->sms(['name' => 'Mine visible']);
        $this->sms(['name' => 'Theirs hidden'], $this->b);

        $this->page(['channel' => 'sms'])->assertOk()->assertSee('Mine visible')->assertDontSee('Theirs hidden');
    }

    public function test_sms_search_and_filters_narrow_the_cards(): void
    {
        $this->sms(['name' => 'Water cut notice', 'purpose' => 'notice']);
        $this->sms(['name' => 'Pass revoked', 'purpose' => 'gate_pass', 'body' => 'Pass for {{person_name}}', 'is_active' => false]);

        $this->page(['channel' => 'sms', 'q' => 'water'])->assertSee('Water cut notice')->assertDontSee('Pass revoked');
        $this->page(['channel' => 'sms', 'q' => 'person_name'])->assertSee('Pass revoked')->assertDontSee('Water cut notice');
        $this->page(['channel' => 'sms', 'purpose' => 'gate_pass'])->assertSee('Pass revoked')->assertDontSee('Water cut notice');
        $this->page(['channel' => 'sms', 'status' => 'inactive'])->assertSee('Pass revoked')->assertDontSee('Water cut notice');
        $this->page(['channel' => 'sms', 'status' => 'active'])->assertSee('Water cut notice')->assertDontSee('Pass revoked');
        $this->page(['channel' => 'sms', 'q' => 'zzz-nothing'])->assertSee('No SMS templates match this filter.');
        $this->page(['channel' => 'sms', 'purpose' => 'bogus', 'status' => 'bogus'])->assertOk()->assertSee('Water cut notice');
    }

    public function test_the_empty_state_offers_a_new_template(): void
    {
        $this->page(['channel' => 'sms'])->assertOk()->assertSee('No SMS templates yet.')->assertSee(route('admin.message-templates.sms.create'), false);
    }

    // ── WhatsApp tab ────────────────────────────────────────────────────────

    public function test_whatsapp_templates_of_another_society_are_not_listed(): void
    {
        $this->wa(['name' => 'mine_tpl', 'friendly_name' => 'Mine Visible'], $this->a);
        $this->wa(['name' => 'theirs_tpl', 'friendly_name' => 'Theirs Hidden'], $this->b);

        $this->page(['channel' => 'whatsapp'])->assertOk()->assertSee('Mine Visible')->assertDontSee('Theirs Hidden');
    }

    // ── Email tab ───────────────────────────────────────────────────────────

    public function test_email_templates_show_society_and_system_rows_but_not_another_societys(): void
    {
        $mine = EmailTemplate::create(['society_id' => $this->a->id, 'name' => 'Alpha welcome', 'slug' => 'alpha-welcome-' . uniqid(), 'subject' => 'Welcome aboard', 'body' => 'x', 'type' => 'custom', 'status' => 'active', 'trigger_event' => 'user_registered']);
        EmailTemplate::create(['society_id' => $this->b->id, 'name' => 'Beta private template', 'slug' => 'beta-priv-' . uniqid(), 'subject' => 'S', 'body' => 'x', 'type' => 'custom', 'status' => 'active']);
        $system = EmailTemplate::create(['society_id' => null, 'name' => 'Platform default', 'slug' => 'platform-default-' . uniqid(), 'subject' => 'S', 'body' => 'x', 'type' => 'system', 'status' => 'active']);

        $r = $this->page(['channel' => 'email'])->assertOk();

        $r->assertSee('Alpha welcome')->assertSee('Your template')->assertSee('Welcome aboard')
            ->assertSee('Platform default')->assertSee('System template')
            ->assertDontSee('Beta private template');
        $r->assertSee(route('admin.email-templates.edit', $mine), false)->assertSee(route('admin.email-templates.show', $system), false);
        $r->assertSee('New Template')->assertSee(route('admin.email-templates.create'), false);
    }

    public function test_the_email_tab_search_narrows_the_list(): void
    {
        EmailTemplate::create(['society_id' => $this->a->id, 'name' => 'Pool rules mail', 'slug' => 'pool-' . uniqid(), 'subject' => 'Pool', 'body' => 'x', 'type' => 'custom', 'status' => 'active']);
        EmailTemplate::create(['society_id' => $this->a->id, 'name' => 'Gym rules mail', 'slug' => 'gym-' . uniqid(), 'subject' => 'Gym', 'body' => 'x', 'type' => 'custom', 'status' => 'active']);

        $this->page(['channel' => 'email', 'q' => 'pool'])->assertSee('Pool rules mail')->assertDontSee('Gym rules mail');
    }

    // ── settings pages only link here ───────────────────────────────────────

    public function test_the_sms_settings_page_only_links_to_the_templates(): void
    {
        $this->sms(['name' => 'Notice alert']);

        $settings = $this->actingAs($this->admin)->get('/settings?tab=sms')->assertOk();
        $settings->assertDontSee('Use for notifications')->assertDontSee('Fetch from Fast2SMS')->assertDontSee('Notice alert')->assertSee('Manage approved templates');
        $settings->assertSee(route('admin.message-templates.index', ['channel' => 'sms']), false);
    }

    // ── safety ──────────────────────────────────────────────────────────────

    public function test_secrets_never_reach_the_page_and_reading_it_contacts_no_provider(): void
    {
        config(['messaging.enabled' => true]);
        $called = false;
        $stub = function () use (&$called) { $called = true; return [['return' => true], 200]; };
        Fast2SmsAccount::$transportOverride = $stub;
        SmsService::$transportOverride = $stub;
        SystemSetting::set('sms', 'fast2sms_api_key', 'super-secret-sms-key', 'encrypted', $this->a->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'super-secret-wa-key', 'encrypted', $this->a->id);

        foreach (['sms', 'whatsapp', 'email'] as $channel) {
            $html = $this->page(['channel' => $channel])->assertOk()->getContent();
            $this->assertStringNotContainsString('super-secret-sms-key', $html);
            $this->assertStringNotContainsString('super-secret-wa-key', $html);
        }
        $html = $this->actingAs($this->admin)->get(route('admin.message-templates.sms.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('super-secret-sms-key', $html);
        $this->assertFalse($called, 'viewing the templates makes no provider request');
    }
}
