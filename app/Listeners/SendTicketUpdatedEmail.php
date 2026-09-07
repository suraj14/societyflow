<?php

namespace App\Listeners;

use App\Events\TicketUpdated;
use App\Services\DynamicEmailService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendTicketUpdatedEmail
{
    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Ticket Updated:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ✅
     * - Accountant: ❌
     */
    public function handle(TicketUpdated $event): void
    {
        try {
            $complaint = $event->complaint;
            $societyId = $complaint->society_id;

            // Allowed roles for ticket updated event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Admin', 'Staff'];

            $recipients = [];

            // Add complaint creator
            if ($complaint->created_by) {
                $creator = User::find($complaint->created_by);
                if ($creator && $creator->hasAnyRole($allowedRoles)) {
                    $recipients[] = [
                        'email' => $creator->email,
                        'name' => $creator->name,
                        'role' => $creator->getRoleNames()->first() ?? 'user',
                    ];
                }
            }

            // Add admin and staff
            $staff = User::where('society_id', $societyId)
                ->whereHas('roles', function($q) {
                    $q->whereIn('name', ['Admin', 'Staff']);
                })
                ->get();
            
            foreach ($staff as $member) {
                $recipients[] = [
                    'email' => $member->email,
                    'name' => $member->name,
                    'role' => $member->getRoleNames()->first() ?? 'user',
                ];
            }

            if (empty($recipients)) {
                Log::info('No recipients for ticket updated email', [
                    'complaint_id' => $complaint->id,
                ]);
                return;
            }

            $baseVariables = [
                'ticket_number' => $complaint->id ?? '',
                'ticket_title' => $complaint->title ?? '',
                'ticket_status' => $complaint->status ?? 'open',
                'ticket_category' => $complaint->category ?? '',
                'view_url' => route('complaints.show', $complaint->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DynamicEmailService::sendByEvent(
                    'ticket_updated',
                    [$recipient],
                    $variables,
                    $societyId
                );
            }

            Log::info('Ticket updated email sent', [
                'complaint_id' => $complaint->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send ticket updated email', [
                'complaint_id' => $event->complaint->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
