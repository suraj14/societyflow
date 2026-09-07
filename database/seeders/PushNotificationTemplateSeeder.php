<?php

namespace Database\Seeders;

use App\Models\PushNotificationTemplate;
use Illuminate\Database\Seeder;

class PushNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'trigger_action' => 'bill_generated',
                'title' => 'New Bill Generated',
                'body' => 'A new {{bill_type}} bill of {{amount}} has been generated. Due date: {{due_date}}',
                'click_action' => '/bills',
            ],
            [
                'trigger_action' => 'payment_success',
                'title' => 'Payment Received',
                'body' => 'Your payment of {{amount}} has been received successfully. Thank you!',
                'click_action' => '/payments',
            ],
            [
                'trigger_action' => 'payment_overdue',
                'title' => 'Payment Overdue',
                'body' => 'Your bill of {{amount}} is now overdue. Please pay immediately.',
                'click_action' => '/bills',
            ],
            [
                'trigger_action' => 'notice_published',
                'title' => 'New Notice Published',
                'body' => '{{notice_title}} - {{society_name}}',
                'click_action' => '/notices',
            ],
            [
                'trigger_action' => 'ticket_created',
                'title' => 'Ticket Created',
                'body' => 'Your complaint ticket {{ticket_number}} has been created successfully.',
                'click_action' => '/tickets',
            ],
            [
                'trigger_action' => 'ticket_updated',
                'title' => 'Ticket Status Updated',
                'body' => 'Your ticket {{ticket_number}} status has been updated to {{status}}.',
                'click_action' => '/tickets',
            ],
            [
                'trigger_action' => 'visitor_approved',
                'title' => 'Visitor Approved',
                'body' => '{{visitor_name}} has been approved to visit on {{visit_date}}.',
                'click_action' => '/visitors',
            ],
            [
                'trigger_action' => 'visitor_rejected',
                'title' => 'Visitor Rejected',
                'body' => 'Visitor request for {{visitor_name}} has been rejected.',
                'click_action' => '/visitors',
            ],
            [
                'trigger_action' => 'event_reminder',
                'title' => 'Event Reminder',
                'body' => '{{event_name}} is happening {{event_time}} at {{society_name}}.',
                'click_action' => '/events',
            ],
            [
                'trigger_action' => 'user_added',
                'title' => 'Account Created',
                'body' => 'Welcome {{user_name}}! Your account has been created successfully.',
                'click_action' => '/dashboard',
            ],
            [
                'trigger_action' => 'password_reset',
                'title' => 'Password Reset Request',
                'body' => 'You requested a password reset. Click to reset your password.',
                'click_action' => '/password-reset',
            ],
            [
                'trigger_action' => 'test_notification',
                'title' => 'Test Notification',
                'body' => 'This is a test push notification from {{society_name}}.',
                'click_action' => '/dashboard',
            ],
        ];

        foreach ($templates as $template) {
            PushNotificationTemplate::firstOrCreate(
                ['trigger_action' => $template['trigger_action']],
                array_merge($template, ['is_active' => true])
            );
        }
    }
}
