<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Settings -> Email -> "Send a test email": while the global safety switch is off the panel says so in plain words and does not repeat the
 * technical sentence ("External messaging is disabled in this environment (EXTERNAL_MESSAGING_ENABLED=false)"). The switch, the allow-list and
 * every delivery check are exactly as before: the server still refuses, still answers blocked, and nothing is sent.
 */
class EmailTestPanelBlockedMessageTest extends TestCase
{
    use RefreshDatabase;

    private const TECHNICAL = 'External messaging is disabled in this environment (EXTERNAL_MESSAGING_ENABLED=false)';

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $society = $this->makeSociety('Royal Eden');
        $this->admin = $this->makeUserWithRole($society, 'Admin');
        Permission::firstOrCreate(['name' => 'manage_settings', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('manage_settings');
    }

    public function test_the_email_test_panel_does_not_render_the_technical_sentence_and_shows_the_plain_one(): void
    {
        // the panel is the shared "channel-test" partial, rendered here exactly as Settings -> Email includes it (its script goes to the page's script stack)
        $panel = view('settings.partials.channel-test', [
            'title' => 'Send a test email', 'hint' => 'Uses your saved settings. Save first if you changed anything.', 'url' => route('settings.email.test'),
            'fields' => [['name' => 'test_email', 'type' => 'email', 'label' => 'Send to', 'value' => 'a@example.test', 'placeholder' => 'you@example.com']],
            'button' => 'Send Test Email', 'icon' => 'fa-paper-plane',
        ])->render();
        // the panel's script is pushed to the page's stack (and Blade drops the stack once a standalone render ends), so it is checked from its source
        $panel .= file_get_contents(resource_path('views/settings/partials/channel-test.blade.php'));

        $this->assertStringNotContainsString(self::TECHNICAL, $panel);
        $this->assertStringNotContainsString('EXTERNAL_MESSAGING_ENABLED=false', $panel, 'the panel never prints the setting and its value');
        $this->assertStringNotContainsString('Blocked by the safety switch - this is not a settings error', $panel, 'the old duplicated prefix is gone');
        $this->assertStringContainsString('Blocked by the safety switch — delivery is currently disabled.', $panel);
        $this->assertStringContainsString("data.reason === 'messaging_disabled'", $panel, 'keyed on the reason code, not on the server\'s sentence');
        $this->assertStringContainsString('Send a test email', $panel, 'the panel itself is unchanged');
    }

    public function test_the_settings_email_tab_still_shows_the_panel_and_its_own_text_has_no_technical_sentence(): void
    {
        $page = $this->actingAs($this->admin)->get(route('settings.index', ['tab' => 'email']))->assertOk()->getContent();

        $this->assertStringContainsString('Send a test email', $page);
        $this->assertStringContainsString('Send Test Email', $page);
        $this->assertStringNotContainsString(self::TECHNICAL, $page, 'the sentence the panel used to repeat is nowhere in the page');
    }

    public function test_the_server_still_blocks_and_says_so_machine_readably_and_sends_nothing(): void
    {
        Mail::fake();
        config(['messaging.enabled' => false]);

        $r = $this->actingAs($this->admin)->postJson(route('settings.email.test'), ['test_email' => 'someone@example.test'])->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertTrue($r->json('blocked'));
        $this->assertSame('messaging_disabled', $r->json('reason'));
        $this->assertStringContainsString('Not sent', $r->json('message'), 'the API answer is unchanged: only the screen no longer repeats it');
        $this->assertSame(0, EmailLog::count(), 'nothing was logged as sent');
        Mail::assertNothingSent();
    }

    public function test_the_allow_list_still_blocks_an_unlisted_address_when_delivery_is_on(): void
    {
        Mail::fake();
        config(['messaging.enabled' => true, 'messaging.allowlist' => 'tester@example.test']);

        $r = $this->actingAs($this->admin)->postJson(route('settings.email.test'), ['test_email' => 'resident@example.test'])->assertOk();

        $this->assertFalse($r->json('success'));
        $this->assertTrue($r->json('blocked'));
        $this->assertSame('not_allowlisted', $r->json('reason'));
        $this->assertSame(0, EmailLog::count());
        Mail::assertNothingSent();
    }

    public function test_validation_and_authorisation_of_the_test_action_are_unchanged(): void
    {
        $this->actingAs($this->admin)->postJson(route('settings.email.test'), ['test_email' => 'not-an-email'])->assertStatus(422);

        $resident = $this->makeUserWithRole($this->makeSociety('Other'), 'Tenant');
        $this->actingAs($resident)->postJson(route('settings.email.test'), ['test_email' => 'x@example.test'])->assertForbidden();
    }
}
