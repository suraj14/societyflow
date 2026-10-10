<?php

namespace App\Listeners;

use App\Events\AccessPassRevoked;
use App\Services\SmsService;
use App\Support\NotificationRules;
use Illuminate\Support\Facades\Log;

/**
 * Second, independent channel for the same security-relevant event - see
 * SendAccessPassRevokedEmail's docblock for why this one matters. A
 * separate listener, not a branch inside that one, for the same reason
 * SendNoticePublishedSms is separate from SendNoticePublishedEmail: one
 * throwing must never stop the other from running.
 *
 * `SmsService::sendDltNotification()` already no-ops safely when
 * `notification_sms_enabled` is off or no DLT template is configured -
 * nothing here needs to re-check that.
 */
class SendAccessPassRevokedSms
{
    public function handle(AccessPassRevoked $event): void
    {
        try {
            $pass = $event->accessPass;
            $user = $pass->createdBy;

            if (!$user || !$user->phone) {
                return;
            }

            if (!NotificationRules::isEnabled('access_pass_revoked', 'sms', $pass->society_id)) {
                return;
            }

            $message = \Illuminate\Support\Str::limit(
                "Gate access for {$pass->person_name} has been revoked.",
                60,
                ''
            );

            SmsService::sendDltNotification('notification', $user->phone, $message, $pass->society_id);
        } catch (\Throwable $e) {
            Log::error('Failed to send access pass revoked SMS', [
                'pass_id' => $event->accessPass->id ?? 'unknown',
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
