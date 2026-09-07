<?php

namespace App\Listeners;

use App\Events\PasswordReset;
use App\Services\DynamicEmailService;
use Illuminate\Support\Facades\Log;

class SendPasswordResetEmail
{
    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Password Reset:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ✅
     * - Accountant: ✅
     */
    public function handle(PasswordReset $event): void
    {
        try {
            $user = $event->user;
            $societyId = $user->society_id;

            // All roles can receive password reset email
            $recipients = [[
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->getRoleNames()->first() ?? 'user',
            ]];

            $variables = [
                'user_name' => $user->name,
                'reset_url' => route('password.reset', $event->resetToken),
                'reset_token' => $event->resetToken,
            ];

            // Send email using DynamicEmailService with configured template
            DynamicEmailService::sendByEvent(
                'password_reset',
                $recipients,
                $variables,
                $societyId
            );

            Log::info('Password reset email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $event->user->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
