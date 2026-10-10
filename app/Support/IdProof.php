<?php

namespace App\Support;

/**
 * What a gate may keep from a visitor's ID document.
 *
 * The mobile app already reduces an Aadhaar number to its last four digits
 * before it ever leaves the device (see `id_document.dart`), but this runs
 * again on arrival. A client is not a security boundary: anything that can
 * post to `/api/v1/visitors` could send the full twelve digits, and
 * `visitors.visitor_id_number` is not the place for them. Redacting on the
 * way in means the database cannot hold a complete Aadhaar even if a future
 * client, an old build, or a curl command tries to put one there.
 *
 * Every other document's reference is kept whole: a PAN, a licence number or
 * a passport number is the ordinary identifier a paper gate register has
 * always recorded, and truncating it would make the record useless without
 * protecting anything.
 */
class IdProof
{
    /** The document types `visitors.visitor_id_proof` accepts. */
    public const TYPES = ['aadhaar', 'driving_licence', 'pan', 'passport', 'other'];

    /** Only this one is reduced. */
    public const REDACTED_TYPES = ['aadhaar'];

    public static function redactsType(?string $type): bool
    {
        return in_array((string) $type, self::REDACTED_TYPES, true);
    }

    /**
     * The reference as it may be stored.
     *
     * An Aadhaar becomes "XXXX XXXX 1234". Fewer than four digits returns
     * null rather than a padded stub: a partial number is worse than none,
     * because it still reads like data.
     */
    public static function redact(?string $type, ?string $number): ?string
    {
        $number = is_string($number) ? trim($number) : null;

        if ($number === null || $number === '') {
            return null;
        }

        if (!self::redactsType($type)) {
            return $number;
        }

        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) < 4) {
            return null;
        }

        return 'XXXX XXXX ' . substr($digits, -4);
    }

    /**
     * The last ten digits of a phone number, which is how an Indian mobile is
     * matched regardless of whether it was saved as "+91 98765 43210",
     * "919876543210" or "9876543210".
     */
    public static function phoneDigits(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
