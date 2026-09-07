<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class AddMissingEmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Ticket Updated',
                'slug' => 'ticket-updated',
                'subject' => 'Ticket {{ticket_number}} Updated - {{ticket_status}}',
                'body' => 'Dear {{user_name}}, Your complaint ticket {{ticket_number}} has been updated. Status: {{ticket_status}}. Category: {{ticket_category}}',
                'variables' => ['user_name', 'society_name', 'ticket_number', 'ticket_title', 'ticket_status', 'ticket_category', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'trigger_event' => 'ticket_updated'
            ],
            [
                'name' => 'Visitor Approved',
                'slug' => 'visitor-approved',
                'subject' => 'Visitor Approved - {{visitor_name}}',
                'body' => 'Dear {{user_name}}, Your visitor {{visitor_name}} has been approved. Visit Date: {{visit_date}} at {{visit_time}}',
                'variables' => ['user_name', 'society_name', 'visitor_name', 'visitor_phone', 'visit_date', 'visit_time', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'trigger_event' => 'visitor_approved'
            ],
            [
                'name' => 'Payment Overdue',
                'slug' => 'payment-overdue',
                'subject' => 'Payment Overdue - {{society_name}}',
                'body' => 'Dear {{user_name}}, Your payment for bill {{bill_number}} is now {{days_overdue}} days overdue. Amount due: {{amount}}. Please pay immediately.',
                'variables' => ['user_name', 'society_name', 'bill_number', 'amount', 'days_overdue', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'trigger_event' => 'payment_overdue'
            ],
            [
                'name' => 'Tenant/Owner Added',
                'slug' => 'tenant-owner-added',
                'subject' => 'New Tenant/Owner Added - {{tenant_name}}',
                'body' => 'Dear {{user_name}}, A new tenant/owner {{tenant_name}} has been added to property {{property_number}}. Lease period: {{lease_start_date}} to {{lease_end_date}}',
                'variables' => ['user_name', 'society_name', 'tenant_name', 'tenant_phone', 'property_number', 'lease_start_date', 'lease_end_date'],
                'type' => 'system',
                'status' => 'active',
                'trigger_event' => 'tenant_owner_added'
            ],
            [
                'name' => 'Report Generated',
                'slug' => 'report-generated',
                'subject' => 'Report Generated - {{report_type}}',
                'body' => 'Dear {{user_name}}, A {{report_type}} report has been generated on {{generated_at}}. Please review the attached report.',
                'variables' => ['user_name', 'society_name', 'report_type', 'generated_at', 'report_data'],
                'type' => 'system',
                'status' => 'active',
                'trigger_event' => 'report_generated'
            ]
        ];

        foreach ($templates as $template) {
            // Check if template already exists by slug
            $existing = EmailTemplate::where('slug', $template['slug'])->first();
            
            if (!$existing) {
                EmailTemplate::create($template);
            } else {
                // Update trigger_event if it's missing
                if (!$existing->trigger_event) {
                    $existing->update(['trigger_event' => $template['trigger_event']]);
                }
            }
        }
    }
}
