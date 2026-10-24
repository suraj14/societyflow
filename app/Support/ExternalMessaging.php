<?php

namespace App\Support;

/**
 * Single answer to "may this process talk to a real mail/SMS/WhatsApp
 * provider?". See config/messaging.php.
 *
 * Two independent gates, both enforced at the point a request would leave the process:
 *   1. the kill switch  (EXTERNAL_MESSAGING_ENABLED, default OFF) - nothing at all is sent while it is off;
 *   2. the test allow-list (EXTERNAL_MESSAGING_ALLOWLIST) - when the variable is present, only the listed people can receive anything.
 *
 * The allow-list fails closed: present but empty means nobody; ANY malformed entry (an empty item, a bad e-mail address, a number
 * that is not an Indian mobile) invalidates the whole list, so nobody is allowed until it is fixed. Matching is exact after
 * normalisation (lower-cased e-mail; a 10-digit Indian mobile with an optional 91 / 0 prefix) - never a substring, wildcard, domain
 * or partial-number match, and never a number with some other country code.
 */
class ExternalMessaging
{
    public const SUPPRESSED_MESSAGE = 'External messaging is disabled in this environment (EXTERNAL_MESSAGING_ENABLED=false).';

    public const NOT_ALLOWED_MESSAGE = 'Not sent: this recipient is not on the test allow-list (EXTERNAL_MESSAGING_ALLOWLIST).';

    public const ALLOWLIST_INVALID_MESSAGE = 'Not sent: EXTERNAL_MESSAGING_ALLOWLIST contains an invalid entry, so no recipient is allowed until it is corrected.';

    /** Why a message was blocked, as a stable code the screens and logs can tell apart from a provider fault. */
    public const BLOCKED_DISABLED = 'messaging_disabled';
    public const BLOCKED_NOT_ALLOWLISTED = 'not_allowlisted';

    /**
     * Tests install a guard here (tests/TestCase.php). It is called immediately before any real outbound provider
     * request and may throw, so an automated test can never reach a real SMS / WhatsApp provider by accident.
     * Always null in production.
     */
    public static ?\Closure $outboundGuard = null;

    public static function enabled(): bool
    {
        return (bool) config('messaging.enabled', false);
    }

    /** True when real delivery is limited to an allow-list (EXTERNAL_MESSAGING_ALLOWLIST is present, even if empty). */
    public static function restricted(): bool
    {
        return config('messaging.allowlist') !== null;
    }

    /**
     * Normalise one contact: an e-mail address (lower-cased, must be valid) or an Indian mobile (10 digits starting 6-9, with an
     * optional +91 / 91 / 0 prefix). Null when it is neither - which is never allowed.
     */
    public static function normalizeContact(?string $contact): ?string
    {
        $contact = trim((string) $contact);
        if ($contact === '') {
            return null;
        }
        if (str_contains($contact, '@')) {
            $email = strtolower($contact);

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }
        if (!preg_match('/^[+0-9\s().\-]+$/', $contact)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $contact) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9][0-9]{9}$/', $digits) ? $digits : null;
    }

    /**
     * @return array{entries: list<string>, invalid: list<string>}  invalid = items that could not be normalised (never echoes more than a short hint)
     */
    public static function parseAllowlist(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return ['entries' => [], 'invalid' => []];          // present but empty: nobody
        }
        $entries = [];
        $invalid = [];
        foreach (explode(',', $raw) as $i => $token) {
            $n = self::normalizeContact($token);
            if ($n === null) {
                $invalid[] = 'item ' . ($i + 1);
            } else {
                $entries[$n] = true;
            }
        }

        // array_keys() would turn a numeric key such as a phone number into an int, which a strict comparison then never matches
        return ['entries' => array_map('strval', array_keys($entries)), 'invalid' => $invalid];
    }

    /** Null when the allow-list is absent or valid; otherwise a short reason (shown to the operator, never the contents). */
    public static function allowlistProblem(): ?string
    {
        if (!self::restricted()) {
            return null;
        }
        $parsed = self::parseAllowlist(self::rawAllowlist());

        return $parsed['invalid'] ? 'EXTERNAL_MESSAGING_ALLOWLIST has invalid ' . implode(', ', $parsed['invalid']) . '; all recipients are blocked until it is fixed.' : null;
    }

    private static function rawAllowlist(): string
    {
        $v = config('messaging.allowlist');

        return is_array($v) ? implode(',', $v) : (string) $v;      // an array is accepted for tests; production config is the raw string
    }

    /**
     * May a real message go to this e-mail address or phone number? Always true when no allow-list is configured; with one, only an
     * exact (normalised) match of a valid list - and never when the list has an invalid entry.
     */
    public static function allows(?string $contact): bool
    {
        if (!self::restricted()) {
            return true;
        }
        $parsed = self::parseAllowlist(self::rawAllowlist());
        if ($parsed['invalid']) {
            return false;
        }
        $n = self::normalizeContact($contact);

        return $n !== null && in_array($n, $parsed['entries'], true);
    }

    /** The reason code and message for a blocked send, whichever gate stopped it. */
    public static function blockedReason(?string $contact = null): ?array
    {
        if (!self::enabled()) {
            return ['code' => self::BLOCKED_DISABLED, 'message' => self::SUPPRESSED_MESSAGE];
        }
        if ($contact !== null && !self::allows($contact)) {
            return ['code' => self::BLOCKED_NOT_ALLOWLISTED, 'message' => self::allowlistProblem() ? self::ALLOWLIST_INVALID_MESSAGE : self::NOT_ALLOWED_MESSAGE];
        }

        return null;
    }

    /**
     * The JSON a "Send test" endpoint answers when a gate stopped it. `blocked` lets the screen show a neutral "blocked by the
     * safety switch" notice instead of a red provider error.
     */
    public static function blockedResponse(string $code, ?string $message = null): array
    {
        return [
            'success' => false, 'blocked' => true, 'reason' => $code, 'details' => null,
            // what to do about it, so the notice is a way forward and not a dead end
            'hint' => $code === self::BLOCKED_DISABLED
                ? 'Delivery is off by default. To test on this computer, run "php artisan messaging:status" and follow docs/LOCAL_MESSAGING_TESTING.md (enable it with a test allow-list). On a live server the operator sets EXTERNAL_MESSAGING_ENABLED=true.'
                : 'Add this recipient to EXTERNAL_MESSAGING_ALLOWLIST only if they are an approved tester, or correct the list if it has an invalid entry (php artisan messaging:status shows which).',
            'message' => 'Not sent: ' . ltrim(preg_replace('/^Not sent:\s*/', '', $message ?? ($code === self::BLOCKED_DISABLED ? self::SUPPRESSED_MESSAGE : self::NOT_ALLOWED_MESSAGE))),
        ];
    }

    /** Recipients named in a provider request (query string or JSON body); none for account / template look-ups. @return list<string> */
    public static function recipientsOf(string $url, ?array $json = null): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $found = [];
        foreach (['numbers', 'number', 'mobile', 'mobile_number', 'msisdn', 'to', 'phone', 'recipient', 'recipients'] as $key) {
            foreach ([$q[$key] ?? null, $json[$key] ?? null] as $v) {
                foreach ((array) $v as $item) {
                    if (is_string($item) || is_int($item)) {
                        $found = array_merge($found, array_filter(array_map('trim', explode(',', (string) $item)), fn ($x) => $x !== ''));
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    /** False when any recipient of this request is off the allow-list (and so nothing may be sent). */
    public static function requestAllowed(string $url, ?array $json = null): bool
    {
        foreach (self::recipientsOf($url, $json) as $r) {
            if (!self::allows($r)) {
                return false;
            }
        }

        return true;
    }

    /** Called right before a real outbound request leaves the process. */
    public static function beforeOutbound(string $channel, string $host): void
    {
        if (self::$outboundGuard !== null) {
            (self::$outboundGuard)($channel, $host);
        }
    }
}
