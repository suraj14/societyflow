<?php

namespace App\Support;

/**
 * The DLT-approved SMS texts SocietyFlow's own messages are sent as, copied from the telecom (Jio DLT) template screens.
 *
 * IMPORTANT - what this is and is not:
 *  - It is a REFERENCE taken from screenshots, not proof. Every entry says `verified => false`: nothing here has been compared
 *    with the live provider account, and "Active" on a screen does not guarantee the account that sends can use it.
 *  - `dlt_template_id` is the long telecom ID. It is NOT the Fast2SMS Message ID (a short number, saved per template as
 *    `dlt_message_id`); the two must never be swapped, and no Message ID is invented here.
 *  - It exists so the app can refuse a template whose wording or variable order differs from the registered text, and refuse a
 *    value the registered slot cannot carry, instead of sending a malformed SMS.
 *
 * Slot types follow the DLT editor: `alphanumeric` (letters, digits, spaces and light punctuation, at most 30 characters),
 * `number` (digits only, at most 30).
 */
class DltTemplates
{
    public const MAX_VALUE_LENGTH = 30;

    /** @return array<string, array{name:string, dlt_template_id:string, content:string, slots:array<int,array{field:string,type:string}>, verified:bool, enforce:bool}> */
    public static function all(): array
    {
        return [
            'complaint' => [
                'name' => 'societyflow_complaint_update',
                'dlt_template_id' => '1277179196367432151',
                'content' => 'SocietyFlow: Complaint {#alphanumeric#} status updated to {#alphanumeric#}. Please check the SocietyFlow app for details.',
                'slots' => [['field' => 'complaint_ref', 'type' => 'alphanumeric'], ['field' => 'complaint_status', 'type' => 'alphanumeric']],
                'verified' => false, 'enforce' => true,
            ],
            'bill' => [
                'name' => 'societyflow_bill_generated',
                'dlt_template_id' => '1277179196734446136',
                'content' => 'SocietyFlow: Your {#alphanumeric#} bill is Rs. {#number#}. Please check the SocietyFlow app for due date and payment details. Thank you.',
                'slots' => [['field' => 'bill_label', 'type' => 'alphanumeric'], ['field' => 'amount_plain', 'type' => 'number']],
                'verified' => false, 'enforce' => true,
            ],
            'notice' => [
                'name' => 'societyflow_notice',
                'dlt_template_id' => '1277179196313898498',
                'content' => 'SocietyFlow: New notice {#alphanumeric#} published by your society. Please check the SocietyFlow app for details. Thank you.',
                'slots' => [['field' => 'notice_title', 'type' => 'alphanumeric']],
                'verified' => false, 'enforce' => true,
            ],
            // Reference only: the OTP flow has its own verified path (and a society's saved body may carry its own header), so it
            // is never rewritten or rejected because of this entry. The sample OTP shown on the telecom screen is never used.
            'login_otp' => [
                'name' => 'societyflow_login_otp',
                'dlt_template_id' => '1277179196034433976',
                'content' => 'RoyalPrime(Sainiweb): {#number#} is your OTP to verify your mobile number. Do not share this OTP with anyone.',
                'slots' => [['field' => 'otp', 'type' => 'number']],
                'verified' => false, 'enforce' => false,
            ],
        ];
    }

    public static function forPurpose(string $purpose): ?array
    {
        return self::all()[$purpose] ?? null;
    }

    /** A body reduced to its wording, every variable (either spelling) as one marker, whitespace collapsed. */
    private static function skeleton(string $text): string
    {
        $text = preg_replace('/\{\{\s*[A-Za-z0-9_]+\s*\}\}|\{#\s*[A-Za-z0-9_]+\s*#\}/', "\u{00A7}", $text) ?? $text;

        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        // "Rs. {#var#} ." and "Rs. {#var#}." are the same text: stray spaces before punctuation (common in a pasted telecom template) never matter
        return preg_replace('/\s+([.,;:!?])/u', '$1', $text) ?? $text;
    }

    /** Which registered purpose a template body is the wording of, if any (a body that matches none returns null). */
    public static function purposeOfBody(string $body): ?string
    {
        $skeleton = self::skeleton($body);
        foreach (self::all() as $purpose => $entry) {
            if ($skeleton === self::skeleton($entry['content'])) {
                return $purpose;
            }
        }

        return null;
    }

    /**
     * A body that is the registered wording of ANOTHER purpose, as a sentence (null when it is not). A template saved for "Society
     * document published" that reads "Your bill is Rs. ..." would send a bill text to every resident when a document is published.
     */
    public static function purposeConflict(string $purpose, string $body): ?string
    {
        $owner = self::purposeOfBody($body);
        if ($owner === null || $owner === $purpose) {
            return null;
        }
        $label = fn (string $p) => \App\Support\TemplateVariables::smsLabels()[$p] ?? $p;

        return 'This wording is the registered text for "' . $label($owner) . '", not for "' . $label($purpose) . '". Sending it for a ' . strtolower($label($purpose))
            . ' would tell residents something else. Choose "' . $label($owner) . '" as the type, or use this type\'s own approved wording.';
    }

    /**
     * Problems with a template body for a purpose that has a registered DLT text (empty = fine, or nothing registered/enforced).
     * Checked only for a template that goes out on the DLT route (it has a Message ID): that is the case where the provider sends
     * the REGISTERED text and the body here is only a preview, so a different body would misdescribe what residents receive.
     *
     * @return list<string>
     */
    public static function bodyProblems(string $purpose, string $body): array
    {
        $entry = self::forPurpose($purpose);
        if ($entry === null || !$entry['enforce']) {
            return [];
        }

        $problems = [];
        if (self::skeleton($body) !== self::skeleton($entry['content'])) {
            $problems[] = 'This purpose is registered with the telecom as: "' . self::asPlaceholders($entry) . '". A template with a Message ID is sent as exactly that text, so the wording here must match it.';

            return $problems;
        }
        $order = TemplateVariables::placeholders($body);
        $expected = array_column($entry['slots'], 'field');
        if ($order !== $expected) {
            $problems[] = 'The variables must appear in this order: ' . implode(', ', array_map(fn ($f) => '{{' . $f . '}}', $expected)) . '.';
        }

        return $problems;
    }

    /** The registered text with each slot shown as the {{field}} that fills it. */
    public static function asPlaceholders(array $entry): string
    {
        $i = 0;

        return preg_replace_callback('/\{#\s*[A-Za-z0-9_]+\s*#\}/', function () use (&$i, $entry) {
            return '{{' . $entry['slots'][$i++]['field'] . '}}';
        }, $entry['content']) ?? $entry['content'];
    }

    /**
     * Values that cannot fill their registered slot: missing, too long, or not the slot's type. A message is rejected whole rather
     * than sent with a truncated or reformatted value.
     *
     * @param array<string,string> $values
     * @return list<string> reasons (empty = every slot can carry its value)
     */
    public static function valueProblems(string $purpose, array $values): array
    {
        $entry = self::forPurpose($purpose);
        if ($entry === null || !$entry['enforce']) {
            return [];
        }

        $problems = [];
        foreach ($entry['slots'] as $slot) {
            $v = trim((string) ($values[$slot['field']] ?? ''));
            if ($v === '') {
                $problems[] = "no value for {{{$slot['field']}}}";
                continue;
            }
            if (mb_strlen($v) > self::MAX_VALUE_LENGTH) {
                $problems[] = "{{{$slot['field']}}} is longer than " . self::MAX_VALUE_LENGTH . ' characters';
            }
            $ok = $slot['type'] === 'number' ? preg_match('/^[0-9]+$/', $v) : preg_match('/^[A-Za-z0-9 .,\-\/]+$/', $v);
            if (!$ok) {
                $problems[] = "{{{$slot['field']}}} has characters this " . $slot['type'] . ' slot does not accept';
            }
        }

        return $problems;
    }
}
