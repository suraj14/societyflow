<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ComplaintCategory;
use App\Models\Society;

class ComplaintCategoriesForAllSocietiesSeeder extends Seeder
{
    public function run(): void
    {
        // Get all societies
        $societies = Society::all();
        
        if ($societies->isEmpty()) {
            echo "No societies found. Please create societies first.\n";
            return;
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
            ['name' => 'HVAC', 'description' => 'Heating, ventilation, and air conditioning issues'],
            ['name' => 'Internet/Cable', 'description' => 'Internet and cable TV related issues'],
            ['name' => 'Pest Control', 'description' => 'Pest and rodent control issues'],
            ['name' => 'Garbage/Waste', 'description' => 'Garbage collection and waste management'],
            ['name' => 'Water Supply', 'description' => 'Water supply and quality issues'],
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

        foreach ($societies as $society) {
            echo "Creating categories for society: {$society->name} (ID: {$society->id})\n";
            
            foreach ($categories as $category) {
                ComplaintCategory::firstOrCreate(
                    [
                        'name' => $category['name'], 
                        'society_id' => $society->id
                    ],
                    array_merge($category, [
                        'society_id' => $society->id, 
                        'status' => 'active'
                    ])
                );
            }
            
            $categoryCount = ComplaintCategory::where('society_id', $society->id)->count();
            echo "✅ {$categoryCount} categories created for {$society->name}\n";
        }
        
        echo "\n🎉 Complaint categories created for all societies!\n";
    }
}