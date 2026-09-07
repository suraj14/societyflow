<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Society;
use Illuminate\Database\Seeder;

class SampleServiceProvidersSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first society for demo data
        $society = Society::first();
        
        if (!$society) {
            $this->command->warn('No society found. Please create a society first.');
            return;
        }

        // Get services
        $services = Service::all();
        
        if ($services->isEmpty()) {
            $this->command->warn('No services found. Please run ServicesSeeder first.');
            return;
        }

        $sampleProviders = [
            // Daily Services
            [
                'service_name' => 'Maid',
                'providers' => [
                    ['name' => 'Sunita Devi', 'contact' => '9876543210', 'price' => 3000, 'price_type' => 'per_month', 'daily_help' => true],
                    ['name' => 'Kamala Bai', 'contact' => '9876543211', 'price' => 2800, 'price_type' => 'per_month', 'daily_help' => true],
                ]
            ],
            [
                'service_name' => 'Cook',
                'providers' => [
                    ['name' => 'Ravi Kumar', 'contact' => '9876543212', 'price' => 5000, 'price_type' => 'per_month', 'daily_help' => true],
                    ['name' => 'Meera Sharma', 'contact' => '9876543213', 'price' => 4500, 'price_type' => 'per_month', 'daily_help' => true],
                ]
            ],
            [
                'service_name' => 'Driver',
                'providers' => [
                    ['name' => 'Rajesh Singh', 'contact' => '9876543214', 'price' => 8000, 'price_type' => 'per_month', 'daily_help' => false],
                    ['name' => 'Amit Yadav', 'contact' => '9876543215', 'price' => 7500, 'price_type' => 'per_month', 'daily_help' => false],
                ]
            ],
            
            // Maintenance Services
            [
                'service_name' => 'Electrician',
                'providers' => [
                    ['name' => 'Suresh Electricals', 'contact' => '9876543216', 'price' => 500, 'price_type' => 'per_visit', 'daily_help' => false],
                    ['name' => 'Modern Electric Works', 'contact' => '9876543217', 'price' => 600, 'price_type' => 'per_visit', 'daily_help' => false],
                ]
            ],
            [
                'service_name' => 'Plumber',
                'providers' => [
                    ['name' => 'Ramesh Plumbing', 'contact' => '9876543218', 'price' => 400, 'price_type' => 'per_visit', 'daily_help' => false],
                    ['name' => 'Quick Fix Plumbers', 'contact' => '9876543219', 'price' => 450, 'price_type' => 'per_visit', 'daily_help' => false],
                ]
            ],
            
            // Society Support
            [
                'service_name' => 'Security Staff',
                'providers' => [
                    ['name' => 'Vikram Security', 'contact' => '9876543220', 'price' => 15000, 'price_type' => 'per_month', 'daily_help' => true],
                    ['name' => 'Safe Guard Services', 'contact' => '9876543221', 'price' => 16000, 'price_type' => 'per_month', 'daily_help' => true],
                ]
            ],
            [
                'service_name' => 'Gardener',
                'providers' => [
                    ['name' => 'Green Thumb Gardens', 'contact' => '9876543222', 'price' => 2000, 'price_type' => 'per_month', 'daily_help' => true],
                    ['name' => 'Nature Care', 'contact' => '9876543223', 'price' => 1800, 'price_type' => 'per_month', 'daily_help' => true],
                ]
            ],
            
            // Optional Services
            [
                'service_name' => 'Milkman',
                'providers' => [
                    ['name' => 'Fresh Dairy', 'contact' => '9876543224', 'price' => 60, 'price_type' => 'per_day', 'daily_help' => true],
                    ['name' => 'Pure Milk Co.', 'contact' => '9876543225', 'price' => 55, 'price_type' => 'per_day', 'daily_help' => true],
                ]
            ],
        ];

        foreach ($sampleProviders as $serviceData) {
            $service = $services->where('name', $serviceData['service_name'])->first();
            
            if (!$service) {
                continue;
            }

            foreach ($serviceData['providers'] as $providerData) {
                ServiceProvider::create([
                    'society_id' => $society->id,
                    'service_id' => $service->id,
                    'name' => $providerData['name'],
                    'contact_number' => $providerData['contact'],
                    'availability' => 'available',
                    'is_daily_help' => $providerData['daily_help'],
                    'price' => $providerData['price'],
                    'price_type' => $providerData['price_type'],
                    'status' => 'active',
                    'notes' => 'Sample service provider for demonstration',
                ]);
            }
        }

        $this->command->info('Sample service providers created successfully!');
    }
}