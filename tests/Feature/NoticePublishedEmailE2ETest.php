<?php

namespace Tests\Feature;

use App\Jobs\DispatchEventNotification;
use App\Models\Notice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The exact reported defect: Settings -> Test Email works, but a published
 * Notice reaches nobody. Root cause was two bugs stacked on an otherwise
 * correctly wired pipeline (NoticeController -> NoticePublished event ->
 * SendNoticePublishedEmail listener -> DynamicEmailService::sendByEvent):
 *
 *   1. No active `notice_published` EmailTemplate existed, so sendByEvent's
 *      EmailTemplate::getTemplateForEvent() lookup returned null and the
 *      send was silently skipped - Test Email never hits that lookup at
 *      all (Mail::raw(), no template), which is why it "worked" while real
 *      notices did not.
 *   2. The listener's recipient resolution could leave $users unset and
 *      throw on an undefined variable, caught and logged as a quiet
 *      failure, for any target_audience with no overlap in its allowed
 *      roles.
 *
 * Both are fixed by the 2026_10_07_000002 migration (template) and the
 * listener's `$users = collect()` default.
 *
 * The send itself now happens in a queued, `afterCommit()`-dispatched job
 * (DispatchEventNotification) rather than inline, so it survives a rolled
 * back transaction and can retry. `afterCommit()` callbacks never fire
 * inside RefreshDatabase's own test-wrapping transaction - it rolls back,
 * never truly commits - so this test asserts at the real boundary that
 * transaction allows: the listener dispatched the right job, with the
 * right recipient, for the right notice. The job's own send/idempotency/
 * retry behaviour is verified directly in DispatchEventNotificationTest,
 * which calls handle() outside any dispatch/afterCommit machinery.
 */
class NoticePublishedEmailE2ETest extends TestCase
{
    use RefreshDatabase;

    /** A bare test-role Admin has no permissions; NoticeController::store() requires this one. */
    private function adminWhoCanManageNotices(\App\Models\Society $society): \App\Models\User
    {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_notices', 'guard_name' => 'web']);
        $admin = $this->makeUserWithRole($society, 'Admin');
        $admin->givePermissionTo('manage_notices');
        return $admin;
    }

    public function test_publishing_a_notice_queues_an_email_job_for_tenants_and_owners(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin   = $this->adminWhoCanManageNotices($society);
        // The listener resolves recipients purely by Spatie role, not by
        // flat/ownership - makeResidentUser() creates a legacy Resident row
        // with no Spatie role, so it would never match here regardless of
        // the fix. Tenant is one of the listener's allowed roles.
        $owner   = $this->makeUserWithRole($society, 'Tenant');

        $this->actingAs($admin)->post('/notices', [
            'title'   => 'Water Supply Interruption',
            'content' => 'Water will be shut off from 10am to 2pm for maintenance.',
            'type'    => 'general',
            'priority' => 'medium',
            'status'  => 'published',
            'send_email' => 1,   // the box now decides the channel (see NoticeChannelChoiceAndResendTest)
        ])->assertRedirect(route('notices.index'));

        $notice = Notice::where('title', 'Water Supply Interruption')->firstOrFail();
        $this->assertSame('published', $notice->status);

        Queue::assertPushed(DispatchEventNotification::class, function ($job) use ($owner, $notice) {
            return $job->triggerEvent === 'notice_published'
                && $job->recipient['email'] === $owner->email
                && $job->notificationKey === "notice_published:{$notice->id}:{$owner->email}";
        });
    }

    public function test_no_job_is_queued_for_guard_staff_or_accountant(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin   = $this->adminWhoCanManageNotices($society);
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $this->actingAs($admin)->post('/notices', [
            'title'   => 'Gate Maintenance',
            'content' => 'The main gate will be serviced tomorrow.',
            'type'    => 'general',
            'priority' => 'low',
            'status'  => 'published',
        ])->assertRedirect();

        Queue::assertNotPushed(DispatchEventNotification::class, fn ($job) => $job->recipient['email'] === $guard->email);
    }

    public function test_a_draft_notice_queues_no_email_job(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin   = $this->adminWhoCanManageNotices($society);
        $this->makeUserWithRole($society, 'Tenant');

        $this->actingAs($admin)->post('/notices', [
            'title'   => 'Draft Notice',
            'content' => 'Not published yet.',
            'type'    => 'general',
            'priority' => 'low',
            'status'  => 'draft',
        ])->assertRedirect();

        Queue::assertNotPushed(DispatchEventNotification::class);
    }

    public function test_another_societys_tenant_gets_no_job(): void
    {
        Queue::fake();

        $society = $this->makeSociety();
        $admin   = $this->adminWhoCanManageNotices($society);

        $otherSociety = $this->makeSociety();
        $otherTenant  = $this->makeUserWithRole($otherSociety, 'Tenant');

        $this->actingAs($admin)->post('/notices', [
            'title'   => 'Society A Only Notice',
            'content' => 'x',
            'type'    => 'general',
            'priority' => 'low',
            'status'  => 'published',
        ])->assertRedirect();

        Queue::assertNotPushed(DispatchEventNotification::class, fn ($job) => $job->recipient['email'] === $otherTenant->email);
    }
}
