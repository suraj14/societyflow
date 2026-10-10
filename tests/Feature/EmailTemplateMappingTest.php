<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Settings > Email template mapping (which template serves which event). The update and destroy actions
 * explicitly allowed SYSTEM templates (society_id null) and then rewrote their trigger_event: one society's
 * Admin remapping or unmapping a template every society shares changed - or silently switched off - that
 * event's email platform-wide. Now: a society's own templates are theirs to remap; system templates only
 * a Super Admin may touch.
 */
class EmailTemplateMappingTest extends TestCase
{
    use RefreshDatabase;

    private Society $a;
    private Society $b;
    private User $adminA;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'manage_settings', 'guard_name' => 'web']);
        $this->a = $this->makeSociety('Alpha');
        $this->b = $this->makeSociety('Beta');
        $this->adminA = $this->makeUserWithRole($this->a, 'Admin');
        $this->adminA->givePermissionTo('manage_settings');
        $this->super = $this->makeUserWithRole($this->a, 'Super Admin', ['society_id' => null]);
    }

    private function template(?Society $s, string $slug, ?string $event = null): EmailTemplate
    {
        return EmailTemplate::create([
            'society_id' => $s?->id, 'name' => $slug, 'slug' => $slug, 'subject' => 'S {{user_name}}', 'body' => '<p>B</p>',
            'type' => 'custom', 'status' => 'active', 'trigger_event' => $event,
        ]);
    }

    private function system(string $event): EmailTemplate
    {
        return EmailTemplate::where('trigger_event', $event)->whereNull('society_id')->firstOrFail();
    }

    public function test_an_admin_can_map_and_unmap_their_own_societys_template(): void
    {
        $mine = $this->template($this->a, 'alpha-notice');

        $this->actingAs($this->adminA)->putJson(route('admin.email-template-mappings.update', 'notice_published'), ['template_id' => $mine->id])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('notice_published', $mine->fresh()->trigger_event);

        $this->actingAs($this->adminA)->deleteJson(route('admin.email-template-mappings.destroy', 'notice_published'))->assertOk();
        $this->assertNull($mine->fresh()->trigger_event);
    }

    public function test_a_societys_own_template_takes_precedence_over_the_system_one_for_that_society_only(): void
    {
        $system = $this->system('notice_published');
        $mine = $this->template($this->a, 'alpha-notice', 'notice_published');

        $this->assertSame($mine->id, EmailTemplate::getTemplateForEvent('notice_published', $this->a->id)->id, 'Alpha uses its own template');
        $this->assertSame($system->id, EmailTemplate::getTemplateForEvent('notice_published', $this->b->id)->id, 'Beta still uses the system template');
        $this->assertSame($system->id, EmailTemplate::getTemplateForEvent('notice_published', null)->id);
    }

    public function test_the_notification_job_really_sends_with_the_societys_own_template(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $mine = $this->template($this->a, 'alpha-notice', 'notice_published');

        (new \App\Jobs\DispatchEventNotification(
            'notice_published',
            ['email' => 'resident@example.invalid', 'name' => 'R', 'role' => 'Tenant'],
            ['user_name' => 'R', 'notice_title' => 'T', 'notice_content' => 'x', 'view_url' => '#'],
            $this->a->id,
            'mapping-precedence:1',
        ))->handle();

        $log = \App\Models\EmailLog::where('notification_key', 'mapping-precedence:1')->where('status', 'sent')->firstOrFail();
        $this->assertSame($mine->id, (int) $log->email_template_id);
    }

    public function test_a_society_admin_cannot_remap_a_system_template_that_every_society_shares(): void
    {
        $shared = $this->system('notice_published');
        $other = $this->system('bill_generated');

        // Point the shared "notice published" template at a different event...
        $this->actingAs($this->adminA)->putJson(route('admin.email-template-mappings.update', 'bill_generated'), ['template_id' => $shared->id])->assertStatus(403);

        $this->assertSame('notice_published', $shared->fresh()->trigger_event, 'the shared template still serves its event');
        $this->assertSame('bill_generated', $other->fresh()->trigger_event);
    }

    public function test_a_society_admin_cannot_switch_off_an_event_by_unmapping_the_system_template(): void
    {
        $shared = $this->system('notice_published');

        $this->actingAs($this->adminA)->deleteJson(route('admin.email-template-mappings.destroy', 'notice_published'))->assertStatus(403);

        $this->assertSame('notice_published', $shared->fresh()->trigger_event, "every society's notice emails still have their template");
    }

    public function test_a_society_admin_cannot_use_another_societys_template(): void
    {
        $theirs = $this->template($this->b, 'beta-notice');

        $this->actingAs($this->adminA)->putJson(route('admin.email-template-mappings.update', 'notice_published'), ['template_id' => $theirs->id])->assertStatus(403);

        $this->assertNull($theirs->fresh()->trigger_event);
    }

    public function test_a_super_admin_can_remap_a_system_template(): void
    {
        $shared = $this->system('notice_published');

        $this->actingAs($this->super)->deleteJson(route('admin.email-template-mappings.destroy', 'notice_published'))->assertOk();

        $this->assertNull($shared->fresh()->trigger_event);
    }

    public function test_invalid_events_and_templates_are_refused(): void
    {
        $mine = $this->template($this->a, 'alpha-x');

        $this->actingAs($this->adminA)->putJson(route('admin.email-template-mappings.update', 'not_an_event'), ['template_id' => $mine->id])->assertStatus(400);
        $this->actingAs($this->adminA)->putJson(route('admin.email-template-mappings.update', 'notice_published'), ['template_id' => 999999])->assertStatus(422);
        $this->actingAs($this->adminA)->putJson(route('admin.email-template-mappings.update', 'notice_published'), [])->assertStatus(422);
        $this->actingAs($this->adminA)->deleteJson(route('admin.email-template-mappings.destroy', 'not_an_event'))->assertStatus(400);
    }

    public function test_the_test_send_does_not_claim_delivery_while_external_messaging_is_disabled(): void
    {
        $response = $this->actingAs($this->adminA)->postJson(route('admin.email-template-mappings.test', 'notice_published'), ['recipient_email' => 'someone@example.invalid']);

        $response->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('Not sent', (string) $response->json('message'));
    }

    public function test_users_without_the_settings_permission_are_refused(): void
    {
        $tenant = $this->makeUserWithRole($this->a, 'Tenant');
        $mine = $this->template($this->a, 'alpha-y');

        $this->actingAs($tenant)->putJson(route('admin.email-template-mappings.update', 'notice_published'), ['template_id' => $mine->id])->assertStatus(403);
        $this->assertNull($mine->fresh()->trigger_event);
    }
}
