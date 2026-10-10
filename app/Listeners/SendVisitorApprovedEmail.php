<?php

namespace App\Listeners;

use App\Events\VisitorApproved;
use App\Services\DynamicEmailService;
use App\Jobs\DispatchEventNotification;
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

            // Every one of these read a non-existent attribute
            // ($visitor->name, ->phone, ->visit_date, ->visit_time), so the
            // approval email rendered with an empty name, phone and date.
            // The real columns are visitor_name, visitor_phone and
            // expected_entry_time.
            $expectedEntry = $visitor->expected_entry_time;

            $baseVariables = [
                'visitor_name' => $visitor->visitor_name ?? '',
                'visitor_phone' => $visitor->visitor_phone ?? '',
                'visit_date' => $expectedEntry ? $expectedEntry->format('F j, Y') : '',
                'visit_time' => $expectedEntry ? $expectedEntry->format('g:i A') : '',
                'view_url' => route('visitors.show', $visitor->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DispatchEventNotification::dispatch(
                    'visitor_approved',
                    $recipient,
                    $variables,
                    $societyId,
                    "visitor_approved:{$visitor->id}:{$recipient['email']}",
                )->afterCommit();
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
