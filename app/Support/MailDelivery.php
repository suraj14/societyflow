<?php

namespace App\Support;

/**
 * What an e-mail send actually amounted to, for the delivery log.
 *
 * Mail::send() returns normally in several very different situations, and the log used to say "sent" for all of them:
 *
 *   sent       handed to a real mail server (SMTP accepted it) - NOT proof the recipient received it
 *   blocked    deliberately not sent: external messaging is switched off (kill switch), or the recipient is not on the test
 *              allow-list. `reason` says which, so it is never mistaken for a provider fault
 *   simulated  rendered and written to the local log file only (the "log" mailer) - nothing left the server
 *
 * Nothing here ever reports "delivered": no mail provider confirms inbox delivery to this application.
 *
 * The "array" transport (and a test's Mail::fake()) is the in-memory capture the automated tests use; it is a test double that
 * really did receive the message and is never a real mailer, so it is recorded as "sent" and tests that run the real job can
 * assert the pipeline end to end.
 *
 * Call AFTER the society's mail settings were applied: they decide the active mailer.
 */
class MailDelivery
{
    public const SENT = 'sent';
    public const BLOCKED = 'blocked';
    public const SIMULATED = 'simulated';

    /** @return array{status: string, reason: ?string, code: ?string} */
    public static function outcome(?string $recipient = null): array
    {
        $transport = config('mail.mailers.' . config('mail.default') . '.transport');

        if ($transport === 'array' || \Illuminate\Support\Facades\Mail::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\MailFake) {
            return ['status' => self::SENT, 'reason' => null, 'code' => null];
        }
        if (!ExternalMessaging::enabled()) {
            return ['status' => self::BLOCKED, 'reason' => 'Blocked: ' . ExternalMessaging::SUPPRESSED_MESSAGE, 'code' => ExternalMessaging::BLOCKED_DISABLED];
        }
        if ($transport === 'log') {
            return ['status' => self::SIMULATED, 'reason' => 'Simulated: the mailer is the local log file, so nothing was sent.', 'code' => null];
        }
        if ($recipient !== null && !ExternalMessaging::allows($recipient)) {
            $why = ExternalMessaging::allowlistProblem() ? ExternalMessaging::ALLOWLIST_INVALID_MESSAGE : ExternalMessaging::NOT_ALLOWED_MESSAGE;

            return ['status' => self::BLOCKED, 'reason' => 'Blocked: ' . preg_replace('/^Not sent:\s*/', '', $why), 'code' => ExternalMessaging::BLOCKED_NOT_ALLOWLISTED];
        }

        return ['status' => self::SENT, 'reason' => null, 'code' => null];
    }

    public static function statusAfterSend(?string $recipient = null): string
    {
        return self::outcome($recipient)['status'];
    }

    /** Anything that is not a real hand-off to a mail server. */
    public static function isNotSent(string $status): bool
    {
        return in_array($status, [self::BLOCKED, self::SIMULATED], true);
    }
}