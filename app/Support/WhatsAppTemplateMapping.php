<?php

namespace App\Support;

/**
 * Which value fills each {{1}}, {{2}} ... of an approved template.
 *
 * An approved template fixes the order of its variables in its own wording ("Hello {{1}}, ... notice from {{2}}: {{3}}"),
 * and that order is rarely the order a notification happens to list its values in. So, unless the admin chose the values
 * by hand (saved on the template), each placeholder is matched to a field of the notification from the words just before
 * (or after) it. A placeholder no rule recognises takes the next field of the notification's own order, and the card, the
 * preview and the test send all show the result, so a wrong guess is visible and can be changed.
 */
class WhatsAppTemplateMapping
{
    /** Stands for "another placeholder" in the text a rule reads, so a neighbour's braces never leak into a rule. */
    private const MARK = "\x01";

    /**
     * [field, regex on the words before the placeholder, regex on the words after it]. First match wins; a rule only
     * applies when the notification really has that field and it is not taken already.
     */
    private const RULES = [
        ['resident_name',    '/\b(hello|hi|hey|dear|namaste)\s*,?\s*$/', null],
        ['society_phone',    '/\b(contact|call|phone|helpline|reach)\b[^:]{0,30}:?\s*$/', null],
        ['complaint_ref',    '/\b(complaint|ticket)\s*(#|no\.?|number|id)?\s*:?\s*#?$/', null],
        ['complaint_status', '/\bstatus\s*(is|:)?\s*(now)?\s*:?\s*$/', null],
        ['complaint_update', '/\b(update|remarks?|resolution|note)s?\s*:?\s*$/', null],
        ['visitor_name',     '/\b(visitor|guest)\s*:?\s*$/', null],
        ['visit_date',       '/\b(entry at|arrived at|arrives at|arrive at|visit(ing)? (on|at)|approved for|expected (at|on)|at)\s*$/', null],
        ['bill_ref',         '/\b(bill|invoice)\s*(#|no\.?|number)\s*:?\s*$/', null],
        ['bill_period',      '/\b(bill|invoice|dues|maintenance)\s+(for|of)\s*$/', null],
        ['amount_number',    '/(\brs\.?|\binr|₹)\s*$/', null],
        ['amount',           '/\b(amount|total|payment of|paid|pay)\s*:?\s*$/', null],
        ['due_date',         '/\b(pay by|due (on|by|date)?|before|by)\s*:?\s*$/', null],
        ['receipt_ref',      '/\breceipt\s*(#|no\.?|number)?\s*:?\s*$/', null],
        ['payment_date',     '/\bon\s*$/', null],
        ['society_name',     '/\b(regards,?|from|team)\s*$/', '/^\s+(invites|announces)\b/'],
        ['notice_title',     '/\b(notice|announcement|circular)\b[^:]{0,40}:\s*$/', null],
        ['event_name',       '/\b(invites you to|invited to|announcing|join us (for|at)|to)\s*$/', null],
        ['event_day',        '/\bon\s*$/', null],
        ['event_time',       '/\bat\s*$/', null],
        ['event_location',   '/\b(venue|location)\s*:?\s*$/', null],
        ['complaint_title',  '/\b(regarding|about|issue|titled)\s*:?\s*$/', null],
        ['document_title',   '/\b(document|rules|policy)\s*:?\s*$/', null],
    ];

    /**
     * The field for each placeholder number 1..$count, in order. Empty when the notification type is unknown.
     *
     * @return list<string>
     */
    public static function suggest(?string $purpose, string $body, int $count): array
    {
        $fields = array_keys(WhatsAppPurposes::all()[(string) $purpose]['fields'] ?? []);
        if ($fields === [] || $count < 1) {
            return [];
        }

        // the first occurrence of each placeholder number: where it starts and how long it is
        $at = [];
        if (preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => [$text, $offset]) {
                $at[(int) $m[1][$i][0]] ??= [$offset, strlen($text)];
            }
        }

        $out = [];
        foreach (range(1, $count) as $n) {
            $pick = null;
            if (isset($at[$n])) {
                [$offset, $length] = $at[$n];
                $before = substr(self::flatten(substr($body, 0, $offset)), -70);
                $after = substr(self::flatten(substr($body, $offset + $length)), 0, 30);

                foreach (self::RULES as [$field, $beforeRe, $afterRe]) {
                    if (!in_array($field, $fields, true) || in_array($field, $out, true)) {
                        continue;
                    }
                    if (($beforeRe && preg_match($beforeRe, $before)) || ($afterRe && preg_match($afterRe, $after))) {
                        $pick = $field;
                        break;
                    }
                }
            }
            // nothing in the wording says: the notification's own next value
            $pick ??= collect($fields)->first(fn ($f) => !in_array($f, $out, true));
            if ($pick === null) {
                break;
            }
            $out[] = $pick;
        }

        return $out;
    }

    private static function flatten(string $text): string
    {
        return strtolower((string) preg_replace('/\{\{\s*\d+\s*\}\}/', self::MARK, $text));
    }

    /** "{{1}} Resident name · {{2}} Bill month ..." for the card. */
    public static function describe(?string $purpose, array $fieldKeys): string
    {
        $labels = WhatsAppPurposes::all()[(string) $purpose]['fields'] ?? [];

        return collect($fieldKeys)->map(fn ($key, $i) => '{{' . ($i + 1) . '}} ' . ($labels[$key] ?? $key))->implode(' · ');
    }
}
