<?php

namespace App\Listeners;

use App\Events\AccessPassRevoked;
use App\Services\WhatsAppService;
use App\Support\NotificationRules;
use Illuminate\Support\Facades\Log;

/**
 * Third channel for the same event. `WhatsAppService::sendNotification()`
 * already supports an 'access_pass' purpose - its Settings form
 * (`whatsapp_access_pass_template_name`) already existed before this slice,
 * built but never called from anywhere. This is its first real caller.
 * Safely no-ops (returns success without sending) when WhatsApp is
 * disabled or no template name is configured for this purpose - the same
 * "exists but nothing fires it yet" pattern SmsService::sendDltNotification
 * used to be in, not a new failure mode.
 */
class SendAccessPassRevokedWhatsApp
{
    public function handle(AccessPassRevoked $event): void
    {
        try {
            $pass = $event->accessPass;
            $user = $pass->createdBy;

            if (!$user || !$user->phone) {
                return;
            }

            if (!NotificationRules::isEnabled('access_pass_revoked', 'whatsapp', $pass->society_id)) {
                return;
            }

            WhatsAppService::sendNotification(
                'access_pass',
                $user->phone,
                [
                    'resident_name' => $user->name,
                    'person_name'   => $pass->person_name,
                    'pass_status'   => 'revoked',
                    'society_name'  => $pass->society?->name ?? '',
                ],
                $pass->society_id,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to send access pass revoked WhatsApp message', [
                'pass_id' => $event->accessPass->id ?? 'unknown',
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
