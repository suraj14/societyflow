<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\Building;
use App\Models\Flat;
use App\Models\VillaArea;
use App\Models\Facility;
use App\Models\Notice;
use App\Models\Complaint;
use App\Models\ServiceProvider;
use App\Models\Service;
use App\Models\Visitor;
use App\Models\Payment;
use Spatie\Permission\Models\Role;

class TwoAdminSampleDataSeeder extends Seeder
{
    public function run()
    {
        // Create two societies with different admins
        $this->createSocieties();
        
        // Create sample data for both societies
        $this->createSampleDataForSociety1(); // admin@societyflow.com
        $this->createSampleDataForSociety2(); // sahgunvilla@gmail.com
        
        // Create different settings for both societies
        $this->createDifferentSettings();
    }

    private function createSocieties()
    {
        // Society 1 - Urban Heights (check if exists first)
        $society1 = Society::where('slug', 'urban-heights-residency')->first();
        if (!$society1) {
            $society1 = Society::create([
                'name' => 'Urban Heights Residency',
                'slug' => 'urban-heights-residency',
                'subdomain' => 'urbanheights',
                'address' => '123 Metropolitan Avenue, Downtown District, Mumbai, Maharashtra, India',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '400001',
                'phone' => '+91-9876543210',
                'email' => 'info@urbanheights.com',
                'website' => 'www.urbanheights.com',
                'description' => 'Premium residential complex with modern amenities and excellent connectivity.',
                'settings' => json_encode([
                    'registration_number' => 'UHR2024001',
                    'established_date' => '2020-01-15',
                    'total_units' => 150,
                    'total_area' => '5.2 acres',
                    'amenities' => [
                        'Swimming Pool', 'Gym', 'Club House', 'Children Play Area', 
                        'Jogging Track', 'Security', 'Power Backup', 'Parking'
                    ]
                ]),
                'status' => 'active',
                'trial_ends_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Society 2 - Sahgun Villa Estate (check if exists first)
        $society2 = Society::where('slug', 'sahgun-villa-estate')->first();
        if (!$society2) {
            $society2 = Society::create([
                'name' => 'Sahgun Villa Estate',
                'slug' => 'sahgun-villa-estate',
                'subdomain' => 'sahgunvilla',
                'address' => '456 Garden Valley Road, Green Hills, Pune, Maharashtra, India',
                'city' => 'Pune',
                'state' => 'Maharashtra', 
                'country' => 'India',
                'pincode' => '411001',
                'phone' => '+91-8765432109',
                'email' => 'contact@sahgunvilla.com',
                'website' => 'www.sahgunvilla.com',
                'description' => 'Luxury villa estate with spacious homes and premium lifestyle amenities.',
                'settings' => json_encode([
                    'registration_number' => 'SVE2024002',
                    'established_date' => '2019-06-20',
                    'total_units' => 85,
                    'total_area' => '8.5 acres',
                    'amenities' => [
                        'Private Gardens', 'Community Hall', 'Tennis Court', 'Spa & Wellness',
                        'Organic Farm', '24x7 Security', 'Solar Power', 'Private Parking'
                    ]
                ]),
                'status' => 'active',
                'trial_ends_at' => now()->addYear(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create Admin Users
        $adminRole = Role::where('name', 'Admin')->first();

        // Admin 1 - Urban Heights (check if exists first)
        $admin1 = User::where('email', 'admin@societyflow.com')->first();
        if (!$admin1) {
            $admin1 = User::create([
                'name' => 'Rajesh Kumar',
                'email' => 'admin@societyflow.com',
                'password' => Hash::make('password'),
                'phone' => '+91-9876543210',
                'society_id' => $society1->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $admin1->assignRole($adminRole);
        } else {
            // Update existing user
            $admin1->update([
                'society_id' => $society1->id,
                'name' => 'Rajesh Kumar',
                'phone' => '+91-9876543210'
            ]);
        }

        // Admin 2 - Sahgun Villa (check if exists first)
        $admin2 = User::where('email', 'sahgunvilla@gmail.com')->first();
        if (!$admin2) {
            $admin2 = User::create([
                'name' => 'Priya Sharma',
                'email' => 'sahgunvilla@gmail.com',
                'password' => Hash::make('password'),
                'phone' => '+91-8765432109',
                'society_id' => $society2->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $admin2->assignRole($adminRole);
        } else {
            // Update existing user
            $admin2->update([
                'society_id' => $society2->id,
                'name' => 'Priya Sharma',
                'phone' => '+91-8765432109'
            ]);
        }

        // Update societies with admin_id
        $society1->update(['admin_id' => $admin1->id]);
        $society2->update(['admin_id' => $admin2->id]);
    }

    private function createSampleDataForSociety1()
    {
        $society1 = Society::where('name', 'Urban Heights Residency')->first();

        // Create 5 Buildings
        $buildings = [];
        for ($i = 1; $i <= 5; $i++) {
            $buildings[] = Building::create([
                'name' => "Tower $i",
                'description' => "Modern residential tower with premium amenities",
                'floors' => rand(15, 25),
                'flats_per_floor' => rand(4, 8),
                'society_id' => $society1->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create 5 Apartments per building (25 total)
        $apartmentOwnerRole = Role::where('name', 'Apartment Owner')->first();
        $tenantRole = Role::where('name', 'Tenant')->first();
        
        foreach ($buildings as $building) {
            for ($j = 1; $j <= 5; $j++) {
                $flat = Flat::create([
                    'flat_number' => $building->name . "-" . str_pad($j, 3, '0', STR_PAD_LEFT),
                    'property_type' => 'apartment',
                    'floor' => rand(1, $building->floors),
                    'type' => ['1BHK', '2BHK', '3BHK', '4BHK'][rand(0, 3)],
                    'built_up_area' => rand(600, 1500),
                    'carpet_area' => rand(500, 1200),
                    'building_id' => $building->id,
                    'society_id' => $society1->id,
                    'status' => ['occupied', 'vacant', 'maintenance'][rand(0, 2)],
                    'maintenance_amount' => rand(15000, 45000),
                    'bedrooms' => rand(1, 4),
                    'bathrooms' => rand(1, 3),
                    'has_parking' => rand(0, 1),
                    'parking_slots' => rand(0, 2),
                    'amenities' => json_encode(['Parking', 'Security', 'Power Backup', 'Lift']),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Create apartment owner
                if ($flat->status === 'occupied') {
                    $owner = User::create([
                        'name' => $this->generateIndianName(),
                        'email' => 'owner' . $flat->id . '@urbanheights.com',
                        'password' => Hash::make('password'),
                        'phone' => '+91-' . rand(7000000000, 9999999999),
                        'society_id' => $society1->id,
                        'status' => 'active',
                        'email_verified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $owner->assignRole($apartmentOwnerRole);

                    // Update flat with owner_id
                    $flat->update(['owner_id' => $owner->id]);

                    // Sometimes add tenant
                    if (rand(0, 1)) {
                        $tenant = User::create([
                            'name' => $this->generateIndianName(),
                            'email' => 'tenant' . $flat->id . '@urbanheights.com',
                            'password' => Hash::make('password'),
                            'phone' => '+91-' . rand(7000000000, 9999999999),
                            'society_id' => $society1->id,
                            'status' => 'active',
                            'email_verified_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $tenant->assignRole($tenantRole);
                    }
                }
            }
        }

        // Create 5 Facilities
        $facilities = [
            ['name' => 'Swimming Pool', 'description' => 'Olympic size swimming pool with separate kids pool', 'capacity' => 50, 'booking_fee' => 500],
            ['name' => 'Gymnasium', 'description' => 'Fully equipped modern gym with latest equipment', 'capacity' => 30, 'booking_fee' => 300],
            ['name' => 'Club House', 'description' => 'Multi-purpose hall for events and gatherings', 'capacity' => 200, 'booking_fee' => 2000],
            ['name' => 'Tennis Court', 'description' => 'Professional tennis court with flood lights', 'capacity' => 4, 'booking_fee' => 800],
            ['name' => 'Children Play Area', 'description' => 'Safe and fun play area for children', 'capacity' => 25, 'booking_fee' => 200]
        ];

        foreach ($facilities as $facilityData) {
            Facility::create([
                'name' => $facilityData['name'],
                'description' => $facilityData['description'],
                'capacity' => $facilityData['capacity'],
                'booking_fee' => $facilityData['booking_fee'],
                'society_id' => $society1->id,
                'status' => 'active',
                'rules' => json_encode([
                    'Advance booking required',
                    'Maximum 2 hours per booking',
                    'Cleaning charges applicable',
                    'No outside food allowed'
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create 5 Notices
        $notices = [
            ['title' => 'Monthly Maintenance Due', 'content' => 'Dear Residents, Monthly maintenance for January 2025 is due. Please pay by 10th January.'],
            ['title' => 'Swimming Pool Maintenance', 'content' => 'Swimming pool will be closed for maintenance from 15th to 17th January 2025.'],
            ['title' => 'New Year Celebration', 'content' => 'Join us for New Year celebration at Club House on 31st December at 8 PM.'],
            ['title' => 'Parking Guidelines', 'content' => 'Please follow parking guidelines. Unauthorized parking will be towed.'],
            ['title' => 'Security Update', 'content' => 'New security protocols implemented. Please carry ID cards at all times.']
        ];

        foreach ($notices as $noticeData) {
            Notice::create([
                'title' => $noticeData['title'],
                'content' => $noticeData['content'],
                'society_id' => $society1->id,
                'created_by' => User::where('society_id', $society1->id)->where('email', 'admin@societyflow.com')->first()->id,
                'status' => 'published',
                'priority' => ['low', 'medium', 'high'][rand(0, 2)],
                'published_at' => now()->subDays(rand(1, 30)),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create 5 Service Providers
        $serviceProviders = [
            ['name' => 'Mumbai Cleaning Services', 'service_type' => 'Cleaning', 'contact' => '+91-9876543211'],
            ['name' => 'SecureGuard Security', 'service_type' => 'Security', 'contact' => '+91-9876543212'],
            ['name' => 'GreenThumb Gardening', 'service_type' => 'Gardening', 'contact' => '+91-9876543213'],
            ['name' => 'TechFix Maintenance', 'service_type' => 'Maintenance', 'contact' => '+91-9876543214'],
            ['name' => 'AquaClear Pool Service', 'service_type' => 'Pool Maintenance', 'contact' => '+91-9876543215']
        ];

        foreach ($serviceProviders as $providerData) {
            ServiceProvider::create([
                'name' => $providerData['name'],
                'service_type' => $providerData['service_type'],
                'contact_person' => $this->generateIndianName(),
                'phone' => $providerData['contact'],
                'email' => strtolower(str_replace(' ', '', $providerData['name'])) . '@services.com',
                'address' => 'Service Provider Address, Mumbai',
                'society_id' => $society1->id,
                'status' => 'active',
                'rating' => rand(35, 50) / 10,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create 5 Visitors
        for ($i = 1; $i <= 5; $i++) {
            Visitor::create([
                'name' => $this->generateIndianName(),
                'phone' => '+91-' . rand(7000000000, 9999999999),
                'purpose' => ['Personal Visit', 'Delivery', 'Service', 'Official'][rand(0, 3)],
                'flat_id' => Flat::where('society_id', $society1->id)->inRandomOrder()->first()->id,
                'society_id' => $society1->id,
                'entry_time' => now()->subHours(rand(1, 24)),
                'exit_time' => rand(0, 1) ? now()->subHours(rand(0, 12)) : null,
                'status' => ['checked_in', 'checked_out'][rand(0, 1)],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    private function createSampleDataForSociety2()
    {
        $society2 = Society::where('name', 'Sahgun Villa Estate')->first();

        // Create 5 Villa Areas
        $villaAreas = [];
        $areaNames = ['Rose Garden Villas', 'Lotus Valley Homes', 'Orchid Heights', 'Jasmine Residences', 'Marigold Estates'];
        
        for ($i = 0; $i < 5; $i++) {
            $villaAreas[] = VillaArea::create([
                'name' => $areaNames[$i],
                'description' => "Premium villa area with landscaped gardens and modern amenities",
                'total_villas' => rand(15, 25),
                'society_id' => $society2->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create 5 Villas per area (25 total)
        $villaOwnerRole = Role::where('name', 'Villa Owner')->first();
        $tenantRole = Role::where('name', 'Tenant')->first();
        
        foreach ($villaAreas as $area) {
            for ($j = 1; $j <= 5; $j++) {
                $villa = Flat::create([
                    'flat_number' => $area->name[0] . $j,
                    'villa_name' => $area->name[0] . $j,
                    'property_type' => 'villa',
                    'type' => ['3BHK Villa', '4BHK Villa', '5BHK Villa', 'Duplex Villa'][rand(0, 3)],
                    'built_up_area' => rand(2000, 4000),
                    'plot_area' => rand(2500, 5000),
                    'villa_area_id' => $area->id,
                    'society_id' => $society2->id,
                    'status' => ['occupied', 'vacant', 'maintenance'][rand(0, 2)],
                    'maintenance_amount' => rand(50000, 120000),
                    'bedrooms' => rand(3, 5),
                    'bathrooms' => rand(3, 6),
                    'has_garden' => true,
                    'has_parking' => true,
                    'parking_slots' => rand(2, 4),
                    'amenities' => json_encode(['Private Garden', 'Parking', 'Security', 'Power Backup']),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Create villa owner
                if ($villa->status === 'occupied') {
                    $owner = User::create([
                        'name' => $this->generateIndianName(),
                        'email' => 'owner' . $villa->id . '@sahgunvilla.com',
                        'password' => Hash::make('password'),
                        'phone' => '+91-' . rand(7000000000, 9999999999),
                        'society_id' => $society2->id,
                        'status' => 'active',
                        'email_verified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $owner->assignRole($villaOwnerRole);

                    // Update villa with owner_id
                    $villa->update(['owner_id' => $owner->id]);

                    // Sometimes add tenant
                    if (rand(0, 1)) {
                        $tenant = User::create([
                            'name' => $this->generateIndianName(),
                            'email' => 'tenant' . $villa->id . '@sahgunvilla.com',
                            'password' => Hash::make('password'),
                            'phone' => '+91-' . rand(7000000000, 9999999999),
                            'society_id' => $society2->id,
                            'status' => 'active',
                            'email_verified_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $tenant->assignRole($tenantRole);
                    }
                }
            }
        }

        // Create 5 Facilities
        $facilities = [
            ['name' => 'Community Hall', 'description' => 'Elegant hall for weddings and celebrations', 'capacity' => 300, 'booking_fee' => 5000],
            ['name' => 'Spa & Wellness Center', 'description' => 'Rejuvenating spa with massage and wellness services', 'capacity' => 20, 'booking_fee' => 1500],
            ['name' => 'Tennis Court', 'description' => 'Championship tennis court with coaching facility', 'capacity' => 4, 'booking_fee' => 1000],
            ['name' => 'Organic Farm', 'description' => 'Community organic farming area', 'capacity' => 15, 'booking_fee' => 300],
            ['name' => 'Private Gardens', 'description' => 'Beautifully landscaped private garden spaces', 'capacity' => 50, 'booking_fee' => 800]
        ];

        foreach ($facilities as $facilityData) {
            Facility::create([
                'name' => $facilityData['name'],
                'description' => $facilityData['description'],
                'capacity' => $facilityData['capacity'],
                'booking_fee' => $facilityData['booking_fee'],
                'society_id' => $society2->id,
                'status' => 'active',
                'rules' => json_encode([
                    'Prior booking mandatory',
                    'Decoration allowed with approval',
                    'Professional catering permitted',
                    'Security deposit required'
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create 5 Notices
        $notices = [
            ['title' => 'Villa Maintenance Schedule', 'content' => 'Annual villa maintenance will begin from 20th January. Please coordinate with maintenance team.'],
            ['title' => 'Organic Farm Workshop', 'content' => 'Learn organic farming techniques. Workshop on 25th January at 10 AM in Community Hall.'],
            ['title' => 'Tennis Tournament', 'content' => 'Annual tennis tournament registration open. Prize money Rs. 50,000. Register by 30th January.'],
            ['title' => 'Spa Services Update', 'content' => 'New wellness packages available at Spa Center. Special discounts for residents.'],
            ['title' => 'Security Enhancement', 'content' => 'CCTV cameras upgraded. New access control system installed at all entry points.']
        ];

        foreach ($notices as $noticeData) {
            Notice::create([
                'title' => $noticeData['title'],
                'content' => $noticeData['content'],
                'society_id' => $society2->id,
                'created_by' => User::where('society_id', $society2->id)->where('email', 'sahgunvilla@gmail.com')->first()->id,
                'status' => 'published',
                'priority' => ['low', 'medium', 'high'][rand(0, 2)],
                'published_at' => now()->subDays(rand(1, 30)),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create 5 Service Providers
        $serviceProviders = [
            ['name' => 'Pune Elite Cleaning', 'service_type' => 'Housekeeping', 'contact' => '+91-8765432111'],
            ['name' => 'GreenScape Landscaping', 'service_type' => 'Landscaping', 'contact' => '+91-8765432112'],
            ['name' => 'VillaCare Maintenance', 'service_type' => 'Villa Maintenance', 'contact' => '+91-8765432113'],
            ['name' => 'AquaPure Water Systems', 'service_type' => 'Water Treatment', 'contact' => '+91-8765432114'],
            ['name' => 'SolarTech Energy', 'service_type' => 'Solar Maintenance', 'contact' => '+91-8765432115']
        ];

        foreach ($serviceProviders as $providerData) {
            ServiceProvider::create([
                'name' => $providerData['name'],
                'service_type' => $providerData['service_type'],
                'contact_person' => $this->generateIndianName(),
                'phone' => $providerData['contact'],
                'email' => strtolower(str_replace(' ', '', $providerData['name'])) . '@services.com',
                'address' => 'Service Provider Address, Pune',
                'society_id' => $society2->id,
                'status' => 'active',
                'rating' => rand(40, 50) / 10,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create 5 Visitors
        for ($i = 1; $i <= 5; $i++) {
            Visitor::create([
                'name' => $this->generateIndianName(),
                'phone' => '+91-' . rand(7000000000, 9999999999),
                'purpose' => ['Family Visit', 'Business Meeting', 'Catering Service', 'Maintenance'][rand(0, 3)],
                'flat_id' => Flat::where('society_id', $society2->id)->where('property_type', 'villa')->inRandomOrder()->first()->id,
                'society_id' => $society2->id,
                'entry_time' => now()->subHours(rand(1, 24)),
                'exit_time' => rand(0, 1) ? now()->subHours(rand(0, 12)) : null,
                'status' => ['checked_in', 'checked_out'][rand(0, 1)],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    private function createDifferentSettings()
    {
        $society1 = Society::where('name', 'Urban Heights Residency')->first();
        $society2 = Society::where('name', 'Sahgun Villa Estate')->first();

        // Settings for Society 1 (Urban Heights) - Modern Corporate Style
        $society1Settings = [
            // Society Info
            ['key' => 'society_logo', 'value' => 'urban-heights-logo.png', 'society_id' => $society1->id],
            ['key' => 'society_tagline', 'value' => 'Modern Living, Premium Lifestyle', 'society_id' => $society1->id],
            ['key' => 'society_vision', 'value' => 'To provide world-class residential experience with cutting-edge amenities', 'society_id' => $society1->id],
            
            // App Settings
            ['key' => 'app_name', 'value' => 'Urban Heights Connect', 'society_id' => $society1->id],
            ['key' => 'app_timezone', 'value' => 'Asia/Kolkata', 'society_id' => $society1->id],
            ['key' => 'app_language', 'value' => 'en', 'society_id' => $society1->id],
            ['key' => 'date_format', 'value' => 'd/m/Y', 'society_id' => $society1->id],
            ['key' => 'time_format', 'value' => '24', 'society_id' => $society1->id],
            
            // Theme Settings
            ['key' => 'primary_color', 'value' => '#2563eb', 'society_id' => $society1->id],
            ['key' => 'secondary_color', 'value' => '#64748b', 'society_id' => $society1->id],
            ['key' => 'accent_color', 'value' => '#f59e0b', 'society_id' => $society1->id],
            ['key' => 'theme_mode', 'value' => 'light', 'society_id' => $society1->id],
            ['key' => 'sidebar_style', 'value' => 'modern', 'society_id' => $society1->id],
            
            // Email Settings
            ['key' => 'smtp_host', 'value' => 'smtp.gmail.com', 'society_id' => $society1->id],
            ['key' => 'smtp_port', 'value' => '587', 'society_id' => $society1->id],
            ['key' => 'smtp_username', 'value' => 'noreply@urbanheights.com', 'society_id' => $society1->id],
            ['key' => 'smtp_password', 'value' => encrypt('smtp_password_123'), 'society_id' => $society1->id],
            ['key' => 'mail_from_name', 'value' => 'Urban Heights Residency', 'society_id' => $society1->id],
            
            // Payment Gateway Settings
            ['key' => 'razorpay_enabled', 'value' => '1', 'society_id' => $society1->id],
            ['key' => 'razorpay_key_id', 'value' => 'rzp_test_urbanheights123', 'society_id' => $society1->id],
            ['key' => 'razorpay_key_secret', 'value' => encrypt('rzp_secret_urban123'), 'society_id' => $society1->id],
            ['key' => 'stripe_enabled', 'value' => '1', 'society_id' => $society1->id],
            ['key' => 'stripe_publishable_key', 'value' => 'pk_test_urban_heights_123', 'society_id' => $society1->id],
            ['key' => 'stripe_secret_key', 'value' => encrypt('sk_test_urban_secret_123'), 'society_id' => $society1->id],
            ['key' => 'paytm_enabled', 'value' => '1', 'society_id' => $society1->id],
            
            // Push Notifications
            ['key' => 'fcm_server_key', 'value' => encrypt('fcm_urban_heights_key_123'), 'society_id' => $society1->id],
            ['key' => 'push_notifications_enabled', 'value' => '1', 'society_id' => $society1->id],
            ['key' => 'notification_sound', 'value' => 'default', 'society_id' => $society1->id],
            
            // Security Settings
            ['key' => 'two_factor_enabled', 'value' => '1', 'society_id' => $society1->id],
            ['key' => 'session_timeout', 'value' => '120', 'society_id' => $society1->id],
            ['key' => 'password_policy', 'value' => 'strong', 'society_id' => $society1->id],
            ['key' => 'login_attempts', 'value' => '5', 'society_id' => $society1->id],
        ];

        // Settings for Society 2 (Sahgun Villa) - Luxury Premium Style
        $society2Settings = [
            // Society Info
            ['key' => 'society_logo', 'value' => 'sahgun-villa-logo.png', 'society_id' => $society2->id],
            ['key' => 'society_tagline', 'value' => 'Luxury Redefined, Nature Embraced', 'society_id' => $society2->id],
            ['key' => 'society_vision', 'value' => 'Creating an exclusive community where luxury meets sustainability', 'society_id' => $society2->id],
            
            // App Settings
            ['key' => 'app_name', 'value' => 'Sahgun Villa Portal', 'society_id' => $society2->id],
            ['key' => 'app_timezone', 'value' => 'Asia/Kolkata', 'society_id' => $society2->id],
            ['key' => 'app_language', 'value' => 'en', 'society_id' => $society2->id],
            ['key' => 'date_format', 'value' => 'M d, Y', 'society_id' => $society2->id],
            ['key' => 'time_format', 'value' => '12', 'society_id' => $society2->id],
            
            // Theme Settings
            ['key' => 'primary_color', 'value' => '#059669', 'society_id' => $society2->id],
            ['key' => 'secondary_color', 'value' => '#78716c', 'society_id' => $society2->id],
            ['key' => 'accent_color', 'value' => '#dc2626', 'society_id' => $society2->id],
            ['key' => 'theme_mode', 'value' => 'light', 'society_id' => $society2->id],
            ['key' => 'sidebar_style', 'value' => 'elegant', 'society_id' => $society2->id],
            
            // Email Settings
            ['key' => 'smtp_host', 'value' => 'smtp.outlook.com', 'society_id' => $society2->id],
            ['key' => 'smtp_port', 'value' => '587', 'society_id' => $society2->id],
            ['key' => 'smtp_username', 'value' => 'admin@sahgunvilla.com', 'society_id' => $society2->id],
            ['key' => 'smtp_password', 'value' => encrypt('sahgun_smtp_pass_456'), 'society_id' => $society2->id],
            ['key' => 'mail_from_name', 'value' => 'Sahgun Villa Estate', 'society_id' => $society2->id],
            
            // Payment Gateway Settings
            ['key' => 'razorpay_enabled', 'value' => '1', 'society_id' => $society2->id],
            ['key' => 'razorpay_key_id', 'value' => 'rzp_test_sahgunvilla456', 'society_id' => $society2->id],
            ['key' => 'razorpay_key_secret', 'value' => encrypt('rzp_secret_sahgun456'), 'society_id' => $society2->id],
            ['key' => 'paypal_enabled', 'value' => '1', 'society_id' => $society2->id],
            ['key' => 'paypal_client_id', 'value' => 'paypal_sahgun_client_456', 'society_id' => $society2->id],
            ['key' => 'paypal_client_secret', 'value' => encrypt('paypal_sahgun_secret_456'), 'society_id' => $society2->id],
            ['key' => 'phonepe_enabled', 'value' => '1', 'society_id' => $society2->id],
            
            // Push Notifications
            ['key' => 'fcm_server_key', 'value' => encrypt('fcm_sahgun_villa_key_456'), 'society_id' => $society2->id],
            ['key' => 'push_notifications_enabled', 'value' => '1', 'society_id' => $society2->id],
            ['key' => 'notification_sound', 'value' => 'chime', 'society_id' => $society2->id],
            
            // Security Settings
            ['key' => 'two_factor_enabled', 'value' => '1', 'society_id' => $society2->id],
            ['key' => 'session_timeout', 'value' => '180', 'society_id' => $society2->id],
            ['key' => 'password_policy', 'value' => 'medium', 'society_id' => $society2->id],
            ['key' => 'login_attempts', 'value' => '3', 'society_id' => $society2->id],
        ];

        // Insert all settings
        foreach (array_merge($society1Settings, $society2Settings) as $setting) {
            SystemSetting::create($setting);
        }
    }

    private function generateIndianName()
    {
        $firstNames = [
            'Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Ayaan', 'Krishna', 'Ishaan',
            'Aadhya', 'Ananya', 'Diya', 'Ira', 'Pihu', 'Prisha', 'Anvi', 'Riya', 'Myra', 'Aanya',
            'Rajesh', 'Suresh', 'Mahesh', 'Ramesh', 'Dinesh', 'Mukesh', 'Naresh', 'Hitesh', 'Jignesh', 'Paresh',
            'Priya', 'Pooja', 'Neha', 'Kavya', 'Shreya', 'Divya', 'Ritu', 'Meera', 'Sita', 'Gita'
        ];
        
        $lastNames = [
            'Sharma', 'Verma', 'Gupta', 'Agarwal', 'Bansal', 'Jain', 'Singhal', 'Goel', 'Mittal', 'Chopra',
            'Kumar', 'Singh', 'Patel', 'Shah', 'Mehta', 'Desai', 'Modi', 'Joshi', 'Trivedi', 'Pandya',
            'Reddy', 'Rao', 'Nair', 'Menon', 'Pillai', 'Iyer', 'Krishnan', 'Subramanian', 'Venkatesh', 'Raman'
        ];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }
}