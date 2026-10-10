<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `app/Console/Commands/SendBillReminders.php` (scheduled daily) sends
 * through `EmailNotificationService::sendByTrigger('bill-due-reminder', ...)`
 * - a second, slug-keyed template system, separate from the
 * `trigger_event`-keyed one the 2026_10_07_000002 migration seeded for
 * NoticePublished and friends. Same failure mode, same fix: no migration
 * ever created a 'bill-due-reminder' row, so on any environment that was
 * never hand-seeded with it (every fresh install, and every test), the
 * command's own lookup finds nothing and silently sends nothing - on top of
 * the undefined-method and missing-permission bugs fixed in the same
 * commit as this migration.
 *
 * Content matches the row already present in this project's dev database
 * (added by some earlier, undocumented, non-migration seeding), not newly
 * invented copy.
 *
 * This is one row for one confirmed, fixed call site. The slug-keyed system
 * has other call sites (welcome-user, reset-password, new-bill-generated,
 * payment-received, ticket-*, new-notice, new-event, visitor-*) that were
 * not audited here - they may have the same gap and are flagged, not fixed,
 * in this pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('email_templates')
            ->where('slug', 'bill-due-reminder')
            ->whereNull('society_id')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('email_templates')->insert([
            'society_id' => null,
            'name'       => 'Bill Due Reminder',
            'slug'       => 'bill-due-reminder',
            'subject'    => 'Bill Due Reminder - {{society_name}}',
            'body'       => '<h2>Bill Due Reminder</h2>'
                . '<p>Dear {{user_name}},</p>'
                . '<p>This is a friendly reminder that your {{bill_type}} bill is due soon.</p>'
                . '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">'
                . '<strong>Bill Amount:</strong> {{amount}}<br>'
                . '<strong>Due Date:</strong> {{due_date}}<br>'
                . '<strong>Days Remaining:</strong> {{days_remaining}}'
                . '</div>'
                . '<div style="text-align: center; margin: 30px 0;">'
                . '<a href="{{view_url}}" style="background: #ffc107; color: #212529; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Pay Now</a>'
                . '</div>'
                . '<p>Please make the payment before the due date to avoid late fees.</p>'
                . '<p>Best regards,<br>{{society_name}} Team</p>',
            'variables'  => json_encode(['user_name', 'society_name', 'bill_type', 'amount', 'due_date', 'days_remaining', 'view_url']),
            'type'       => 'system',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('slug', 'bill-due-reminder')
            ->whereNull('society_id')
            ->where('type', 'system')
            ->delete();
    }
};
