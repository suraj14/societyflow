<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Society;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;
use App\Models\Resident;
use App\Models\ComplaintCategory;
use App\Models\Complaint;
use Illuminate\Support\Facades\Hash;

class QuickTestSeeder extends Seeder
{
    public function run(): void
    {
        // Create a test society if it doesn't exist
        $society = Society::firstOrCreate(
            ['email' => 'test@society.com'],
            [
                'name' => 'Test Society',
                'phone' => '+91-9999999999',
                'address' => 'Test Address',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '400001',
                'status' => 'active',
            ]
        );

        // Create a test building
        $building = Building::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Test Building A'],
            [
                'total_floors' => 5,
                'total_flats' => 20,
                'description' => 'Test building for demo',
                'status' => 'active',
            ]
        );

        // Create some test flats
        for ($i = 1; $i <= 5; $i++) {
            Flat::firstOrCreate(
                ['building_id' => $building->id, 'flat_number' => '10' . $i],
                [
                    'society_id' => $society->id,
                    'floor' => 1,
                    'type' => '2BHK',
                    'carpet_area' => 1200,
                    'maintenance_amount' => 5000,
                    'status' => $i <= 3 ? 'occupied' : 'vacant',
                ]
            );
        }

        // Create test users and residents for occupied flats
        $occupiedFlats = Flat::where('building_id', $building->id)->where('status', 'occupied')->get();
        
        foreach ($occupiedFlats as $flat) {
            $user = User::firstOrCreate(
                ['email' => 'resident' . $flat->flat_number . '@test.com'],
                [
                    'society_id' => $society->id,
                    'name' => 'Test Resident ' . $flat->flat_number,
                    'password' => Hash::make('password'),
                    'phone' => '+91-900000000' . $flat->flat_number,
                    'status' => 'active',
                ]
            );

            Resident::firstOrCreate(
                ['user_id' => $user->id, 'flat_id' => $flat->id],
                [
                    'society_id' => $society->id,
                    'type' => 'owner',
                    'move_in_date' => now()->subDays(rand(30, 365)),
                    'status' => 'active',
                ]
            );
        }

        // Create complaint categories for this society
        $categories = ['Maintenance', 'Plumbing', 'Electrical', 'Security', 'Other'];
        foreach ($categories as $categoryName) {
            ComplaintCategory::firstOrCreate(
                ['society_id' => $society->id, 'name' => $categoryName],
                [
                    'description' => $categoryName . ' related issues',
                    'status' => 'active',
                ]
            );
        }

        // Create a few test complaints
        $users = User::where('society_id', $society->id)->get();
        $categories = ComplaintCategory::where('society_id', $society->id)->get();
        
        if ($users->count() > 0 && $categories->count() > 0) {
            for ($i = 1; $i <= 3; $i++) {
                $user = $users->random();
                $category = $categories->random();
                $resident = $user->resident;
                
                if ($resident) {
                    Complaint::firstOrCreate(
                        ['complaint_number' => 'TEST' . str_pad($i, 4, '0', STR_PAD_LEFT)],
                        [
                            'society_id' => $society->id,
                            'complaint_category_id' => $category->id,
                            'flat_id' => $resident->flat_id,
                            'created_by' => $user->id,
                            'title' => 'Test Complaint ' . $i,
                            'description' => 'This is a test complaint for demonstration purposes.',
                            'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                            'status' => ['open', 'in_progress', 'resolved'][array_rand(['open', 'in_progress', 'resolved'])],
                        ]
                    );
                }
            }
        }
    }
}