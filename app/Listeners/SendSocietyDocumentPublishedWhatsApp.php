<?php

namespace App\Listeners;

use App\Events\SocietyDocumentPublished;
use App\Services\WhatsAppService;
use App\Support\NotificationRules;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp channel, through the `document` purpose template configured in
 * Settings -> WhatsApp. WhatsAppService::sendNotification() returns without
 * sending when no template is set for that purpose.
 */
class SendSocietyDocumentPublishedWhatsApp
{
    public function handle(SocietyDocumentPublished $event): void
    {
        $doc = $event->document;

        if (!$event->wants('whatsapp') || !NotificationRules::isEnabled('society_document_published', 'whatsapp', $doc->society_id)) {
            return;
        }

        try {
            foreach ($event->recipients() as $user) {
                if ($user->phone) {
                    WhatsAppService::sendNotification('document', $user->phone, [
                        'resident_name'    => $user->name,
                        'document_title'   => $doc->title,
                        'document_version' => $doc->version,
                        'society_name'     => $doc->society?->name ?? '',
                    ], $doc->society_id);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send society document published WhatsApp message', [
                'document_id' => $doc->id ?? 'unknown',
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
