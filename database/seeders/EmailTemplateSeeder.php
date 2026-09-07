<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Authentication & Security Templates (5)
            [
                'name' => 'Welcome User',
                'slug' => 'welcome-user',
                'subject' => 'Welcome to {{society_name}}!',
                'body' => '<h2>Welcome to {{society_name}}!</h2>
<p>Dear {{user_name}},</p>
<p>Your account has been successfully created. Here are your login details:</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Login URL:</strong> <a href="{{login_url}}">{{login_url}}</a><br>
    <strong>Email:</strong> {{user_email}}<br>
    @if(isset($temporary_password))
    <strong>Temporary Password:</strong> {{temporary_password}}
    @endif
</div>
<p>Please login and change your password for security.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'login_url', 'user_email', 'temporary_password'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Password Reset',
                'slug' => 'reset-password',
                'subject' => 'Password Reset Request - {{society_name}}',
                'body' => '<h2>Password Reset Request</h2>
<p>Dear {{user_name}},</p>
<p>You have requested a password reset for your account. Click the button below to reset your password:</p>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{reset_url}}" style="background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Reset Password</a>
</div>
<p>If you did not request this password reset, please ignore this email.</p>
<p>This link will expire in 60 minutes for security reasons.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'reset_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Password Changed',
                'slug' => 'password-changed',
                'subject' => 'Password Changed Successfully - {{society_name}}',
                'body' => '<h2>Password Changed Successfully</h2>
<p>Dear {{user_name}},</p>
<p>Your password has been successfully changed.</p>
<p>If you did not make this change, please contact the administrator immediately.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Account Activated',
                'slug' => 'account-activated',
                'subject' => 'Account Activated - {{society_name}}',
                'body' => '<h2>Account Activated</h2>
<p>Dear {{user_name}},</p>
<p>Your account has been successfully activated. You can now access all features of {{society_name}}.</p>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{login_url}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Login Now</a>
</div>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'login_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Account Deactivated',
                'slug' => 'account-deactivated',
                'subject' => 'Account Deactivated - {{society_name}}',
                'body' => '<h2>Account Deactivated</h2>
<p>Dear {{user_name}},</p>
<p>Your account has been deactivated. Please contact the administrator if you believe this is an error.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name'],
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