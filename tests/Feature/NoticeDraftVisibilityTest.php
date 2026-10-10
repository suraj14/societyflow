<?php

namespace Tests\Feature;

use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found in the browser audit: a draft notice created by the admin was listed to residents of the same society,
 * because the list only scoped by society, never by status. Stats for non-admins also counted every society.
 */
class NoticeDraftVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function notice($society, $author, string $title, string $status): Notice
    {
        return Notice::create(['society_id' => $society->id, 'created_by' => $author->id, 'title' => $title, 'content' => 'x', 'type' => 'general', 'priority' => 'medium', 'status' => $status, 'publish_date' => now()->subDay()]);
    }

    public function test_residents_see_only_published_notices_and_cannot_open_a_draft_by_id(): void
    {
        $a = $this->makeSociety('A');
        $b = $this->makeSociety('B');
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_notices', 'guard_name' => 'web']);
        $admin = $this->makeUserWithRole($a, 'Admin');
        $admin->givePermissionTo('manage_notices');
        $tenant = $this->makeUserWithRole($a, 'Tenant');
        $otherAdmin = $this->makeUserWithRole($b, 'Admin');

        $live = $this->notice($a, $admin, 'Live pool notice', 'published');
        $draft = $this->notice($a, $admin, 'Secret draft notice', 'draft');
        $pending = $this->notice($a, $admin, 'Pending notice', 'pending');
        $this->notice($b, $otherAdmin, 'Other society draft', 'draft');

        $page = $this->actingAs($tenant)->get(route('notices.index'))->assertOk();
        $page->assertSee('Live pool notice')->assertDontSee('Secret draft notice')->assertDontSee('Pending notice')->assertDontSee('Other society draft');
        $this->assertSame(1, $page->viewData('stats')['total'], 'stats count only what the resident may see, in their society');

        $this->actingAs($tenant)->get(route('notices.show', $draft))->assertNotFound();
        $this->actingAs($tenant)->get(route('notices.show', $live))->assertOk();

        $this->actingAs($admin)->get(route('notices.index'))->assertOk()->assertSee('Secret draft notice')->assertSee('Pending notice');
        $this->actingAs($admin)->get(route('notices.show', $draft))->assertOk();
    }

    public function test_other_society_notices_by_id_and_non_office_roles_do_not_reach_unpublished_content(): void
    {
        $a = $this->makeSociety('A2');
        $b = $this->makeSociety('B2');
        $adminB = $this->makeUserWithRole($b, 'Admin');
        $draftB = $this->notice($b, $adminB, 'Beta confidential draft', 'draft');
        $liveB = $this->notice($b, $adminB, 'Beta live notice', 'published');
        $adminA = $this->makeUserWithRole($a, 'Admin');
        $pendingA = $this->notice($a, $adminA, 'Alpha pending approval', 'pending');

        foreach (['Tenant', 'Guard', 'Accountant', 'Staff', 'Apartment Owner'] as $role) {
            $u = $this->makeUserWithRole($a, $role);
            foreach ([$draftB, $liveB] as $foreign) {
                $r = $this->actingAs($u)->get(route('notices.show', $foreign));
                $this->assertContains($r->getStatusCode(), [403, 404], "$role must not open another society's notice");
                $this->assertStringNotContainsString('Beta', (string) $r->getContent());
            }
            $own = $this->actingAs($u)->get(route('notices.show', $pendingA));
            $this->assertContains($own->getStatusCode(), [403, 404], "$role must not read an unpublished notice of their own society");
            $this->assertStringNotContainsString('Alpha pending approval', (string) $own->getContent());
            $queue = $this->actingAs($u)->get(route('notices.pending'));
            $this->assertContains($queue->getStatusCode(), [200, 403]);
            $this->assertStringNotContainsString('Alpha pending approval', (string) $queue->getContent());
            $hist = $this->actingAs($u)->get(route('notices.history'));
            $this->assertContains($hist->getStatusCode(), [200, 403]);
            $this->assertStringNotContainsString('Alpha pending approval', (string) $hist->getContent());
            $this->assertStringNotContainsString('Beta', (string) $hist->getContent());
        }
    }

    public function test_the_villa_owner_portal_lists_published_notices_and_hides_unpublished_ones(): void
    {
        $a = $this->makeSociety('Villa Soc');
        $area = \App\Models\VillaArea::create(['society_id' => $a->id, 'name' => 'A', 'status' => 'active']);
        $record = \App\Models\Owner::create(['society_id' => $a->id, 'name' => 'Vo', 'email' => 'vo@t.local', 'phone' => '0000000002', 'status' => 'active', 'property_type' => 'villa']);
        \App\Models\Flat::create(['society_id' => $a->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '9', 'status' => 'occupied', 'owner_id' => $record->id]);
        $owner = $this->makeUserWithRole($a, 'Owner', ['owner_id' => $record->id]);
        $admin = $this->makeUserWithRole($a, 'Admin');
        $live = $this->notice($a, $admin, 'Villa live notice', 'published');
        $draft = $this->notice($a, $admin, 'Villa hidden draft', 'draft');

        $this->actingAs($owner)->get(route('villa-owner.notices'))->assertOk()->assertSee('Villa live notice')->assertDontSee('Villa hidden draft');
        $this->actingAs($owner)->get(route('villa-owner.notices.show', $live))->assertOk();
        $this->actingAs($owner)->get(route('villa-owner.notices.show', $draft))->assertNotFound();
    }
}
