<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The dispatch pipeline for every one of these events was already correct:
 * a controller fires the event, EventServiceProvider maps it to a listener,
 * the listener resolves recipients and calls
 * DynamicEmailService::sendByEvent($triggerEvent, ...). What was missing is
 * what sendByEvent needs to actually send anything -
 * EmailTemplate::getTemplateForEvent() requires an *active* row whose
 * `trigger_event` column matches, and none existed. Settings -> Test Email
 * bypasses this entirely (Mail::raw(), no template), which is why that
 * button worked while every real event silently logged "No email template
 * configured" and sent nothing.
 *
 * A near-identical set of templates already existed in
 * AutoEmailTriggerTemplatesSeeder, but that seeder is not called from
 * DatabaseSeeder - CLAUDE.md's documented default db:seed path is
 * RolesAndPermissions/SystemSettings/Services/SimpleTwoAdmin only, email
 * content deliberately is not auto-seeded on every install - and even if it
 * were run, its rows never set `trigger_event`, only `slug`, so the lookup
 * this project's actually-wired pipeline performs would still have found
 * nothing. This migration, not that seeder, is the fix: a migration runs
 * exactly once in every environment including production, so the templates
 * exist the moment this deploys, without changing the documented default
 * seed path or requiring anyone to remember to run an extra command.
 *
 * Global (society_id = null): EmailTemplate::byEventAndSociety() falls back
 * to the null row when a society has not configured its own, so this is a
 * sensible default, not a hardcoded final wording - any society can
 * override it from Settings -> Email Templates, and this migration never
 * touches a row that already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templates = [
            [
                'name' => 'Welcome Email',
                'slug' => 'user_registered',
                'trigger_event' => 'user_registered',
                'subject' => 'Welcome to {{society_name}}!',
                'body' => '<p>Dear {{user_name}},</p><p>Welcome to {{society_name}}! Your account has been created.</p><p>Login: <a href="{{login_url}}">{{login_url}}</a></p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'temporary_password', 'login_url', 'society_name'],
            ],
            [
                'name' => 'Bill Generated',
                'slug' => 'bill_generated',
                'trigger_event' => 'bill_generated',
                'subject' => 'New Bill - {{bill_number}} - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>A new {{bill_type}} bill has been generated.</p><ul><li>Bill Number: {{bill_number}}</li><li>Amount: {{amount}}</li><li>Due Date: {{due_date}}</li></ul><p><a href="{{view_url}}">View Bill</a></p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'bill_number', 'bill_type', 'amount', 'due_date', 'view_url', 'society_name'],
            ],
            [
                'name' => 'Payment Successful',
                'slug' => 'payment_success',
                'trigger_event' => 'payment_success',
                'subject' => 'Payment Received - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>We have received your payment of {{amount}} on {{payment_date}}.</p><p><a href="{{receipt_url}}">View Receipt</a></p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'payment_id', 'amount', 'payment_date', 'receipt_url', 'society_name'],
            ],
            [
                'name' => 'Notice Published',
                'slug' => 'notice_published',
                'trigger_event' => 'notice_published',
                'subject' => 'New Notice - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>A new notice has been published in {{society_name}}.</p><p><strong>{{notice_title}}</strong></p><p>{{notice_content}}</p><p><a href="{{view_url}}">Read Full Notice</a></p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'notice_title', 'notice_content', 'view_url', 'society_name'],
            ],
            [
                'name' => 'Event Created',
                'slug' => 'event_created',
                'trigger_event' => 'event_created',
                'subject' => 'New Event - {{event_name}} - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>A new event has been scheduled in {{society_name}}.</p><ul><li>Event: {{event_name}}</li><li>Date: {{event_date}}</li><li>Time: {{event_time}}</li></ul><p><a href="{{view_url}}">View Event</a></p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'event_name', 'event_date', 'event_time', 'view_url', 'society_name'],
            ],
            [
                'name' => 'Payment Overdue',
                'slug' => 'payment_overdue',
                'trigger_event' => 'payment_overdue',
                'subject' => 'Payment Overdue - {{bill_number}} - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>Your {{bill_type}} bill {{bill_number}} of {{amount}} was due on {{due_date}} and is now {{days_overdue}} day(s) overdue.</p><p><a href="{{view_url}}">Pay Now</a></p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'bill_number', 'bill_type', 'amount', 'due_date', 'days_overdue', 'view_url', 'society_name'],
            ],
            [
                'name' => 'Complaint Update',
                'slug' => 'ticket_updated',
                'trigger_event' => 'ticket_updated',
                'subject' => 'Complaint #{{ticket_number}} Updated - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>Complaint #{{ticket_number}} ({{ticket_title}}) is now <strong>{{ticket_status}}</strong>.</p><p><a href="{{view_url}}">View Complaint</a></p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'ticket_number', 'ticket_title', 'ticket_status', 'ticket_category', 'view_url', 'society_name'],
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'password_reset',
                'trigger_event' => 'password_reset',
                'subject' => 'Password Reset Request - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>A password reset was requested for your account.</p><p><a href="{{reset_url}}">Reset Password</a></p><p>If you did not request this, you can ignore this email.</p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'reset_url', 'reset_token', 'society_name'],
            ],
            [
                'name' => 'Tenant / Owner Added',
                'slug' => 'tenant_owner_added',
                'trigger_event' => 'tenant_owner_added',
                'subject' => 'New Resident Added - {{tenant_name}} - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>{{tenant_name}} ({{tenant_phone}}) has been added to {{property_number}}.</p><p>Lease: {{lease_start_date}} to {{lease_end_date}}</p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'tenant_name', 'tenant_phone', 'property_number', 'lease_start_date', 'lease_end_date', 'society_name'],
            ],
            [
                'name' => 'Visitor Approved',
                'slug' => 'visitor_approved',
                'trigger_event' => 'visitor_approved',
                'subject' => 'Visitor Approved - {{visitor_name}} - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>Your visitor <strong>{{visitor_name}}</strong> ({{visitor_phone}}) has been approved for {{visit_date}} at {{visit_time}}.</p><p><a href="{{view_url}}">View Details</a></p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'visitor_name', 'visitor_phone', 'visit_date', 'visit_time', 'view_url', 'society_name'],
            ],
            [
                'name' => 'Report Generated',
                'slug' => 'report_generated',
                'trigger_event' => 'report_generated',
                'subject' => 'Report Ready - {{report_type}} - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p><p>Your requested {{report_type}} report, generated at {{generated_at}}, is ready.</p><p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'report_type', 'generated_at', 'society_name'],
            ],
        ];

        foreach ($templates as $t) {
            // society_id IS NULL is not matched by a plain where() in SQLite/MySQL
            // the same way, so this is checked explicitly rather than chained.
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
        $events = [
            'user_registered', 'bill_generated', 'payment_success', 'notice_published',
            'event_created', 'payment_overdue', 'ticket_updated', 'password_reset',
            'tenant_owner_added', 'visitor_approved', 'report_generated',
        ];

        DB::table('email_templates')
            ->whereIn('trigger_event', $events)
            ->whereNull('society_id')
            ->where('type', 'system')
            ->delete();
    }
};
