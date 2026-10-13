<?php

namespace Tests\Feature;

use App\Jobs\DispatchEventNotification;
use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Email, end to end on the server side: the reset link (one use, expiry, no duplicates, what happens when the mail
 * server is down), and what the delivery log keeps. Mail is faked or goes through a transport that throws; nothing here
 * contacts an SMTP server.
 */
class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $society = $this->makeSociety('Alpha');
        $this->user = $this->makeUserWithRole($society, 'Tenant', ['email' => 'asha@example.test']);
    }

    private function forgot(string $email)
    {
        return $this->post(route('password.email'), ['email' => $email]);
    }

    // ── the reset link ──────────────────────────────────────────────────────

    public function test_a_registered_address_gets_exactly_one_reset_link_and_an_unknown_one_gets_none(): void
    {
        Notification::fake();

        $known = $this->forgot('asha@example.test')->assertRedirect();
        $unknown = $this->forgot('nobody@example.test')->assertRedirect();

        Notification::assertSentToTimes($this->user, ResetPasswordNotification::class, 1);
        Notification::assertNothingSentTo(new User(['email' => 'nobody@example.test']));
        $this->assertSame(session('success'), 'If an account exists for that email, a password reset link has been sent.');
        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode(), 'the same answer either way');
    }

    public function test_the_link_carries_the_token_and_the_email_and_points_at_the_reset_page(): void
    {
        Notification::fake();
        $this->forgot('asha@example.test');

        Notification::assertSentTo($this->user, ResetPasswordNotification::class, function ($n) {
            $mail = $n->toMail($this->user);
            $url = $mail->actionUrl;
            $this->assertStringContainsString('/reset-password/', $url);
            $this->assertStringContainsString('email=' . urlencode('asha@example.test'), $url);
            $this->assertStringContainsString($n->token, $url);

            return true;
        });
    }

    public function test_asking_again_within_the_throttle_does_not_send_a_second_email(): void
    {
        Notification::fake();

        $this->forgot('asha@example.test');
        $this->forgot('asha@example.test');
        $this->forgot('asha@example.test');

        Notification::assertSentToTimes($this->user, ResetPasswordNotification::class, 1);
    }

    public function test_the_reset_email_is_queued_and_retried_not_fired_and_forgotten(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new ResetPasswordNotification('x'));
        $n = new ResetPasswordNotification('x');
        $this->assertSame(3, $n->tries);
        $this->assertSame([30, 120], $n->backoff());
        $this->assertTrue($n instanceof ResetPassword);
    }

    public function test_when_the_mail_server_is_down_the_page_still_answers_alike_and_logs_no_token(): void
    {
        // a transport that always fails, on a "real" (non-local) mailer with messaging switched on, so the guard lets it through
        config(['messaging.enabled' => true, 'mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'broken']]);
        Mail::extend('broken', fn () => new class extends \Symfony\Component\Mailer\Transport\AbstractTransport {
            protected function doSend(\Symfony\Component\Mailer\SentMessage $message): void
            {
                throw new \Symfony\Component\Mailer\Exception\TransportException('Connection to smtp.example.test:587 refused (user ops@example.test)');
            }

            public function __toString(): string
            {
                return 'broken://';
            }
        });
        $lines = [];
        Event::listen(\Illuminate\Log\Events\MessageLogged::class, function ($e) use (&$lines) { $lines[] = $e->level . ' ' . $e->message . ' ' . json_encode($e->context); });

        $known = $this->forgot('asha@example.test');
        $unknown = $this->forgot('nobody@example.test');

        $known->assertRedirect()->assertSessionHas('success');
        $unknown->assertRedirect()->assertSessionHas('success');
        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode(), 'a failure does not turn into a server error for registered addresses only');
        $log = implode("\n", $lines);
        $this->assertStringContainsString('Password reset email could not be sent', $log, 'the operator can see it failed');
        $this->assertStringNotContainsString('smtp.example.test', $log, 'not the server name');
        $this->assertStringNotContainsString('ops@example.test', $log, 'not the SMTP user');
        $token = \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', 'asha@example.test')->value('token');
        $this->assertNotNull($token);
        $this->assertStringNotContainsString($token, $log);
    }

    // ── spending the link ───────────────────────────────────────────────────

    private function token(): string
    {
        return Password::createToken($this->user);
    }

    private function reset(string $token, string $password = 'A-new-Strong-pass-9')
    {
        return $this->post(route('password.update'), ['token' => $token, 'email' => 'asha@example.test', 'password' => $password, 'password_confirmation' => $password]);
    }

    public function test_a_reset_link_works_once(): void
    {
        $token = $this->token();

        $this->reset($token)->assertRedirect(route('login'))->assertSessionHas('success');
        $this->assertTrue(Hash::check('A-new-Strong-pass-9', $this->user->fresh()->password));

        $again = $this->reset($token, 'Another-Strong-pass-1');
        $again->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('Another-Strong-pass-1', $this->user->fresh()->password), 'a spent link changes nothing');
    }

    public function test_a_reset_link_expires_after_the_configured_time(): void
    {
        $token = $this->token();
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

        $this->reset($token)->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('A-new-Strong-pass-9', $this->user->fresh()->password));
    }

    public function test_a_wrong_token_or_the_wrong_address_is_refused(): void
    {
        $token = $this->token();

        $this->reset('not-the-token')->assertSessionHasErrors('email');
        $this->post(route('password.update'), ['token' => $token, 'email' => 'someone-else@example.test', 'password' => 'A-new-Strong-pass-9', 'password_confirmation' => 'A-new-Strong-pass-9'])->assertSessionHasErrors();
        $this->assertFalse(Hash::check('A-new-Strong-pass-9', $this->user->fresh()->password));
    }

    public function test_a_weak_password_is_refused_and_the_link_stays_usable(): void
    {
        $token = $this->token();

        $this->reset($token, 'short')->assertSessionHasErrors('password');
        $this->reset($token)->assertRedirect(route('login'));
    }

    public function test_resetting_signs_out_every_api_session(): void
    {
        $this->user->createToken('mobile-app');

        $this->reset($this->token())->assertRedirect(route('login'));

        $this->assertSame(0, $this->user->tokens()->count());
    }

    // ── what the delivery log keeps ─────────────────────────────────────────

    public function test_a_temporary_password_is_never_written_to_the_delivery_log(): void
    {
        $log = EmailLog::create([
            'society_id' => $this->user->society_id, 'recipient_email' => 'asha@example.test', 'trigger_action' => 'user_registered', 'channel' => 'email', 'status' => 'sent',
            'variables' => ['user_name' => 'Asha', 'temporary_password' => 'Xk9-secret-pass', 'login_url' => 'https://app.example.test/login', 'reset_token' => 'tok123', 'society_name' => 'Alpha'],
        ]);

        $raw = \Illuminate\Support\Facades\DB::table('email_logs')->where('id', $log->id)->value('variables');
        $this->assertStringNotContainsString('Xk9-secret-pass', $raw);
        $this->assertStringNotContainsString('tok123', $raw);
        $vars = $log->fresh()->variables;
        $this->assertSame('[hidden]', $vars['temporary_password']);
        $this->assertSame('[hidden]', $vars['reset_token']);
        $this->assertSame('Asha', $vars['user_name'], 'ordinary values are kept');
        $this->assertSame('https://app.example.test/login', $vars['login_url']);
    }

    public function test_a_welcome_email_still_carries_the_password_but_the_log_of_it_does_not(): void
    {
        Mail::fake();
        $society = $this->user->society_id;
        EmailTemplate::create(['society_id' => $society, 'name' => 'Welcome', 'slug' => 'welcome', 'subject' => 'Welcome {{user_name}}', 'body' => 'Your first password: {{temporary_password}}',
            'type' => 'system', 'status' => 'active', 'trigger_event' => 'user_registered']);

        \App\Services\DynamicEmailService::sendByEvent('user_registered', [['email' => 'asha@example.test', 'name' => 'Asha', 'role' => 'Tenant']],
            ['user_name' => 'Asha', 'temporary_password' => 'Xk9-secret-pass', 'login_url' => 'https://x'], $society, 'user_registered:1:asha');

        $log = EmailLog::where('recipient_email', 'asha@example.test')->firstOrFail();
        $this->assertSame('sent', $log->status);
        $this->assertSame('[hidden]', $log->variables['temporary_password']);
        $this->assertStringNotContainsString('Xk9-secret-pass', json_encode(\Illuminate\Support\Facades\DB::table('email_logs')->get()));
    }

    public function test_the_log_screen_does_not_print_a_password(): void
    {
        $admin = $this->makeUserWithRole($this->user->society, 'Admin');
        $log = EmailLog::create(['society_id' => $this->user->society_id, 'recipient_email' => 'asha@example.test', 'trigger_action' => 'user_registered', 'channel' => 'email', 'status' => 'sent',
            'variables' => ['temporary_password' => 'Xk9-secret-pass', 'user_name' => 'Asha']]);

        $html = $this->actingAs($admin)->get(route('admin.email-logs.show', $log))->assertOk()->getContent();

        $this->assertStringNotContainsString('Xk9-secret-pass', $html);
        $this->assertStringContainsString('[hidden]', $html);
    }

    public function test_retrying_a_failed_email_works_but_not_for_one_whose_password_was_not_kept(): void
    {
        Queue::fake();
        $admin = $this->makeUserWithRole($this->user->society, 'Admin');
        $template = EmailTemplate::create(['society_id' => $this->user->society_id, 'name' => 'Notice', 'slug' => 'notice', 'subject' => 'S', 'body' => 'B', 'type' => 'system', 'status' => 'active', 'trigger_event' => 'notice_published']);
        $plain = EmailLog::create(['email_template_id' => $template->id, 'society_id' => $this->user->society_id, 'recipient_email' => 'asha@example.test', 'trigger_action' => 'notice_published', 'channel' => 'email', 'status' => 'failed', 'variables' => ['notice_title' => 'Water']]);
        $welcome = EmailLog::create(['email_template_id' => $template->id, 'society_id' => $this->user->society_id, 'recipient_email' => 'asha@example.test', 'trigger_action' => 'user_registered', 'channel' => 'email', 'status' => 'failed', 'variables' => ['temporary_password' => 'Xk9-secret-pass']]);
        $orphan = EmailLog::create(['email_template_id' => null, 'society_id' => $this->user->society_id, 'recipient_email' => 'asha@example.test', 'trigger_action' => 'x', 'channel' => 'email', 'status' => 'failed']);

        $this->actingAs($admin)->post(route('admin.email-logs.retry', $plain))->assertRedirect()->assertSessionHas('success');
        Queue::assertPushed(SendEmailJob::class, 1);

        $this->actingAs($admin)->post(route('admin.email-logs.retry', $welcome))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.email-logs.retry', $orphan))->assertRedirect()->assertSessionHas('error');
        Queue::assertPushed(SendEmailJob::class, 1);   // still just the one
    }

    public function test_the_retry_job_never_sends_the_word_hidden_to_a_person(): void
    {
        Mail::fake();
        $template = EmailTemplate::create(['society_id' => $this->user->society_id, 'name' => 'W', 'slug' => 'w', 'subject' => 'S', 'body' => 'pw {{temporary_password}}', 'type' => 'system', 'status' => 'active', 'trigger_event' => 'user_registered']);
        $log = EmailLog::create(['email_template_id' => $template->id, 'society_id' => $this->user->society_id, 'recipient_email' => 'asha@example.test', 'trigger_action' => 'user_registered', 'channel' => 'email', 'status' => 'failed', 'variables' => ['temporary_password' => 'abc']]);

        (new SendEmailJob($log, $template))->handle();

        Mail::assertNothingSent();
        $this->assertSame('failed', $log->fresh()->status);
    }

    // ── queues ──────────────────────────────────────────────────────────────

    public function test_the_notification_job_payload_is_encrypted_so_a_queue_never_holds_a_password(): void
    {
        $this->assertInstanceOf(ShouldBeEncrypted::class, new DispatchEventNotification('user_registered', ['email' => 'a@b.c'], ['temporary_password' => 'Xk9-secret-pass'], 1, 'k'));

        $queue = app('queue')->connection('sync');
        $payload = (new \ReflectionMethod($queue, 'createPayload'))->invoke($queue, new DispatchEventNotification('user_registered', ['email' => 'a@b.c'], ['temporary_password' => 'Xk9-secret-pass'], 1, 'k'), 'default');

        $this->assertStringNotContainsString('Xk9-secret-pass', $payload);
        $this->assertStringContainsString('DispatchEventNotification', $payload, 'the job is still identifiable to the worker');
    }

    public function test_the_same_notification_is_not_queued_twice(): void
    {
        Bus::fake();
        $a = new DispatchEventNotification('notice_published', ['email' => 'a@b.c'], [], 1, 'notice_published:1:a@b.c');
        $b = new DispatchEventNotification('notice_published', ['email' => 'a@b.c'], [], 1, 'notice_published:1:a@b.c');

        $this->assertSame($a->uniqueId(), $b->uniqueId(), 'one identity per notification, so a retried event is one send');
    }
}
