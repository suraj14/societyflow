<?php

namespace App\Support;

/**
 * Plain-language reasons for the errors Meta's WhatsApp Cloud API reports through Fast2SMS - often inside an HTTP 200
 * answer, e.g. `(#100) Tried accessing nonexistent field (whatsapp_business_profile) on node type (WhatsAppBusinessAccount)`.
 * The provider's own words stay available as the technical detail; this only adds what to do about it. It never decides
 * success: a caller must already have seen the answer is an error.
 */
class WhatsAppProviderError
{
    /** The numeric code inside "(#100)" or an `error.code`, or null. */
    public static function code(string $message, $codeField = null): ?int
    {
        if (is_numeric($codeField)) {
            return (int) $codeField;
        }

        return preg_match('/\(#(\d{2,6})\)/', $message, $m) ? (int) $m[1] : null;
    }

    /** What the manager should do, or null when there is nothing more useful than the provider's own text. */
    public static function explain(string $message, $codeField = null): ?string
    {
        $code = self::code($message, $codeField);
        $lower = strtolower($message);

        // (#100) on the profile / phone-number endpoints: the id is not a WhatsApp phone number's id.
        if ($code === 100 && str_contains($lower, 'nonexistent field')) {
            return 'Fast2SMS answered that this ID is not a WhatsApp phone number. The Phone Number ID is probably the Business Account (WABA) ID or another ID - copy the Phone Number ID shown for your WhatsApp number in Fast2SMS and save it.';
        }

        return match (true) {
            $code === 100                                  => 'Fast2SMS rejected one of the values sent (' . ($code ? "code {$code}" : '') . '). Check the Phone Number ID and the template details.',
            $code === 190 || str_contains($lower, 'invalid authorization') || str_contains($lower, 'access token') => 'Fast2SMS did not accept the API key. Copy it again from Fast2SMS -> Dev API.',
            $code === 132000                               => 'The template expects a different number of values than were sent. Open the template, check its text and variables, and sync again.',
            $code === 132001                               => 'WhatsApp has no approved template with this name and language. Check the exact approved name and language, or sync templates.',
            $code === 132005 || $code === 132007           => 'The filled-in message was refused (too long or against WhatsApp policy). Check the values and the template text.',
            $code === 132012                               => 'A value is in a format the template does not accept. Check the variables.',
            $code === 132015 || $code === 132016           => 'This template is paused or disabled by WhatsApp. Use another approved template.',
            $code === 131026                               => 'The recipient could not receive the message (not on WhatsApp, or has not accepted the terms).',
            $code === 131047                               => 'More than 24 hours since the customer last wrote: only an approved template can be sent.',
            $code === 131048 || $code === 131056 || $code === 130429 || $code === 80007 => 'WhatsApp is rate-limiting this number. Try again later.',
            $code === 131030                               => 'The recipient number is not on the allowed list of this (test) WhatsApp number.',
            $code === 131031 || $code === 131005           => 'This WhatsApp number or key is not permitted to send. Check the account in Fast2SMS.',
            default                                        => null,
        };
    }

    /**
     * What an HTTP status means for the person fixing it. 400 and 404 are answers about the REQUEST (nothing to retry);
     * 429 and 5xx are the provider's state (try later); 0 means nothing came back. Null for a status with no advice.
     */
    public static function forHttp(int $status): ?string
    {
        return match (true) {
            $status === 401 || $status === 403 => 'Fast2SMS did not accept the API key (or the key is not allowed to use WhatsApp). Copy the key again from Fast2SMS -> Dev API.',
            $status === 404                    => 'Fast2SMS does not know this Phone Number ID (or Business Account). Use the Phone Number ID of one of the WhatsApp numbers on your account: press "Verify configuration" to list them, or find it in the Fast2SMS panel under WhatsApp.',
            $status === 400 || $status === 422 => 'Fast2SMS refused the request as sent: check the template (Message ID, number of values) and the Phone Number ID. This was not retried.',
            $status === 429                    => 'Fast2SMS is rate-limiting this account. Wait a few minutes and try again.',
            $status >= 500                     => 'Fast2SMS had a problem on its side. Try again later; nothing was confirmed.',
            $status === 0                      => 'Could not reach Fast2SMS (no answer or timed out). Check the internet connection and try again.',
            default                            => null,
        };
    }

    /**
     * The message for a rejected request: what to do (from the provider's error code, else from the HTTP status) followed
     * by the provider's own sanitised words. A provider error inside an HTTP 200 uses the code; 400/404/429/5xx the status.
     */
    public static function describe(string $safeMessage, int $httpStatus, $codeField = null): string
    {
        $advice = self::explain($safeMessage, $codeField) ?? ($httpStatus !== 200 ? self::forHttp($httpStatus) : null);

        return $advice ? trim($advice . ' (' . $safeMessage . ')') : $safeMessage;
    }

    /** Appends the advice to the provider's own (already sanitised) text. */
    public static function withAdvice(string $safeMessage, $codeField = null): string
    {
        $advice = self::explain($safeMessage, $codeField);

        return $advice ? trim($advice . ' (' . $safeMessage . ')') : $safeMessage;
    }
}
