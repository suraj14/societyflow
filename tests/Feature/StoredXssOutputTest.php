<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Text that people type (a resident's own name, an event, an e-mail template) is shown to other people - often an admin - so it must reach
 * the page as text. Each case here put it into the page as markup or as an unescaped value inside a <script> block.
 */
class StoredXssOutputTest extends TestCase
{
    use RefreshDatabase;

    private const BREAKOUT = '</script><script>alert(1)</script>';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($society, 'Admin');

        // the screens below are behind the Events switch and the manage_settings permission
        \App\Models\SystemSetting::set('features', 'enable_events', true, 'boolean', $society->id);
        $this->admin->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_settings', 'guard_name' => 'web']));
    }

    public function test_a_users_name_cannot_close_the_script_block_of_the_user_list(): void
    {
        $this->makeUserWithRole($this->admin->society, 'Tenant', ['name' => self::BREAKOUT]);

        $html = $this->actingAs($this->admin)->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(self::BREAKOUT, $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $escaped = trim(json_encode('</script>', JSON_HEX_TAG), '"');   // the form it takes inside the page's data
        $this->assertStringContainsString($escaped, $html, 'the name is still there, escaped inside the data');
    }

    public function test_a_building_or_villa_area_name_cannot_close_the_script_block_of_the_unit_pickers(): void
    {
        $society = $this->admin->society;
        $society->update(['property_type' => 'both']);
        $building = \App\Models\Building::create(['society_id' => $society->id, 'name' => self::BREAKOUT, 'total_floors' => 3]);
        $this->makeFlat($society, $building, '101');

        foreach ([route('admin.tenants.create'), route('residents.create')] as $url) {
            $response = $this->actingAs($this->admin)->get($url);
            if ($response->getStatusCode() !== 200) {
                continue;   // a screen this role cannot open shows nothing to attack
            }
            $html = $response->getContent();
            $this->assertStringNotContainsString(self::BREAKOUT, $html, $url);
            $this->assertStringNotContainsString('<script>alert(1)', $html, $url);
        }
    }

    public function test_an_email_template_body_is_never_printed_into_the_admin_page_as_html(): void
    {
        $template = EmailTemplate::create([
            'society_id' => $this->admin->society_id, 'name' => 'Welcome', 'slug' => 'welcome', 'subject' => 'Hello',
            'body' => '<p>Hi {{user_name}}</p><script>alert(1)</script><img src=x onerror=alert(2)>',
            'type' => 'system', 'status' => 'active', 'trigger_event' => 'user_registered',
        ]);
        $log = EmailLog::create(['email_template_id' => $template->id, 'society_id' => $this->admin->society_id, 'recipient_email' => 'asha@example.test',
            'trigger_action' => 'user_registered', 'channel' => 'email', 'status' => 'sent']);

        $preview = $this->actingAs($this->admin)->get(route('admin.email-templates.show', $template))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $preview);
        $this->assertStringNotContainsString('<img src=x onerror', $preview);
        $this->assertStringContainsString('<iframe sandbox=""', $preview, 'the preview is shown in a frame that cannot run scripts');
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $preview, 'the body is carried as attribute text');

        $logPage = $this->actingAs($this->admin)->get(route('admin.email-logs.show', $log))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $logPage);
        $this->assertStringNotContainsString('onerror=alert(2)', $logPage);
        $this->assertStringContainsString('Hi {{user_name}}', $logPage, 'the readable text is still shown');
    }

    public function test_the_event_calendar_escapes_event_text_before_putting_it_into_markup(): void
    {
        $html = $this->actingAs($this->admin)->get(route('events.calendar'))->assertOk()->getContent();

        foreach (['event.event_name', 'event.location', 'event.description', 'event.status'] as $field) {
            $this->assertStringNotContainsString('${' . $field . '}', $html, "{$field} is interpolated into markup unescaped");
            $this->assertStringContainsString('${esc(' . $field . ')}', $html);
        }
    }

    public function test_the_template_picker_builds_names_as_text_and_has_no_inline_handler_to_break_out_of(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.email-template-mappings.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('${template.name}', $html);
        $this->assertStringNotContainsString("selectTemplate(\${template.id}, '", $html);
        $this->assertStringContainsString('textContent = template.name', $html);
    }
}
