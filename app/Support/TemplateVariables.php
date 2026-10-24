<?php

namespace App\Support;

/**
 * The one registry of message-template variables, per notification context. Every variable listed is a value a real
 * SocietyFlow event supplies when it sends (see the SMS/WhatsApp listeners and OtpAuthController); nothing is invented
 * for a flow that does not exist. The SMS template form, its validation, the live preview and the send path all read
 * this, so what a manager can insert is exactly what is available at send time.
 *
 * WhatsApp's notification contexts (App\Support\WhatsAppPurposes) already declare the fields each event provides; the
 * SMS contexts reuse those field definitions rather than repeating them. Login OTP is the one SMS-only context.
 *
 * Placeholder syntax is {{name}}. For a DLT template, placeholders are filled into variables_values in the order they
 * appear in the body - which must match the order of the {#var#} slots in the approved DLT text.
 */
class TemplateVariables
{
    /** A believable value for each variable, only ever shown in the editor's preview. */
    private const SAMPLES = [
        'otp'              => '4821',
        'resident_name'    => 'Asha Rao',
        'society_name'     => 'Alpha Heights',
        'notice_title'     => 'Water supply off 2-4 PM',
        'person_name'      => 'Ravi (driver)',
        'pass_status'      => 'revoked',
        'document_title'   => 'Society Rules',
        'document_version' => 'v2',
        'complaint_ref'    => 'C102',
        'complaint_status' => 'In Progress',
        'bill_label'       => 'Maintenance October 2026',
        'amount_plain'     => '2500',
    ];

    /**
     * SMS notification contexts that have a real sender today.
     *
     * @return array<string, array{label: string, help: string, audience: string, variables: array<string,string>, required: list<string>, via: string}>
     */
    public static function sms(): array
    {
        $wa = WhatsAppPurposes::all();

        return [
            'login_otp' => [
                'label'     => 'Login OTP',
                'help'      => 'The code sent when someone signs in by phone.',
                'audience'  => 'resident',
                'variables' => ['otp' => 'The one-time code', 'society_name' => 'Society name'],
                'required'  => ['otp'],
                'via'       => 'OTP login',
            ],
            'notice' => [
                'label'     => 'Notice published',
                'help'      => 'When a notice is published (needs "notifications by SMS" switched on in SMS settings).',
                'audience'  => 'resident',
                'variables' => $wa['notice']['fields'],
                'required'  => [],
                'via'       => 'a published notice',
            ],
            'gate_pass' => [
                'label'     => 'Gate pass revoked',
                'help'      => 'Tells the resident who created a gate pass that it was revoked.',
                'audience'  => 'resident',
                'variables' => $wa['access_pass']['fields'],
                'required'  => [],
                'via'       => 'a revoked gate pass',
            ],
            // The two below have a real sender (BusinessNotifications) and an approved telecom text (App\Support\DltTemplates).
            // They send only through a template with a Message ID, never as Quick SMS.
            'complaint' => [
                'label'     => 'Complaint status update',
                'help'      => 'When a complaint changes status, to the resident who raised it. Needs a template with a Fast2SMS Message ID.',
                'audience'  => 'resident',
                'variables' => ['complaint_ref' => 'Complaint number', 'complaint_status' => 'New status', 'society_name' => 'Society name'],
                'required'  => ['complaint_ref', 'complaint_status'],
                'via'       => 'a complaint status change',
            ],
            'bill' => [
                'label'     => 'Bill generated',
                'help'      => 'When a bill is generated, to the resident billed. Needs a template with a Fast2SMS Message ID.',
                'audience'  => 'resident',
                'variables' => ['bill_label' => 'Bill type and month, e.g. Maintenance October 2026', 'amount_plain' => 'Amount in whole rupees, digits only', 'society_name' => 'Society name'],
                'required'  => ['bill_label', 'amount_plain'],
                'via'       => 'a generated bill',
            ],
            'document' => [
                'label'     => 'Society document published',
                'help'      => 'When a rules document is published with "Announce".',
                'audience'  => 'resident',
                'variables' => $wa['document']['fields'],
                'required'  => [],
                'via'       => 'a published document',
            ],
        ];
    }

    /** @return array<string, string> purpose key => label */
    public static function smsLabels(): array
    {
        return array_map(fn ($p) => $p['label'], self::sms());
    }

    /** @return array<string,string> variable key => description, for one SMS purpose ([] if the purpose is unknown) */
    public static function forSms(string $purpose): array
    {
        return self::sms()[$purpose]['variables'] ?? [];
    }

    /** The preview value for a variable. */
    public static function sample(string $variable): string
    {
        return self::SAMPLES[$variable] ?? ucwords(str_replace('_', ' ', $variable));
    }

    /** Names of the {{placeholders}} in a body, in order of appearance (repeats kept: each is its own DLT slot). */
    public static function placeholders(string $body): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $body, $m);

        return $m[1];
    }

    /**
     * Problems with an SMS template body for a purpose, as plain sentences ([] when fine):
     *  - a {{placeholder}} that is not available for this kind of message,
     *  - a half-written placeholder such as "{{otp" or "{{ }}",
     *  - a variable this kind of message cannot work without (the OTP code for a login OTP).
     *
     * @return list<string>
     */
    public static function problems(string $purpose, string $body): array
    {
        $registry = self::sms()[$purpose] ?? null;
        if ($registry === null) {
            return ['Choose what this message is for.'];
        }

        $problems = [];
        $names = self::placeholders($body);

        // any "{{" or "}}" that is not part of a complete {{name}}
        $stripped = preg_replace('/\{\{\s*[A-Za-z0-9_]+\s*\}\}/', '', $body) ?? $body;
        if (str_contains($stripped, '{{') || str_contains($stripped, '}}')) {
            $problems[] = 'A variable is not written correctly. Use the form {{name}}, with two curly brackets on each side.';
        }

        $unknown = array_values(array_unique(array_filter($names, fn ($n) => !array_key_exists($n, $registry['variables']))));
        if ($unknown) {
            $problems[] = 'Not available for "' . $registry['label'] . '": ' . implode(', ', array_map(fn ($n) => '{{' . $n . '}}', $unknown))
                . '. You can use: ' . implode(', ', array_map(fn ($n) => '{{' . $n . '}}', array_keys($registry['variables']))) . '.';
        }

        foreach ($registry['required'] as $required) {
            if (!in_array($required, $names, true)) {
                $problems[] = 'This message must include {{' . $required . '}} (' . ($registry['variables'][$required] ?? $required) . ').';
            }
        }

        return $problems;
    }
}
