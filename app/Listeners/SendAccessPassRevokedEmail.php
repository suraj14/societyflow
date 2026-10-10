<?php

namespace App\Listeners;

use App\Events\AccessPassRevoked;
use App\Jobs\DispatchEventNotification;
use Illuminate\Support\Facades\Log;

/**
 * Security-relevant: `AccessPassController::revoke()` can be called by
 * Admin/Super Admin on a pass a *resident* created
 * (`authorizeOwnPass()` lets them act on anyone's pass in their society) -
 * so the resident who owns this pass may not be the one who just revoked
 * it, and finding out their household staff's gate access was withdrawn
 * should not depend on them happening to check the access-pass list.
 *
 * The Communication Center's email on/off rule for this event is enforced
 * centrally in `DispatchEventNotification::handle()`, not here.
 */
class SendAccessPassRevokedEmail
{
    public function handle(AccessPassRevoked $event): void
    {
        try {
            $pass = $event->accessPass;
            $user = $pass->createdBy;

            if (!$user || !$user->email) {
                return;
            }

            $variables = [
                'user_name'   => $user->name,
                'person_name' => $pass->person_name,
                'pass_type'   => $pass->pass_type === 'recurring_staff' ? 'Recurring Staff' : 'Temporary Service',
            ];

            DispatchEventNotification::dispatch(
                'access_pass_revoked',
                ['email' => $user->email, 'name' => $user->name, 'role' => $user->getRoleNames()->first() ?? 'user'],
                $variables,
                $pass->society_id,
                "access_pass_revoked:{$pass->id}:{$user->email}",
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::error('Failed to send access pass revoked email', [
                'pass_id' => $event->accessPass->id ?? 'unknown',
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
