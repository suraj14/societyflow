<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The exact same bug class the Notice and bill-due-reminder migrations
 * fixed, found this time by building the canonical event registry +
 * configuration integrity test (see NotificationConfigIntegrityTest) rather
 * than by a bug report: `EmailNotificationService::sendServiceRequestNotification()`
 * (app/Http/Controllers/ServiceController.php, Api/ServiceApiController.php -
 * both real, already-dispatched call sites from this project's Service
 * Request work) calls `sendByTrigger("service-request-{$action}", ...)` for
 * assigned/in_progress/completed/cancelled, and no template with any of
 * those slugs has ever existed. Every service request status email has
 * been silently skipped since that feature shipped.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        [
            'slug'    => 'service-request-assigned',
            'name'    => 'Service Request Assigned',
            'subject' => 'Your service request has been assigned - {{society_name}}',
            'body'    => '<p>Dear {{user_name}},</p><p>Your request for <strong>{{service_name}}</strong> has been assigned to a provider.</p><p><a href="{{view_url}}">View Request</a></p><p>Best regards,<br>{{society_name}} Team</p>',
        ],
        [
            'slug'    => 'service-request-in_progress',
            'name'    => 'Service Request In Progress',
            'subject' => 'Your service request is in progress - {{society_name}}',
            'body'    => '<p>Dear {{user_name}},</p><p>Work has started on your request for <strong>{{service_name}}</strong>.</p><p><a href="{{view_url}}">View Request</a></p><p>Best regards,<br>{{society_name}} Team</p>',
        ],
        [
            'slug'    => 'service-request-completed',
            'name'    => 'Service Request Completed',
            'subject' => 'Your service request is complete - {{society_name}}',
            'body'    => '<p>Dear {{user_name}},</p><p>Your request for <strong>{{service_name}}</strong> has been completed.</p><p><a href="{{view_url}}">View Request</a></p><p>Best regards,<br>{{society_name}} Team</p>',
        ],
        [
            'slug'    => 'service-request-cancelled',
            'name'    => 'Service Request Cancelled',
            'subject' => 'Your service request was cancelled - {{society_name}}',
            'body'    => '<p>Dear {{user_name}},</p><p>Your request for <strong>{{service_name}}</strong> has been cancelled.</p><p><a href="{{view_url}}">View Request</a></p><p>Best regards,<br>{{society_name}} Team</p>',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::TEMPLATES as $t) {
            $exists = DB::table('email_templates')->where('slug', $t['slug'])->whereNull('society_id')->exists();
            if ($exists) {
                continue;
            }

            DB::table('email_templates')->insert([
                'society_id' => null,
                'name'       => $t['name'],
                'slug'       => $t['slug'],
                'subject'    => $t['subject'],
                'body'       => $t['body'],
                'variables'  => json_encode(['user_name', 'society_name', 'service_name', 'view_url']),
                'type'       => 'system',
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('slug', array_column(self::TEMPLATES, 'slug'))
            ->whereNull('society_id')
            ->where('type', 'system')
            ->delete();
    }
};
