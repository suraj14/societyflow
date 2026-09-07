<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Facility;
use App\Models\Notice;
use App\Models\ServiceProvider;
use App\Models\User;
use Carbon\Carbon;

class DemoTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get demo users for testing
        $adminUser = User::where('email', 'admin@societyflow.com')->first();

        // Get or create a society
        $society = \App\Models\Society::first() ?? \App\Models\Society::create([
            'name' => 'Demo Society',
            'address' => '123 Main Street, City',
            'city' => 'City',
            'state' => 'State',
            'postal_code' => '123456',
            'country' => 'Country',
        ]);

        // Create Facilities (5 rows)
        $facilities = [
            [
                'society_id' => $society->id,
                'name' => 'Swimming Pool',
                'description' => 'Olympic-size swimming pool with changing rooms',
                'type' => 'swimming_pool',
                'capacity' => 50,
                'booking_charge' => 500,
                'status' => 'active',
                'image' => 'pool.jpg',
                'opening_time' => '06:00:00',
                'closing_time' => '20:00:00',
                'available_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                'advance_booking_days' => 7,
                'max_booking_hours' => 2,
                'requires_approval' => false,
            ],
            [
                'society_id' => $society->id,
                'name' => 'Gym & Fitness Center',
                'description' => 'Fully equipped gym with modern equipment',
                'type' => 'gym',
                'capacity' => 30,
                'booking_charge' => 0,
                'status' => 'active',
                'image' => 'gym.jpg',
                'opening_time' => '05:00:00',
                'closing_time' => '22:00:00',
                'available_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                'advance_booking_days' => 7,
                'max_booking_hours' => 3,
                'requires_approval' => false,
            ],
            [
                'society_id' => $society->id,
                'name' => 'Community Hall',
                'description' => 'Multi-purpose hall for events and gatherings',
                'type' => 'hall',
                'capacity' => 200,
                'booking_charge' => 2000,
                'status' => 'active',
                'image' => 'hall.jpg',
                'opening_time' => '08:00:00',
                'closing_time' => '23:00:00',
                'available_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                'advance_booking_days' => 14,
                'max_booking_hours' => 8,
                'requires_approval' => true,
            ],
            [
                'society_id' => $society->id,
                'name' => 'Tennis Court',
                'description' => 'Professional tennis court with lighting',
                'type' => 'other',
                'capacity' => 4,
                'booking_charge' => 300,
                'status' => 'active',
                'image' => 'tennis.jpg',
                'opening_time' => '07:00:00',
                'closing_time' => '21:00:00',
                'available_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                'advance_booking_days' => 7,
                'max_booking_hours' => 1.5,
                'requires_approval' => false,
            ],
            [
                'society_id' => $society->id,
                'name' => 'Children Playground',
                'description' => 'Safe playground with modern equipment',
                'type' => 'playground',
                'capacity' => 100,
                'booking_charge' => 0,
                'status' => 'active',
                'image' => 'playground.jpg',
                'opening_time' => '06:00:00',
                'closing_time' => '19:00:00',
                'available_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                'advance_booking_days' => 3,
                'max_booking_hours' => 2,
                'requires_approval' => false,
            ],
        ];

        foreach ($facilities as $facility) {
            Facility::create($facility);
        }

        // Create Notices (5 rows)
        $notices = [
            [
                'society_id' => $society->id,
                'title' => 'Maintenance Schedule - Water Tank Cleaning',
                'content' => 'Water tank cleaning will be done on 15th January. Water supply will be interrupted from 8 AM to 2 PM.',
                'type' => 'maintenance',
                'priority' => 'high',
                'status' => 'published',
                'created_by' => $adminUser->id ?? 1,
                'publish_date' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'society_id' => $society->id,
                'title' => 'Annual General Meeting',
                'content' => 'AGM will be held on 20th January at 6 PM in the Community Hall. All residents are requested to attend.',
                'type' => 'meeting',
                'priority' => 'high',
                'status' => 'published',
                'created_by' => $adminUser->id ?? 1,
                'publish_date' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'society_id' => $society->id,
                'title' => 'Parking Rules Update',
                'content' => 'New parking rules have been implemented. Please ensure vehicles are parked in designated areas only.',
                'type' => 'general',
                'priority' => 'medium',
                'status' => 'published',
                'created_by' => $adminUser->id ?? 1,
                'publish_date' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'society_id' => $society->id,
                'title' => 'Garbage Collection Schedule',
                'content' => 'Garbage collection will now be done on Mondays, Wednesdays, and Fridays at 6 AM.',
                'type' => 'general',
                'priority' => 'medium',
                'status' => 'published',
                'created_by' => $adminUser->id ?? 1,
                'publish_date' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'society_id' => $society->id,
                'title' => 'Security Alert - Unauthorized Entry',
                'content' => 'Please be vigilant about unauthorized entries. Report any suspicious activity to security immediately.',
                'type' => 'urgent',
                'priority' => 'high',
                'status' => 'published',
                'created_by' => $adminUser->id ?? 1,
                'publish_date' => Carbon::now()->format('Y-m-d'),
            ],
        ];

        foreach ($notices as $notice) {
            Notice::create($notice);
        }

        $this->command->info('Demo test data seeded successfully!');
        $this->command->info('✓ 5 Facilities created');
        $this->command->info('✓ 5 Notices created');
    }
}
