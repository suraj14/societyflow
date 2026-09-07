<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ComplaintCategory;

class ComplaintCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Get the first society or create a default one
        $society = \App\Models\Society::first();
        if (!$society) {
            $society = \App\Models\Society::create([
                'name' => 'Default Society',
                'email' => 'default@example.com',
                'phone' => '+91-9999999999',
                'address' => 'Default Address',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '400001',
                'status' => 'active',
            ]);
        }

        $categories = [
            ['name' => 'Maintenance', 'description' => 'General maintenance issues'],
            ['name' => 'Plumbing', 'description' => 'Water and plumbing related issues'],
            ['name' => 'Electrical', 'description' => 'Electrical problems and repairs'],
            ['name' => 'Security', 'description' => 'Security and safety concerns'],
            ['name' => 'Noise', 'description' => 'Noise complaints'],
            ['name' => 'Parking', 'description' => 'Parking related issues and violations'],
            ['name' => 'Cleanliness', 'description' => 'Cleaning and hygiene issues'],
            ['name' => 'Lift/Elevator', 'description' => 'Lift and elevator problems'],
            ['name' => 'Common Area', 'description' => 'Common area related issues'],
            ['name' => 'Management', 'description' => 'Society management and administration issues'],
            ['name' => 'Suggestion', 'description' => 'Suggestions for society improvements'],
            ['name' => 'Billing/Finance', 'description' => 'Billing, payments, and financial issues'],
            ['name' => 'Facilities', 'description' => 'Amenities and facility related issues'],
            ['name' => 'Visitor Management', 'description' => 'Issues related to visitor entry and management'],
            ['name' => 'Staff/Service', 'description' => 'Issues with society staff or service providers'],
            ['name' => 'Rules/Policy', 'description' => 'Society rules and policy related concerns'],
            ['name' => 'Emergency', 'description' => 'Emergency situations and urgent issues'],
            ['name' => 'Other', 'description' => 'Other miscellaneous complaints'],
        ];

        foreach ($categories as $category) {
            ComplaintCategory::firstOrCreate(
                ['name' => $category['name'], 'society_id' => $society->id],
                array_merge($category, ['society_id' => $society->id, 'status' => 'active'])
            );
        }
    }
}