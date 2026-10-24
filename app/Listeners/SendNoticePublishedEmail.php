<?php

namespace App\Listeners;

use App\Events\NoticePublished;
use App\Services\DynamicEmailService;
use App\Jobs\DispatchEventNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendNoticePublishedEmail
{

    /**
     * Handle the event
     * 
     * Role-based recipient resolution for Notice Published:
     * - Owner: ✅
     * - Tenant: ✅
     * - Admin: ✅
     * - Staff: ❌
     * - Accountant: ❌
     */
    public function handle(NoticePublished $event): void
    {
        // the "Send Email" checkbox on the notice: unticked means no e-mail for this notice (in-app is separate)
        if (!$event->notice->send_email) {
            return;
        }

        $this->send($event->notice);
    }

    /**
     * Queue the e-mails of a notice. $generation is null for the first publication and a fresh token for an explicit resend, so a resend
     * gets its own idempotency keys (a double submit of the same token still sends once).
     */
    public function send(\App\Models\Notice $notice, ?string $generation = null): void
    {
        try {
            $societyId = $notice->society_id;

            // Allowed roles for notice published event
            $allowedRoles = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Admin'];

            // Get recipients based on target audience
            $recipients = [];
            
            // $users was previously left unset whenever target_audience was a
            // non-empty array with no overlap in $allowedRoles (e.g. every value
            // naming a role this event excludes) - the foreach below then ran on
            // an undefined variable, threw, and the catch silently logged the
            // notice as failed instead of sending to anyone. Starting from an
            // empty collection makes "no matching recipients" an empty send,
            // not an exception.
            $users = collect();
            if ($notice->target_audience && is_array($notice->target_audience)) {
                // Get users with specific roles that are in allowed list
                $allowedTargetRoles = array_intersect($notice->target_audience, $allowedRoles);
                if (!empty($allowedTargetRoles)) {
                    $users = User::active()->where('society_id', $societyId)
                        ->whereHas('roles', function($q) use ($allowedTargetRoles) {
                            $q->whereIn('name', $allowedTargetRoles);
                        })
                        ->get();
                }
            } else {
                // Get all allowed roles
                $users = User::active()->where('society_id', $societyId)
                    ->whereHas('roles', function($q) use ($allowedRoles) {
                        $q->whereIn('name', $allowedRoles);
                    })
                    ->get();
            }

            // Only people who can actually be written to, once each: an inactive account, an account with no e-mail address, or
            // two accounts sharing one address (a person with several roles) must not produce a send, a failure or a duplicate.
            $users = $users->filter(fn ($u) => filter_var($u->email, FILTER_VALIDATE_EMAIL))
                ->unique(fn ($u) => strtolower((string) $u->email))
                ->values();

            // Format recipients
            foreach ($users as $user) {
                $recipients[] = [
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->getRoleNames()->first() ?? 'user',
                ];
            }

            if (empty($recipients)) {
                Log::info('No recipients for notice published email', [
                    'notice_id' => $notice->id,
                ]);
                return;
            }

            // Prepare base variables (will be merged with per-recipient variables)
            $baseVariables = [
                'notice_title' => $notice->title ?? '',
                'notice_content' => substr($notice->content ?? '', 0, 200),
                'view_url' => route('notices.show', $notice->id) ?? '#',
            ];

            // Send email to each recipient with their name
            foreach ($recipients as $recipient) {
                $variables = array_merge($baseVariables, [
                    'user_name' => $recipient['name'],
                ]);

                DispatchEventNotification::dispatch(
                    'notice_published',
                    $recipient,
                    $variables,
                    $societyId,
                    $generation === null
                        ? "notice_published:{$notice->id}:{$recipient['email']}"
                        : "notice_resend:{$notice->id}:{$generation}:{$recipient['email']}",
                )->afterCommit();
            }

            Log::info('Notice published email sent', [
                'notice_id' => $notice->id,
                'recipient_count' => count($recipients),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notice published email', [
                'notice_id' => $notice->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
