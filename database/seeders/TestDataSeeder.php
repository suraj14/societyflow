<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Society;
use App\Models\Building;
use App\Models\Flat;
use App\Models\VillaArea;
use App\Models\Resident;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create a society
        $society = Society::firstOrCreate(
            ['name' => 'Sunshine Residency'],
            [
                'slug' => 'sunshine-residency',
                'address' => '123 Main Street, Downtown',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '400001',
                'phone' => '+91 9876543210',
                'email' => 'info@sunshineresidency.com',
                'status' => 'active',
            ]
        );

        // Create Buildings
        $building1 = Building::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Tower A'],
            [
                'code' => 'TA',
                'total_floors' => 10,
                'total_flats' => 40,
                'status' => 'active',
            ]
        );

        $building2 = Building::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Tower B'],
            [
                'code' => 'TB',
                'total_floors' => 10,
                'total_flats' => 40,
                'status' => 'active',
            ]
        );

        // Create Villa Areas
        $villaArea1 = VillaArea::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Green Valley Villas'],
            [
                'code' => 'GV',
                'description' => 'Premium villa community with modern amenities',
                'total_villas' => 20,
                'status' => 'active',
            ]
        );

        // ==================== APARTMENTS ====================
        $apartments = [
            [
                'flat_number' => '101',
                'floor' => 1,
                'type' => '2BHK',
                'carpet_area' => 850.50,
                'built_up_area' => 1200.00,
                'maintenance_amount' => 5000,
                'status' => 'occupied',
            ],
            [
                'flat_number' => '201',
                'floor' => 2,
                'type' => '3BHK',
                'carpet_area' => 1200.75,
                'built_up_area' => 1600.00,
                'maintenance_amount' => 7500,
                'status' => 'occupied',
            ],
            [
                'flat_number' => '301',
                'floor' => 3,
                'type' => '1BHK',
                'carpet_area' => 600.25,
                'built_up_area' => 900.00,
                'maintenance_amount' => 3500,
                'status' => 'vacant',
            ],
        ];

        $apartmentFlats = [];
        foreach ($apartments as $apt) {
            $flat = Flat::firstOrCreate(
                [
                    'society_id' => $society->id,
                    'building_id' => $building1->id,
                    'flat_number' => $apt['flat_number'],
                ],
                array_merge($apt, [
                    'property_type' => 'apartment',
                    'amenities' => ['Balcony', 'Modular Kitchen', 'AC'],
                    'description' => 'Well-maintained apartment with modern amenities',
                ])
            );
            $apartmentFlats[] = $flat;
        }

        // ==================== VILLAS ====================
        $villas = [
            [
                'villa_name' => 'Villa 01',
                'flat_number' => 'V-01',
                'type' => '4BHK',
                'plot_area' => 5000.00,
                'carpet_area' => 3500.00,
                'built_up_area' => 4200.00,
                'bedrooms' => 4,
                'bathrooms' => 3,
                'has_garden' => true,
                'has_parking' => true,
                'parking_slots' => 2,
                'maintenance_amount' => 15000,
                'status' => 'occupied',
            ],
            [
                'villa_name' => 'Villa 02',
                'flat_number' => 'V-02',
                'type' => '3BHK',
                'plot_area' => 4500.00,
                'carpet_area' => 3000.00,
                'built_up_area' => 3800.00,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'has_garden' => true,
                'has_parking' => true,
                'parking_slots' => 2,
                'maintenance_amount' => 12000,
                'status' => 'occupied',
            ],
            [
                'villa_name' => 'Villa 03',
                'flat_number' => 'V-03',
                'type' => '5BHK',
                'plot_area' => 6000.00,
                'carpet_area' => 4500.00,
                'built_up_area' => 5200.00,
                'bedrooms' => 5,
                'bathrooms' => 4,
                'has_garden' => true,
                'has_parking' => true,
                'parking_slots' => 3,
                'maintenance_amount' => 18000,
                'status' => 'vacant',
            ],
        ];

        $villaFlats = [];
        foreach ($villas as $villa) {
            $flat = Flat::firstOrCreate(
                [
                    'society_id' => $society->id,
                    'villa_area_id' => $villaArea1->id,
                    'villa_name' => $villa['villa_name'],
                ],
                array_merge($villa, [
                    'property_type' => 'villa',
                    'amenities' => ['Garden', 'Swimming Pool', 'Parking', 'Security'],
                    'description' => 'Luxurious villa with premium amenities',
                ])
            );
            $villaFlats[] = $flat;
        }

        // ==================== OWNERS ====================
        $ownerData = [
            [
                'name' => 'Rajesh Kumar',
                'email' => 'rajesh.kumar@email.com',
                'phone' => '+91 9876543211',
                'flat_id' => $apartmentFlats[0]->id,
                'family_members' => [
                    ['name' => 'Priya Kumar', 'relationship' => 'Spouse', 'phone' => '+91 9876543212'],
                    ['name' => 'Arjun Kumar', 'relationship' => 'Child', 'phone' => ''],
                ],
            ],
            [
                'name' => 'Amit Patel',
                'email' => 'amit.patel@email.com',
                'phone' => '+91 9876543213',
                'flat_id' => $apartmentFlats[1]->id,
                'family_members' => [
                    ['name' => 'Neha Patel', 'relationship' => 'Spouse', 'phone' => '+91 9876543214'],
                ],
            ],
            [
                'name' => 'Vikram Singh',
                'email' => 'vikram.singh@email.com',
                'phone' => '+91 9876543215',
                'flat_id' => $villaFlats[0]->id,
                'family_members' => [
                    ['name' => 'Anjali Singh', 'relationship' => 'Spouse', 'phone' => '+91 9876543216'],
                    ['name' => 'Rohan Singh', 'relationship' => 'Child', 'phone' => ''],
                    ['name' => 'Zara Singh', 'relationship' => 'Child', 'phone' => ''],
                ],
            ],
        ];

        foreach ($ownerData as $owner) {
            $flatId = $owner['flat_id'];
            $familyMembers = $owner['family_members'];
            unset($owner['flat_id'], $owner['family_members']);

            $user = User::firstOrCreate(
                ['email' => $owner['email']],
                array_merge($owner, [
                    'password' => Hash::make('password123'),
                    'society_id' => $society->id,
                    'status' => 'active',
                ])
            );

            if (!$user->hasRole('Apartment Owner')) {
                $user->syncRoles('Apartment Owner');
            }

            // Create resident record for owner
            Resident::firstOrCreate(
                ['user_id' => $user->id, 'flat_id' => $flatId],
                [
                    'society_id' => $society->id,
                    'type' => 'owner',
                    'move_in_date' => Carbon::now()->subMonths(rand(6, 24)),
                    'status' => 'active',
                    'family_members' => json_encode($familyMembers),
                ]
            );
        }

        // ==================== TENANTS ====================
        $tenantData = [
            [
                'name' => 'Suresh Sharma',
                'email' => 'suresh.sharma@email.com',
                'phone' => '+91 9876543217',
                'flat_id' => $apartmentFlats[2]->id,
                'contract_start_date' => Carbon::now()->subMonths(12),
                'contract_end_date' => Carbon::now()->addMonths(12),
                'rent_amount' => 45000,
                'rent_billing_cycle' => 'Monthly',
                'family_members' => [
                    ['name' => 'Kavya Sharma', 'relationship' => 'Spouse', 'phone' => '+91 9876543218'],
                    ['name' => 'Aditya Sharma', 'relationship' => 'Child', 'phone' => ''],
                ],
            ],
            [
                'name' => 'Priya Desai',
                'email' => 'priya.desai@email.com',
                'phone' => '+91 9876543219',
                'flat_id' => $villaFlats[1]->id,
                'contract_start_date' => Carbon::now()->subMonths(6),
                'contract_end_date' => Carbon::now()->addMonths(18),
                'rent_amount' => 85000,
                'rent_billing_cycle' => 'Monthly',
                'family_members' => [
                    ['name' => 'Rahul Desai', 'relationship' => 'Spouse', 'phone' => '+91 9876543220'],
                ],
            ],
            [
                'name' => 'Nikhil Verma',
                'email' => 'nikhil.verma@email.com',
                'phone' => '+91 9876543221',
                'flat_id' => $villaFlats[2]->id,
                'contract_start_date' => Carbon::now()->subMonths(3),
                'contract_end_date' => Carbon::now()->addMonths(21),
                'rent_amount' => 120000,
                'rent_billing_cycle' => 'Monthly',
                'family_members' => [
                    ['name' => 'Divya Verma', 'relationship' => 'Spouse', 'phone' => '+91 9876543222'],
                    ['name' => 'Isha Verma', 'relationship' => 'Child', 'phone' => ''],
                    ['name' => 'Aryan Verma', 'relationship' => 'Child', 'phone' => ''],
                ],
            ],
        ];

        foreach ($tenantData as $tenant) {
            $flatId = $tenant['flat_id'];
            $contractStartDate = $tenant['contract_start_date'];
            $contractEndDate = $tenant['contract_end_date'];
            $rentAmount = $tenant['rent_amount'];
            $rentBillingCycle = $tenant['rent_billing_cycle'];
            $familyMembers = $tenant['family_members'];
            
            unset($tenant['flat_id'], $tenant['contract_start_date'], $tenant['contract_end_date'], 
                  $tenant['rent_amount'], $tenant['rent_billing_cycle'], $tenant['family_members']);

            $user = User::firstOrCreate(
                ['email' => $tenant['email']],
                array_merge($tenant, [
                    'password' => Hash::make('password123'),
                    'society_id' => $society->id,
                    'status' => 'active',
                ])
            );

            if (!$user->hasRole('Tenant')) {
                $user->syncRoles('Tenant');
            }

            // Create resident record for tenant with rental info
            Resident::firstOrCreate(
                ['user_id' => $user->id, 'flat_id' => $flatId],
                [
                    'society_id' => $society->id,
                    'type' => 'tenant',
                    'move_in_date' => $contractStartDate,
                    'status' => 'active',
                    'family_members' => json_encode($familyMembers),
                ]
            );

            // Update flat status to occupied
            Flat::where('id', $flatId)->update(['status' => 'occupied']);
        }

        $this->command->info('✅ Test data seeded successfully!');
        $this->command->info('');
        $this->command->info('📊 Data Summary:');
        $this->command->info('  • Society: ' . $society->name);
        $this->command->info('  • Buildings: 2 (Tower A, Tower B)');
        $this->command->info('  • Apartments: 3');
        $this->command->info('  • Villas: 3');
        $this->command->info('  • Owners: 3');
        $this->command->info('  • Tenants: 3');
        $this->command->info('');
        $this->command->info('👥 Owner Credentials:');
        $this->command->info('  1. rajesh.kumar@email.com / password123');
        $this->command->info('  2. amit.patel@email.com / password123');
        $this->command->info('  3. vikram.singh@email.com / password123');
        $this->command->info('');
        $this->command->info('🏠 Tenant Credentials:');
        $this->command->info('  1. suresh.sharma@email.com / password123');
        $this->command->info('  2. priya.desai@email.com / password123');
        $this->command->info('  3. nikhil.verma@email.com / password123');
    }
}
