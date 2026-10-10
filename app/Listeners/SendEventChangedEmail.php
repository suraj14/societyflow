<?php

namespace App\Listeners;

use App\Events\EventChanged;
use App\Jobs\DispatchEventNotification;
use App\Support\EventAudience;
use Illuminate\Support\Facades\Log;

/**
 * Cancelled / rescheduled / updated events. Before this existed an edit sent
 * the "new event" email again - "A new event has been created: ..." - so a
 * cancellation read as an announcement. Same audience as the creation email
 * (EventAudience), a template of its own, and a key that includes what
 * changed and when the event is now, so a re-save with nothing new sends
 * nothing and a second, different change is still delivered.
 */
class SendEventChangedEmail
{
    public function handle(EventChanged $change): void
    {
        try {
            $event = $change->event;

            $users = EventAudience::users($event)->filter(fn ($u) => !empty($u->email));
            if ($users->isEmpty()) {
                Log::info('No recipients for event changed email', ['event_id' => $event->id, 'kind' => $change->kind]);

                return;
            }

            // Identifies THIS save: a retried delivery of it dedupes, a later edit does not.
            $stamp = ($event->updated_at?->timestamp ?? 0) . '-' . ($event->start_date?->format('YmdHi') ?? '0');
            foreach ($users as $user) {
                DispatchEventNotification::dispatch(
                    'event_changed',
                    ['email' => $user->email, 'name' => $user->name, 'role' => $user->getRoleNames()->first() ?? 'user'],
                    [
                        'user_name'      => $user->name,
                        'event_name'     => $event->event_name ?? '',
                        'change_summary' => $change->summary(),
                        'change_kind'    => $change->kind,
                        'event_date'     => $event->start_date?->format('Y-m-d') ?? '',
                        'event_time'     => $event->start_date?->format('H:i') ?? '',
                        'event_location' => $event->location ?? '',
                        'view_url'       => route('events.show', $event->id),
                    ],
                    $event->society_id,
                    "event_changed:{$event->id}:{$change->kind}:{$stamp}:{$user->email}",
                )->afterCommit();
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send event changed email', ['event_id' => $change->event->id ?? 'unknown', 'error' => $e->getMessage()]);
        }
    }
}
