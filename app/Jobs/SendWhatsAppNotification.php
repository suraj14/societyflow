<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One WhatsApp notification to one person, off the request thread (a notice
 * to every resident must not hold the admin's browser while each message is
 * sent). WhatsAppService decides whether anything is sent at all: no
 * template for the purpose, or WhatsApp switched off, means it skips.
 *
 * Retries are bounded and only for failures that can pass: no answer at all, a rate limit (429) or a provider-side
 * 5xx (WhatsAppService marks those `transient`). A refused key, an unknown Phone Number ID, a bad request or a
 * template error would fail the same way every time, so they are recorded once and never retried. Retrying cannot
 * message anyone twice: WhatsAppService only remembers an attempt that was ACCEPTED (SendOnce), so a failed one is
 * free to go again, and an accepted one is skipped.
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** The first attempt and two more. */
    public int $tries = 3;

    public function __construct(
        public string $purpose,
        public string $phone,
        public array $values,
        public ?int $societyId,
    ) {
    }

    /** Seconds before the second and third attempts. */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $result = WhatsAppService::sendNotification($this->purpose, $this->phone, $this->values, $this->societyId);

        if (!empty($result['transient']) && $this->job !== null && $this->attempts() < $this->tries) {
            // never throws: a worker puts it back for later; with the sync queue (no worker) there is nowhere to put it back,
            // the failure is already in the delivery log and the business action is untouched
            $this->release($this->backoff()[$this->attempts() - 1] ?? 120);
        }
    }
}
