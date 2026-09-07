<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class ComprehensiveEmailTemplateSeeder extends Seeder
{
    public function run()
    {
        $templates = [
            // VISITOR MANAGEMENT TEMPLATES
            [
                'name' => 'Visitor Entry Created',
                'slug' => 'visitor-entry-created',
                'subject' => 'Visitor Entry Approved - {{society_name}}',
                'body' => $this->getVisitorEntryCreatedTemplate(),
                'variables' => ['visitor_name', 'host_name', 'entry_date', 'entry_time', 'purpose', 'society_name', 'gate_number'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'visitor_management',
                'recipients' => 'Host, Security Staff'
            ],
            [
                'name' => 'Visitor Status Updated',
                'slug' => 'visitor-status-updated',
                'subject' => 'Visitor Status Update - {{visitor_name}}',
                'body' => $this->getVisitorStatusUpdatedTemplate(),
                'variables' => ['visitor_name', 'host_name', 'old_status', 'new_status', 'updated_by', 'society_name', 'remarks'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'visitor_management',
                'recipients' => 'Host, Security Staff, Admin'
            ],

            // NOTICE & EVENT TEMPLATES
            [
                'name' => 'New Notice Published',
                'slug' => 'new-notice',
                'subject' => 'New Notice: {{notice_title}} - {{society_name}}',
                'body' => $this->getNewNoticeTemplate(),
                'variables' => ['notice_title', 'notice_content', 'published_by', 'publish_date', 'priority', 'society_name', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'communication',
                'recipients' => 'All Residents, Owners, Tenants'
            ],
            [
                'name' => 'Notice Updated',
                'slug' => 'notice-updated',
                'subject' => 'Notice Updated: {{notice_title}} - {{society_name}}',
                'body' => $this->getNoticeUpdatedTemplate(),
                'variables' => ['notice_title', 'notice_content', 'updated_by', 'update_date', 'changes_made', 'society_name', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'communication',
                'recipients' => 'All Residents, Owners, Tenants'
            ],
            [
                'name' => 'New Event Created',
                'slug' => 'new-event',
                'subject' => 'New Event: {{event_name}} - {{society_name}}',
                'body' => $this->getNewEventTemplate(),
                'variables' => ['event_name', 'event_description', 'event_date', 'event_time', 'venue', 'organizer', 'society_name', 'registration_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'events',
                'recipients' => 'All Residents, Owners, Tenants'
            ],
            [
                'name' => 'Event Updated',
                'slug' => 'event-updated',
                'subject' => 'Event Update: {{event_name}} - {{society_name}}',
                'body' => $this->getEventUpdatedTemplate(),
                'variables' => ['event_name', 'event_description', 'event_date', 'event_time', 'venue', 'changes_made', 'society_name', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'events',
                'recipients' => 'Registered Participants, All Residents'
            ],
            [
                'name' => 'Event Reminder',
                'slug' => 'event-reminder',
                'subject' => 'Reminder: {{event_name}} Tomorrow - {{society_name}}',
                'body' => $this->getEventReminderTemplate(),
                'variables' => ['event_name', 'event_date', 'event_time', 'venue', 'what_to_bring', 'contact_person', 'society_name'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'events',
                'recipients' => 'Registered Participants'
            ],

            // COMPLAINT/TICKET MANAGEMENT TEMPLATES
            [
                'name' => 'Ticket Created',
                'slug' => 'ticket-created',
                'subject' => 'Ticket Created: #{{ticket_number}} - {{society_name}}',
                'body' => $this->getTicketCreatedTemplate(),
                'variables' => ['ticket_number', 'ticket_title', 'category', 'priority', 'description', 'created_by', 'created_date', 'society_name', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'support',
                'recipients' => 'Ticket Creator, Admin'
            ],
            [
                'name' => 'Ticket Assigned',
                'slug' => 'ticket-assigned',
                'subject' => 'Ticket Assigned: #{{ticket_number}} - {{society_name}}',
                'body' => $this->getTicketAssignedTemplate(),
                'variables' => ['ticket_number', 'ticket_title', 'assigned_to', 'assigned_by', 'priority', 'due_date', 'society_name', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'support',
                'recipients' => 'Assigned Staff, Ticket Creator, Admin'
            ],
            [
                'name' => 'Ticket Status Updated',
                'slug' => 'ticket-status-updated',
                'subject' => 'Ticket Update: #{{ticket_number}} - {{new_status}}',
                'body' => $this->getTicketStatusUpdatedTemplate(),
                'variables' => ['ticket_number', 'ticket_title', 'old_status', 'new_status', 'updated_by', 'update_notes', 'society_name', 'view_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'support',
                'recipients' => 'Ticket Creator, Assigned Staff, Admin'
            ],
            [
                'name' => 'Ticket Closed',
                'slug' => 'ticket-closed',
                'subject' => 'Ticket Resolved: #{{ticket_number}} - {{society_name}}',
                'body' => $this->getTicketClosedTemplate(),
                'variables' => ['ticket_number', 'ticket_title', 'resolution', 'resolved_by', 'resolution_date', 'feedback_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'support',
                'recipients' => 'Ticket Creator, Assigned Staff'
            ],

            // BILLING & PAYMENT TEMPLATES
            [
                'name' => 'New Bill Generated',
                'slug' => 'new-bill-generated',
                'subject' => 'New {{bill_type}} Bill Generated - {{society_name}}',
                'body' => $this->getNewBillGeneratedTemplate(),
                'variables' => ['bill_type', 'bill_number', 'amount', 'due_date', 'billing_period', 'flat_number', 'owner_name', 'society_name', 'payment_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'billing',
                'recipients' => 'Property Owner, Tenant (if applicable)'
            ],
            [
                'name' => 'Rent Bill Generated',
                'slug' => 'rent-bill-generated',
                'subject' => 'Monthly Rent Bill - {{billing_month}} - {{society_name}}',
                'body' => $this->getRentBillGeneratedTemplate(),
                'variables' => ['billing_month', 'rent_amount', 'due_date', 'flat_number', 'tenant_name', 'late_fee', 'society_name', 'payment_url'],
                'type' => 'system',
                'status' => 'active',
                'category' => 'billing',
                'recipients' => 'Tenant, Property Owner'
            ],
            [
                'name' => 'Payment Received',
                'slug' => 'payment-received',
                'subject' => 'Payment Received - Thank You! - {{society_name}}',
                'body' => $this->getPaymentReceivedTemplate(),
                'variables' => ['payment_amount', 'payment_date', 'payment_method', 'transaction_id', 'bill_typ