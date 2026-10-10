<?php

namespace Tests\Feature;

use App\Jobs\DispatchEventNotification;
use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\NullQueue;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regression: config/queue.php was missing, so the queue resolved to
 * NullQueue and every queued notification job was silently discarded.
 * These tests deliberately do NOT fake the bus - they dispatch for real.
 */
class QueueConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_connection_is_configured_and_not_the_null_driver(): void
    {
        $this->assertNotNull(config('queue.default'));
        $this->assertNotInstanceOf(NullQueue::class, app('queue')->connection());
        $this->assertSame('failed_jobs', config('queue.failed.table'));
    }

    public function test_a_dispatched_notification_job_actually_sends(): void
    {
        Mail::fake();
        $society = $this->makeSociety();

        DispatchEventNotification::dispatch(
            'notice_published',
            ['email' => 'resident@test.local', 'name' => 'Resident', 'role' => 'Tenant'],
            ['user_name' => 'Resident', 'notice_title' => 'Water cut', 'notice_content' => 'x', 'view_url' => '#'],
            $society->id,
            'queue-config-test:1',
        );

        $this->assertTrue(
            EmailLog::where('notification_key', 'queue-config-test:1')->where('status', 'sent')->exists(),
            'The job was dispatched but nothing was sent - is the queue discarding jobs?'
        );
    }

    public function test_publishing_an_announced_document_emails_residents_end_to_end(): void
    {
        Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $tenant = $this->makeUserWithRole($society, 'Tenant');

        $this->actingAs($admin)->post(route('admin.society-documents.store'), [
            'title' => 'Parking Rules', 'category' => 'parking', 'version' => 'v1',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('p.pdf', "%PDF-1.4\nx\n%%EOF\n"),
        ]);
        $doc = \App\Models\SocietyDocument::firstOrFail();
        $this->actingAs($admin)->post(route('admin.society-documents.publish', $doc), ['announce' => 1, 'channels' => ['email']]);

        $log = EmailLog::where('recipient_email', $tenant->email)->where('status', 'sent')->first();
        $this->assertNotNull($log, 'The tenant was not actually emailed.');
        $this->assertNull(EmailLog::where('recipient_email', $admin->email)->first(), 'Admin is not a resident recipient.');
    }
}
