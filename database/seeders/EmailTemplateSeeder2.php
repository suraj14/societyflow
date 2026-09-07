<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder2 extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Billing & Payment Templates (5)
            [
                'name' => 'New Bill Generated',
                'slug' => 'new-bill-generated',
                'subject' => 'New {{bill_type}} Bill Generated - {{society_name}}',
                'body' => '<h2>New {{bill_type}} Bill Generated</h2>
<p>Dear {{user_name}},</p>
<p>A new {{bill_type}} bill has been generated for your property.</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Bill Amount:</strong> {{amount}}<br>
    <strong>Due Date:</strong> {{due_date}}<br>
    <strong>Bill Type:</strong> {{bill_type}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Bill</a>
</div>
<p>Please make the payment before the due date to avoid late fees.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'bill_type', 'amount', 'due_date', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Rent Bill Generated',
                'slug' => 'rent-bill-generated',
                'subject' => 'Monthly Rent Bill - {{society_name}}',
                'body' => '<h2>Monthly Rent Bill Generated</h2>
<p>Dear {{user_name}},</p>
<p>Your monthly rent bill has been generated.</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Rent Amount:</strong> {{amount}}<br>
    <strong>Due Date:</strong> {{due_date}}<br>
    <strong>Property:</strong> {{property_details}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Pay Rent</a>
</div>
<p>Please make the payment on time to avoid late fees.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'amount', 'due_date', 'property_details', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Payment Received',
                'slug' => 'payment-received',
                'subject' => 'Payment Received - Thank You',
                'body' => '<h2>Payment Received Successfully</h2>
<p>Dear {{user_name}},</p>
<p>We have successfully received your payment. Thank you!</p>
<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
    <strong>Payment Amount:</strong> {{amount}}<br>
    <strong>Payment Date:</strong> {{payment_date}}<br>
    <strong>Transaction ID:</strong> {{transaction_id}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{receipt_url}}" style="background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Download Receipt</a>
</div>
<p>Your payment has been processed and your account has been updated.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'amount', 'payment_date', 'transaction_id', 'receipt_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Payment Failed',
                'slug' => 'payment-failed',
                'subject' => 'Payment Failed - {{society_name}}',
                'body' => '<h2>Payment Failed</h2>
<p>Dear {{user_name}},</p>
<p>Unfortunately, your recent payment attempt was unsuccessful.</p>
<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
    <strong>Payment Amount:</strong> {{amount}}<br>
    <strong>Failure Reason:</strong> {{failure_reason}}<br>
    <strong>Transaction ID:</strong> {{transaction_id}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{retry_url}}" style="background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Try Again</a>
</div>
<p>Please try again or contact support if the issue persists.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'amount', 'failure_reason', 'transaction_id', 'retry_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Bill Due Reminder',
                'slug' => 'bill-due-reminder',
                'subject' => 'Bill Due Reminder - {{society_name}}',
                'body' => '<h2>Bill Due Reminder</h2>
<p>Dear {{user_name}},</p>
<p>This is a friendly reminder that your {{bill_type}} bill is due soon.</p>
<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
    <strong>Bill Amount:</strong> {{amount}}<br>
    <strong>Due Date:</strong> {{due_date}}<br>
    <strong>Days Remaining:</strong> {{days_remaining}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #ffc107; color: #212529; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Pay Now</a>
</div>
<p>Please make the payment before the due date to avoid late fees.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'bill_type', 'amount', 'due_date', 'days_remaining', 'view_url'],
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