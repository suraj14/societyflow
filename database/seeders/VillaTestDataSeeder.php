<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Society;
use App\Models\VillaArea;
use App\Models\Flat;

class VillaTestDataSeeder extends Seeder
{
    public function run()
    {
        // Get all societies
        $societies = Society::all();
        
        foreach ($societies as $society) {
            // Create villa areas for each society
            $villaAreas = [
                ['name' => 'Green Valley Villas', 'description' => 'Premium villa area with garden views'],
                ['name' => 'Sunset Heights', 'description' => 'Luxury villas with mountain views'],
                ['name' => 'Royal Gardens', 'description' => 'Executive villas with private gardens'],
            ];
            
            foreach ($villaAreas as $areaData) {
                $villaArea = VillaArea::create([
                    'society_id' => $society->id,
                    'name' => $areaData['name'],
                    'description' => $areaData['description'],
                    'total_villas' => 10,
                ]);
                
                // Create villas in each area
                for ($i = 1; $i <= 10; $i++) {
                    Flat::create([
                        'society_id' => $society->id,
                        'villa_area_id' => $villaArea->id,
                        'property_type' => 'villa',
                        'flat_number' => 'V' . str_pad($i, 3, '0', STR_PAD_LEFT),
                        'villa_name' => $areaData['name'] . ' Villa ' . $i,
                        'type' => '3BHK',
                        'carpet_area' => 2000.00,
                        'built_up_area' => 2500.00,
                        'plot_area' => 3000.00,
                        'bedrooms' => 3,
                        'bathrooms' => 3,
                        'has_garden' => true,
                        'has_parking' => true,
                        'parking_slots' => 2,
                        'maintenance_amount' => 5000.00,
                        'status' => 'vacant',
                    ]);
                }
            }
        }
        
        $this->command->info('Villa test data created successfully!');
    }
}