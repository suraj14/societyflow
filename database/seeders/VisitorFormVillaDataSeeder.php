<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Society;
use App\Models\VillaArea;
use App\Models\Flat;

class VisitorFormVillaDataSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Creating villa data for visitor form...');

        // Find admin user
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin || !$admin->society_id) {
            $this->command->error('Admin user not found or has no society assigned!');
            return;
        }

        $societyId = $admin->society_id;
        $this->command->info("Creating villa data for society ID: {$societyId}");

        // Check if villa areas already exist
        $existingVillaAreas = VillaArea::where('society_id', $societyId)->count();
        if ($existingVillaAreas > 0) {
            $this->command->info("Villa areas already exist ({$existingVillaAreas} found)");
            return;
        }

        // Create villa areas
        $villaArea1 = VillaArea::create([
            'society_id' => $societyId,
            'name' => 'Green Valley Villas',
            'description' => 'Premium villa area with landscaped gardens',
            'total_villas' => 10,
        ]);

        $villaArea2 = VillaArea::create([
            'society_id' => $societyId,
            'name' => 'Sunset Villas',
            'description' => 'Luxury villas with scenic views',
            'total_villas' => 8,
        ]);

        $this->command->info("Created villa areas: {$villaArea1->name}, {$villaArea2->name}");

        // Create villas for Green Valley
        for ($i = 1; $i <= 10; $i++) {
            Flat::create([
                'society_id' => $societyId,
                'villa_area_id' => $villaArea1->id,
                'property_type' => 'villa',
                'villa_name' => 'Villa ' . $i,
                'flat_number' => 'GV' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'plot_area' => 2500.00,
                'built_up_area' => 1800.00,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'has_garden' => true,
                'has_parking' => true,
                'parking_slots' => 2,
                'maintenance_amount' => 5000.00,
                'status' => 'vacant',
            ]);
        }

        // Create villas for Sunset Villas
        for ($i = 1; $i <= 8; $i++) {
            Flat::create([
                'society_id' => $societyId,
                'villa_area_id' => $villaArea2->id,
                'property_type' => 'villa',
                'villa_name' => 'Sunset Villa ' . $i,
                'flat_number' => 'SV' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'plot_area' => 3000.00,
                'built_up_area' => 2200.00,
                'bedrooms' => 4,
                'bathrooms' => 4,
                'has_garden' => true,
                'has_parking' => true,
                'parking_slots' => 3,
                'maintenance_amount' => 7500.00,
                'status' => 'vacant',
            ]);
        }

        $totalVillas = Flat::where('society_id', $societyId)->where('property_type', 'villa')->count();
        $this->command->info("Created {$totalVillas} villas total");
        $this->command->info('Villa data creation completed!');
    }
}