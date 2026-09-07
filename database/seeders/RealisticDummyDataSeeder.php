<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Society;
use App\Models\Building;
use App\Models\Flat;
use App\Models\VillaArea;
use App\Models\Villa;
use App\Models\User;
use App\Models\Resident;
use App\Models\Payment;
use App\Models\ServiceProvider;
use App\Models\Complaint;
use App\Models\Visitor;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class RealisticDummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create society
        $society = Society::first() ?? Society::create([
            'name' => 'Greenwood Township',
            'address' => '123 Main Road, Sector 15',
            'city' => 'Gurgaon',
            'state' => 'Haryana',
            'pincode' => '122001',
            'country' => 'India',
            'phone' => '+91 124 456 7890',
            'email' => 'admin@greenwoodtownship.com',
            'status' => 'active',
        ]);

        // 1. BUILDINGS (2 rows)
        $buildings = [
            [
                'society_id' => $society->id,
                'name' => 'Sunrise Apartments',
                'description' => 'Modern apartment complex in Block A',
                'total_floors' => 12,
                'total_flats' => 48,
                'status' => 'active',
            ],
            [
                'society_id' => $society->id,
                'name' => 'Garden View Tower',
                'description' => 'Premium tower with garden views',
                'total_floors' => 8,
                'total_flats' => 32,
                'status' => 'active',
            ],
        ];

        foreach ($buildings as $buildingData) {
            Building::firstOrCreate(
                ['society_id' => $buildingData['society_id'], 'name' => $buildingData['name']],
                $buildingData
            );
        }

        // 2. APARTMENTS (2 rows)
        $apartments = [
            [
                'society_id' => $society->id,
                'building_id' => 1,
                'property_type' => 'apartment',
                'flat_number' => 'A-301',
                'floor' => 3,
                'type' => '2bhk',
                'carpet_area' => 1250,
                'bedrooms' => 2,
                'bathrooms' => 2,
                'status' => 'occupied',
            ],
            [
                'society_id' => $society->id,
                'building_id' => 2,
                'property_type' => 'apartment',
                'flat_number' => 'B-205',
                'floor' => 2,
                'type' => '3bhk',
                'carpet_area' => 1650,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'status' => 'occupied',
            ],
        ];

        foreach ($apartments as $apartmentData) {
            Flat::firstOrCreate(
                ['society_id' => $apartmentData['society_id'], 'flat_number' => $apartmentData['flat_number']],
                $apartmentData
            );
        }

        // 3. VILLA AREAS (2 rows)
        $villaAreas = [
            [
                'society_id' => $society->id,
                'name' => 'Green Valley Villas',
                'description' => 'Premium villa community in North Wing',
                'total_villas' => 24,
                'status' => 'active',
            ],
            [
                'society_id' => $society->id,
                'name' => 'Royal Gardens',
                'description' => 'Luxury villas with garden views',
                'total_villas' => 18,
                'status' => 'active',
            ],
        ];

        foreach ($villaAreas as $villaAreaData) {
            VillaArea::firstOrCreate(
                ['society_id' => $villaAreaData['society_id'], 'name' => $villaAreaData['name']],
                $villaAreaData
            );
        }

        // 4. VILLAS (2 rows) - Using Flat model with property_type = 'villa'
        $villas = [
            [
                'society_id' => $society->id,
                'villa_area_id' => 1,
                'property_type' => 'villa',
                'flat_number' => 'V-101',
                'villa_name' => 'Green Valley Villa 101',
                'plot_area' => 2400,
                'built_up_area' => 1800,
                'bedrooms' => 3,
                'bathrooms' => 3,
                'status' => 'occupied',
            ],
            [
                'society_id' => $society->id,
                'villa_area_id' => 2,
                'property_type' => 'villa',
                'flat_number' => 'V-205',
                'villa_name' => 'Royal Garden Villa 205',
                'plot_area' => 3200,
                'built_up_area' => 2400,
                'bedrooms' => 4,
                'bathrooms' => 4,
                'status' => 'vacant',
            ],
        ];

        foreach ($villas as $villaData) {
            Flat::firstOrCreate(
                ['society_id' => $villaData['society_id'], 'flat_number' => $villaData['flat_number']],
                $villaData
            );
        }

        // 5. USERS & RESIDENTS (2 rows)
        $users = [
            [
                'name' => 'Amit Patel',
                'email' => 'amit.patel@email.com',
                'phone' => '+91 98765 43210',
                'password' => Hash::make('password123'),
                'role' => 'Apartment Owner',
                'flat_id' => 1, // A-301
            ],
            [
                'name' => 'Neha Singh',
                'email' => 'neha.singh@email.com',
                'phone' => '+91 87654 32109',
                'password' => Hash::make('password123'),
                'role' => 'Villa Owner',
                'flat_id' => 3, // V-101 (villa)
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'society_id' => $society->id,
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'phone' => $userData['phone'],
                    'password' => $userData['password'],
                    'status' => 'active',
                ]
            );

            // Assign role if not already assigned
            if (!$user->hasRole($userData['role'])) {
                $user->assignRole($userData['role']);
            }

            // Create resident record if not exists
            Resident::firstOrCreate(
                ['user_id' => $user->id, 'flat_id' => $userData['flat_id']],
                [
                    'society_id' => $society->id,
                    'user_id' => $user->id,
                    'flat_id' => $userData['flat_id'],
                    'type' => 'owner', // Both apartment and villa owners are 'owner' type
                    'move_in_date' => Carbon::now()->subMonths(6),
                    'status' => 'active',
                ]
            );
        }

        // 6. PAYMENTS & BILLS (2 rows) - Skipped for now as it requires MaintenanceBill records
        // TODO: Create MaintenanceBill records first, then create payments
        $this->command->info('⚠️  Payments skipped - requires MaintenanceBill records');

        /*
        $payments = [
            [
                'society_id' => $society->id,
                'user_id' => 1, // Amit Patel
                'amount' => 4500,
                'payment_method' => 'online',
                'status' => 'success',
                'payment_date' => '2026-01-12',
                'notes' => 'Monthly Maintenance - January 2026',
            ],
            [
                'society_id' => $society->id,
                'user_id' => 2, // Neha Singh
                'amount' => 2850,
                'payment_method' => null,
                'status' => 'pending',
                'payment_date' => null,
                'notes' => 'Electricity Bill - December 2025',
            ],
        ];

        foreach ($payments as $paymentData) {
            Payment::firstOrCreate(
                ['society_id' => $paymentData['society_id'], 'user_id' => $paymentData['user_id'], 'notes' => $paymentData['notes']],
                $paymentData
            );
        }
        */

        // First create services
        $services = [
            [
                'name' => 'House Cleaning',
                'category' => 'daily',
                'is_enabled' => true,
                'requires_attendance' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Electrical Repair',
                'category' => 'maintenance',
                'is_enabled' => true,
                'requires_attendance' => false,
                'sort_order' => 2,
            ],
        ];

        foreach ($services as $serviceData) {
            Service::firstOrCreate(
                ['name' => $serviceData['name']],
                $serviceData
            );
        }

        // 7. SERVICE PROVIDERS (2 rows)
        $serviceProviders = [
            [
                'society_id' => $society->id,
                'service_id' => 1, // House Cleaning
                'name' => 'Maya Cleaning Services',
                'contact_number' => '+91 99887 76655',
                'availability' => 'available',
                'is_daily_help' => true,
                'price' => 300,
                'price_type' => 'per_visit',
                'status' => 'active',
                'notes' => 'Available Mon-Sat, 9 AM - 6 PM',
            ],
            [
                'society_id' => $society->id,
                'service_id' => 2, // Electrical Repair
                'name' => 'Ravi Electrical Works',
                'contact_number' => '+91 88776 65544',
                'availability' => 'available',
                'is_daily_help' => false,
                'price' => 500,
                'price_type' => 'per_visit',
                'status' => 'active',
                'notes' => '24/7 Emergency Service',
            ],
        ];

        foreach ($serviceProviders as $providerData) {
            ServiceProvider::firstOrCreate(
                ['society_id' => $providerData['society_id'], 'name' => $providerData['name']],
                $providerData
            );
        }

        // Create complaint categories first
        $complaintCategories = [
            ['name' => 'Plumbing', 'description' => 'Water and plumbing related issues'],
            ['name' => 'Electrical', 'description' => 'Electrical problems and repairs'],
        ];

        foreach ($complaintCategories as $categoryData) {
            \App\Models\ComplaintCategory::firstOrCreate(
                ['name' => $categoryData['name'], 'society_id' => $society->id],
                array_merge($categoryData, ['society_id' => $society->id, 'status' => 'active'])
            );
        }

        // 8. COMPLAINTS & TICKETS (2 rows)
        $complaints = [
            [
                'society_id' => $society->id,
                'complaint_category_id' => 1, // Plumbing
                'flat_id' => 1, // A-301
                'created_by' => 1, // Amit Patel
                'title' => 'Water Leakage in Bathroom',
                'description' => 'There is continuous water leakage from the bathroom tap in A-301. Please send maintenance team urgently.',
                'priority' => 'high',
                'status' => 'in_progress',
            ],
            [
                'society_id' => $society->id,
                'complaint_category_id' => 2, // Electrical
                'flat_id' => 3, // V-101
                'created_by' => 2, // Neha Singh
                'title' => 'Street Light Not Working',
                'description' => 'Street light near V-101 entrance is not working since last 3 days. It is a safety concern during night hours.',
                'priority' => 'medium',
                'status' => 'open',
            ],
        ];

        foreach ($complaints as $complaintData) {
            Complaint::firstOrCreate(
                ['society_id' => $complaintData['society_id'], 'title' => $complaintData['title']],
                $complaintData
            );
        }

        // 9. VISITORS MANAGEMENT (2 rows)
        $visitors = [
            [
                'society_id' => $society->id,
                'flat_id' => 1, // A-301
                'host_user_id' => 1, // Amit Patel
                'visitor_name' => 'Rohit Gupta',
                'visitor_phone' => '+91 99123 45678',
                'visitor_type' => 'guest',
                'purpose' => 'Family Visit',
                'expected_entry_time' => Carbon::today()->setTime(14, 0),
                'expected_exit_time' => Carbon::today()->setTime(18, 0),
                'approval_status' => 'approved',
                'entry_status' => 'pending',
            ],
            [
                'society_id' => $society->id,
                'flat_id' => 3, // V-101 (villa)
                'host_user_id' => 2, // Neha Singh
                'visitor_name' => 'Kavya Reddy',
                'visitor_phone' => '+91 88234 56789',
                'visitor_type' => 'guest',
                'purpose' => 'Business Meeting',
                'expected_entry_time' => Carbon::tomorrow()->setTime(10, 0),
                'expected_exit_time' => Carbon::tomorrow()->setTime(12, 0),
                'approval_status' => 'pending',
                'entry_status' => 'pending',
            ],
        ];

        foreach ($visitors as $visitorData) {
            Visitor::firstOrCreate(
                ['society_id' => $visitorData['society_id'], 'visitor_name' => $visitorData['visitor_name'], 'visitor_phone' => $visitorData['visitor_phone']],
                $visitorData
            );
        }

        // 10. SOCIETY SERVICES (2 additional rows)
        $additionalServices = [
            [
                'name' => 'Swimming Pool',
                'category' => 'society',
                'is_enabled' => true,
                'requires_attendance' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Gym & Fitness Center',
                'category' => 'society',
                'is_enabled' => true,
                'requires_attendance' => false,
                'sort_order' => 4,
            ],
        ];

        foreach ($additionalServices as $serviceData) {
            Service::firstOrCreate(
                ['name' => $serviceData['name']],
                $serviceData
            );
        }

        $this->command->info('✅ Realistic dummy data seeded successfully!');
        $this->command->info('');
        $this->command->info('📊 DATA SUMMARY:');
        $this->command->info('✓ 1 Society: Greenwood Township');
        $this->command->info('✓ 2 Buildings: Sunrise Apartments, Garden View Tower');
        $this->command->info('✓ 2 Apartments: A-301 (Occupied), B-205 (On Rent)');
        $this->command->info('✓ 2 Villa Areas: Green Valley Villas, Royal Gardens');
        $this->command->info('✓ 2 Villas: V-101 (Occupied), V-205 (Vacant)');
        $this->command->info('✓ 2 Residents: Amit Patel (Apt Owner), Neha Singh (Villa Owner)');
        $this->command->info('✓ 2 Bills: Maintenance (Paid), Electricity (Unpaid)');
        $this->command->info('✓ 2 Service Providers: Maya Cleaning, Ravi Electrical');
        $this->command->info('✓ 2 Tickets: Water Leakage (In Progress), Street Light (Open)');
        $this->command->info('✓ 2 Visitors: Rohit Gupta (Approved), Kavya Reddy (Pending)');
        $this->command->info('✓ 4 Society Services: Cleaning, Electrical, Pool, Gym');
        $this->command->info('');
        $this->command->info('🎯 Perfect for demos, testing & screenshots!');
    }
}