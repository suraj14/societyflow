<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Society;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Resident;
use App\Models\MaintenanceBill;
use App\Models\User;

class Society2TestDataSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Creating test data for Society ID: 2');

        // Get society 2
        $society = Society::find(2);
        if (!$society) {
            $this->command->error('Society with ID 2 not found!');
            return;
        }

        $this->command->info("Society: {$society->name}");

        // Create building for society 2
        $building = Building::firstOrCreate([
            'society_id' => 2,
            'name' => 'Building A'
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
                    'society_id' => 2,
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

        // Create residents
        $names = [
            'John Doe', 'Jane Smith', 'Mike Johnson', 'Sarah Wilson', 
            'David Brown', 'Lisa Davis', 'Tom Anderson', 'Mary Taylor',
            'James Wilson', 'Emma Johnson', 'Robert Davis', 'Olivia Brown'
        ];

        $residents = [];
        foreach ($flats as $index => $flat) {
            if ($index < count($names)) {
                $resident = Resident::firstOrCreate([
                    'society_id' => 2,
                    'flat_id' => $flat->id,
                    'name' => $names[$index]
                ], [
                    'email' => strtolower(str_replace(' ', '.', $names[$index])) . '@example.com',
                    'phone' => '9876543' . str_pad($index, 3, '0', STR_PAD_LEFT),
                    'type' => 'owner',
                    'move_in_date' => now()->subMonths(rand(1, 12)),
                    'status' => 'active'
                ]);
                
                $residents[] = $resident;
                $this->command->info("Resident created: {$resident->name} - Flat {$flat->flat_number}");
            }
        }

        // Create maintenance bills
        $months = ['January', 'February', 'March', 'April', 'May'];
        $bills = [];

        foreach ($flats as $flat) {
            foreach (array_slice($months, 0, 3) as $monthIndex => $month) {
                $maintenanceAmount = rand(2000, 5000);
                $waterCharges = rand(200, 500);
                $electricityCharges = rand(300, 800);
                $parkingCharges = rand(100, 300);
                $otherCharges = rand(0, 200);
                
                $totalAmount = $maintenanceAmount + $waterCharges + $electricityCharges + $parkingCharges + $otherCharges;
                
                $bill = MaintenanceBill::firstOrCreate([
                    'society_id' => 2,
                    'flat_id' => $flat->id,
                    'month' => $month,
                    'year' => 2026
                ], [
                    'bill_date' => now()->subMonths(4 - $monthIndex)->startOfMonth(),
                    'due_date' => now()->subMonths(4 - $monthIndex)->endOfMonth(),
                    'maintenance_amount' => $maintenanceAmount,
                    'water_charges' => $waterCharges,
                    'electricity_charges' => $electricityCharges,
                    'parking_charges' => $parkingCharges,
                    'penalty_amount' => 0,
                    'other_charges' => $otherCharges,
                    'total_amount' => $totalAmount,
                    'paid_amount' => 0,
                    'balance_amount' => $totalAmount,
                    'status' => rand(0, 1) ? 'pending' : 'partial'
                ]);
                
                $bills[] = $bill;
                $this->command->info("Bill created: {$bill->bill_number} - Flat {$flat->flat_number} - {$month} 2026 - ₹{$bill->total_amount}");
            }
        }

        $this->command->info('');
        $this->command->info('=== SUMMARY ===');
        $this->command->info("Society: {$society->name} (ID: 2)");
        $this->command->info('Buildings: 1');
        $this->command->info('Flats: ' . count($flats));
        $this->command->info('Residents: ' . count($residents));
        $this->command->info('Maintenance Bills: ' . count($bills));
        $this->command->info('');
        $this->command->info('Test data created successfully!');
        $this->command->info('You can now access the payment form with populated dropdowns.');
    }
}