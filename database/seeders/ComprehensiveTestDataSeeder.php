<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Event;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;
use App\Models\Visitor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ComprehensiveTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use existing society (Urban Heights Residency) or create if doesn't exist
        $society = Society::where('slug', 'urban-heights-residency')->first();
        
        if (!$society) {
            $society = Society::firstOrCreate(
                ['name' => 'Urban Heights Residency'],
                [
                    'slug' => 'urban-heights-residency',
                    'email' => 'info@urbanheights.com',
                    'phone' => '9876543210',
                    'address' => '123 Main Street, Downtown',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'postal_code' => '400001',
                    'country' => 'India',
                    'admin_id' => null,
                ]
            );
        }

        // Get existing admin user or create one
        $adminUser = User::where('email', 'admin@societyflow.com')->first();
        
        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'Admin User',
                'email' => 'admin@societyflow.com',
                'phone' => '9876543211',
                'password' => Hash::make('password'),
                'society_id' => $society->id,
                'status' => 'active',
            ]);
            $adminUser->assignRole('Admin');
        }
        
        $society->update(['admin_id' => $adminUser->id]);

        // ==================== BUILDINGS & APARTMENTS ====================
        $building1 = Building::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Tower A'],
            [
                'code' => 'TOWER_A',
                'total_floors' => 10,
                'total_flats' => 40,
                'description' => 'Premium residential tower with modern amenities',
                'status' => 'active',
            ]
        );

        $building2 = Building::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Tower B'],
            [
                'code' => 'TOWER_B',
                'total_floors' => 8,
                'total_flats' => 24,
                'description' => 'Contemporary architecture with eco-friendly features',
                'status' => 'active',
            ]
        );

        // Create apartments
        $apartments = [];
        $floorNumbers = [1, 2, 3, 4, 5];
        $flatNumbers = ['A', 'B', 'C', 'D'];

        foreach ($floorNumbers as $floor) {
            foreach ($flatNumbers as $flatNum) {
                $flatNumber = $floor . $flatNum;
                $apartments[] = Flat::firstOrCreate(
                    ['society_id' => $society->id, 'building_id' => $building1->id, 'flat_number' => $flatNumber],
                    [
                        'property_type' => 'apartment',
                        'floor' => $floor,
                        'type' => '2BHK',
                        'carpet_area' => 850.00,
                        'built_up_area' => 1100.00,
                        'bedrooms' => 2,
                        'bathrooms' => 2,
                        'has_parking' => true,
                        'parking_slots' => 1,
                        'maintenance_amount' => 5000.00,
                        'status' => 'vacant',
                        'amenities' => ['balcony', 'modular_kitchen', 'ac'],
                    ]
                );
            }
        }

        // ==================== VILLA AREAS & VILLAS ====================
        $villaArea1 = VillaArea::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Green Valley Villas'],
            [
                'description' => 'Spacious villas with private gardens',
                'total_villas' => 12,
            ]
        );

        $villaArea2 = VillaArea::firstOrCreate(
            ['society_id' => $society->id, 'name' => 'Sunset Villas'],
            [
                'description' => 'Premium villas with scenic views',
                'total_villas' => 8,
            ]
        );

        // Create villas
        $villas = [];
        for ($i = 1; $i <= 12; $i++) {
            $villas[] = Flat::firstOrCreate(
                ['society_id' => $society->id, 'villa_area_id' => $villaArea1->id, 'villa_name' => 'Villa ' . $i],
                [
                    'property_type' => 'villa',
                    'flat_number' => 'V' . $i,
                    'plot_area' => 2500.00,
                    'built_up_area' => 1800.00,
                    'bedrooms' => 3,
                    'bathrooms' => 3,
                    'has_garden' => true,
                    'has_parking' => true,
                    'parking_slots' => 2,
                    'maintenance_amount' => 8000.00,
                    'status' => 'vacant',
                    'amenities' => ['garden', 'swimming_pool', 'garage'],
                ]
            );
        }

        // ==================== OWNERS ====================
        $ownerUsers = [];
        $ownerNames = [
            'Rajesh Kumar',
            'Priya Sharma',
            'Amit Patel',
            'Neha Gupta',
            'Vikram Singh',
        ];

        foreach ($ownerNames as $index => $name) {
            $email = 'owner' . ($index + 1) . '@example.com';
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => '98765432' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'society_id' => $society->id,
                    'status' => 'active',
                ]
            );
            if (!$user->hasRole('Apartment Owner')) {
                $user->assignRole('Apartment Owner');
            }
            $ownerUsers[] = $user;
        }

        // Assign apartments to owners
        foreach ($ownerUsers as $index => $owner) {
            if (isset($apartments[$index])) {
                $apartments[$index]->update([
                    'owner_id' => $owner->id,
                    'status' => 'occupied',
                ]);
            }
        }

        // ==================== TENANTS ====================
        $tenantNames = [
            'John Doe',
            'Sarah Johnson',
            'Michael Brown',
            'Emily Davis',
            'David Wilson',
            'Jessica Martinez',
            'Robert Taylor',
            'Lisa Anderson',
        ];

        $tenantUsers = [];
        foreach ($tenantNames as $index => $name) {
            $email = 'tenant' . ($index + 1) . '@example.com';
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => '97654321' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'society_id' => $society->id,
                    'status' => 'active',
                ]
            );
            if (!$user->hasRole('Tenant')) {
                $user->assignRole('Tenant');
            }
            $tenantUsers[] = $user;

            // Create tenant record
            $flatId = isset($apartments[$index + 5]) ? $apartments[$index + 5]->id : null;

            Tenant::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'society_id' => $society->id,
                    'flat_id' => $flatId,
                    'owner_id' => null,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $user->phone,
                    'status' => 'active',
                    'contract_start_date' => Carbon::now()->subMonths(6),
                    'contract_end_date' => Carbon::now()->addMonths(6),
                    'move_in_date' => Carbon::now()->subMonths(6),
                    'monthly_rent' => 50000.00,
                    'rent_billing_cycle' => 'Monthly',
                    'id_type' => 'Aadhar',
                ]
            );

            // Update flat status
            if ($flatId) {
                Flat::find($flatId)->update(['status' => 'occupied']);
            }
        }

        // ==================== FACILITIES ====================
        $facilityData = [
            ['name' => 'Swimming Pool', 'type' => 'swimming_pool'],
            ['name' => 'Gym', 'type' => 'gym'],
            ['name' => 'Community Hall', 'type' => 'hall'],
            ['name' => 'Tennis Court', 'type' => 'playground'],
            ['name' => 'Basketball Court', 'type' => 'playground'],
            ['name' => 'Yoga Studio', 'type' => 'clubhouse'],
            ['name' => 'Library', 'type' => 'clubhouse'],
            ['name' => 'Kids Play Area', 'type' => 'playground'],
        ];

        $facilities = [];
        foreach ($facilityData as $data) {
            $facilities[] = Facility::firstOrCreate(
                ['society_id' => $society->id, 'name' => $data['name']],
                [
                    'description' => 'Premium ' . strtolower($data['name']) . ' facility for residents',
                    'type' => $data['type'],
                    'capacity' => rand(20, 100),
                    'booking_charge' => rand(500, 2000),
                    'status' => 'active',
                    'opening_time' => '06:00',
                    'closing_time' => '22:00',
                    'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                    'requires_approval' => false,
                ]
            );
        }

        // ==================== FACILITY BOOKINGS ====================
        foreach ($tenantUsers as $index => $user) {
            if ($index < 3 && isset($facilities[$index])) {
                // Get the user's flat if they have one
                $tenant = Tenant::where('user_id', $user->id)->first();
                $flatId = $tenant ? $tenant->flat_id : (isset($apartments[$index]) ? $apartments[$index]->id : null);
                
                if ($flatId) {
                    FacilityBooking::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'facility_id' => $facilities[$index]->id,
                            'booking_date' => Carbon::now()->addDays($index + 1),
                        ],
                        [
                            'society_id' => $society->id,
                            'flat_id' => $flatId,
                            'start_time' => '10:00',
                            'end_time' => '12:00',
                            'status' => 'approved',
                            'admin_notes' => 'Facility booking for ' . $user->name,
                            'purpose' => 'Recreation',
                            'expected_guests' => 5,
                            'booking_amount' => 500,
                            'security_deposit' => 100,
                            'payment_status' => 'paid',
                        ]
                    );
                }
            }
        }

        // ==================== SERVICE PROVIDERS ====================
        $serviceProviderNames = [
            'ABC Plumbing Services',
            'XYZ Electrical Works',
            'Quick Repairs Co.',
            'Home Maintenance Plus',
            'Professional Cleaning Services',
        ];

        foreach ($serviceProviderNames as $index => $name) {
            $email = 'provider' . ($index + 1) . '@example.com';
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => '96543210' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'society_id' => $society->id,
                    'status' => 'active',
                ]
            );
            if (!$user->hasRole('Staff')) {
                $user->assignRole('Staff');
            }
        }

        // ==================== SERVICES ====================
        // Services are global, not per-society, so we skip creating them here

        // ==================== VISITORS ====================
        // Visitors require complex setup, skipping for now

        // ==================== COMPLAINT CATEGORIES ====================
        $categoryNames = [
            'Maintenance Issue',
            'Noise Complaint',
            'Parking Issue',
            'Water Problem',
            'Electricity Issue',
            'Cleanliness',
            'Security Concern',
            'Other',
        ];

        $categories = [];
        foreach ($categoryNames as $name) {
            $categories[] = ComplaintCategory::firstOrCreate(
                ['society_id' => $society->id, 'name' => $name],
                [
                    'description' => 'Category for ' . strtolower($name),
                ]
            );
        }

        // ==================== COMPLAINTS ====================
        // Complaints require complex setup, skipping for now

        // ==================== NOTICES ====================
        $noticeTitles = [
            'Maintenance Work Schedule',
            'Water Supply Interruption',
            'Annual General Meeting',
            'Facility Booking Rules',
            'Security Guidelines Update',
            'Parking Policy Change',
            'Festival Celebration Notice',
        ];

        foreach ($noticeTitles as $index => $title) {
            Notice::firstOrCreate(
                ['society_id' => $society->id, 'title' => $title],
                [
                    'content' => 'Important notice: ' . $title . '. Please read carefully and follow the guidelines.',
                    'status' => 'published',
                    'type' => ['general', 'urgent', 'maintenance', 'event', 'meeting'][$index % 5],
                    'priority' => ['low', 'medium', 'high'][$index % 3],
                    'created_by' => $adminUser->id,
                    'publish_date' => Carbon::now()->subDays($index),
                ]
            );
        }

        // ==================== EVENTS ====================
        $eventNames = [
            'Diwali Celebration',
            'New Year Party',
            'Sports Day',
            'Community Cleanup',
            'Yoga Session',
            'Movie Night',
            'Kitty Party',
            'Children\'s Day Celebration',
        ];

        foreach ($eventNames as $index => $name) {
            Event::firstOrCreate(
                ['society_id' => $society->id, 'event_name' => $name],
                [
                    'description' => 'Join us for ' . strtolower($name) . '. All residents are welcome!',
                    'start_date' => Carbon::now()->addDays($index + 5)->setTime(10, 0),
                    'end_date' => Carbon::now()->addDays($index + 5)->setTime(14, 0),
                    'location' => 'Community Hall',
                    'created_by' => $adminUser->id,
                    'status' => 'pending',
                ]
            );
        }

        echo "✅ Comprehensive test data seeded successfully!\n";
        echo "Society: {$society->name}\n";
        echo "Admin Email: {$adminUser->email}\n";
        echo "Admin Password: password\n";
        echo "\nTest Accounts:\n";
        echo "Owner Email: owner1@example.com | Password: password\n";
        echo "Tenant Email: tenant1@example.com | Password: password\n";
        echo "Staff Email: provider1@example.com | Password: password\n";
    }
}
