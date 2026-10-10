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
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public string $purpose,
        public string $phone,
        public array $values,
        public ?int $societyId,
    ) {
    }

    public function handle(): void
    {
        WhatsAppService::sendNotification($this->purpose, $this->phone, $this->values, $this->societyId);
    }
}
