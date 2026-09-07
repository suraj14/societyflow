<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Services\DynamicEmailService;
use Illuminate\Support\Facades\Log;

class SendUserWelcomeEmail
{

    /**
     * Handle the event
     * 
     * Role-based recipient resolution for User Registered (Welcome):
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ✅
     * - Accountant: ✅
     */
    public function handle(UserCreated $event): void
    {
        try {
            $user = $event->user;
            $societyId = $user->society_id;

            // All roles can receive welcome email
            $recipients = [[
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->getRoleNames()->first() ?? 'user',
            ]];

            $variables = [
                'user_name' => $user->name,
                'temporary_password' => $event->temporaryPassword ?? 'password123',
                'login_url' => route('login'),
            ];

            // Send email using DynamicEmailService with configured template
            DynamicEmailService::sendByEvent(
                'user_registered',
                $recipients,
                $variables,
                $societyId
            );

            Log::info('Welcome email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $event->user->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
