<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Found by NotificationConfigIntegrityTest. VisitorController ->
 * EmailNotificationService::sendVisitorNotification($visitor, 'denied') ->
 * sendByTrigger('visitor-denied', ...), and no migration ever seeded that
 * slug - same shape as 'ticket-created' and 'new-event' above, all three
 * existing only in the dev database.sqlite from a legacy, non-migration
 * seeder run by hand. A fresh install never gets a "your visitor was
 * denied" email. Reusing the exact dev-DB content.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('email_templates')->where('slug', 'visitor-denied')->whereNull('society_id')->exists();
        if ($exists) {
            return;
        }

        $now = now();

        DB::table('email_templates')->insert([
            'society_id' => null,
            'name'       => 'Visitor Denied',
            'slug'       => 'visitor-denied',
            'subject'    => 'Visitor Denied - {{society_name}}',
            'body'       => 'Dear {{user_name}}, Your visitor {{visitor_name}} ({{visitor_type}}) has been denied entry.',
            'variables'  => json_encode(['user_name', 'society_name', 'visitor_name', 'visitor_type', 'view_url']),
            'type'       => 'system',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('slug', 'visitor-denied')
            ->whereNull('society_id')
            ->where('type', 'system')
            ->delete();
    }
};
