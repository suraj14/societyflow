<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Templates for the 3 genuinely new notification events wired this round
 * (P1-61): access_pass_created, access_pass_revoked,
 * facility_booking_status_updated. `bill_generated` needs no new row here -
 * it was already seeded by 2026_10_07_000002 when BillGenerated was still
 * dead; this migration only reactivated its dispatch, not its template.
 *
 * Same pattern as every other template-seeding migration in this project:
 * global (society_id = null), idempotent (never touches a row that already
 * exists), so a society's own customisation is never overwritten.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        [
            'name'          => 'Access Pass Created',
            'slug'          => 'access-pass-created',
            'trigger_event' => 'access_pass_created',
            'subject'       => 'Gate pass created for {{person_name}} - {{society_name}}',
            'body'          => '<p>Dear {{user_name}},</p><p>A {{pass_type}} gate pass has been created for <strong>{{person_name}}</strong>.</p><p>Valid: {{valid_from}} to {{valid_until}}</p><p><a href="{{view_url}}">View Pass</a></p><p>Best regards,<br>{{society_name}} Team</p>',
            'variables'     => ['user_name', 'society_name', 'person_name', 'pass_type', 'valid_from', 'valid_until', 'view_url'],
        ],
        [
            'name'          => 'Access Pass Revoked',
            'slug'          => 'access-pass-revoked',
            'trigger_event' => 'access_pass_revoked',
            'subject'       => 'Gate pass revoked for {{person_name}} - {{society_name}}',
            'body'          => '<p>Dear {{user_name}},</p><p>The {{pass_type}} gate pass for <strong>{{person_name}}</strong> has been revoked and can no longer be used to enter the society.</p><p>If you did not expect this, please contact your society office.</p><p>Best regards,<br>{{society_name}} Team</p>',
            'variables'     => ['user_name', 'society_name', 'person_name', 'pass_type'],
        ],
        [
            'name'          => 'Facility Booking Status Updated',
            'slug'          => 'facility-booking-status-updated',
            'trigger_event' => 'facility_booking_status_updated',
            'subject'       => 'Booking {{booking_number}} is now {{booking_status}} - {{society_name}}',
            'body'          => '<p>Dear {{user_name}},</p><p>Your booking <strong>{{booking_number}}</strong> for {{facility_name}} on {{booking_date}} is now <strong>{{booking_status}}</strong>.</p><p>{{admin_notes}}</p><p><a href="{{view_url}}">View Booking</a></p><p>Best regards,<br>{{society_name}} Team</p>',
            'variables'     => ['user_name', 'society_name', 'facility_name', 'booking_number', 'booking_date', 'booking_status', 'admin_notes', 'view_url'],
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::TEMPLATES as $t) {
            $exists = DB::table('email_templates')
                ->where('trigger_event', $t['trigger_event'])
                ->whereNull('society_id')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('email_templates')->insert([
                'society_id'    => null,
                'name'          => $t['name'],
                'slug'          => $t['slug'],
                'subject'       => $t['subject'],
                'body'          => $t['body'],
                'variables'     => json_encode($t['variables']),
                'type'          => 'system',
                'status'        => 'active',
                'trigger_event' => $t['trigger_event'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('trigger_event', array_column(self::TEMPLATES, 'trigger_event'))
            ->whereNull('society_id')
            ->where('type', 'system')
            ->delete();
    }
};
