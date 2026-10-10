<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email template for `event.changed` (NotificationEvents): an event was
 * cancelled, rescheduled or updated. Global (society_id = null) and
 * idempotent, like every other template-seeding migration here - a society's
 * own copy is never touched.
 *
 * Until this existed an edit re-sent the "new event" email, whose body says
 * "A new event has been created", so a cancellation read as an announcement.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('email_templates')
            ->where('trigger_event', 'event_changed')
            ->whereNull('society_id')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::table('email_templates')->insert([
            'society_id'    => null,
            'name'          => 'Event Cancelled / Rescheduled / Updated',
            'slug'          => 'event-changed',
            'subject'       => '{{event_name}} {{change_summary}} - {{society_name}}',
            'body'          => '<p>Dear {{user_name}},</p><p><strong>{{event_name}}</strong> {{change_summary}}.</p><p>Date: {{event_date}} {{event_time}}<br>Location: {{event_location}}</p><p><a href="{{view_url}}">View the event</a></p><p>Best regards,<br>{{society_name}} Team</p>',
            'variables'     => json_encode(['user_name', 'society_name', 'event_name', 'change_summary', 'change_kind', 'event_date', 'event_time', 'event_location', 'view_url']),
            'type'          => 'system',
            'status'        => 'active',
            'trigger_event' => 'event_changed',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('trigger_event', 'event_changed')
            ->whereNull('society_id')
            ->delete();
    }
};
