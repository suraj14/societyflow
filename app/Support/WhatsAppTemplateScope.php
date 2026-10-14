<?php

namespace App\Support;

use App\Models\WhatsAppTemplate;

/**
 * Decides which templates in a Fast2SMS account belong to SocietyFlow, and under which of its five categories.
 *
 * One Fast2SMS account can also carry another product's templates (BookMizo: bookings, slots, refunds, staff and customer
 * messages). A template is never taken as SocietyFlow's because of a name prefix. In order:
 *
 *   1. A template an admin typed in here (source manual) is SocietyFlow's - it was added on this page on purpose.
 *   2. A template whose own name or wording is about bookings, slots, refunds, customers, staff or appointments is
 *      BookMizo's, even when someone attached it to a notification: that attachment is a mistake, reported on the page,
 *      and the sender refuses it (see WhatsAppService::sendNotification).
 *   3. Otherwise the notification type it is attached to decides the category ("mapped").
 *   4. Otherwise its wording does - visitor, gate, OTP, bill, complaint, notice ... ("wording"); the card says so.
 *   5. Nothing recognisable: "unknown" - stays in the list under "Not sorted yet" (only BookMizo's go under "Other
 *      templates"), so a custom template of the society's own is never hidden.
 */
class WhatsAppTemplateScope
{
    public const SOCIETY = 'society';
    public const BOOKMIZO = 'bookmizo';
    public const UNKNOWN = 'unknown';

    public const OTP = 'otp';
    public const VISITORS = 'visitors';
    public const SERVICES = 'services';
    public const BILLS = 'bills';
    public const NOTICES = 'notices';

    public static function categories(): array
    {
        return [
            self::OTP      => 'Login & OTP',
            self::VISITORS => 'Visitors & Gate Security',
            self::SERVICES => 'Services & Complaints',
            self::BILLS    => 'Bills & Payments',
            self::NOTICES  => 'Notices, Events & Society Security',
        ];
    }

    /** The category of a notification type (WhatsAppPurposes key). */
    public static function purposeCategory(?string $purpose): ?string
    {
        return [
            'visitor' => self::VISITORS, 'access_pass' => self::VISITORS,
            'complaint' => self::SERVICES, 'service_request' => self::SERVICES,
            'bill' => self::BILLS, 'payment' => self::BILLS,
            'notice' => self::NOTICES, 'event' => self::NOTICES, 'document' => self::NOTICES,
        ][(string) $purpose] ?? null;
    }

    /** Words that mark another product's template (bookings, slots, refunds ...). */
    private const BOOKMIZO_WORDS = ['bookmizo', 'booking', 'slot', 'appointment', 'reschedul', 'refund', 'customer', 'venue', 'staff_', 'staff '];

    /** Words that mark a SocietyFlow category, checked in this order. */
    private const WORDING = [
        self::OTP      => ['otp', 'login', 'verification', 'one time', 'one_time', 'password'],
        self::VISITORS => ['visitor', 'gate', 'guest', 'access_pass', 'access pass', 'guard', 'delivery'],
        self::SERVICES => ['complaint', 'service', 'ticket', 'helpdesk'],
        self::BILLS    => ['bill', 'maintenance', 'invoice', 'dues', 'receipt', 'payment'],
        self::NOTICES  => ['notice', 'event', 'announcement', 'emergency', 'alert', 'security', 'circular', 'document', 'rules'],
    ];

    /**
     * @return array{scope: string, category: ?string, basis: string}  basis: manual | mapped | wording | none
     */
    public static function classify(WhatsAppTemplate $t): array
    {
        $mapped = self::purposeCategory($t->purpose);

        if ($t->isManual()) {
            return ['scope' => self::SOCIETY, 'category' => $mapped ?? self::fromWording($t) ?? self::NOTICES, 'basis' => 'manual'];
        }

        $text = strtolower($t->name . ' ' . $t->friendly_name . ' ' . $t->body_text);
        foreach (self::BOOKMIZO_WORDS as $word) {
            if (str_contains($text, $word)) {
                return ['scope' => self::BOOKMIZO, 'category' => null, 'basis' => 'wording'];
            }
        }

        if ($mapped) {
            return ['scope' => self::SOCIETY, 'category' => $mapped, 'basis' => 'mapped'];
        }

        $byWording = self::fromWording($t);

        return $byWording
            ? ['scope' => self::SOCIETY, 'category' => $byWording, 'basis' => 'wording']
            : ['scope' => self::UNKNOWN, 'category' => null, 'basis' => 'none'];
    }

    private static function fromWording(WhatsAppTemplate $t): ?string
    {
        $text = strtolower($t->name . ' ' . $t->friendly_name . ' ' . $t->body_text);
        foreach (self::WORDING as $category => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /** For an older settings value that is only a template name (no saved row to inspect): is it another product's? */
    public static function nameLooksBookMizo(string $name): bool
    {
        $text = strtolower($name);
        foreach (self::BOOKMIZO_WORDS as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    public static function isBookMizo(WhatsAppTemplate $t): bool
    {
        return self::classify($t)['scope'] === self::BOOKMIZO;
    }
}
