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
        // the "Send SMS" checkbox on the notice: unticked means no SMS for this notice
        if (!$event->notice->send_sms) {
            return;
        }

        $this->send($event->notice);
    }

    /** SMS to the notice's audience (also used by an explicit resend; SendOnce still stops a copy inside the duplicate window). */
    public function send(\App\Models\Notice $notice): void
    {
        try {
            $societyId = $notice->society_id;

            // Active accounts of THIS society with a valid mobile number, once per number (two accounts on one phone get one SMS).
            $users = User::where('society_id', $societyId)
                ->where('status', 'active')
                ->whereNotNull('phone')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', self::ALLOWED_ROLES))
                ->get()
                ->filter(fn ($u) => preg_match('/^[0-9]{10}$/', SmsService::normalizePhone((string) $u->phone)) === 1)
                ->unique(fn ($u) => SmsService::normalizePhone((string) $u->phone))
                ->values();

            if ($users->isEmpty()) {
                return;
            }

            // A Message Templates -> SMS template for notices is filled from the named values below (see
            // App\Support\TemplateVariables). Without one, the legacy single DLT slot takes the title (see
            // SmsService::sendDltNotification) - the body itself is what the approved template already says.
            $message = \Illuminate\Support\Str::limit($notice->title ?? 'New notice published', 60, '');
            $societyName = (string) ($notice->society?->name ?? '');

            foreach ($users as $user) {
                SmsService::sendDltNotification('notification', $user->phone, $message, $societyId, false, 'notice', [
                    'resident_name' => (string) $user->name,
                    'notice_title'  => (string) ($notice->title ?? ''),
                    'society_name'  => $societyName,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send notice published SMS', [
                'notice_id' => $notice->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
