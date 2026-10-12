<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Keeps one notification from reaching the same person twice. An event that fires twice, a queue retry after a send that
 * did succeed, or a double click all produce the same message to the same number for the same reason - only the first
 * one goes out. Only a SUCCESSFUL send is remembered, so a failed attempt can still be retried straight away, and a
 * send that is in flight blocks a parallel copy of itself.
 *
 *   $token = SendOnce::begin('sms', $societyId, $phone, 'notice', $content);
 *   if ($token === null) { ...already sent (or being sent): do nothing... }
 *   ... send ...
 *   SendOnce::finish($token, $succeeded);
 *
 * "Same message" = same channel, society, recipient number, notification type and content. A different notice, or the
 * same notice once the window (config messaging.duplicate_window, seconds) has passed, is a new message. Login OTPs do
 * not use this: every code is different and a resend is a deliberate request.
 */
class SendOnce
{
    public static function begin(string $channel, ?int $societyId, string $recipient, string $purpose, string $content): ?string
    {
        $key = 'sendonce:' . sha1(implode('|', [$channel, (string) $societyId, preg_replace('/\D+/', '', $recipient), $purpose, $content]));

        if (Cache::has($key . ':sent')) {
            return null;
        }

        // an identical send already in flight (parallel workers, a double dispatch) - the lock expires on its own
        return Cache::add($key . ':busy', 1, 60) ? $key : null;
    }

    public static function finish(?string $token, bool $succeeded): void
    {
        if ($token === null) {
            return;
        }

        Cache::forget($token . ':busy');
        if ($succeeded) {
            Cache::put($token . ':sent', 1, max(1, (int) config('messaging.duplicate_window', 300)));
        }
    }
}
