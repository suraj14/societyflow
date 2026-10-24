<?php

namespace App\Support;

use App\Models\User;

/**
 * Is this person a legitimate recipient of a SOCIETY notification? Checked at the point an e-mail is about to be handed to a mail
 * server, so it holds for every listener, job, retry and scheduled command - not only the ones that remembered to filter.
 *
 * A recipient is skipped (and the skip is logged with its reason - never silently dropped, never reported as sent) when:
 *  - the address is not a valid e-mail address;
 *  - it belongs to an account of THIS society that is not active (deactivated, blocked, left);
 *  - it belongs to a Super Admin and the message is a society notice (a Super Admin is not a resident or a committee member).
 *    Account mail to a Super Admin (welcome, password reset) is not a society notice and is not affected.
 *
 * An address with no account in this society (a free address typed into a "send test" box, or an external contact) is not judged here.
 */
class RecipientEligibility
{
    /** Notification keys that are about the account itself, so they may reach a Super Admin. */
    public const ACCOUNT_EVENTS = ['user_registered', 'password_reset', 'welcome-user', 'reset-password'];

    /** @return string|null the reason it must be skipped, or null when it may be sent */
    public static function emailSkipReason(?string $email, ?int $societyId, string $notificationKey = ''): ?string
    {
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'the e-mail address is not valid';
        }
        if ($societyId === null) {
            return null;
        }

        // Settings -> App -> "Enable e-mail notifications" is the society's master switch for society mail (account mail is exempt)
        if (!in_array($notificationKey, self::ACCOUNT_EVENTS, true)
            && !\App\Models\SystemSetting::get('app', 'enable_email_notifications', true, $societyId)) {
            return 'e-mail notifications are switched off for this society';
        }

        $accounts = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->where('society_id', $societyId)->get(['id', 'status']);
        if ($accounts->isNotEmpty() && $accounts->every(fn (User $u) => $u->status !== 'active')) {
            return 'the account is not active';
        }

        if (!in_array($notificationKey, self::ACCOUNT_EVENTS, true)
            && User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->get()->contains(fn (User $u) => $u->hasRole('Super Admin'))) {
            return 'a Super Admin is not a society recipient';
        }

        return null;
    }
}
