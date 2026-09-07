<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class AutoEmailTriggerTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // User/Auth Emails
            [
                'name' => 'Welcome Email',
                'slug' => 'welcome_email',
                'subject' => 'Welcome to {{society_name}}!',
                'body' => '<p>Dear {{user_name}},</p>
<p>Welcome to {{society_name}}! Your account has been created successfully.</p>
<p>Login URL: {{login_url}}</p>
<p>Temporary Password: {{temporary_password}}</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'login_url', 'temporary_password'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'User Added',
                'slug' => 'user_added',
                'subject' => 'New User Account Created - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>A new account has been created for you in {{society_name}}.</p>
<p>Login URL: {{login_url}}</p>
<p>Temporary Password: {{temporary_password}}</p>
<p>Please change your password after first login.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'login_url', 'temporary_password'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'reset_password',
                'subject' => 'Password Reset Request - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>You have requested a password reset for your {{society_name}} account.</p>
<p>Click the link below to reset your password:</p>
<p><a href="{{reset_url}}">Reset Password</a></p>
<p>If you did not request this, please ignore this email.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'reset_url'],
                'type' => 'system',
                'status' => 'active'
            ],

            // Bill & Payment Emails
            [
                'name' => 'Bill Generated',
                'slug' => 'bill_generated',
                'subject' => 'New {{bill_type}} Bill Generated - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>A new {{bill_type}} bill has been generated for your property.</p>
<p><strong>Bill Details:</strong></p>
<ul>
<li>Bill Number: {{bill_number}}</li>
<li>Amount: {{amount}}</li>
<li>Due Date: {{due_date}}</li>
</ul>
<p><a href="{{view_url}}">View Bill</a></p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'bill_number', 'bill_type', 'amount', 'due_date', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Payment Success',
                'slug' => 'payment_success',
                'subject' => 'Payment Received - Thank You',
                'body' => '<p>Dear {{user_name}},</p>
<p>We have successfully received your payment of {{amount}}.</p>
<p><strong>Payment Details:</strong></p>
<ul>
<li>Payment ID: {{payment_id}}</li>
<li>Amount: {{amount}}</li>
<li>Date: {{payment_date}}</li>
</ul>
<p><a href="{{receipt_url}}">Download Receipt</a></p>
<p>Thank you for your prompt payment!</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'payment_id', 'amount', 'payment_date', 'receipt_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Payment Overdue',
                'slug' => 'payment_overdue',
                'subject' => 'Payment Overdue - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>Your payment for {{bill_type}} bill is now overdue.</p>
<p><strong>Bill Details:</strong></p>
<ul>
<li>Bill Number: {{bill_number}}</li>
<li>Amount: {{amount}}</li>
<li>Due Date: {{due_date}}</li>
</ul>
<p>Please make the payment as soon as possible to avoid penalties.</p>
<p><a href="{{view_url}}">View Bill</a></p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'bill_number', 'bill_type', 'amount', 'due_date', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],

            // Ticket/Complaint Emails
            [
                'name' => 'Ticket Created',
                'slug' => 'ticket_created',
                'subject' => 'Ticket Created - {{complaint_number}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>Your complaint ticket has been created successfully.</p>
<p><strong>Ticket Details:</strong></p>
<ul>
<li>Ticket Number: {{complaint_number}}</li>
<li>Title: {{complaint_title}}</li>
<li>Status: {{status}}</li>
</ul>
<p><a href="{{view_url}}">View Ticket</a></p>
<p>We will get back to you soon.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'complaint_number', 'complaint_title', 'status', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Ticket Updated',
                'slug' => 'ticket_update',
                'subject' => 'Ticket Updated - {{complaint_number}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>Your complaint ticket has been updated.</p>
<p><strong>Ticket Details:</strong></p>
<ul>
<li>Ticket Number: {{complaint_number}}</li>
<li>Title: {{complaint_title}}</li>
<li>Status: {{status}}</li>
</ul>
<p><a href="{{view_url}}">View Ticket</a></p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'complaint_number', 'complaint_title', 'status', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],

            // Notice & Event Emails
            [
                'name' => 'Notice Published',
                'slug' => 'notice_published',
                'subject' => 'New Notice - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>A new notice has been published in {{society_name}}.</p>
<p><strong>Notice Details:</strong></p>
<ul>
<li>Title: {{notice_title}}</li>
<li>Content: {{notice_content}}</li>
</ul>
<p><a href="{{view_url}}">Read Full Notice</a></p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'notice_title', 'notice_content', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Event Invitation',
                'slug' => 'event_invitation',
                'subject' => 'New Event - {{event_name}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>A new event has been created in {{society_name}}.</p>
<p><strong>Event Details:</strong></p>
<ul>
<li>Event Name: {{event_name}}</li>
<li>Date: {{event_date}}</li>
<li>Time: {{event_time}}</li>
</ul>
<p><a href="{{view_url}}">View Event Details</a></p>
<p>We look forward to your participation!</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'event_name', 'event_date', 'event_time', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],

            // Visitor Emails
            [
                'name' => 'Visitor Status Updated',
                'slug' => 'visitor_status',
                'subject' => 'Visitor {{action}} - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>Your visitor request has been {{action}}.</p>
<p><strong>Visitor Details:</strong></p>
<ul>
<li>Visitor Name: {{visitor_name}}</li>
<li>Visitor Type: {{visitor_type}}</li>
<li>Status: {{action}}</li>
</ul>
<p><a href="{{view_url}}">View Details</a></p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'visitor_name', 'visitor_type', 'action', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],

            // Report Email
            [
                'name' => 'Report Generated',
                'slug' => 'report_generated',
                'subject' => 'Report Generated - {{society_name}}',
                'body' => '<p>Dear {{user_name}},</p>
<p>Your requested report has been generated.</p>
<p><a href="{{view_url}}">Download Report</a></p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
        ];

        foreach ($templates as $template) {
            // Check if template already exists by slug
            $exists = EmailTemplate::where('slug', $template['slug'])->exists();
            
            if (!$exists) {
                EmailTemplate::create($template);
            }
        }

        $this->command->info('Auto Email Trigger templates seeded successfully!');
    }
}
