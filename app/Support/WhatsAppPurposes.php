<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * The WhatsApp notification types a manager can attach an approved template
 * to, in plain language. Backend-only mapping: each key is the `purpose`
 * WhatsAppService::sendNotification() is called with from a real business
 * event, and `fields` are the values that event supplies, in the order used
 * when a template's {{1}}..{{n}} are filled by default.
 *
 * Only types with a real sender are listed. A type with no template
 * assigned is simply skipped - nothing else needs configuring.
 *
 * Assignments live in the existing settings keys
 * `whatsapp_{purpose}_template_name` (the technical template name the send
 * API needs) and `whatsapp_{purpose}_variables` (which field fills each
 * {{n}}), so earlier configuration keeps working.
 */
class WhatsAppPurposes
{
    public static function all(): array
    {
        return [
            'visitor' => [
                'label'  => 'Visitor approved',
                'help'   => 'Tells a resident their visitor was approved.',
                'fields' => ['resident_name' => 'Resident name', 'visitor_name' => 'Visitor name', 'visit_date' => 'Visit date', 'society_name' => 'Society name', 'society_phone' => 'Society phone (security contact)'],
            ],
            'access_pass' => [
                'label'  => 'Gate pass updates',
                'help'   => 'When a gate pass is created or revoked.',
                'fields' => ['resident_name' => 'Resident name', 'person_name' => 'Pass holder', 'pass_status' => 'Status (created / revoked)', 'society_name' => 'Society name'],
            ],
            'complaint' => [
                'label'  => 'Complaint updates',
                'help'   => 'When a complaint changes status.',
                'fields' => ['resident_name' => 'Resident name', 'complaint_ref' => 'Complaint number', 'complaint_title' => 'Complaint title', 'complaint_status' => 'New status', 'society_name' => 'Society name', 'complaint_update' => 'Latest update (resolution note, else the new status)'],
            ],
            'service_request' => [
                'label'  => 'Service request updates',
                'help'   => 'When a service request is assigned, started, completed or cancelled.',
                'fields' => ['resident_name' => 'Resident name', 'service_name' => 'Service', 'request_status' => 'New status', 'society_name' => 'Society name'],
            ],
            'bill' => [
                'label'  => 'New bill',
                'help'   => 'When a maintenance or rent bill is generated.',
                'fields' => ['resident_name' => 'Resident name', 'amount' => 'Amount', 'due_date' => 'Due date', 'bill_ref' => 'Bill number', 'society_name' => 'Society name', 'bill_period' => 'Bill month', 'amount_number' => 'Amount (number only, no symbol)'],
            ],
            'payment' => [
                'label'  => 'Payment received',
                'help'   => 'Confirms a payment to the resident who paid.',
                'fields' => ['amount' => 'Amount', 'resident_name' => 'Resident name', 'payment_date' => 'Payment date', 'receipt_ref' => 'Receipt number', 'society_name' => 'Society name', 'amount_number' => 'Amount (number only, no symbol)'],
            ],
            'notice' => [
                'label'  => 'Important notices',
                'help'   => 'When a notice is published.',
                'fields' => ['resident_name' => 'Resident name', 'notice_title' => 'Notice title', 'society_name' => 'Society name'],
            ],
            'event' => [
                'label'  => 'Society events',
                'help'   => 'When an event is announced, rescheduled, updated or cancelled (owners of the event\'s audience).',
                'fields' => ['resident_name' => 'Resident name', 'event_name' => 'Event name', 'event_date' => 'Event date & time', 'event_status' => 'What happened (New event / Rescheduled / Updated / Cancelled)', 'society_name' => 'Society name', 'event_day' => 'Event day', 'event_time' => 'Event time', 'event_location' => 'Event location'],
            ],
            'document' => [
                'label'  => 'Society rules & documents',
                'help'   => 'When a rules document is published with "Announce".',
                'fields' => ['resident_name' => 'Resident name', 'document_title' => 'Document title', 'document_version' => 'Version', 'society_name' => 'Society name'],
            ],
        ];
    }

    /**
     * What a manager can attach a template to. `service_request` is left out: Service Requests is no longer a workflow, so
     * no new template can be made for it (a row that already carries it keeps working and still shows its label).
     */
    public static function selectable(): array
    {
        return array_diff_key(self::all(), ['service_request' => true]);
    }

    /** An example value for a field, for previews only (never sent). */
    public static function sample(string $field): string
    {
        return [
            'visitor_name' => 'Ravi (courier)', 'visit_date' => '15 Oct, 10:00 AM', 'complaint_ref' => 'CMP-1042', 'complaint_title' => 'Water leakage',
            'complaint_status' => 'In progress', 'service_name' => 'Plumbing', 'request_status' => 'Assigned', 'amount' => '2,700', 'due_date' => '15 Oct 2026',
            'bill_ref' => 'MB-2026-0042', 'payment_date' => '12 Oct 2026', 'receipt_ref' => 'RCP-0042', 'event_name' => 'Diwali get-together',
            'event_date' => '25 Oct, 6:00 PM', 'event_status' => 'New event', 'society_phone' => '98765 43210', 'complaint_update' => 'Plumber visit booked',
            'bill_period' => 'October 2026', 'amount_number' => '2,700.00', 'event_day' => '25 Oct 2026', 'event_time' => '06:00 PM', 'event_location' => 'Clubhouse',
        ][$field] ?? TemplateVariables::sample($field);
    }

    /** Who receives a notification of this type (decided by the sending code, not per template). */
    public static function recipient(?string $purpose): string
    {
        return [
            'visitor' => 'The resident being visited', 'access_pass' => 'The resident who owns the pass', 'complaint' => 'The resident who raised it',
            'service_request' => 'The resident who asked', 'bill' => 'The resident billed', 'payment' => 'The resident who paid',
            'notice' => 'Residents the notice is for', 'event' => 'Owners in the event\'s audience', 'document' => 'Owners of the society',
        ][(string) $purpose] ?? 'Not assigned';
    }

    public static function exists(string $purpose): bool
    {
        return isset(self::all()[$purpose]);
    }

    /** Field keys filling {{1}}..{{n}}: the saved choice, else the default order. */
    public static function variableMap(string $purpose, int $varCount, ?int $societyId): array
    {
        $saved = self::readSetting("whatsapp_{$purpose}_variables", $societyId);
        if (is_string($saved)) {
            $saved = json_decode($saved, true);
        }
        if (is_array($saved) && count($saved) === $varCount) {
            return array_values($saved);
        }

        return array_slice(array_keys(self::all()[$purpose]['fields'] ?? []), 0, $varCount);
    }

    private static function readSetting(string $key, ?int $societyId)
    {
        $row = SystemSetting::query()->where('group', 'whatsapp')->where('key', $key)
            ->where(fn ($q) => $societyId === null ? $q->whereNull('society_id') : $q->where('society_id', $societyId))
            ->first();

        return $row ? ($row->type === 'json' ? json_decode($row->value, true) : $row->value) : null;
    }
}
