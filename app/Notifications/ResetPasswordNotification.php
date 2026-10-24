<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The mailed "reset your password" link: Laravel's own message, queued.
 *
 * Queued so that, wherever a queue worker runs, a temporary SMTP failure is retried (three tries, with a pause) and a
 * permanent one lands in failed_jobs instead of being lost. On a `sync` queue it still runs inside the request, which is
 * why AuthController::forgotPassword catches a failure itself and answers the same way either way.
 *
 * The link is built by the parent (route 'password.reset' + token + email, valid for config('auth.passwords.users.expire')
 * minutes, one use); this class does not log or store the token.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use \Illuminate\Bus\Queueable;

    public int $tries = 3;

    /** @return list<int> seconds to wait before the second and third try */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->afterCommit = true;
    }

    /**
     * Sent through the account's society mail settings. Someone asking for a reset link is signed out, so nothing has
     * applied those settings to the request (LoadSocietyEmailSettings only runs for a signed-in user) - without this the
     * link went out through the bare .env mailer, which on a server configured only through Settings is a placeholder.
     */
    public function toMail($notifiable)
    {
        $message = parent::toMail($notifiable);

        if ($mailer = \App\Services\EmailConfigurationService::mailerFor($notifiable->society_id ?? null)) {
            $message->mailer($mailer);
        }

        return $message;
    }
}