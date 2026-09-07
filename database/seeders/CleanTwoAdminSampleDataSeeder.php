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
use App\Models\ServiceProvider;
use App\Models\Visitor;
use Spatie\Permission\Models\Role;

class CleanTwoAdminSampleDataSeeder extends Seeder
{
    public function run()
    {
        // Clean existing data for these two societies
        $this->cleanExistingData();
        
        // Create fresh sample data
        $this->createSampleData();
    }

    private function cleanExistingData()
    {
        // Find existing societies
        $society1 = Society::where('slug', 'urban-heights-residency')->first();
        $society2 = Society::where('slug', 'sahgun-villa-estate')->first();

        $societyIds = [];
        if ($society1) $societyIds[] = $society1->id;
        if ($society2) $societyIds[] = $society2->id;

        if (!empty($societyIds)) {
            // Delete related data
            Visitor::whereIn('society_id', $societyIds)->delete();
            ServiceProvider::whereIn('society_id', $societyIds)->delete();
            Notice::whereIn('society_id', $societyIds)->delete();
            Facility::whereIn('society_id', $societyIds)->delete();
            Flat::whereIn('society_id', $societyIds)->delete();
            VillaArea::whereIn('society_id', $societyIds)->delete();
            Building::whereIn('society_id', $societyIds)->delete();
            SystemSetting::whereIn('society_id', $societyIds)->delete();
            User::whereIn('society_id', $societyIds)->delete();
            
            // Delete societies
            Society::whereIn('id', $societyIds)->delete();
        }

        // Also clean specific admin users if they exist
        User::whereIn('email', ['admin@societyflow.com', 'sahgunvilla@gmail.com'])->delete();
    }

    private function createSampleData()
    {
        // Create Society 1 - Urban Heights
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
                'amenities' => ['Swimming Pool', 'Gym', 'Club House', 'Children Play Area', 'Jogging Track', 'Security', 'Power Backup', 'Parking']
            ]),
            'status' => 'active',
            'trial_ends_at' => now()->addYear(),
        ]);

        // Create Society 2 - Sahgun Villa Estate
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
                'amenities' => ['Private Gardens', 'Community Hall', 'Tennis Court', 'Spa & Wellness', 'Organic Farm', '24x7 Security', 'Solar Power', 'Private Parking']
            ]),
            'status' => 'active',
            'trial_ends_at' => now()->addYear(),
        ]);

        // Create Admin Users
        $adminRole = Role::where('name', 'Admin')->first();

        $admin1 = User::create([
            'name' => 'Rajesh Kumar',
            'email' => 'admin@societyflow.com',
            'password' => Hash::make('password'),
            'phone' => '+91-9876543210',
            'society_id' => $society1->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin1->assignRole($adminRole);

        $admin2 = User::create([
            'name' => 'Priya Sharma',
            'email' => 'sahgunvilla@gmail.com',
            'password' => Hash::make('password'),
            'phone' => '+91-8765432109',
            'society_id' => $society2->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin2->assignRole($adminRole);

        // Update societies with admin_id
        $society1->update(['admin_id' => $admin1->id]);
        $society2->update(['admin_id' => $admin2->id]);

        // Create sample data for both societies
        $this->createUrbanHeightsData($society1);
        $this->createSahgunVillaData($society2);
        $this->createDifferentSettings($society1, $society2);
    }

    private function createUrbanHeightsData($society)
    {
        // Create 5 Buildings
        for ($i = 1; $i <= 5; $i++) {
            $building = Building::create([
                'name' => "Tower $i",
                'description' => "Modern residential tower with premium amenities",
                'total_floors' => rand(15, 25),
                'total_flats' => 5, // We'll create 5 flats per building
                'society_id' => $society->id,
                'status' => 'active',
            ]);

            // Create 5 Apartments per building
            for ($j = 1; $j <= 5; $j++) {
                Flat::create([
                    'flat_number' => $building->name . "-" . str_pad($j, 3, '0', STR_PAD_LEFT),
                    'property_type' => 'apartment',
                    'floor' => rand(1, $building->total_floors),
                    'type' => ['1BHK', '2BHK', '3BHK', '4BHK'][rand(0, 3)],
                    'built_up_area' => rand(600, 1500),
                    'carpet_area' => rand(500, 1200),
                    'building_id' => $building->id,
                    'society_id' => $society->id,
                    'status' => ['occupied', 'vacant'][rand(0, 1)],
                    'maintenance_amount' => rand(15000, 45000),
                    'bedrooms' => rand(1, 4),
                    'bathrooms' => rand(1, 3),
                    'has_parking' => rand(0, 1),
                    'parking_slots' => rand(0, 2),
                    'amenities' => json_encode(['Parking', 'Security', 'Power Backup', 'Lift']),
                ]);
            }
        }

        // Create 5 Facilities
        $facilities = [
            ['name' => 'Swimming Pool', 'description' => 'Olympic size swimming pool', 'capacity' => 50, 'booking_charge' => 500],
            ['name' => 'Gymnasium', 'description' => 'Fully equipped modern gym', 'capacity' => 30, 'booking_charge' => 300],
            ['name' => 'Club House', 'description' => 'Multi-purpose hall for events', 'capacity' => 200, 'booking_charge' => 2000],
            ['name' => 'Tennis Court', 'description' => 'Professional tennis court', 'capacity' => 4, 'booking_charge' => 800],
            ['name' => 'Children Play Area', 'description' => 'Safe play area for children', 'capacity' => 25, 'booking_charge' => 200]
        ];

        foreach ($facilities as $facilityData) {
            Facility::create([
                'name' => $facilityData['name'],
                'description' => $facilityData['description'],
                'capacity' => $facilityData['capacity'],
                'booking_charge' => $facilityData['booking_charge'],
                'society_id' => $society->id,
                'status' => 'active',
                'rules' => json_encode(['Advance booking required', 'Maximum 2 hours per booking']),
            ]);
        }

        // Create 5 Notices
        $notices = [
            ['title' => 'Monthly Maintenance Due', 'content' => 'Monthly maintenance for January 2025 is due.'],
            ['title' => 'Swimming Pool Maintenance', 'content' => 'Swimming pool will be closed for maintenance.'],
            ['title' => 'New Year Celebration', 'content' => 'Join us for New Year celebration at Club House.'],
            ['title' => 'Parking Guidelines', 'content' => 'Please follow parking guidelines.'],
            ['title' => 'Security Update', 'content' => 'New security protocols implemented.']
        ];

        foreach ($notices as $noticeData) {
            Notice::create([
                'title' => $noticeData['title'],
                'content' => $noticeData['content'],
                'society_id' => $society->id,
                'created_by' => User::where('society_id', $society->id)->first()->id,
                'status' => 'published',
                'priority' => ['low', 'medium', 'high'][rand(0, 2)],
                'published_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // Create 5 Service Providers
        $serviceProviders = [
            ['name' => 'Mumbai Cleaning Services', 'service_type' => 'Cleaning'],
            ['name' => 'SecureGuard Security', 'service_type' => 'Security'],
            ['name' => 'GreenThumb Gardening', 'service_type' => 'Gardening'],
            ['name' => 'TechFix Maintenance', 'service_type' => 'Maintenance'],
            ['name' => 'AquaClear Pool Service', 'service_type' => 'Pool Maintenance']
        ];

        foreach ($serviceProviders as $providerData) {
            ServiceProvider::create([
                'name' => $providerData['name'],
                'service_type' => $providerData['service_type'],
                'contact_person' => $this->generateIndianName(),
                'phone' => '+91-' . rand(7000000000, 9999999999),
                'email' => strtolower(str_replace(' ', '', $providerData['name'])) . '@services.com',
                'address' => 'Service Provider Address, Mumbai',
                'society_id' => $society->id,
                'status' => 'active',
                'rating' => rand(35, 50) / 10,
            ]);
        }

        // Create 5 Visitors
        for ($i = 1; $i <= 5; $i++) {
            Visitor::create([
                'name' => $this->generateIndianName(),
                'phone' => '+91-' . rand(7000000000, 9999999999),
                'purpose' => ['Personal Visit', 'Delivery', 'Service', 'Official'][rand(0, 3)],
                'flat_id' => Flat::where('society_id', $society->id)->inRandomOrder()->first()->id,
                'society_id' => $society->id,
                'entry_time' => now()->subHours(rand(1, 24)),
                'exit_time' => rand(0, 1) ? now()->subHours(rand(0, 12)) : null,
                'status' => ['checked_in', 'checked_out'][rand(0, 1)],
            ]);
        }
    }

    private function createSahgunVillaData($society)
    {
        // Create 5 Villa Areas
        $areaNames = ['Rose Garden Villas', 'Lotus Valley Homes', 'Orchid Heights', 'Jasmine Residences', 'Marigold Estates'];
        
        for ($i = 0; $i < 5; $i++) {
            $villaArea = VillaArea::create([
                'name' => $areaNames[$i],
                'description' => "Premium villa area with landscaped gardens",
                'total_villas' => 5,
                'society_id' => $society->id,
                'status' => 'active',
            ]);

            // Create 5 Villas per area
            for ($j = 1; $j <= 5; $j++) {
                Flat::create([
                    'flat_number' => $areaNames[$i][0] . $j,
                    'villa_name' => $areaNames[$i][0] . $j,
                    'property_type' => 'villa',
                    'type' => ['3BHK Villa', '4BHK Villa', '5BHK Villa', 'Duplex Villa'][rand(0, 3)],
                    'built_up_area' => rand(2000, 4000),
                    'plot_area' => rand(2500, 5000),
                    'villa_area_id' => $villaArea->id,
                    'society_id' => $society->id,
                    'status' => ['occupied', 'vacant'][rand(0, 1)],
                    'maintenance_amount' => rand(50000, 120000),
                    'bedrooms' => rand(3, 5),
                    'bathrooms' => rand(3, 6),
                    'has_garden' => true,
                    'has_parking' => true,
                    'parking_slots' => rand(2, 4),
                    'amenities' => json_encode(['Private Garden', 'Parking', 'Security', 'Power Backup']),
                ]);
            }
        }

        // Create 5 Facilities
        $facilities = [
            ['name' => 'Community Hall', 'description' => 'Elegant hall for celebrations', 'capacity' => 300, 'booking_charge' => 5000],
            ['name' => 'Spa & Wellness Center', 'description' => 'Rejuvenating spa services', 'capacity' => 20, 'booking_charge' => 1500],
            ['name' => 'Tennis Court', 'description' => 'Championship tennis court', 'capacity' => 4, 'booking_charge' => 1000],
            ['name' => 'Organic Farm', 'description' => 'Community organic farming', 'capacity' => 15, 'booking_charge' => 300],
            ['name' => 'Private Gardens', 'description' => 'Landscaped garden spaces', 'capacity' => 50, 'booking_charge' => 800]
        ];

        foreach ($facilities as $facilityData) {
            Facility::create([
                'name' => $facilityData['name'],
                'description' => $facilityData['description'],
                'capacity' => $facilityData['capacity'],
                'booking_charge' => $facilityData['booking_charge'],
                'society_id' => $society->id,
                'status' => 'active',
                'rules' => json_encode(['Prior booking mandatory', 'Decoration allowed with approval']),
            ]);
        }

        // Create 5 Notices
        $notices = [
            ['title' => 'Villa Maintenance Schedule', 'content' => 'Annual villa maintenance will begin from 20th January.'],
            ['title' => 'Organic Farm Workshop', 'content' => 'Learn organic farming techniques.'],
            ['title' => 'Tennis Tournament', 'content' => 'Annual tennis tournament registration open.'],
            ['title' => 'Spa Services Update', 'content' => 'New wellness packages available.'],
            ['title' => 'Security Enhancement', 'content' => 'CCTV cameras upgraded.']
        ];

        foreach ($notices as $noticeData) {
            Notice::create([
                'title' => $noticeData['title'],
                'content' => $noticeData['content'],
                'society_id' => $society->id,
                'created_by' => User::where('society_id', $society->id)->first()->id,
                'status' => 'published',
                'priority' => ['low', 'medium', 'high'][rand(0, 2)],
                'published_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // Create 5 Service Providers
        $serviceProviders = [
            ['name' => 'Pune Elite Cleaning', 'service_type' => 'Housekeeping'],
            ['name' => 'GreenScape Landscaping', 'service_type' => 'Landscaping'],
            ['name' => 'VillaCare Maintenance', 'service_type' => 'Villa Maintenance'],
            ['name' => 'AquaPure Water Systems', 'service_type' => 'Water Treatment'],
            ['name' => 'SolarTech Energy', 'service_type' => 'Solar Maintenance']
        ];

        foreach ($serviceProviders as $providerData) {
            ServiceProvider::create([
                'name' => $providerData['name'],
                'service_type' => $providerData['service_type'],
                'contact_person' => $this->generateIndianName(),
                'phone' => '+91-' . rand(7000000000, 9999999999),
                'email' => strtolower(str_replace(' ', '', $providerData['name'])) . '@services.com',
                'address' => 'Service Provider Address, Pune',
                'society_id' => $society->id,
                'status' => 'active',
                'rating' => rand(40, 50) / 10,
            ]);
        }

        // Create 5 Visitors
        for ($i = 1; $i <= 5; $i++) {
            Visitor::create([
                'name' => $this->generateIndianName(),
                'phone' => '+91-' . rand(7000000000, 9999999999),
                'purpose' => ['Family Visit', 'Business Meeting', 'Catering Service', 'Maintenance'][rand(0, 3)],
                'flat_id' => Flat::where('society_id', $society->id)->where('property_type', 'villa')->inRandomOrder()->first()->id,
                'society_id' => $society->id,
                'entry_time' => now()->subHours(rand(1, 24)),
                'exit_time' => rand(0, 1) ? now()->subHours(rand(0, 12)) : null,
                'status' => ['checked_in', 'checked_out'][rand(0, 1)],
            ]);
        }
    }

    private function createDifferentSettings($society1, $society2)
    {
        // Settings for Urban Heights (Modern Corporate Style)
        $society1Settings = [
            ['key' => 'primary_color', 'value' => '#2563eb', 'society_id' => $society1->id],
            ['key' => 'secondary_color', 'value' => '#64748b', 'society_id' => $society1->id],
            ['key' => 'app_name', 'value' => 'Urban Heights Connect', 'society_id' => $society1->id],
            ['key' => 'date_format', 'value' => 'd/m/Y', 'society_id' => $society1->id],
            ['key' => 'razorpay_enabled', 'value' => '1', 'society_id' => $society1->id],
            ['key' => 'stripe_enabled', 'value' => '1', 'society_id' => $society1->id],
        ];

        // Settings for Sahgun Villa (Luxury Premium Style)
        $society2Settings = [
            ['key' => 'primary_color', 'value' => '#059669', 'society_id' => $society2->id],
            ['key' => 'secondary_color', 'value' => '#78716c', 'society_id' => $society2->id],
            ['key' => 'app_name', 'value' => 'Sahgun Villa Portal', 'society_id' => $society2->id],
            ['key' => 'date_format', 'value' => 'M d, Y', 'society_id' => $society2->id],
            ['key' => 'razorpay_enabled', 'value' => '1', 'society_id' => $society2->id],
            ['key' => 'paypal_enabled', 'value' => '1', 'society_id' => $society2->id],
        ];

        foreach (array_merge($society1Settings, $society2Settings) as $setting) {
            SystemSetting::create($setting);
        }
    }

    private function generateIndianName()
    {
        $firstNames = ['Aarav', 'Vivaan', 'Aditya', 'Priya', 'Pooja', 'Neha', 'Rajesh', 'Suresh', 'Mahesh'];
        $lastNames = ['Sharma', 'Verma', 'Gupta', 'Patel', 'Shah', 'Mehta', 'Kumar', 'Singh', 'Reddy'];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }
}