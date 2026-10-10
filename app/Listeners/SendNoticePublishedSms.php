<?php

namespace App\Listeners;

use App\Events\NoticePublished;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

/**
 * SMS for a published notice - a second, independent channel alongside
 * SendNoticePublishedEmail. Deliberately a separate listener, not a branch
 * inside that one: Laravel calls each of NoticePublished's listeners in
 * turn, and an uncaught exception in one stops the ones after it in that
 * dispatch. Two independent listeners, each defensively wrapped (the same
 * try/catch convention every listener here already uses), is what actually
 * gives "email failing does not stop SMS" - not a shared try/catch that
 * would still couple them.
 *
 * Gated by the notification_sms_enabled Settings toggle
 * (SmsService::sendDltNotification's own gate) - off by default, so this
 * sends nothing until an admin turns it on and configures a DLT template.
 */
class SendNoticePublishedSms
{
    private const ALLOWED_ROLES = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Admin'];

    public function handle(NoticePublished $event): void
    {
        try {
            $notice = $event->notice;
            $societyId = $notice->society_id;

            $users = User::where('society_id', $societyId)
                ->whereNotNull('phone')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ALLOWED_ROLES))
                ->get();

            if ($users->isEmpty()) {
                return;
            }

            // One DLT template, one variable slot (see SmsService::sendDltNotification) -
            // the title is what fits; the body itself is what the approved
            // template already says.
            $message = \Illuminate\Support\Str::limit($notice->title ?? 'New notice published', 60, '');

            foreach ($users as $user) {
                SmsService::sendDltNotification('notification', $user->phone, $message, $societyId);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send notice published SMS', [
                'notice_id' => $event->notice->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
