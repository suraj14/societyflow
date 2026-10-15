<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The e-mails of the gate approval workflow (App\Support\VisitorNotifications), one system template per event, looked up
 * by trigger_event like every other DispatchEventNotification e-mail. "Visitor approved" already exists
 * (trigger_event visitor_approved) and is reused. A society can still edit its own copy; nothing here overwrites one.
 *
 * Every {{variable}} below is supplied by VisitorNotifications::email() for every one of these events, so no placeholder
 * can reach a recipient unresolved.
 */
return new class extends Migration
{
    private const VARIABLES = ['user_name', 'society_name', 'visitor_name', 'visitor_phone', 'visitor_type', 'unit', 'purpose', 'reason', 'actor_name', 'decision', 'visit_time', 'view_url'];

    private const TEMPLATES = [
        [
            'trigger' => 'visitor_request',
            'name'    => 'Visitor At The Gate',
            'subject' => 'Visitor at the gate for {{unit}} - {{society_name}}',
            'body'    => '<p>Dear {{user_name}},</p><p><strong>{{visitor_name}}</strong> ({{visitor_type}}, {{visitor_phone}}) is at the gate for <strong>{{unit}}</strong>.</p><p>Purpose: {{purpose}}</p><p>Please allow or deny entry from the SocietyFlow app: <a href="{{view_url}}">open the request</a>. The visitor is not let in until someone decides.</p><p>Best regards,<br>{{society_name}} Security</p>',
        ],
        [
            'trigger' => 'visitor_rejected',
            'name'    => 'Visitor Rejected',
            'subject' => 'Visitor turned away - {{society_name}}',
            'body'    => '<p>Dear {{user_name}},</p><p>{{visitor_name}} ({{visitor_type}}) who came for <strong>{{unit}}</strong> was turned away by {{actor_name}}.</p><p>Reason: {{reason}}</p><p><a href="{{view_url}}">View details</a></p><p>Best regards,<br>{{society_name}} Security</p>',
        ],
        [
            'trigger' => 'visitor_override',
            'name'    => 'Visitor Decision Changed',
            'subject' => 'Visitor decision changed by an administrator - {{society_name}}',
            'body'    => '<p>Dear {{user_name}},</p><p>An administrator, {{actor_name}}, changed the decision about <strong>{{visitor_name}}</strong> ({{visitor_type}}) for <strong>{{unit}}</strong>. The visitor is now: <strong>{{decision}}</strong>.</p><p>Reason given: {{reason}}</p><p><a href="{{view_url}}">View the full history</a></p><p>Best regards,<br>{{society_name}} Security</p>',
        ],
        [
            'trigger' => 'visitor_checked_in',
            'name'    => 'Visitor Entered',
            'subject' => 'Your visitor has entered - {{society_name}}',
            'body'    => '<p>Dear {{user_name}},</p><p>{{visitor_name}} ({{visitor_type}}) has been checked in at the gate to visit <strong>{{unit}}</strong>.</p><p><a href="{{view_url}}">View details</a></p><p>Best regards,<br>{{society_name}} Security</p>',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::TEMPLATES as $t) {
            if (DB::table('email_templates')->where('trigger_event', $t['trigger'])->whereNull('society_id')->exists()) {
                continue;
            }

            DB::table('email_templates')->insert([
                'society_id'    => null,
                'name'          => $t['name'],
                'slug'          => str_replace('_', '-', $t['trigger']),
                'subject'       => $t['subject'],
                'body'          => $t['body'],
                'variables'     => json_encode(self::VARIABLES),
                'type'          => 'system',
                'trigger_event' => $t['trigger'],
                'status'        => 'active',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('trigger_event', array_column(self::TEMPLATES, 'trigger'))
            ->whereNull('society_id')->where('type', 'system')
            ->delete();
    }
};
