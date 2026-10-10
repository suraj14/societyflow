<?php

namespace App\Listeners;

use App\Events\SocietyDocumentPublished;
use App\Services\SmsService;
use App\Support\NotificationRules;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SMS channel - separate listener so an email failure cannot stop it
 * (see SendNoticePublishedSms). SmsService::sendDltNotification() already
 * no-ops when SMS notifications are off or no DLT template is configured.
 */
class SendSocietyDocumentPublishedSms
{
    public function handle(SocietyDocumentPublished $event): void
    {
        $doc = $event->document;

        if (!$event->wants('sms') || !NotificationRules::isEnabled('society_document_published', 'sms', $doc->society_id)) {
            return;
        }

        try {
            $message = Str::limit("{$doc->title} {$doc->version} published", 60, '');

            foreach ($event->recipients() as $user) {
                if ($user->phone) {
                    SmsService::sendDltNotification('notification', $user->phone, $message, $doc->society_id);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send society document published SMS', [
                'document_id' => $doc->id ?? 'unknown',
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
