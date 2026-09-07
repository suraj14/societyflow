<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Society;
use App\Models\VillaArea;
use App\Models\Flat;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SampleVillaDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get first society or create one
        $society = Society::first();
        
        if (!$society) {
            $this->command->error('No society found. Please create a society first.');
            return;
        }

        // Create Villa Area
        $villaArea = VillaArea::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Premium Villas'],
            [
                'code' => 'PV',
                'description' => 'Premium villa section with large plots and gardens',
                'status' => 'active',
            ]
        );

        $this->command->info('Villa Area: ' . $villaArea->name);

        // Create Villa Owner User
        $villaOwner = User::firstOrCreate(
            ['email' => 'villaowner@example.com'],
            [
                'society_id' => $society->id,
                'name' => 'Rajesh Kumar',
                'password' => Hash::make('123456'),
                'phone' => '9876543210',
                'status' => 'active',
            ]
        );

        // Assign Villa Owner role
        if (!$villaOwner->hasRole('Villa Owner')) {
            $villaOwner->assignRole('Villa Owner');
        }

        $this->command->info('Villa Owner user: ' . $villaOwner->email);

        // Create Villa
        $villa = Flat::firstOrCreate(
            ['society_id' => $society->id, 'flat_number' => 'V-101', 'property_type' => 'villa'],
            [
                'villa_area_id' => $villaArea->id,
                'owner_id' => $villaOwner->id,
                'villa_name' => 'Rose Villa',
                'plot_area' => 2500,
                'built_up_area' => 1800,
                'bedrooms' => 4,
                'bathrooms' => 3,
                'has_garden' => true,
                'has_parking' => true,
                'parking_slots' => 2,
                'maintenance_amount' => 5000,
                'status' => 'occupied',
                'description' => 'Beautiful 4BHK villa with garden and parking',
            ]
        );

        $this->command->info('Villa: ' . $villa->villa_name);

        // Create another villa
        Flat::firstOrCreate(
            ['society_id' => $society->id, 'flat_number' => 'V-102', 'property_type' => 'villa'],
            [
                'villa_area_id' => $villaArea->id,
                'villa_name' => 'Sunset Villa',
                'plot_area' => 3000,
                'built_up_area' => 2200,
                'bedrooms' => 5,
                'bathrooms' => 4,
                'has_garden' => true,
                'has_parking' => true,
                'parking_slots' => 3,
                'maintenance_amount' => 6000,
                'status' => 'vacant',
                'description' => 'Spacious 5BHK villa with large garden',
            ]
        );

        $this->command->info('Sample villa data seeded successfully!');
        $this->command->info('');
        $this->command->info('Villa Owner Login:');
        $this->command->info('Email: villaowner@example.com');
        $this->command->info('Password: 123456');
        $this->command->info('Dashboard URL: /my/dashboard');
    }
}
