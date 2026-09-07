<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder3 extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Complaint & Ticket Templates (4)
            [
                'name' => 'Ticket Created',
                'slug' => 'ticket-created',
                'subject' => 'Complaint Ticket Created - {{complaint_number}}',
                'body' => '<h2>Complaint Ticket Created</h2>
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
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'complaint_number', 'complaint_category', 'complaint_status', 'created_date', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Ticket Assigned',
                'slug' => 'ticket-assigned',
                'subject' => 'Ticket Assigned - {{complaint_number}}',
                'body' => '<h2>Ticket Assigned</h2>
<p>Dear {{user_name}},</p>
<p>Your complaint ticket has been assigned to our team for resolution.</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Ticket Number:</strong> {{complaint_number}}<br>
    <strong>Assigned To:</strong> {{assigned_to}}<br>
    <strong>Priority:</strong> {{priority}}<br>
    <strong>Expected Resolution:</strong> {{expected_resolution}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #17a2b8; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Progress</a>
</div>
<p>We are working on resolving your issue as quickly as possible.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'complaint_number', 'assigned_to', 'priority', 'expected_resolution', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Ticket Status Updated',
                'slug' => 'ticket-status-updated',
                'subject' => 'Ticket Status Updated - {{complaint_number}}',
                'body' => '<h2>Ticket Status Updated</h2>
<p>Dear {{user_name}},</p>
<p>The status of your complaint ticket has been updated.</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Ticket Number:</strong> {{complaint_number}}<br>
    <strong>Previous Status:</strong> {{old_status}}<br>
    <strong>New Status:</strong> {{new_status}}<br>
    <strong>Updated Date:</strong> {{updated_date}}
</div>
<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Update Message:</strong><br>
    {{update_message}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #fd7e14; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Ticket</a>
</div>
<p>Thank you for your patience.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'complaint_number', 'old_status', 'new_status', 'updated_date', 'update_message', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Ticket Closed',
                'slug' => 'ticket-closed',
                'subject' => 'Ticket Resolved - {{complaint_number}}',
                'body' => '<h2>Ticket Resolved</h2>
<p>Dear {{user_name}},</p>
<p>Your complaint ticket has been successfully resolved and closed.</p>
<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
    <strong>Ticket Number:</strong> {{complaint_number}}<br>
    <strong>Resolution Date:</strong> {{resolution_date}}<br>
    <strong>Resolved By:</strong> {{resolved_by}}
</div>
<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Resolution Notes:</strong><br>
    {{resolution_notes}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{feedback_url}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Provide Feedback</a>
</div>
<p>Thank you for your patience. We hope the resolution meets your expectations.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'complaint_number', 'resolution_date', 'resolved_by', 'resolution_notes', 'feedback_url'],
                'type' => 'system',
                'status' => 'active'
            ]
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['slug' => $template['slug'], 'society_id' => null],
                $template
            );
        }
    }
}