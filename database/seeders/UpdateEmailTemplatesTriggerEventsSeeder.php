<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class UpdateEmailTemplatesTriggerEventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Map of slug to trigger_event
        $mappings = [
            'welcome-user' => 'user_registered',
            'user_welcome' => 'user_registered',
            'reset-password' => 'password_reset',
            'password_reset' => 'password_reset',
            'new-bill-generated' => 'bill_generated',
            'payment-received' => 'payment_success',
            'new-notice' => 'notice_published',
            'new-event' => 'event_created',
            'ticket-created' => 'ticket_updated',
            'ticket-updated' => 'ticket_updated',
            'visitor-approved' => 'visitor_approved',
            'payment-overdue' => 'payment_overdue',
            'tenant-owner-added' => 'tenant_owner_added',
            'report-generated' => 'report_generated',
        ];

        foreach ($mappings as $slug => $event) {
            EmailTemplate::where('slug', $slug)
                ->whereNull('trigger_event')
                ->update(['trigger_event' => $event]);
        }
    }
}
