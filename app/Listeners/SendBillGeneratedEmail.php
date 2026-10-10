<?php

namespace App\Listeners;

use App\Events\BillGenerated;
use App\Jobs\DispatchEventNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Was registered in EventServiceProvider with a template already seeded
 * (2026_10_07_000002) but **nothing in the codebase ever dispatched
 * `BillGenerated`** - confirmed by grep, documented in
 * `NotificationEvents::knownDeadEventClasses()`'s own history. Now real:
 * `Admin\RentController::store()` dispatches it the moment a rent bill is
 * created. Recipient resolution and variables below are unchanged from
 * when this was written; only the *send* moved from the old direct
 * `DynamicEmailService::sendByEvent()` call to the queued/afterCommit/
 * idempotent job every other trigger_event listener already uses.
 */
class SendBillGeneratedEmail
{

    /**
     * Handle the event
     *
     * Role-based recipient resolution for Bill Generated:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ❌
     * - Accountant: ✅
     */
    public function handle(BillGenerated $event): void
    {
        try {
            $bill = $event->bill;
            $societyId = $bill->society_id;
            $flatId = $bill->flat_id;

            // Allowed roles for bill generated event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Admin', 'Accountant'];

            $recipients = [];

            // Get property owners
            if ($flatId) {
                // flats.id, not id - see App\Models\User::ownedProperties()'s docblock.
                $owners = User::whereHas('ownedProperties', function($q) use ($flatId) {
                    $q->where('flats.id', $flatId);
                })->where('society_id', $societyId)
                  ->whereHas('roles', function($q) use ($allowedRoles) {
                      $q->whereIn('name', $allowedRoles);
                  })
                  ->get();

                foreach ($owners as $owner) {
                    $recipients[] = [
                        'email' => $owner->email,
                        'name' => $owner->name,
                        'role' => $owner->getRoleNames()->first() ?? 'user',
                    ];
                }

                // Get tenants
                $tenants = User::whereHas('tenant', function($q) use ($flatId) {
                    $q->whereHas('flat', function($subQ) use ($flatId) {
                        $subQ->where('id', $flatId);
                    });
                })->where('society_id', $societyId)
                  ->whereHas('roles', function($q) use ($allowedRoles) {
                      $q->whereIn('name', $allowedRoles);
                  })
                  ->get();

                foreach ($tenants as $tenant) {
                    $recipients[] = [
                        'email' => $tenant->email,
                        'name' => $tenant->name,
                        'role' => $tenant->getRoleNames()->first() ?? 'user',
                    ];
                }
            }

            // Also notify admin and accountant
            $admins = User::where('society_id', $societyId)
                ->whereHas('roles', function($q) {
                    $q->whereIn('name', ['Admin', 'Accountant']);
                })
                ->get();
            
            foreach ($admins as $admin) {
                $recipients[] = [
                    'email' => $admin->email,
                    'name' => $admin->name,
                    'role' => $admin->getRoleNames()->first() ?? 'user',
                ];
            }

            if (empty($recipients)) {
                Log::info('No recipients for bill generated email', [
                    'bill_id' => $bill->id,
                ]);
                return;
            }

            $baseVariables = [
                'bill_number' => $bill->bill_number ?? '',
                'bill_type' => $event->billType ?? 'maintenance',
                'amount' => $bill->amount ?? $bill->total_amount ?? 0,
                'due_date' => $bill->due_date ?? '',
                'view_url' => route('payments.show', $bill->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DispatchEventNotification::dispatch(
                    'bill_generated',
                    $recipient,
                    $variables,
                    $societyId,
                    "bill_generated:{$bill->id}:{$recipient['email']}",
                )->afterCommit();
            }

            Log::info('Bill generated email sent', [
                'bill_id' => $bill->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send bill generated email', [
                'bill_id' => $event->bill->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
