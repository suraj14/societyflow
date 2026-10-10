<?php

namespace Tests\Feature;

use App\Jobs\DispatchEventNotification;
use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * DispatchEventNotification::handle(), called directly rather than through
 * dispatch()/afterCommit() - those defer until a real database commit, which
 * RefreshDatabase's own test-wrapping transaction never performs (it rolls
 * back). Calling handle() is a plain method call and exercises the real
 * send, idempotency and retry logic without that boundary.
 */
class DispatchEventNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $key, array $recipient = ['email' => 'resident@test.local', 'name' => 'Test Resident']): DispatchEventNotification
    {
        // email_logs.society_id is NOT NULL - every real listener always
        // passes a real society id (there is no "global" notification send
        // in practice), so the job is tested the same way.
        $society = $this->makeSociety();

        return new DispatchEventNotification(
            'notice_published',
            $recipient,
            ['notice_title' => 'Test Notice', 'notice_content' => 'Body', 'view_url' => '#'],
            $society->id,
            $key,
        );
    }

    public function test_handle_actually_sends_and_logs_with_the_notification_key(): void
    {
        Mail::fake();

        $this->job('notice_published:1:resident@test.local')->handle();

        $this->assertDatabaseHas('email_logs', [
            'recipient_email'  => 'resident@test.local',
            'notification_key' => 'notice_published:1:resident@test.local',
            'channel'          => 'email',
            'status'           => 'sent',
        ]);
    }

    public function test_handle_is_idempotent_a_second_call_with_the_same_key_sends_nothing_more(): void
    {
        Mail::fake();
        $key = 'notice_published:2:resident@test.local';

        $this->job($key)->handle();
        $this->job($key)->handle();

        $this->assertSame(1, EmailLog::where('notification_key', $key)->count());
    }

    public function test_a_different_key_for_the_same_recipient_sends_again(): void
    {
        // Proves the dedupe is keyed correctly, not just "same recipient
        // sent to before" - a status-change or a second notice must still
        // reach the same inbox.
        Mail::fake();

        $this->job('notice_published:3:resident@test.local')->handle();
        $this->job('notice_published:4:resident@test.local')->handle();

        $this->assertSame(2, EmailLog::where('recipient_email', 'resident@test.local')->count());
    }

    public function test_a_transport_failure_is_retried_not_swallowed(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \Exception('SMTP connection refused'));

        $this->expectException(\RuntimeException::class);

        $this->job('notice_published:5:resident@test.local')->handle();
    }

    public function test_a_failed_send_is_logged_before_the_retry_exception_is_thrown(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \Exception('SMTP connection refused'));

        try {
            $this->job('notice_published:6:resident@test.local')->handle();
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertDatabaseHas('email_logs', [
            'notification_key' => 'notice_published:6:resident@test.local',
            'status'           => 'failed',
        ]);
    }

    public function test_a_retry_after_a_real_failure_is_allowed_to_send(): void
    {
        // Idempotency blocks a duplicate of a *successful* send, never a
        // retry of a failed one - a transient SMTP error must not become a
        // silent permanent skip.
        Mail::shouldReceive('send')->once()->andThrow(new \Exception('SMTP down'));
        $key = 'notice_published:7:resident@test.local';

        try {
            $this->job($key)->handle();
        } catch (\RuntimeException $e) {
        }

        Mail::fake();
        $this->job($key)->handle();

        $this->assertDatabaseHas('email_logs', ['notification_key' => $key, 'status' => 'sent']);
    }

    public function test_the_unique_id_is_the_notification_key(): void
    {
        $job = $this->job('notice_published:8:resident@test.local');
        $this->assertSame('notice_published:8:resident@test.local', $job->uniqueId());
    }

    public function test_backoff_is_a_fixed_short_schedule(): void
    {
        $this->assertSame([10, 30, 60], $this->job('notice_published:9:resident@test.local')->backoff());
    }
}
