<?php

namespace Tests\Feature;

use App\Models\PushNotificationTemplate;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Push notification template screens.
 *
 * Both views were missing, so the list and edit screens returned 500
 * (found by browser sweep). updateTemplate() also had no ownership check
 * even though editTemplate() did, so an Admin could PUT to any society's
 * template by id.
 */
class PushNotificationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(?Society $society, string $trigger, string $title): PushNotificationTemplate
    {
        return PushNotificationTemplate::create([
            'society_id'     => $society?->id,
            'trigger_action' => $trigger,
            'title'          => $title,
            'body'           => 'Body of ' . $title,
            'is_active'      => true,
        ]);
    }

    public function test_list_renders_with_and_without_templates(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->get('/admin/push-notifications/templates')
            ->assertOk()->assertSee('No push templates yet', false);

        $this->makeTemplate($society, 'visitor_approved', 'Visitor approved');

        $this->actingAs($admin)->get('/admin/push-notifications/templates')
            ->assertOk()->assertSee('Visitor approved', false);
    }

    public function test_admin_sees_own_and_global_templates_but_not_another_societys(): void
    {
        $societyA = $this->makeSociety('A');
        $societyB = $this->makeSociety('B');
        $adminA   = $this->makeUserWithRole($societyA, 'Admin');

        $this->makeTemplate($societyA, 'bill_generated', 'MINE bill');
        $this->makeTemplate(null, 'notice_published', 'GLOBAL notice');
        $this->makeTemplate($societyB, 'payment_received', 'THEIRS bill');

        $response = $this->actingAs($adminA)->get('/admin/push-notifications/templates')->assertOk();
        $response->assertSee('MINE bill', false);
        $response->assertSee('GLOBAL notice', false);
        $response->assertDontSee('THEIRS bill', false);
    }

    public function test_edit_screen_renders_and_form_saves_including_unticked_checkbox(): void
    {
        $society  = $this->makeSociety();
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $template = $this->makeTemplate($society, 'visitor_approved', 'Old title');

        $this->actingAs($admin)->get("/admin/push-notifications/templates/{$template->id}/edit")
            ->assertOk()->assertSee('Old title', false);

        // The form sends a hidden is_active=0 so an unticked checkbox still
        // satisfies the controller's required|boolean rule.
        $this->actingAs($admin)->put("/admin/push-notifications/templates/{$template->id}", [
            'title' => 'New title', 'body' => 'Hello {{visitor_name}}', 'is_active' => '0',
        ])->assertRedirect(route('admin.push-notifications.templates'));

        $fresh = $template->fresh();
        $this->assertSame('New title', $fresh->title);
        $this->assertFalse($fresh->is_active);
    }

    public function test_admin_cannot_edit_or_update_another_societys_template(): void
    {
        $societyA  = $this->makeSociety('A');
        $societyB  = $this->makeSociety('B');
        $adminA    = $this->makeUserWithRole($societyA, 'Admin');
        $templateB = $this->makeTemplate($societyB, 'bill_generated', 'THEIRS');

        $this->actingAs($adminA)->get("/admin/push-notifications/templates/{$templateB->id}/edit")->assertForbidden();

        $this->actingAs($adminA)->put("/admin/push-notifications/templates/{$templateB->id}", [
            'title' => 'Hijacked', 'body' => 'x', 'is_active' => '1',
        ])->assertForbidden();

        $this->assertSame('THEIRS', $templateB->fresh()->title);
    }

    public function test_super_admin_can_edit_any_template(): void
    {
        $societyB   = $this->makeSociety('B');
        $superAdmin = $this->makeUserWithRole($societyB, 'Super Admin', ['society_id' => null]);
        $templateB  = $this->makeTemplate($societyB, 'bill_generated', 'THEIRS');

        $this->actingAs($superAdmin)->get("/admin/push-notifications/templates/{$templateB->id}/edit")->assertOk();
        $this->actingAs($superAdmin)->put("/admin/push-notifications/templates/{$templateB->id}", [
            'title' => 'Adjusted', 'body' => 'x', 'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('Adjusted', $templateB->fresh()->title);
    }
}
