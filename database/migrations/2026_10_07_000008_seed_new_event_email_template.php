<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Found by NotificationConfigIntegrityTest, not a bug report: EventController::update
 * -> EmailNotificationService::sendEventNotification($event, 'updated') always looks
 * up slug 'new-event' (it never branches on $action the way sendVisitorNotification
 * does - see the comment on NotificationEvents::all()['event.updated']), and no
 * migration has ever seeded a template with that slug. The 2026_10_07_000002
 * migration seeded trigger_event='event_created' (slug 'event_created', underscore)
 * for the separate DynamicEmailService path, which is a different row entirely. A
 * fresh install or the MySQL production database has never had a 'new-event' row,
 * so "event updated" emails have been silently skipped there since the feature
 * shipped - the dev database.sqlite only has one because a legacy, non-migration
 * seeder was run by hand at some point. Reusing that exact content.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('email_templates')->where('slug', 'new-event')->whereNull('society_id')->exists();
        if ($exists) {
            return;
        }

        $now = now();

        DB::table('email_templates')->insert([
            'society_id' => null,
            'name'       => 'New Event',
            'slug'       => 'new-event',
            'subject'    => 'New Event - {{event_name}}',
            'body'       => <<<'HTML'
<h2>New Event Announcement</h2>
<p>Dear {{user_name}},</p>
<p>A new event has been organized in {{society_name}}:</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Event:</strong> {{event_name}}<br>
    <strong>Date:</strong> {{event_date}}<br>
    <strong>Time:</strong> {{event_time}}<br>
    <strong>Venue:</strong> {{event_venue}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #6f42c1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Event Details</a>
</div>
<p>We look forward to your participation!</p>
<p>Best regards,<br>{{society_name}} Team</p>
HTML,
            'variables'  => json_encode(['user_name', 'society_name', 'event_name', 'event_date', 'event_time', 'event_venue', 'view_url']),
            'type'       => 'system',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('slug', 'new-event')
            ->whereNull('society_id')
            ->where('type', 'system')
            ->delete();
    }
};
