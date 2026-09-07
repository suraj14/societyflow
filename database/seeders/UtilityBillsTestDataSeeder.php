<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Society;
use App\Models\Building;
use App\Models\Flat;
use App\Models\User;

class UtilityBillsTestDataSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Creating test data for utility bills...');

        // Find admin users and create data for their societies
        $adminUsers = User::whereHas('roles', function($query) {
            $query->where('name', 'Admin');
        })->get();

        foreach ($adminUsers as $user) {
            if (!$user->society_id) {
                $this->command->warn("Admin user {$user->email} has no society_id, skipping...");
                continue;
            }

            $society = Society::find($user->society_id);
            if (!$society) {
                $this->command->warn("Society with ID {$user->society_id} not found, skipping...");
                continue;
            }

            $this->command->info("Creating data for society: {$society->name} (ID: {$society->id})");

            // Check if flats already exist for this society
            $existingFlats = Flat::where('society_id', $society->id)->count();
            if ($existingFlats > 0) {
                $this->command->info("Society {$society->name} already has {$existingFlats} flats, skipping creation...");
                continue;
            }

            // Create building for this society
            $building = Building::firstOrCreate([
                'society_id' => $society->id,
                'name' => 'Main Building'
            ], [
                'floors' => 5,
                'flats_per_floor' => 4,
                'status' => 'active'
            ]);

            $this->command->info("Building created: {$building->name}");

            // Create flats
            $flats = [];
            for ($floor = 1; $floor <= 3; $floor++) {
                for ($flat = 1; $flat <= 4; $flat++) {
                    $flatNumber = $floor . str_pad($flat, 2, '0', STR_PAD_LEFT);
                    
                    $flatRecord = Flat::firstOrCreate([
                        'society_id' => $society->id,
                        'building_id' => $building->id,
                        'flat_number' => $flatNumber
                    ], [
                        'floor' => $floor,
                        'type' => 'apartment',
                        'bedrooms' => rand(1, 3),
                        'bathrooms' => rand(1, 2),
                        'area_sqft' => rand(800, 1500),
                        'status' => 'occupied'
                    ]);
                    
                    $flats[] = $flatRecord;
                    $this->command->info("Flat created: {$flatRecord->flat_number}");
                }
            }

            $this->command->info("Created " . count($flats) . " flats for society: {$society->name}");
        }

        $this->command->info('Utility bills test data creation completed!');
    }
}