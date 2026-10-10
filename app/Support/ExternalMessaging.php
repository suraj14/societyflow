<?php

namespace App\Support;

/**
 * Single answer to "may this process talk to a real mail/SMS/WhatsApp
 * provider?". See config/messaging.php.
 */
class ExternalMessaging
{
    public const SUPPRESSED_MESSAGE = 'External messaging is disabled in this environment (EXTERNAL_MESSAGING_ENABLED=false).';

    public static function enabled(): bool
    {
        return (bool) config('messaging.enabled', true);
    }
}
