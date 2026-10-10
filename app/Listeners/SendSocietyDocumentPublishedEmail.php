<?php

namespace App\Listeners;

use App\Events\SocietyDocumentPublished;
use App\Jobs\DispatchEventNotification;
use Illuminate\Support\Facades\Log;

/**
 * Email channel for an announced society document. One queued job per
 * recipient with an idempotency key, the same as SendNoticePublishedEmail;
 * the Communication Center on/off rule is enforced inside the job.
 */
class SendSocietyDocumentPublishedEmail
{
    public function handle(SocietyDocumentPublished $event): void
    {
        if (!$event->wants('email')) {
            return;
        }

        try {
            $doc = $event->document;

            foreach ($event->recipients() as $user) {
                if (!$user->email) {
                    continue;
                }

                DispatchEventNotification::dispatch(
                    'society_document_published',
                    ['email' => $user->email, 'name' => $user->name, 'role' => $user->getRoleNames()->first() ?? 'user'],
                    [
                        'user_name'      => $user->name,
                        'document_title' => $doc->title,
                        'document_version' => $doc->version,
                        'category'       => $doc->category_label,
                        'effective_from' => $doc->effective_from?->format('d M Y') ?? 'immediately',
                        'view_url'       => route('society-rules.show', $doc->id),
                    ],
                    $doc->society_id,
                    "society_document_published:{$doc->id}:{$user->email}",
                )->afterCommit();
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send society document published email', [
                'document_id' => $event->document->id ?? 'unknown',
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
