<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class ProductionEmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Auth & Users (5 templates)
            [
                'name' => 'User Welcome',
                'slug' => 'user_welcome',
                'subject' => 'Welcome to {{society_name}}',
                'body' => $this->getWelcomeTemplate(),
                'variables' => ['user_name', 'user_email', 'temporary_password', 'login_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Account Created by Admin',
                'slug' => 'account_created_by_admin',
                'subject' => 'Your Account Has Been Created - {{society_name}}',
                'body' => $this->getAccountCreatedTemplate(),
                'variables' => ['user_name', 'user_email', 'temporary_password', 'login_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'password_reset',
                'subject' => 'Password Reset Request - {{society_name}}',
                'body' => $this->getPasswordResetTemplate(),
                'variables' => ['user_name', 'reset_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Account Disabled',
                'slug' => 'account_disabled',
                'subject' => 'Your Account Has Been Disabled',
                'body' => $this->getAccountDisabledTemplate(),
                'variables' => ['user_name', 'society_name', 'reason'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Security Login Alert',
                'slug' => 'security_login_alert',
                'subject' => 'New Login to Your Account - {{society_name}}',
                'body' => $this->getSecurityLoginAlertTemplate(),
                'variables' => ['user_name', 'login_time', 'login_ip', 'login_device', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],

            // Owner/Tenant (5 templates)
            [
                'name' => 'Owner Added',
                'slug' => 'owner_added',
                'subject' => 'You Have Been Added as Property Owner - {{society_name}}',
                'body' => $this->getOwnerAddedTemplate(),
                'variables' => ['owner_name', 'property_name', 'property_address', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Tenant Added',
                'slug' => 'tenant_added',
                'subject' => 'You Have Been Added as Tenant - {{society_name}}',
                'body' => $this->getTenantAddedTemplate(),
                'variables' => ['tenant_name', 'property_name', 'property_address', 'lease_start', 'lease_end', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Tenant Assigned to Unit',
                'slug' => 'tenant_assigned_to_unit',
                'subject' => 'Tenant Assignment - {{society_name}}',
                'body' => $this->getTenantAssignedTemplate(),
                'variables' => ['tenant_name', 'property_name', 'assignment_date', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Tenant Removed',
                'slug' => 'tenant_removed',
                'subject' => 'Tenant Removal Notice - {{society_name}}',
                'body' => $this->getTenantRemovedTemplate(),
                'variables' => ['tenant_name', 'property_name', 'removal_date', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Ownership Changed',
                'slug' => 'ownership_changed',
                'subject' => 'Property Ownership Change - {{society_name}}',
                'body' => $this->getOwnershipChangedTemplate(),
                'variables' => ['property_name', 'new_owner_name', 'old_owner_name', 'change_date', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],

            // Bills & Payments (7 templates)
            [
                'name' => 'Maintenance Bill Generated',
                'slug' => 'maintenance_bill_generated',
                'subject' => 'Maintenance Bill Generated - {{society_name}}',
                'body' => $this->getMaintenanceBillTemplate(),
                'variables' => ['user_name', 'bill_number', 'amount', 'due_date', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Utility Bill Generated',
                'slug' => 'utility_bill_generated',
                'subject' => 'Utility Bill Generated - {{society_name}}',
                'body' => $this->getUtilityBillTemplate(),
                'variables' => ['user_name', 'bill_number', 'amount', 'due_date', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Rent Invoice Generated',
                'slug' => 'rent_invoice_generated',
                'subject' => 'Rent Invoice - {{society_name}}',
                'body' => $this->getRentInvoiceTemplate(),
                'variables' => ['tenant_name', 'property_name', 'rent_amount', 'due_date', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Payment Received',
                'slug' => 'payment_received',
                'subject' => 'Payment Received - Thank You',
                'body' => $this->getPaymentReceivedTemplate(),
                'variables' => ['user_name', 'amount', 'payment_date', 'receipt_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Payment Failed',
                'slug' => 'payment_failed',
                'subject' => 'Payment Failed - Action Required',
                'body' => $this->getPaymentFailedTemplate(),
                'variables' => ['user_name', 'amount', 'failure_reason', 'retry_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Bill Due Reminder',
                'slug' => 'bill_due_reminder',
                'subject' => 'Bill Due Reminder - {{society_name}}',
                'body' => $this->getBillDueReminderTemplate(),
                'variables' => ['user_name', 'bill_number', 'amount', 'due_date', 'days_remaining', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Upcoming Due Reminder',
                'slug' => 'upcoming_due_reminder',
                'subject' => 'Upcoming Payment Due - {{society_name}}',
                'body' => $this->getUpcomingDueReminderTemplate(),
                'variables' => ['user_name', 'amount', 'due_date', 'days_remaining', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],

            // Tickets/Services (4 templates)
            [
                'name' => 'Ticket Created',
                'slug' => 'ticket_created',
                'subject' => 'Ticket Created - {{complaint_number}}',
                'body' => $this->getTicketCreatedTemplate(),
                'variables' => ['user_name', 'complaint_number', 'complaint_title', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Ticket Assigned',
                'slug' => 'ticket_assigned',
                'subject' => 'Ticket Assigned to You - {{complaint_number}}',
                'body' => $this->getTicketAssignedTemplate(),
                'variables' => ['user_name', 'complaint_number', 'complaint_title', 'assigned_by', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Ticket Updated',
                'slug' => 'ticket_updated',
                'subject' => 'Ticket Updated - {{complaint_number}}',
                'body' => $this->getTicketUpdatedTemplate(),
                'variables' => ['user_name', 'complaint_number', 'status', 'update_message', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Ticket Closed',
                'slug' => 'ticket_closed',
                'subject' => 'Ticket Closed - {{complaint_number}}',
                'body' => $this->getTicketClosedTemplate(),
                'variables' => ['user_name', 'complaint_number', 'resolution', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],

            // Notices & Events (3 templates)
            [
                'name' => 'Notice Published',
                'slug' => 'notice_published',
                'subject' => 'New Notice - {{society_name}}',
                'body' => $this->getNoticePublishedTemplate(),
                'variables' => ['user_name', 'notice_title', 'notice_content', 'published_date', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Event Created',
                'slug' => 'event_created',
                'subject' => 'New Event - {{event_name}}',
                'body' => $this->getEventCreatedTemplate(),
                'variables' => ['user_name', 'event_name', 'event_date', 'event_time', 'event_location', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Event Reminder',
                'slug' => 'event_reminder',
                'subject' => 'Reminder: {{event_name}} is Coming Up',
                'body' => $this->getEventReminderTemplate(),
                'variables' => ['user_name', 'event_name', 'event_date', 'event_time', 'event_location', 'view_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],

            // Reports (2 templates)
            [
                'name' => 'Monthly Report Generated',
                'slug' => 'monthly_report_generated',
                'subject' => 'Monthly Report - {{month}} {{year}}',
                'body' => $this->getMonthlyReportTemplate(),
                'variables' => ['user_name', 'month', 'year', 'report_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Annual Report Generated',
                'slug' => 'annual_report_generated',
                'subject' => 'Annual Report - {{year}}',
                'body' => $this->getAnnualReportTemplate(),
                'variables' => ['user_name', 'year', 'report_url', 'society_name'],
                'type' => 'system',
                'status' => 'active',
            ],

            // System/Subscription (3 templates)
            [
                'name' => 'Trial Expiry Reminder',
                'slug' => 'trial_expiry_reminder',
                'subject' => 'Your Trial Expires Soon - {{society_name}}',
                'body' => $this->getTrialExpiryReminderTemplate(),
                'variables' => ['society_name', 'expiry_date', 'days_remaining', 'upgrade_url'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Subscription Expired',
                'slug' => 'subscription_expired',
                'subject' => 'Your Subscription Has Expired',
                'body' => $this->getSubscriptionExpiredTemplate(),
                'variables' => ['society_name', 'plan_name', 'renewal_url'],
                'type' => 'system',
                'status' => 'active',
            ],
            [
                'name' => 'Plan Purchase Confirmation',
                'slug' => 'plan_purchased_confirmation',
                'subject' => 'Plan Purchase Confirmation - {{society_name}}',
                'body' => $this->getPlanPurchaseTemplate(),
                'variables' => ['society_name', 'plan_name', 'amount', 'start_date', 'end_date', 'invoice_url'],
                'type' => 'system',
                'status' => 'active',
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::firstOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }

    private function getWelcomeTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Welcome to {{society_name}}! Your account has been successfully created.</p>
<p><strong>Login Details:</strong></p>
<ul>
<li>Email: {{user_email}}</li>
<li>Temporary Password: {{temporary_password}}</li>
</ul>
<p><a href="{{login_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Login Now</a></p>
<p>If you have any questions, please contact our support team.</p>';
    }

    private function getAccountCreatedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your account has been created by the administrator of {{society_name}}.</p>
<p><strong>Your Login Credentials:</strong></p>
<ul>
<li>Email: {{user_email}}</li>
<li>Temporary Password: {{temporary_password}}</li>
</ul>
<p>Please log in and change your password immediately for security.</p>
<p><a href="{{login_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Login to Your Account</a></p>';
    }

    private function getPasswordResetTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>You have requested a password reset for your {{society_name}} account.</p>
<p>Click the link below to reset your password:</p>
<p><a href="{{reset_url}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Reset Password</a></p>
<p>This link will expire in 24 hours.</p>
<p>If you did not request this, please ignore this email.</p>';
    }

    private function getAccountDisabledTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your account at {{society_name}} has been disabled.</p>
<p><strong>Reason:</strong> {{reason}}</p>
<p>If you believe this is a mistake, please contact the administrator.</p>';
    }

    private function getSecurityLoginAlertTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>A new login to your {{society_name}} account was detected.</p>
<p><strong>Login Details:</strong></p>
<ul>
<li>Time: {{login_time}}</li>
<li>IP Address: {{login_ip}}</li>
<li>Device: {{login_device}}</li>
</ul>
<p>If this was not you, please change your password immediately.</p>';
    }

    private function getOwnerAddedTemplate(): string
    {
        return '<p>Dear {{owner_name}},</p>
<p>You have been added as a property owner in {{society_name}}.</p>
<p><strong>Property Details:</strong></p>
<ul>
<li>Property: {{property_name}}</li>
<li>Address: {{property_address}}</li>
</ul>
<p>You can now manage your property through your dashboard.</p>';
    }

    private function getTenantAddedTemplate(): string
    {
        return '<p>Dear {{tenant_name}},</p>
<p>You have been added as a tenant in {{society_name}}.</p>
<p><strong>Property Details:</strong></p>
<ul>
<li>Property: {{property_name}}</li>
<li>Address: {{property_address}}</li>
<li>Lease Start: {{lease_start}}</li>
<li>Lease End: {{lease_end}}</li>
</ul>';
    }

    private function getTenantAssignedTemplate(): string
    {
        return '<p>Dear {{tenant_name}},</p>
<p>You have been assigned to {{property_name}} in {{society_name}}.</p>
<p><strong>Assignment Date:</strong> {{assignment_date}}</p>';
    }

    private function getTenantRemovedTemplate(): string
    {
        return '<p>Dear {{tenant_name}},</p>
<p>Your tenancy at {{property_name}} in {{society_name}} has been terminated.</p>
<p><strong>Removal Date:</strong> {{removal_date}}</p>';
    }

    private function getOwnershipChangedTemplate(): string
    {
        return '<p>Property Ownership Change Notice</p>
<p><strong>Property:</strong> {{property_name}}</p>
<p><strong>Previous Owner:</strong> {{old_owner_name}}</p>
<p><strong>New Owner:</strong> {{new_owner_name}}</p>
<p><strong>Change Date:</strong> {{change_date}}</p>';
    }

    private function getMaintenanceBillTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>A new maintenance bill has been generated for your property in {{society_name}}.</p>
<p><strong>Bill Details:</strong></p>
<ul>
<li>Bill Number: {{bill_number}}</li>
<li>Amount: {{amount}}</li>
<li>Due Date: {{due_date}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Bill</a></p>';
    }

    private function getUtilityBillTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your utility bill for {{society_name}} is ready.</p>
<p><strong>Bill Details:</strong></p>
<ul>
<li>Bill Number: {{bill_number}}</li>
<li>Amount: {{amount}}</li>
<li>Due Date: {{due_date}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Bill</a></p>';
    }

    private function getRentInvoiceTemplate(): string
    {
        return '<p>Dear {{tenant_name}},</p>
<p>Your rent invoice for {{property_name}} is ready.</p>
<p><strong>Invoice Details:</strong></p>
<ul>
<li>Amount: {{rent_amount}}</li>
<li>Due Date: {{due_date}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Invoice</a></p>';
    }

    private function getPaymentReceivedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Thank you! We have received your payment.</p>
<p><strong>Payment Details:</strong></p>
<ul>
<li>Amount: {{amount}}</li>
<li>Date: {{payment_date}}</li>
</ul>
<p><a href="{{receipt_url}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Download Receipt</a></p>';
    }

    private function getPaymentFailedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your payment could not be processed.</p>
<p><strong>Reason:</strong> {{failure_reason}}</p>
<p><strong>Amount:</strong> {{amount}}</p>
<p><a href="{{retry_url}}" style="background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Retry Payment</a></p>';
    }

    private function getBillDueReminderTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Reminder: Your bill is due soon!</p>
<p><strong>Bill Details:</strong></p>
<ul>
<li>Bill Number: {{bill_number}}</li>
<li>Amount: {{amount}}</li>
<li>Due Date: {{due_date}}</li>
<li>Days Remaining: {{days_remaining}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Pay Now</a></p>';
    }

    private function getUpcomingDueReminderTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>You have an upcoming payment due.</p>
<p><strong>Payment Details:</strong></p>
<ul>
<li>Amount: {{amount}}</li>
<li>Due Date: {{due_date}}</li>
<li>Days Remaining: {{days_remaining}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #ffc107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Make Payment</a></p>';
    }

    private function getTicketCreatedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your support ticket has been created successfully.</p>
<p><strong>Ticket Details:</strong></p>
<ul>
<li>Ticket Number: {{complaint_number}}</li>
<li>Title: {{complaint_title}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Ticket</a></p>';
    }

    private function getTicketAssignedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>A support ticket has been assigned to you.</p>
<p><strong>Ticket Details:</strong></p>
<ul>
<li>Ticket Number: {{complaint_number}}</li>
<li>Title: {{complaint_title}}</li>
<li>Assigned By: {{assigned_by}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Ticket</a></p>';
    }

    private function getTicketUpdatedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your support ticket has been updated.</p>
<p><strong>Ticket Details:</strong></p>
<ul>
<li>Ticket Number: {{complaint_number}}</li>
<li>Status: {{status}}</li>
<li>Update: {{update_message}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Ticket</a></p>';
    }

    private function getTicketClosedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your support ticket has been closed.</p>
<p><strong>Ticket Details:</strong></p>
<ul>
<li>Ticket Number: {{complaint_number}}</li>
<li>Resolution: {{resolution}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Ticket</a></p>';
    }

    private function getNoticePublishedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>A new notice has been published in {{society_name}}.</p>
<p><strong>Notice Details:</strong></p>
<ul>
<li>Title: {{notice_title}}</li>
<li>Published: {{published_date}}</li>
</ul>
<p>{{notice_content}}</p>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Read Full Notice</a></p>';
    }

    private function getEventCreatedTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>A new event has been created in {{society_name}}.</p>
<p><strong>Event Details:</strong></p>
<ul>
<li>Event: {{event_name}}</li>
<li>Date: {{event_date}}</li>
<li>Time: {{event_time}}</li>
<li>Location: {{event_location}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Event</a></p>';
    }

    private function getEventReminderTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Reminder: {{event_name}} is coming up!</p>
<p><strong>Event Details:</strong></p>
<ul>
<li>Date: {{event_date}}</li>
<li>Time: {{event_time}}</li>
<li>Location: {{event_location}}</li>
</ul>
<p><a href="{{view_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Event</a></p>';
    }

    private function getMonthlyReportTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your monthly report for {{month}} {{year}} is ready.</p>
<p><a href="{{report_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Download Report</a></p>';
    }

    private function getAnnualReportTemplate(): string
    {
        return '<p>Dear {{user_name}},</p>
<p>Your annual report for {{year}} is ready.</p>
<p><a href="{{report_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Download Report</a></p>';
    }

    private function getTrialExpiryReminderTemplate(): string
    {
        return '<p>Dear {{society_name}} Administrator,</p>
<p>Your trial period is expiring soon!</p>
<p><strong>Expiry Date:</strong> {{expiry_date}}</p>
<p><strong>Days Remaining:</strong> {{days_remaining}}</p>
<p><a href="{{upgrade_url}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Upgrade Now</a></p>';
    }

    private function getSubscriptionExpiredTemplate(): string
    {
        return '<p>Dear {{society_name}} Administrator,</p>
<p>Your {{plan_name}} subscription has expired.</p>
<p>Please renew your subscription to continue using our services.</p>
<p><a href="{{renewal_url}}" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Renew Subscription</a></p>';
    }

    private function getPlanPurchaseTemplate(): string
    {
        return '<p>Dear {{society_name}} Administrator,</p>
<p>Thank you for purchasing {{plan_name}}!</p>
<p><strong>Purchase Details:</strong></p>
<ul>
<li>Plan: {{plan_name}}</li>
<li>Amount: {{amount}}</li>
<li>Start Date: {{start_date}}</li>
<li>End Date: {{end_date}}</li>
</ul>
<p><a href="{{invoice_url}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Download Invoice</a></p>';
    }
}
