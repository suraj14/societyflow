<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Services\DynamicEmailService;
use App\Support\NotificationRules;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * One queued, retried, idempotent send for one recipient of one business
 * event - the transport DispatchEventNotification::dispatch() is called
 * into from each of the seven listeners that already resolve recipients
 * and variables correctly (NoticePublished, PaymentReceived, EventCreated,
 * TicketUpdated, TenantOwnerAdded, VisitorApproved, UserCreated). Their
 * recipient resolution and template content are untouched; only *how* the
 * send happens moved here.
 *
 * QUEUE_CONNECTION is `sync` in this environment (CLAUDE.md's env file is
 * not touched without being asked, and there is no running
 * `queue:work` process to send jobs to even if it were switched to
 * `database` - dispatching to a queue nobody drains would silently stop
 * every notification, a worse regression than sending inline). Under
 * `sync`, a queued job still runs - immediately, in the same request -
 * so every property below (ShouldQueue, ShouldBeUnique, tries, backoff,
 * afterCommit()) is real and takes effect the moment a worker environment
 * is configured, with no further code change.
 */
/**
 * ShouldBeEncrypted: this job carries the template variables, which for a new account include the temporary password.
 * On a real queue (redis, a worker) the stored payload is encrypted with the application key, so neither the queue nor
 * a failed job holds it in the clear. (The same APP_KEY must be on every worker.)
 */
class DispatchEventNotification implements ShouldQueue, ShouldBeUnique, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $triggerEvent,
        public readonly array $recipient,
        public readonly array $variables,
        public readonly ?int $societyId,
        /**
         * Deterministic per (event instance, recipient, channel) - e.g.
         * "notice_published:42:owner@example.com". Two dispatches with the
         * same key are the same notification: ShouldBeUnique stops a
         * second one being queued while the first is pending, and the
         * EmailLog check in handle() stops a second one being *sent* once
         * the first has actually succeeded, which covers the gap
         * ShouldBeUnique does not (after the job has already finished).
         */
        public readonly string $notificationKey,
    ) {
    }

    public function uniqueId(): string
    {
        return $this->notificationKey;
    }

    /** Seconds before each retry: 10s, 30s, 60s. */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        if (!NotificationRules::isEnabled($this->triggerEvent, 'email', $this->societyId)) {
            Log::info('Notification skipped - disabled by Communication Center rule', [
                'event' => $this->triggerEvent,
            ]);
            return;
        }

        // only a send that a mail server really accepted counts: an old log-only "sent" row (see EmailLog::HONEST_STATUS_SINCE) must not
        // silence the notification forever, which is how a resident of an already-"notified" notice never got its e-mail
        $alreadySent = EmailLog::deliveredFor($this->notificationKey)->exists();

        if ($alreadySent) {
            Log::info('Notification already delivered, skipping', [
                'event' => $this->triggerEvent,
                'key_hash' => substr(md5($this->notificationKey), 0, 12),
            ]);
            return;
        }

        DynamicEmailService::sendByEvent(
            $this->triggerEvent,
            [$this->recipient],
            $this->variables,
            $this->societyId,
            $this->notificationKey,
        );

        // sendByEvent() swallows a per-recipient send failure into a
        // 'failed' EmailLog row rather than throwing (so one bad address
        // in a batch never stops the rest) - that means this job would
        // otherwise report success on a send that did not happen. Reading
        // the row back is how this job decides whether to retry.
        $logged = EmailLog::where('notification_key', $this->notificationKey)
            ->where('channel', 'email')
            ->latest('id')
            ->first();

        // a row that was deliberately skipped (invalid address, inactive account) is final: retrying cannot change it
        if ($logged && $logged->status === 'failed' && !$logged->isSkipped()) {
            throw new \RuntimeException('Email send failed: ' . ($logged->error_message ?? 'unknown error'));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Notification permanently failed after retries', [
            'event' => $this->triggerEvent,
            'key_hash' => substr(md5($this->notificationKey), 0, 12),
            'error' => $e->getMessage(),
        ]);
    }
}
