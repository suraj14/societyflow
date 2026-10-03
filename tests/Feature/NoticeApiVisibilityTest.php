<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\Society;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile API endpoint behind the Flutter Notices screen (GET /api/v1/notices,
 * Api\NoticeApiController::index) and the eligibility rule it uses
 * (Notice::scopePublished: status=published, publish_date <= now).
 *
 * The reported bug ("Royal Eden shows 1 published notice on the web Notice
 * Board but 'No notices' on mobile") had two real causes, both fixed:
 *
 *  1. The Flutter Notices screen collapsed an API failure into the same
 *     "No notices" empty state as a genuinely empty list - a dead API
 *     looked identical to "nothing posted". Fixed in notices_screen.dart
 *     to show ErrorView + Retry instead.
 *  2. `Notice::scopePublished()` additionally required `expiry_date` to be
 *     null or in the future; the web Notice Board (used by every role,
 *     including residents) never filters by expiry_date at all. The
 *     reported notice's expiry_date (2026-09-30) had passed, so it stayed
 *     visible on web and silently disappeared from mobile. The scope no
 *     longer excludes by expiry_date, so a notice's own `status` column
 *     decides visibility on both surfaces, consistently.
 */
class NoticeApiVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function notice(Society $society, int $authorId, array $overrides = []): Notice
    {
        return Notice::create(array_merge([
            'society_id'   => $society->id,
            'created_by'   => $authorId,
            'title'        => 'Test Notice',
            'content'      => 'Body',
            'type'         => 'general',
            'priority'     => 'medium',
            'publish_date' => now()->subDay(),
            'expiry_date'  => null,
            'status'       => 'published',
        ], $overrides));
    }

    public function test_published_society_wide_notice_is_returned_to_an_eligible_owner(): void
    {
        $society = $this->makeSociety();
        $owner   = $this->makeUserWithRole($society, 'Owner');
        $this->notice($society, $owner->id, ['title' => 'Ganesh Chaturthi Celebration']);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/notices')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Ganesh Chaturthi Celebration')
            ->assertJsonCount(1, 'data');
    }

    public function test_published_society_wide_notice_is_returned_to_an_eligible_tenant(): void
    {
        $society = $this->makeSociety();
        $tenant  = $this->makeUserWithRole($society, 'Tenant');
        $this->notice($society, $tenant->id, ['title' => 'Ganesh Chaturthi Celebration']);

        Sanctum::actingAs($tenant);

        $this->getJson('/api/v1/notices')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Ganesh Chaturthi Celebration')
            ->assertJsonCount(1, 'data');
    }

    public function test_unpublished_notice_is_not_returned(): void
    {
        $society = $this->makeSociety();
        $tenant  = $this->makeUserWithRole($society, 'Tenant');
        $this->notice($society, $tenant->id, ['status' => 'draft']);
        $this->notice($society, $tenant->id, ['status' => 'pending']);

        Sanctum::actingAs($tenant);

        $this->getJson('/api/v1/notices')->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * Pins the exact case the bug report was about: Royal Eden's
     * "Ganesh Chaturthi Celebration" (publish_date 2026-09-19, expiry_date
     * 2026-09-30) is still listed on the web Notice Board - which never
     * filters by expiry_date for any role - after its expiry_date passes.
     * Mobile must keep showing it too, or the two surfaces disagree about
     * a notice's own `status`. (An earlier version of this test asserted
     * the opposite - that mobile should hide it - reflecting the previous
     * `scopePublished()` behaviour; overridden deliberately so mobile and
     * web show the same data for the same notice, not independently.)
     */
    public function test_a_published_notice_is_not_hidden_by_mobile_after_its_expiry_date(): void
    {
        $society = $this->makeSociety();
        $tenant  = $this->makeUserWithRole($society, 'Tenant');
        $this->notice($society, $tenant->id, [
            'title'        => 'Ganesh Chaturthi Celebration',
            'publish_date' => '2026-09-19',
            'expiry_date'  => '2026-09-30',
        ]);

        Sanctum::actingAs($tenant);

        $this->getJson('/api/v1/notices')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Ganesh Chaturthi Celebration')
            ->assertJsonCount(1, 'data');

        // The web Notice Board shows the same row, for the same reason -
        // confirming the two surfaces now agree rather than coincidentally
        // matching.
        $admin = $this->makeUserWithRole($society, 'Admin');
        $html = $this->actingAs($admin)->get('/notices')->assertOk()->getContent();
        $this->assertStringContainsString('Ganesh Chaturthi Celebration', $html);
    }

    public function test_a_notice_published_in_the_future_is_not_yet_returned(): void
    {
        $society = $this->makeSociety();
        $tenant  = $this->makeUserWithRole($society, 'Tenant');
        $this->notice($society, $tenant->id, ['publish_date' => now()->addWeek()]);

        Sanctum::actingAs($tenant);

        $this->getJson('/api/v1/notices')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_notice_from_another_society_is_never_returned(): void
    {
        $societyA = $this->makeSociety('Royal Eden');
        $societyB = $this->makeSociety('Other Society');
        $userA    = $this->makeUserWithRole($societyA, 'Tenant');
        $authorB  = $this->makeUserWithRole($societyB, 'Admin');
        $this->notice($societyB, $authorB->id, ['title' => 'Not Yours']);

        Sanctum::actingAs($userA);

        $this->getJson('/api/v1/notices')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_api_response_contains_every_field_the_flutter_model_reads(): void
    {
        $society = $this->makeSociety();
        $tenant  = $this->makeUserWithRole($society, 'Tenant');
        $this->notice($society, $tenant->id, ['priority' => 'high']);

        Sanctum::actingAs($tenant);

        $this->getJson('/api/v1/notices')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'content', 'status', 'type', 'is_important', 'image', 'attachments', 'posted_by', 'created_at']],
            ])
            ->assertJsonPath('data.0.is_important', true);
    }

    public function test_single_notice_show_endpoint_still_blocks_a_draft_but_not_an_expired_one(): void
    {
        $society = $this->makeSociety();
        $tenant  = $this->makeUserWithRole($society, 'Tenant');
        $expired = $this->notice($society, $tenant->id, ['expiry_date' => now()->subDay()]);
        $draft   = $this->notice($society, $tenant->id, ['status' => 'draft']);

        Sanctum::actingAs($tenant);

        // Same scope as the list endpoint: expiry_date no longer hides it.
        $this->getJson('/api/v1/notices/' . $expired->id)->assertOk();
        $this->getJson('/api/v1/notices/' . $draft->id)->assertNotFound();
    }
}
