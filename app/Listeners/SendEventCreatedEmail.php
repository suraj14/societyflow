<?php

namespace App\Listeners;

use App\Events\EventCreated;
use App\Services\DynamicEmailService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendEventCreatedEmail
{

    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Event Created:
     * - Owner: ✅
     * - Tenant: ❌
     * - Admin: ❌
     * - Staff: ❌
     * - Accountant: ❌
     */
    public function handle(EventCreated $event): void
    {
        try {
            $eventModel = $event->event;
            $societyId = $eventModel->society_id;

            // Allowed roles for event created event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner'];

            $recipients = [];

            // Get users based on visibility, but filter by allowed roles
            if ($eventModel->is_role_based && $eventModel->visible_roles) {
                $allowedVisibleRoles = array_intersect($eventModel->visible_roles, $allowedRoles);
                if (!empty($allowedVisibleRoles)) {
                    $users = User::where('society_id', $societyId)
                        ->whereHas('roles', function($q) use ($allowedVisibleRoles) {
                            $q->whereIn('name', $allowedVisibleRoles);
                        })
                        ->get();
                }
            } elseif ($eventModel->visible_users) {
                $users = User::whereIn('id', $eventModel->visible_users)
                    ->where('society_id', $societyId)
                    ->whereHas('roles', function($q) use ($allowedRoles) {
                        $q->whereIn('name', $allowedRoles);
                    })
                    ->get();
            } else {
                // Default: only owners
                $users = User::where('society_id', $societyId)
                    ->whereHas('roles', function($q) use ($allowedRoles) {
                        $q->whereIn('name', $allowedRoles);
                    })
                    ->get();
            }

            foreach ($users as $user) {
                $recipients[] = [
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->getRoleNames()->first() ?? 'user',
                ];
            }

            if (empty($recipients)) {
                Log::info('No recipients for event created email', [
                    'event_id' => $eventModel->id,
                ]);
                return;
            }

            $baseVariables = [
                'event_name' => $eventModel->event_name ?? '',
                'event_date' => $eventModel->start_date?->format('Y-m-d') ?? '',
                'event_time' => $eventModel->start_date?->format('H:i') ?? '',
                'view_url' => route('events.show', $eventModel->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DynamicEmailService::sendByEvent(
                    'event_created',
                    [$recipient],
                    $variables,
                    $societyId
                );
            }

            Log::info('Event created email sent', [
                'event_id' => $eventModel->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send event created email', [
                'event_id' => $event->event->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
