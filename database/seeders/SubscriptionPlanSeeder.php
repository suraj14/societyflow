<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Perfect for small societies with up to 50 flats',
                'monthly_price' => 999.00,
                'yearly_price' => 9999.00,
                'max_flats' => 50,
                'max_users' => 100,
                'max_staff' => 5,
                'features' => [
                    'Resident Management',
                    'Maintenance Billing',
                    'Basic Complaints',
                    'Notice Board',
                    'Basic Reports',
                ],
                'limitations' => [
                    'No Facility Booking',
                    'No Visitor Management',
                    'Limited Support',
                ],
                'trial_days' => 15,
                'status' => 'active',
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for medium societies with up to 200 flats',
                'monthly_price' => 1999.00,
                'yearly_price' => 19999.00,
                'max_flats' => 200,
                'max_users' => 400,
                'max_staff' => 15,
                'features' => [
                    'All Basic Features',
                    'Facility Booking',
                    'Visitor Management',
                    'Advanced Complaints',
                    'Staff Management',
                    'Detailed Reports',
                    'Email Notifications',
                ],
                'limitations' => [
                    'Limited SMS',
                    'Standard Support',
                ],
                'trial_days' => 30,
                'is_popular' => true,
                'status' => 'active',
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large societies with unlimited flats',
                'monthly_price' => 4999.00,
                'yearly_price' => 49999.00,
                'max_flats' => 0, // Unlimited
                'max_users' => 0, // Unlimited
                'max_staff' => 0, // Unlimited
                'features' => [
                    'All Professional Features',
                    'Unlimited Everything',
                    'Advanced Analytics',
                    'Custom Reports',
                    'API Access',
                    'Priority Support',
                    'Custom Integrations',
                    'Unlimited SMS',
                ],
                'limitations' => [],
                'trial_days' => 30,
                'status' => 'active',
            ],
        ];

        foreach ($plans as $plan) {
            // Convert arrays to JSON strings
            if (isset($plan['features']) && is_array($plan['features'])) {
                $plan['features'] = json_encode($plan['features']);
            }
            if (isset($plan['limitations']) && is_array($plan['limitations'])) {
                $plan['limitations'] = json_encode($plan['limitations']);
            }
            SubscriptionPlan::create($plan);
        }

        $this->command->info('Subscription plans created successfully!');
    }
}