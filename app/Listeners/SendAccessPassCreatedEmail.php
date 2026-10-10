<?php

namespace App\Listeners;

use App\Events\AccessPassCreated;
use App\Jobs\DispatchEventNotification;
use Illuminate\Support\Facades\Log;

/**
 * Confirmation email to whoever created the pass (`created_by`) - a
 * resident authorising their own staff, or an Admin creating a
 * society-wide one. Matches the existing "created" confirmation pattern
 * (TenantOwnerAdded, VisitorApproved): the acting user already sees a
 * success flash in the browser, this is the durable record of it, the
 * same way a payment or tenant-added confirmation already works.
 *
 * The Communication Center's email on/off rule for this event is enforced
 * centrally in `DispatchEventNotification::handle()`, not here.
 */
class SendAccessPassCreatedEmail
{
    public function handle(AccessPassCreated $event): void
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
                'valid_from'  => optional($pass->valid_from)->format('d M Y') ?? '',
                'valid_until' => optional($pass->valid_until)->format('d M Y') ?? 'No end date',
                'view_url'    => route('access-passes.show', $pass),
            ];

            DispatchEventNotification::dispatch(
                'access_pass_created',
                ['email' => $user->email, 'name' => $user->name, 'role' => $user->getRoleNames()->first() ?? 'user'],
                $variables,
                $pass->society_id,
                "access_pass_created:{$pass->id}:{$user->email}",
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::error('Failed to send access pass created email', [
                'pass_id' => $event->accessPass->id ?? 'unknown',
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
