<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class MissingEmailTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Bill Overdue',
                'slug' => 'bill-overdue',
                'subject' => 'Bill Overdue - {{society_name}}',
                'body' => 'Dear {{user_name}}, Your bill for {{bill_type}} is now overdue. Amount: {{amount}}. Please pay as soon as possible.',
                'variables' => ['user_name', 'society_name', 'bill_type', 'amount', 'due_date', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Visitor Approved',
                'slug' => 'visitor-approved',
                'subject' => 'Visitor Approved - {{society_name}}',
                'body' => 'Dear {{user_name}}, Your visitor {{visitor_name}} ({{visitor_type}}) has been approved for entry.',
                'variables' => ['user_name', 'society_name', 'visitor_name', 'visitor_type', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Visitor Denied',
                'slug' => 'visitor-denied',
                'subject' => 'Visitor Denied - {{society_name}}',
                'body' => 'Dear {{user_name}}, Your visitor {{visitor_name}} ({{visitor_type}}) has been denied entry.',
                'variables' => ['user_name', 'society_name', 'visitor_name', 'visitor_type', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
            [
                'name' => 'Rent Bill Generated',
                'slug' => 'rent-bill-generated',
                'subject' => 'Rent Bill Generated - {{society_name}}',
                'body' => 'Dear {{user_name}}, Your rent bill has been generated. Amount: {{amount}}. Due date: {{due_date}}',
                'variables' => ['user_name', 'society_name', 'amount', 'due_date', 'view_url'],
                'type' => 'system',
                'status' => 'active'
            ],
        ];

        foreach ($templates as $template) {
            // Check if template already exists
            $exists = EmailTemplate::where('slug', $template['slug'])->exists();
            
            if (!$exists) {
                EmailTemplate::create($template);
            }
        }
    }
}
