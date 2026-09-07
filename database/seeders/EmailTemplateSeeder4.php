<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder4 extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Notice & Event Templates (5)
            [
                'name' => 'New Notice',
                'slug' => 'new-notice',
                'subject' => 'New Notice - {{society_name}}',
                'body' => '<h2>New Notice Published</h2>
<p>Dear {{user_name}},</p>
<p>A new notice has been published:</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Title:</strong> {{notice_title}}<br>
    <strong>Published Date:</strong> {{published_date}}<br>
    <strong>Category:</strong> {{notice_category}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #17a2b8; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Read Notice</a>
</div>
<p>Please check the notice for important information.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'notice_title', 'published_date', 'notice_category', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Notice Updated',
                'slug' => 'notice-updated',
                'subject' => 'Notice Updated - {{society_name}}',
                'body' => '<h2>Notice Updated</h2>
<p>Dear {{user_name}},</p>
<p>An important notice has been updated:</p>
<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
    <strong>Title:</strong> {{notice_title}}<br>
    <strong>Updated Date:</strong> {{updated_date}}<br>
    <strong>Changes:</strong> {{update_summary}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #ffc107; color: #212529; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Updated Notice</a>
</div>
<p>Please review the updated information.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'notice_title', 'updated_date', 'update_summary', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'New Event',
                'slug' => 'new-event',
                'subject' => 'New Event - {{event_name}}',
                'body' => '<h2>New Event Announcement</h2>
<p>Dear {{user_name}},</p>
<p>A new event has been organized in {{society_name}}:</p>
<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Event:</strong> {{event_name}}<br>
    <strong>Date:</strong> {{event_date}}<br>
    <strong>Time:</strong> {{event_time}}<br>
    <strong>Venue:</strong> {{event_venue}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #6f42c1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Event Details</a>
</div>
<p>We look forward to your participation!</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'event_name', 'event_date', 'event_time', 'event_venue', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Event Updated',
                'slug' => 'event-updated',
                'subject' => 'Event Updated - {{event_name}}',
                'body' => '<h2>Event Updated</h2>
<p>Dear {{user_name}},</p>
<p>An event you may be interested in has been updated:</p>
<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107;">
    <strong>Event:</strong> {{event_name}}<br>
    <strong>New Date:</strong> {{event_date}}<br>
    <strong>New Time:</strong> {{event_time}}<br>
    <strong>Venue:</strong> {{event_venue}}<br>
    <strong>Changes:</strong> {{update_summary}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #fd7e14; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Updated Details</a>
</div>
<p>Please note the changes and update your calendar accordingly.</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'event_name', 'event_date', 'event_time', 'event_venue', 'update_summary', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Event Reminder',
                'slug' => 'event-reminder',
                'subject' => 'Event Reminder - {{event_name}} Tomorrow',
                'body' => '<h2>Event Reminder</h2>
<p>Dear {{user_name}},</p>
<p>This is a friendly reminder about the upcoming event:</p>
<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007bff;">
    <strong>Event:</strong> {{event_name}}<br>
    <strong>Date:</strong> {{event_date}}<br>
    <strong>Time:</strong> {{event_time}}<br>
    <strong>Venue:</strong> {{event_venue}}
</div>
<div style="text-align: center; margin: 30px 0;">
    <a href="{{view_url}}" style="background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Event Details</a>
</div>
<p>We look forward to seeing you there!</p>
<p>Best regards,<br>{{society_name}} Team</p>',
                'variables' => ['user_name', 'society_name', 'event_name', 'event_date', 'event_time', 'event_venue', 'view_url'],
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