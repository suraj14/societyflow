<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Resident;
use App\Models\Flat;
use App\Models\Building;
use App\Models\MaintenanceBill;
use Carbon\Carbon;

class TenantRentRecordsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating tenant records and rent data...');
        
        // Get all tenant users
        $tenantUsers = User::whereHas('roles', function($q) {
            $q->where('name', 'Tenant');
        })->get();
        
        $this->command->info("Found {$tenantUsers->count()} tenant users");
        
        foreach ($tenantUsers as $user) {
            $this->createTenantData($user);
        }
        
        $this->command->info('Tenant rent records seeder completed!');
    }
    
    private function createTenantData($user)
    {
        try {
            // Find or create tenant record
            $tenant = Tenant::where('user_id', $user->id)->first();
            
            if (!$tenant) {
                // Find or create a flat
                $flat = $this->findOrCreateFlat($user->society_id);
                
                // Create tenant record
                $tenant = Tenant::create([
                    'society_id' => $user->society_id,
                    'user_id' => $user->id,
                    'flat_id' => $flat->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '9876543210',
                    'move_in_date' => now()->subMonths(6),
                    'lease_start_date' => now()->subMonths(6),
                    'lease_end_date' => now()->addMonths(6),
                    'monthly_rent' => rand(15000, 25000),
                    'security_deposit' => rand(30000, 50000),
                    'status' => 'active',
                ]);
                
                // Update user tenant_id
                $user->update(['tenant_id' => $tenant->id]);
                
                // Update flat status
                $flat->update(['status' => 'on_rent']);
                
                $this->command->info("Created tenant record for {$user->name} - Flat: {$flat->flat_number}");
            }
            
            // Find or create resident record
            $resident = Resident::where('user_id', $user->id)->first();
            
            if (!$resident) {
                $resident = Resident::create([
                    'society_id' => $user->society_id,
                    'user_id' => $user->id,
                    'flat_id' => $tenant->flat_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '9876543210',
                    'type' => 'tenant',
                    'move_in_date' => $tenant->move_in_date,
                    'status' => 'active',
                ]);
                
                $this->command->info("Created resident record for {$user->name}");
            }
            
            // Create rent records for the last 6 months
            $this->createRentRecords($tenant);
            
        } catch (\Exception $e) {
            $this->command->error("Failed to create tenant data for {$user->name}: " . $e->getMessage());
        }
    }
    
    private function findOrCreateFlat($societyId)
    {
        // Try to find a vacant flat
        $flat = Flat::where('society_id', $societyId)
            ->where('status', 'vacant')
            ->first();
            
        if (!$flat) {
            // Find or create building
            $building = Building::where('society_id', $societyId)->first();
            
            if (!$building) {
                $building = Building::create([
                    'society_id' => $societyId,
                    'name' => 'Tower A',
                    'floors' => 10,
                    'flats_per_floor' => 4,
                    'status' => 'active'
                ]);
            }
            
            // Generate unique flat number
            $existingNumbers = Flat::where('society_id', $societyId)
                ->pluck('flat_number')
                ->toArray();
                
            $flatNumber = $this->generateUniqueFlatNumber($existingNumbers);
            
            // Create new flat
            $flat = Flat::create([
                'society_id' => $societyId,
                'building_id' => $building->id,
                'flat_number' => $flatNumber,
                'floor' => rand(1, 5),
                'property_type' => 'apartment',
                'bedrooms' => rand(1, 3),
                'bathrooms' => rand(1, 2),
                'area_sqft' => rand(800, 1500),
                'status' => 'vacant'
            ]);
        }
        
        return $flat;
    }
    
    private function generateUniqueFlatNumber($existingNumbers)
    {
        // Try common flat numbers first
        $commonNumbers = ['1A', '1B', '2A', '2B', '3A', '3B', '101', '102', '103', '104', '201', '202', '301', '302', '401', '402'];
        
        foreach ($commonNumbers as $number) {
            if (!in_array($number, $existingNumbers)) {
                return $number;
            }
        }
        
        // Generate numbered flats
        for ($i = 1; $i <= 100; $i++) {
            $flatNumber = str_pad($i, 3, '0', STR_PAD_LEFT);
            if (!in_array($flatNumber, $existingNumbers)) {
                return $flatNumber;
            }
        }
        
        // Last resort
        return 'T' . rand(100, 999);
    }
    
    private function createRentRecords($tenant)
    {
        // Create rent records for the last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $billDate = Carbon::now()->subMonths($i)->startOfMonth();
            $dueDate = $billDate->copy()->addDays(10);
            
            // Check if rent record already exists for this month
            $existingBill = MaintenanceBill::where('flat_id', $tenant->flat_id)
                ->where('bill_type', 'rent')
                ->where('month', $billDate->format('F'))
                ->where('year', $billDate->year)
                ->first();
                
            if (!$existingBill) {
                $status = $i > 1 ? 'paid' : ($i == 1 ? 'partial' : 'pending');
                $paidAmount = $status === 'paid' ? $tenant->monthly_rent : ($status === 'partial' ? $tenant->monthly_rent * 0.6 : 0);
                
                MaintenanceBill::create([
                    'society_id' => $tenant->society_id,
                    'flat_id' => $tenant->flat_id,
                    'bill_number' => 'RENT' . $tenant->society_id . $billDate->format('Ym') . str_pad($tenant->id, 3, '0', STR_PAD_LEFT),
                    'month' => $billDate->format('F'),
                    'year' => $billDate->year,
                    'bill_date' => $billDate,
                    'due_date' => $dueDate,
                    'maintenance_amount' => $tenant->monthly_rent,
                    'water_charges' => 0,
                    'electricity_charges' => 0,
                    'parking_charges' => 0,
                    'penalty_amount' => 0,
                    'other_charges' => 0,
                    'total_amount' => $tenant->monthly_rent,
                    'paid_amount' => $paidAmount,
                    'balance_amount' => $tenant->monthly_rent - $paidAmount,
                    'status' => $status,
                    'bill_type' => 'rent',
                    'paid_date' => $status === 'paid' ? $dueDate->copy()->subDays(2) : null,
                ]);
                
                $this->command->info("Created rent record for {$tenant->name} - {$billDate->format('M Y')} - Status: {$status}");
            }
        }
    }
}