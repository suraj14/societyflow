<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;
use App\Models\Society;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class BasicDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create subscription plans
        $defaultPlan = SubscriptionPlan::create([
            'name' => 'Default',
            'slug' => 'default',
            'description' => 'Basic plan with essential features',
            'monthly_price' => 0.00,
            'yearly_price' => 0.00,
            'features' => json_encode([
                'Basic', 'Plus', 'Apartment', 'Rent', 'Common Area Bills',
                'Maintenance', 'Notice Board', 'Amenities', 'Event', 'Ticket',
                'Forum', 'Floor', 'Owner', 'Visitor', 'Service Provider',
                'Service Time Logging', 'Settings', 'Assets', 'Accept Maintenance Payment'
            ]),
            'max_flats' => 100,
            'max_users' => 500,
            'max_staff' => 10,
            'status' => 'active',
        ]);

        $subscriptionPlan = SubscriptionPlan::create([
            'name' => 'Subscription Package',
            'slug' => 'subscription-package',
            'description' => 'Premium plan with advanced features',
            'monthly_price' => 10.00,
            'yearly_price' => 100.00,
            'features' => json_encode([
                'Basic', 'Plus', 'Apartment', 'Rent', 'Common Area Bills',
                'Maintenance', 'Notice Board', 'Amenities', 'Event', 'Ticket',
                'Forum', 'Floor', 'Owner', 'Visitor', 'Service Provider',
                'Service Time Logging', 'Settings', 'Assets', 'Accept Maintenance Payment',
                'Book Security', 'Parking', 'Assets'
            ]),
            'max_flats' => 1000,
            'max_users' => 5000,
            'max_staff' => 50,
            'status' => 'active',
        ]);

        $lifetimeAccess = SubscriptionPlan::create([
            'name' => 'Lifetime Access',
            'slug' => 'lifetime-access',
            'description' => 'One-time payment for lifetime access',
            'monthly_price' => 0.00,
            'yearly_price' => 195.00,
            'features' => json_encode([
                'Basic', 'Plus', 'Apartment', 'Rent', 'Common Area Bills',
                'Maintenance', 'Notice Board', 'Amenities', 'Event', 'Ticket',
                'Forum', 'Floor', 'Owner', 'Visitor', 'Service Provider',
                'Service Time Logging', 'Settings', 'Assets', 'Accept Maintenance Payment',
                'Book Security', 'Parking', 'Assets'
            ]),
            'max_flats' => 0, // unlimited
            'max_users' => 0, // unlimited
            'max_staff' => 0, // unlimited
            'status' => 'active',
        ]);

        $privatePlan = SubscriptionPlan::create([
            'name' => 'Private Package',
            'slug' => 'private-package',
            'description' => 'Custom enterprise solution',
            'monthly_price' => 5.00,
            'yearly_price' => 50.00,
            'features' => json_encode([
                'Basic', 'Plus', 'Apartment', 'Rent', 'Common Area Bills',
                'Maintenance', 'Notice Board', 'Amenities', 'Event', 'Ticket',
                'Forum', 'Floor', 'Owner', 'Visitor', 'Service Provider',
                'Service Time Logging', 'Settings', 'Assets', 'Accept Maintenance Payment'
            ]),
            'max_flats' => 1000,
            'max_users' => 5000,
            'max_staff' => 100,
            'status' => 'active',
        ]);

        // Create sample societies
        $societies = [
            [
                'name' => 'Oakwood Community',
                'email' => 'oakwood.community@example.com',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'plan' => $defaultPlan->id,
                'status' => 'active'
            ],
            [
                'name' => 'Sunset Villas',
                'email' => 'sunset.villas@example.com',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'plan' => $subscriptionPlan->id,
                'status' => 'trial'
            ],
            [
                'name' => 'Harmony Heights',
                'email' => 'harmony.heights@example.com',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'plan' => $lifetimeAccess->id,
                'status' => 'active'
            ],
            [
                'name' => 'Urban Retreat',
                'email' => 'urban.retreat@example.com',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'plan' => $privatePlan->id,
                'status' => 'trial'
            ],
            [
                'name' => 'Demo Society',
                'email' => 'demo.society@example.com',
                'city' => 'Chennai',
                'state' => 'Tamil Nadu',
                'plan' => $defaultPlan->id,
                'status' => 'active'
            ],
        ];

        foreach ($societies as $societyData) {
            Society::create([
                'name' => $societyData['name'],
                'email' => $societyData['email'],
                'phone' => '+91-' . rand(9000000000, 9999999999),
                'address' => 'Sample Address for ' . $societyData['name'],
                'city' => $societyData['city'],
                'state' => $societyData['state'],
                'country' => 'India',
                'pincode' => rand(100000, 999999),
                'status' => $societyData['status'] === 'trial' ? 'active' : $societyData['status'],
                'trial_ends_at' => $societyData['status'] === 'trial' ? now()->addDays(30) : null,
            ]);
        }

        // Create super admin user
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('123456'),
        ]);
    }
}