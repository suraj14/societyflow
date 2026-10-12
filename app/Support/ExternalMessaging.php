<?php

namespace App\Support;

/**
 * Single answer to "may this process talk to a real mail/SMS/WhatsApp
 * provider?". See config/messaging.php.
 */
class ExternalMessaging
{
    public const SUPPRESSED_MESSAGE = 'External messaging is disabled in this environment (EXTERNAL_MESSAGING_ENABLED=false).';

    /**
     * Tests install a guard here (tests/TestCase.php). It is called immediately before any real outbound provider
     * request and may throw, so an automated test can never reach a real SMS / WhatsApp provider by accident.
     * Always null in production.
     */
    public static ?\Closure $outboundGuard = null;

    public static function enabled(): bool
    {
        return (bool) config('messaging.enabled', true);
    }

    /** Called right before a real outbound request leaves the process. */
    public static function beforeOutbound(string $channel, string $host): void
    {
        if (self::$outboundGuard !== null) {
            (self::$outboundGuard)($channel, $host);
        }
    }
}
