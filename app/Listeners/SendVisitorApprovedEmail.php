<?php

namespace App\Listeners;

use App\Events\VisitorApproved;
use App\Services\DynamicEmailService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendVisitorApprovedEmail
{
    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Visitor Approved:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ❌
     * - Staff: ❌
     * - Accountant: ❌
     */
    public function handle(VisitorApproved $event): void
    {
        try {
            $visitor = $event->visitor;
            $societyId = $visitor->society_id;

            // Allowed roles for visitor approved event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'];

            $recipients = [];

            // Add visitor requester
            if ($visitor->created_by) {
                $requester = User::find($visitor->created_by);
                if ($requester && $requester->hasAnyRole($allowedRoles)) {
                    $recipients[] = [
                        'email' => $requester->email,
                        'name' => $requester->name,
                        'role' => $requester->getRoleNames()->first() ?? 'user',
                    ];
                }
            }

            if (empty($recipients)) {
                Log::info('No recipients for visitor approved email', [
                    'visitor_id' => $visitor->id,
                ]);
                return;
            }

            $baseVariables = [
                'visitor_name' => $visitor->name ?? '',
                'visitor_phone' => $visitor->phone ?? '',
                'visit_date' => $visitor->visit_date ?? '',
                'visit_time' => $visitor->visit_time ?? '',
                'view_url' => route('visitors.show', $visitor->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DynamicEmailService::sendByEvent(
                    'visitor_approved',
                    [$recipient],
                    $variables,
                    $societyId
                );
            }

            Log::info('Visitor approved email sent', [
                'visitor_id' => $visitor->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send visitor approved email', [
                'visitor_id' => $event->visitor->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
