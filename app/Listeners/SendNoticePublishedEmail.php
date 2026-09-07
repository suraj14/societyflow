<?php

namespace App\Listeners;

use App\Events\NoticePublished;
use App\Services\DynamicEmailService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendNoticePublishedEmail
{

    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Notice Published:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ❌
     * - Accountant: ❌
     */
    public function handle(NoticePublished $event): void
    {
        try {
            $notice = $event->notice;
            $societyId = $notice->society_id;

            // Allowed roles for notice published event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Admin'];

            // Get recipients based on target audience
            $recipients = [];
            
            if ($notice->target_audience && is_array($notice->target_audience)) {
                // Get users with specific roles that are in allowed list
                $allowedTargetRoles = array_intersect($notice->target_audience, $allowedRoles);
                if (!empty($allowedTargetRoles)) {
                    $users = User::where('society_id', $societyId)
                        ->whereHas('roles', function($q) use ($allowedTargetRoles) {
                            $q->whereIn('name', $allowedTargetRoles);
                        })
                        ->get();
                }
            } else {
                // Get all allowed roles
                $users = User::where('society_id', $societyId)
                    ->whereHas('roles', function($q) use ($allowedRoles) {
                        $q->whereIn('name', $allowedRoles);
                    })
                    ->get();
            }

            // Format recipients
            foreach ($users as $user) {
                $recipients[] = [
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->getRoleNames()->first() ?? 'user',
                ];
            }

            if (empty($recipients)) {
                Log::info('No recipients for notice published email', [
                    'notice_id' => $notice->id,
                ]);
                return;
            }

            // Prepare base variables (will be merged with per-recipient variables)
            $baseVariables = [
                'notice_title' => $notice->title ?? '',
                'notice_content' => substr($notice->content ?? '', 0, 200),
                'view_url' => route('notices.show', $notice->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DynamicEmailService::sendByEvent(
                    'notice_published',
                    [$recipient],
                    $variables,
                    $societyId
                );
            }

            Log::info('Notice published email sent', [
                'notice_id' => $notice->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notice published email', [
                'notice_id' => $event->notice->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
