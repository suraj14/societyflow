<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Services\DynamicEmailService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationEmail
{

    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Payment Success:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ❌
     * - Accountant: ✅
     */
    public function handle(PaymentReceived $event): void
    {
        try {
            $payment = $event->payment;
            $societyId = $payment->society_id;

            // Allowed roles for payment success event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Admin', 'Accountant'];

            $recipients = [];

            // Add payer if they have allowed role
            if ($payment->user_id) {
                $user = User::find($payment->user_id);
                if ($user && $user->hasAnyRole($allowedRoles)) {
                    $recipients[] = [
                        'email' => $user->email,
                        'name' => $user->name,
                        'role' => $user->getRoleNames()->first() ?? 'user',
                    ];
                }
            }

            // Add admin and accountant
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
                Log::info('No recipients for payment confirmation email', [
                    'payment_id' => $payment->id,
                ]);
                return;
            }

            $baseVariables = [
                'payment_id' => $payment->id ?? '',
                'amount' => $payment->amount ?? 0,
                'payment_date' => $payment->payment_date ?? now()->format('Y-m-d'),
                'receipt_url' => route('payments.receipt', $payment->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DynamicEmailService::sendByEvent(
                    'payment_success',
                    [$recipient],
                    $variables,
                    $societyId
                );
            }

            Log::info('Payment confirmation email sent', [
                'payment_id' => $payment->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
                'payment_id' => $event->payment->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
