<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Found by NotificationConfigIntegrityTest, not a bug report: the
 * 'ticket-created' template (used by ComplaintController::store ->
 * EmailNotificationService::sendComplaintNotification() for every new
 * complaint) exists in this project's dev database.sqlite but no migration
 * ever seeded it - it was created by hand at some point. A fresh install,
 * or the MySQL production database, has no row for it at all, so every
 * "your complaint was received" email has been silently skipped there
 * since the feature shipped. Reusing the exact dev-DB content (same
 * subject/body/variables) so behaviour does not change anywhere it is
 * already working.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('email_templates')->where('slug', 'ticket-created')->whereNull('society_id')->exists();
        if ($exists) {
            return;
        }

        $now = now();

        DB::table('email_templates')->insert([
            'society_id' => null,
            'name'       => 'Ticket Created',
            'slug'       => 'ticket-created',
            'subject'    => 'Complaint Ticket Created - {{complaint_number}}',
            'body'       => <<<'HTML'
<h2>Complaint Ticket Created</h2>
<p>Dear {{user_name}},</p>
<p>Your complaint has been successfully submitted and a ticket has been created.</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Ticket Number:</strong> {{complaint_number}}<br>
    <strong>Category:</strong> {{complaint_category}}<br>
    <strong>Status:</strong> {{complaint_status}}<br>
    <strong>Created Date:</strong> {{created_date}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Track Ticket</a>
</div>
<p>We will review your complaint and get back to you soon.</p>
<p>Best regards,<br>{{society_name}} Team</p>
HTML,
            'variables'  => json_encode(['user_name', 'society_name', 'complaint_number', 'complaint_category', 'complaint_status', 'created_date', 'view_url']),
            'type'       => 'system',
            'status'     => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('slug', 'ticket-created')
            ->whereNull('society_id')
            ->where('type', 'system')
            ->delete();
    }
};
