<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Flat;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use App\Models\Society;

class VillaRentTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏡 Creating villa test data for rent form...');

        // Get the first society (assuming admin is testing with society ID 1 or 2)
        $society = Society::first();
        if (!$society) {
            $this->command->error('No society found. Please create a society first.');
            return;
        }

        $societyId = $society->id;
        $this->command->info("Using society: {$society->name} (ID: {$societyId})");

        // Create villa area if it doesn't exist
        $villaArea = VillaArea::firstOrCreate([
            'society_id' => $societyId,
            'name' => 'Premium Villa Area'
        ], [
            'description' => 'Premium villa area for testing rent functionality',
            'total_villas' => 5
        ]);

        $this->command->info("Villa area: {$villaArea->name}");

        // Create 3 test villas with tenants
        for ($i = 1; $i <= 3; $i++) {
            // Create villa
            $villa = Flat::firstOrCreate([
                'society_id' => $societyId,
                'villa_area_id' => $villaArea->id,
                'property_type' => 'villa',
                'flat_number' => "V{$i}",
            ], [
                'villa_name' => "Villa {$i}",
                'type' => '3BHK',
                'carpet_area' => 1500.00,
                'built_up_area' => 1800.00,
                'plot_area' => 2000.00,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'has_garden' => true,
                'has_parking' => true,
                'parking_slots' => 2,
                'maintenance_amount' => 25000 + ($i * 5000), // Different rent amounts
                'status' => 'on_rent',
                'amenities' => ['Garden', 'Parking', 'Security'],
                'description' => "Premium villa {$i} with garden and parking"
            ]);

            // Create tenant user
            $tenantUser = User::firstOrCreate([
                'email' => "villa.tenant{$i}@example.com"
            ], [
                'name' => "Villa Tenant {$i}",
                'phone' => "987654321{$i}",
                'password' => bcrypt('password'),
                'society_id' => $societyId,
                'status' => 'active'
            ]);

            // Assign tenant role
            $tenantUser->assignRole('Tenant');

            // Create tenant record
            $tenant = Tenant::firstOrCreate([
                'flat_id' => $villa->id,
                'user_id' => $tenantUser->id
            ], [
                'society_id' => $societyId,
                'name' => $tenantUser->name,
                'email' => $tenantUser->email,
                'phone' => $tenantUser->phone,
                'move_in_date' => now()->subMonths(6),
                'lease_start_date' => now()->subMonths(6),
                'lease_end_date' => now()->addMonths(6),
                'monthly_rent' => $villa->maintenance_amount,
                'security_deposit' => $villa->maintenance_amount * 2,
                'status' => 'active',
                'family_members' => json_encode([
                    ['name' => "Spouse of Tenant {$i}", 'relation' => 'Spouse', 'age' => 30],
                    ['name' => "Child of Tenant {$i}", 'relation' => 'Child', 'age' => 8]
                ])
            ]);

            $this->command->info("✅ Created Villa {$i} with tenant: {$tenantUser->name}");
        }

        // Update any existing villas with tenants to have 'on_rent' status
        $villasToUpdate = Flat::where('property_type', 'villa')
            ->where('society_id', $societyId)
            ->whereHas('tenants', function($query) {
                $query->where('status', 'active');
            })
            ->where('status', '!=', 'on_rent')
            ->get();

        foreach ($villasToUpdate as $villa) {
            $oldStatus = $villa->status;
            $villa->update(['status' => 'on_rent']);
            $this->command->info("Updated Villa {$villa->flat_number} from '{$oldStatus}' to 'on_rent'");
        }

        // Summary
        $totalVillasOnRent = Flat::where('property_type', 'villa')
            ->where('society_id', $societyId)
            ->where('status', 'on_rent')
            ->whereHas('tenants', function($query) {
                $query->where('status', 'active');
            })
            ->count();

        $this->command->info("🎉 Villa rent test data created successfully!");
        $this->command->info("📊 Total villas available for rent form: {$totalVillasOnRent}");
        
        if ($totalVillasOnRent > 0) {
            $this->command->info("✅ Villa dropdown should now work in the rent form!");
        } else {
            $this->command->warn("⚠️  No villas available. Check tenant assignments.");
        }
    }
}