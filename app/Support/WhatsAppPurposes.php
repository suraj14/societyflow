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
                'fields' => ['resident_name' => 'Resident name', 'visitor_name' => 'Visitor name', 'visit_date' => 'Visit date', 'society_name' => 'Society name'],
            ],
            'access_pass' => [
                'label'  => 'Gate pass updates',
                'help'   => 'When a gate pass is created or revoked.',
                'fields' => ['resident_name' => 'Resident name', 'person_name' => 'Pass holder', 'pass_status' => 'Status (created / revoked)', 'society_name' => 'Society name'],
            ],
            'complaint' => [
                'label'  => 'Complaint updates',
                'help'   => 'When a complaint changes status.',
                'fields' => ['resident_name' => 'Resident name', 'complaint_ref' => 'Complaint number', 'complaint_title' => 'Complaint title', 'complaint_status' => 'New status', 'society_name' => 'Society name'],
            ],
            'service_request' => [
                'label'  => 'Service request updates',
                'help'   => 'When a service request is assigned, started, completed or cancelled.',
                'fields' => ['resident_name' => 'Resident name', 'service_name' => 'Service', 'request_status' => 'New status', 'society_name' => 'Society name'],
            ],
            'bill' => [
                'label'  => 'New bill',
                'help'   => 'When a maintenance or rent bill is generated.',
                'fields' => ['resident_name' => 'Resident name', 'amount' => 'Amount', 'due_date' => 'Due date', 'bill_ref' => 'Bill number', 'society_name' => 'Society name'],
            ],
            'payment' => [
                'label'  => 'Payment received',
                'help'   => 'Confirms a payment to the resident who paid.',
                'fields' => ['amount' => 'Amount', 'resident_name' => 'Resident name', 'payment_date' => 'Payment date', 'receipt_ref' => 'Receipt number', 'society_name' => 'Society name'],
            ],
            'notice' => [
                'label'  => 'Important notices',
                'help'   => 'When a notice is published.',
                'fields' => ['resident_name' => 'Resident name', 'notice_title' => 'Notice title', 'society_name' => 'Society name'],
            ],
            'event' => [
                'label'  => 'Society events',
                'help'   => 'When an event is announced, rescheduled, updated or cancelled (owners of the event\'s audience).',
                'fields' => ['resident_name' => 'Resident name', 'event_name' => 'Event name', 'event_date' => 'Event date & time', 'event_status' => 'What happened (New event / Rescheduled / Updated / Cancelled)', 'society_name' => 'Society name'],
            ],
            'document' => [
                'label'  => 'Society rules & documents',
                'help'   => 'When a rules document is published with "Announce".',
                'fields' => ['resident_name' => 'Resident name', 'document_title' => 'Document title', 'document_version' => 'Version', 'society_name' => 'Society name'],
            ],
        ];
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
