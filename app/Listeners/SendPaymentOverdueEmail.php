<?php

namespace App\Listeners;

use App\Events\PaymentOverdue;
use App\Services\DynamicEmailService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendPaymentOverdueEmail
{
    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Payment Overdue:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ❌
     * - Accountant: ❌
     */
    public function handle(PaymentOverdue $event): void
    {
        try {
            $bill = $event->bill;
            $societyId = $bill->society_id;
            $flatId = $bill->flat_id;

            // Allowed roles for payment overdue event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Admin'];

            $recipients = [];

            // Get property owners
            if ($flatId) {
                $owners = User::whereHas('ownedProperties', function($q) use ($flatId) {
                    $q->where('id', $flatId);
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

            // Also notify admin
            $admin = User::where('society_id', $societyId)
                ->whereHas('roles', function($q) {
                    $q->where('name', 'Admin');
                })
                ->first();
            
            if ($admin) {
                $recipients[] = [
                    'email' => $admin->email,
                    'name' => $admin->name,
                    'role' => 'Admin',
                ];
            }

            if (empty($recipients)) {
                Log::info('No recipients for payment overdue email', [
                    'bill_id' => $bill->id,
                ]);
                return;
            }

            $baseVariables = [
                'bill_number' => $bill->bill_number ?? '',
                'bill_type' => 'maintenance',
                'amount' => $bill->amount ?? $bill->total_amount ?? 0,
                'due_date' => $bill->due_date ?? '',
                'days_overdue' => now()->diffInDays($bill->due_date),
                'view_url' => route('payments.show', $bill->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DynamicEmailService::sendByEvent(
                    'payment_overdue',
                    [$recipient],
                    $variables,
                    $societyId
                );
            }

            Log::info('Payment overdue email sent', [
                'bill_id' => $bill->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment overdue email', [
                'bill_id' => $event->bill->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
