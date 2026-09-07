<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder5 extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Visitor Management Templates (2)
            [
                'name' => 'Visitor Entry Created',
                'slug' => 'visitor-entry-created',
                'subject' => 'Visitor Entry Created - {{society_name}}',
                'body' => '<h2>Visitor Entry Created</h2>
<p>Dear {{user_name}},</p>
<p>A visitor entry has been created for your property:</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Visitor Name:</strong> {{visitor_name}}<br>
    <strong>Phone:</strong> {{visitor_phone}}<br>
    <strong>Purpose:</strong> {{visit_purpose}}<br>
    <strong>Expected Date:</strong> {{visit_date}}<br>
    <strong>Status:</strong> {{approval_status}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #17a2b8; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Details</a>
</div>
<p>Please ensure you are available to receive your visitor.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'visitor_name', 'visitor_phone', 'visit_purpose', 'visit_date', 'approval_status', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Visitor Status Updated',
                'slug' => 'visitor-status-updated',
                'subject' => 'Visitor Status Updated - {{society_name}}',
                'body' => '<h2>Visitor Status Updated</h2>
<p>Dear {{user_name}},</p>
<p>The status of your visitor entry has been updated:</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Visitor Name:</strong> {{visitor_name}}<br>
    <strong>Previous Status:</strong> {{old_status}}<br>
    <strong>New Status:</strong> {{new_status}}<br>
    <strong>Updated Date:</strong> {{updated_date}}
</div>
<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Message:</strong><br>
    {{status_message}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Details</a>
</div>
<p>Thank you for using our visitor management system.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'visitor_name', 'old_status', 'new_status', 'updated_date', 'status_message', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],

            // Society & Subscription Templates (5)
            [
                'name' => 'Society Registration Success',
                'slug' => 'society-registration-success',
                'subject' => 'Welcome to SocietyFlow - Registration Successful',
                'body' => '<h2>Welcome to SocietyFlow!</h2>
<p>Dear {{admin_name}},</p>
<p>Congratulations! Your society "{{society_name}}" has been successfully registered with SocietyFlow.</p>
<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
    <strong>Society Name:</strong> {{society_name}}<br>
    <strong>Admin Email:</strong> {{admin_email}}<br>
    <strong>Registration Date:</strong> {{registration_date}}<br>
    <strong>Plan:</strong> {{subscription_plan}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{dashboard_url}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Access Dashboard</a>
</div>
<p>You can now start managing your society with our comprehensive tools.</p>
<p>Best regards,<br>SocietyFlow Team</p>',
                'variables' => ['admin_name', 'society_name', 'admin_email', 'registration_date', 'subscription_plan', 'dashboard_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Plan Purchase Confirmation',
                'slug' => 'plan-purchase-confirmation',
                'subject' => 'Plan Purchase Confirmation - {{plan_name}}',
                'body' => '<h2>Plan Purchase Confirmation</h2>
<p>Dear {{admin_name}},</p>
<p>Thank you for purchasing the {{plan_name}} plan for {{society_name}}.</p>
<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
    <strong>Plan:</strong> {{plan_name}}<br>
    <strong>Amount:</strong> {{amount}}<br>
    <strong>Valid Until:</strong> {{expiry_date}}<br>
    <strong>Transaction ID:</strong> {{transaction_id}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{invoice_url}}" style="background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Download Invoice</a>
</div>
<p>Your plan is now active and you can enjoy all the premium features.</p>
<p>Best regards,<br>SocietyFlow Team</p>',
                'variables' => ['admin_name', 'society_name', 'plan_name', 'amount', 'expiry_date', 'transaction_id', 'invoice_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Plan Upgraded',
                'slug' => 'plan-upgraded',
                'subject' => 'Plan Upgraded Successfully - {{new_plan_name}}',
                'body' => '<h2>Plan Upgraded Successfully</h2>
<p>Dear {{admin_name}},</p>
<p>Your subscription plan has been successfully upgraded.</p>
<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007bff;">
    <strong>Previous Plan:</strong> {{old_plan_name}}<br>
    <strong>New Plan:</strong> {{new_plan_name}}<br>
    <strong>Upgrade Date:</strong> {{upgrade_date}}<br>
    <strong>New Features:</strong> {{new_features}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{dashboard_url}}" style="background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Explore New Features</a>
</div>
<p>Enjoy the enhanced features and capabilities of your new plan!</p>
<p>Best regards,<br>SocietyFlow Team</p>',
                'variables' => ['admin_name', 'society_name', 'old_plan_name', 'new_plan_name', 'upgrade_date', 'new_features', 'dashboard_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Trial Ending Soon',
                'slug' => 'trial-ending-soon',
                'subject' => 'Trial Ending Soon - {{society_name}}',
                'body' => '<h2>Trial Ending Soon</h2>
<p>Dear {{admin_name}},</p>
<p>Your trial period for {{society_name}} is ending soon.</p>
<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
    <strong>Trial End Date:</strong> {{trial_end_date}}<br>
    <strong>Days Remaining:</strong> {{days_remaining}}<br>
    <strong>Current Plan:</strong> {{current_plan}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{upgrade_url}}" style="background: #ffc107; color: #212529; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Choose a Plan</a>
</div>
<p>Please choose a subscription plan to continue using SocietyFlow without interruption.</p>
<p>Best regards,<br>SocietyFlow Team</p>',
                'variables' => ['admin_name', 'society_name', 'trial_end_date', 'days_remaining', 'current_plan', 'upgrade_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Plan Expired',
                'slug' => 'plan-expired',
                'subject' => 'Plan Expired - {{society_name}}',
                'body' => '<h2>Plan Expired</h2>
<p>Dear {{admin_name}},</p>
<p>Your subscription plan for {{society_name}} has expired.</p>
<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;">
    <strong>Expired Plan:</strong> {{expired_plan}}<br>
    <strong>Expiry Date:</strong> {{expiry_date}}<br>
    <strong>Status:</strong> Suspended
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{renew_url}}" style="background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Renew Now</a>
</div>
<p>Please renew your subscription to restore access to all features.</p>
<p>Best regards,<br>SocietyFlow Team</p>',
                'variables' => ['admin_name', 'society_name', 'expired_plan', 'expiry_date', 'renew_url'],
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