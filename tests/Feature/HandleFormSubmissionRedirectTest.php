<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Flat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HandlesFormSubmissions::handleFormSubmission() only ever uses its
 * closure's return value as JSON `data` - a redirect()/back() response
 * built and returned from inside the closure is silently discarded for an
 * ordinary (non-JSON) request, replaced by handleFormSubmission()'s own
 * back() and its generic default message unless the real message/route
 * were passed to handleFormSubmission() itself. Found live while fixing
 * the confirmed avatar-upload bug (UserController had the identical
 * pattern); the same pattern, with the same user-visible effect - wrong
 * flash message, wrong redirect, and for a closure with an internal early
 * "failure" branch, a false "success" shown for an operation that did not
 * happen - was copy-pasted into four methods of EmailTemplateController
 * and FlatController::store()'s duplicate-flat-number check (a fifth
 * instance, in the Push-notification admin controller, was removed along
 * with Push itself).
 */
class HandleFormSubmissionRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_template_create_shows_its_own_message_and_redirects_to_the_index(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($admin)->post('/admin/email-templates', [
            'name' => 'Welcome', 'slug' => 'welcome-' . uniqid(), 'subject' => 'Hi', 'body' => 'Body',
            'type' => 'custom', 'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.email-templates.index'));
        $response->assertSessionHas('success', 'Email template created successfully.');
    }

    public function test_deleting_a_system_email_template_is_refused_not_silently_reported_as_success(): void
    {
        $society    = $this->makeSociety();
        // A system template has society_id = null; only Super Admin's own
        // authorizeTemplate() check allows reaching one at all.
        $superAdmin = $this->makeUserWithRole($society, 'Super Admin');
        $template = EmailTemplate::create([
            'society_id' => null, 'name' => 'System One', 'slug' => 'system-' . uniqid(),
            'subject' => 'S', 'body' => 'B', 'type' => 'system', 'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin)->delete("/admin/email-templates/{$template->id}");

        $response->assertSessionHas('error', 'System templates cannot be deleted.');
        $response->assertSessionMissing('success');
        $this->assertDatabaseHas('email_templates', ['id' => $template->id]);
    }

    public function test_deleting_a_custom_email_template_actually_deletes_it_and_reports_it(): void
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $template = EmailTemplate::create([
            'society_id' => $society->id, 'name' => 'Custom One', 'slug' => 'custom-' . uniqid(),
            'subject' => 'S', 'body' => 'B', 'type' => 'custom', 'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->delete("/admin/email-templates/{$template->id}");

        $response->assertRedirect(route('admin.email-templates.index'));
        $response->assertSessionHas('success', 'Email template deleted successfully.');
        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    }

    public function test_a_duplicate_flat_number_is_a_real_validation_error_not_a_false_success(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $admin    = $this->makeUserWithRole($society, 'Admin');
        $this->makeFlat($society, $building, '101');

        $before = Flat::count();

        $response = $this->actingAs($admin)->post('/flats', [
            'building_id' => $building->id, 'flat_number' => '101', 'floor' => 2, 'type' => '2BHK',
        ]);

        $response->assertSessionHasErrors('flat_number');
        $response->assertSessionMissing('success');
        $this->assertSame($before, Flat::count(), 'the duplicate must not have been created, and nothing else either');
    }

    public function test_a_non_duplicate_flat_number_is_created_normally(): void
    {
        $society  = $this->makeSociety();
        $building = $this->makeBuilding($society);
        $admin    = $this->makeUserWithRole($society, 'Admin');

        $response = $this->actingAs($admin)->post('/flats', [
            'building_id' => $building->id, 'flat_number' => '202', 'floor' => 2, 'type' => '2BHK',
        ]);

        $response->assertRedirect(route('flats.index'));
        $response->assertSessionHas('success', 'Flat created successfully!');
        $this->assertDatabaseHas('flats', ['building_id' => $building->id, 'flat_number' => '202']);
    }
}
