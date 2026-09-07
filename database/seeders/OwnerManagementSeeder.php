<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Owner;
use App\Models\Society;
use App\Models\Building;
use App\Models\VillaArea;

class OwnerManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing societies, buildings, and villa areas
        $societies = Society::all();
        $buildings = Building::all();
        $villaAreas = VillaArea::all();

        if ($societies->isEmpty()) {
            $this->command->warn('No societies found. Please run society seeders first.');
            return;
        }

        $this->command->info('Creating 10 Owner Management dummy records...');

        // Create 10 diverse owner records
        $owners = [
            [
                'name' => 'Rajesh Kumar Sharma',
                'email' => 'rajesh.sharma@gmail.com',
                'phone' => '+91-9876543210',
                'property_type' => 'apartment',
                'flat_no' => 'A-101',
                'floor' => 1,
                'address' => '123, MG Road, Sector 15',
                'city' => 'Gurgaon',
                'state' => 'Haryana',
                'postal_code' => '122001',
                'id_type' => 'Aadhaar',
                'bank_name' => 'HDFC Bank',
                'account_number' => '50100123456789',
                'ifsc_code' => 'HDFC0001234',
                'family_members' => [
                    ['name' => 'Sunita Sharma', 'relation' => 'Wife', 'age' => 42],
                    ['name' => 'Arjun Sharma', 'relation' => 'Son', 'age' => 16],
                    ['name' => 'Priya Sharma', 'relation' => 'Daughter', 'age' => 12]
                ],
                'notes' => 'Long-term resident, very cooperative with society activities.'
            ],
            [
                'name' => 'Priya Patel',
                'email' => 'priya.patel@yahoo.com',
                'phone' => '+91-9876543211',
                'property_type' => 'villa',
                'villa_no' => 'V-05',
                'address' => '456, Palm Grove, Villa Complex',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'postal_code' => '400001',
                'id_type' => 'PAN Card',
                'bank_name' => 'ICICI Bank',
                'account_number' => '60200234567890',
                'ifsc_code' => 'ICIC0002345',
                'family_members' => [
                    ['name' => 'Amit Patel', 'relation' => 'Husband', 'age' => 45],
                    ['name' => 'Kavya Patel', 'relation' => 'Daughter', 'age' => 18]
                ],
                'notes' => 'Business owner, prefers digital communication.'
            ],
            [
                'name' => 'Dr. Suresh Reddy',
                'email' => 'dr.suresh.reddy@hospital.com',
                'phone' => '+91-9876543212',
                'property_type' => 'apartment',
                'flat_no' => 'B-205',
                'floor' => 2,
                'address' => '789, Medical Colony, Jubilee Hills',
                'city' => 'Hyderabad',
                'state' => 'Telangana',
                'postal_code' => '500033',
                'id_type' => 'Driving License',
                'bank_name' => 'SBI',
                'account_number' => '30400345678901',
                'ifsc_code' => 'SBIN0003456',
                'family_members' => [
                    ['name' => 'Dr. Lakshmi Reddy', 'relation' => 'Wife', 'age' => 38],
                    ['name' => 'Aditya Reddy', 'relation' => 'Son', 'age' => 14],
                    ['name' => 'Ananya Reddy', 'relation' => 'Daughter', 'age' => 10]
                ],
                'notes' => 'Doctor, often travels for medical conferences.'
            ],
            [
                'name' => 'Meera Singh',
                'email' => 'meera.singh@techcorp.com',
                'phone' => '+91-9876543213',
                'property_type' => 'apartment',
                'flat_no' => 'C-301',
                'floor' => 3,
                'address' => '321, IT Park Road, Electronic City',
                'city' => 'Bangalore',
                'state' => 'Karnataka',
                'postal_code' => '560100',
                'id_type' => 'Aadhaar',
                'bank_name' => 'Axis Bank',
                'account_number' => '91500456789012',
                'ifsc_code' => 'UTIB0004567',
                'family_members' => [
                    ['name' => 'Vikram Singh', 'relation' => 'Husband', 'age' => 35],
                    ['name' => 'Ishaan Singh', 'relation' => 'Son', 'age' => 8]
                ],
                'notes' => 'Software engineer, works from home frequently.'
            ],
            [
                'name' => 'Anil Gupta',
                'email' => 'anil.gupta@business.co.in',
                'phone' => '+91-9876543214',
                'property_type' => 'villa',
                'villa_no' => 'V-12',
                'address' => '654, Green Valley, Luxury Villas',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'postal_code' => '411001',
                'id_type' => 'Passport',
                'bank_name' => 'Kotak Mahindra Bank',
                'account_number' => '71600567890123',
                'ifsc_code' => 'KKBK0005678',
                'family_members' => [
                    ['name' => 'Neha Gupta', 'relation' => 'Wife', 'age' => 40],
                    ['name' => 'Rohan Gupta', 'relation' => 'Son', 'age' => 20],
                    ['name' => 'Simran Gupta', 'relation' => 'Daughter', 'age' => 17]
                ],
                'notes' => 'Business owner, frequently hosts society events.'
            ],
            [
                'name' => 'Kavita Joshi',
                'email' => 'kavita.joshi@gmail.com',
                'phone' => '+91-9876543215',
                'property_type' => 'apartment',
                'flat_no' => 'D-102',
                'floor' => 1,
                'address' => '987, Shanti Nagar, Civil Lines',
                'city' => 'Delhi',
                'state' => 'Delhi',
                'postal_code' => '110001',
                'id_type' => 'Voter ID',
                'bank_name' => 'Punjab National Bank',
                'account_number' => '81700678901234',
                'ifsc_code' => 'PUNB0006789',
                'family_members' => [
                    ['name' => 'Ramesh Joshi', 'relation' => 'Husband', 'age' => 50],
                    ['name' => 'Pooja Joshi', 'relation' => 'Daughter', 'age' => 22]
                ],
                'notes' => 'Retired teacher, very active in community service.'
            ],
            [
                'name' => 'Rohit Malhotra',
                'email' => 'rohit.malhotra@finance.com',
                'phone' => '+91-9876543216',
                'property_type' => 'villa',
                'villa_no' => 'V-08',
                'address' => '147, Executive Heights, Premium Villas',
                'city' => 'Noida',
                'state' => 'Uttar Pradesh',
                'postal_code' => '201301',
                'id_type' => 'Aadhaar',
                'bank_name' => 'Yes Bank',
                'account_number' => '92800789012345',
                'ifsc_code' => 'YESB0007890',
                'family_members' => [
                    ['name' => 'Deepika Malhotra', 'relation' => 'Wife', 'age' => 32],
                    ['name' => 'Aryan Malhotra', 'relation' => 'Son', 'age' => 6]
                ],
                'notes' => 'Finance professional, prefers evening meetings.'
            ],
            [
                'name' => 'Sanjay Verma',
                'email' => 'sanjay.verma@manufacturing.in',
                'phone' => '+91-9876543217',
                'property_type' => 'apartment',
                'flat_no' => 'E-403',
                'floor' => 4,
                'address' => '258, Industrial Area, Phase 2',
                'city' => 'Chandigarh',
                'state' => 'Punjab',
                'postal_code' => '160002',
                'id_type' => 'PAN Card',
                'bank_name' => 'Bank of Baroda',
                'account_number' => '03900890123456',
                'ifsc_code' => 'BARB0008901',
                'family_members' => [
                    ['name' => 'Ritu Verma', 'relation' => 'Wife', 'age' => 44],
                    ['name' => 'Karan Verma', 'relation' => 'Son', 'age' => 19],
                    ['name' => 'Nisha Verma', 'relation' => 'Daughter', 'age' => 15]
                ],
                'notes' => 'Manufacturing business owner, travels frequently.'
            ],
            [
                'name' => 'Anita Desai',
                'email' => 'anita.desai@education.org',
                'phone' => '+91-9876543218',
                'property_type' => 'apartment',
                'flat_no' => 'F-201',
                'floor' => 2,
                'address' => '369, University Road, Academic Zone',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'postal_code' => '380001',
                'id_type' => 'Driving License',
                'bank_name' => 'Canara Bank',
                'account_number' => '14001901234567',
                'ifsc_code' => 'CNRB0009012',
                'family_members' => [
                    ['name' => 'Prof. Rajesh Desai', 'relation' => 'Husband', 'age' => 52],
                    ['name' => 'Shreya Desai', 'relation' => 'Daughter', 'age' => 24]
                ],
                'notes' => 'University professor, interested in educational initiatives.'
            ],
            [
                'name' => 'Vikash Kumar',
                'email' => 'vikash.kumar@startup.tech',
                'phone' => '+91-9876543219',
                'property_type' => 'villa',
                'villa_no' => 'V-15',
                'address' => '741, Tech Park, Innovation District',
                'city' => 'Chennai',
                'state' => 'Tamil Nadu',
                'postal_code' => '600001',
                'id_type' => 'Aadhaar',
                'bank_name' => 'IndusInd Bank',
                'account_number' => '25102012345678',
                'ifsc_code' => 'INDB0001023',
                'family_members' => [
                    ['name' => 'Priyanka Kumar', 'relation' => 'Wife', 'age' => 29],
                    ['name' => 'Aarav Kumar', 'relation' => 'Son', 'age' => 4]
                ],
                'notes' => 'Tech entrepreneur, young family, very tech-savvy.'
            ]
        ];

        foreach ($owners as $index => $ownerData) {
            // Assign to first society if available
            $society = $societies->first();
            
            // For apartment owners, assign to first building if available
            $building = null;
            $villaArea = null;
            
            if ($ownerData['property_type'] === 'apartment' && $buildings->isNotEmpty()) {
                $building = $buildings->first();
            } elseif ($ownerData['property_type'] === 'villa' && $villaAreas->isNotEmpty()) {
                $villaArea = $villaAreas->first();
            }

            Owner::create([
                'society_id' => $society->id,
                'property_type' => $ownerData['property_type'],
                'building_id' => $building?->id,
                'villa_area_id' => $villaArea?->id,
                'floor' => $ownerData['floor'] ?? null,
                'flat_no' => $ownerData['flat_no'] ?? null,
                'villa_no' => $ownerData['villa_no'] ?? null,
                'name' => $ownerData['name'],
                'email' => $ownerData['email'],
                'phone' => $ownerData['phone'],
                'address' => $ownerData['address'],
                'city' => $ownerData['city'],
                'state' => $ownerData['state'],
                'postal_code' => $ownerData['postal_code'],
                'country' => 'India',
                'id_type' => $ownerData['id_type'],
                'bank_name' => $ownerData['bank_name'],
                'account_number' => $ownerData['account_number'],
                'ifsc_code' => $ownerData['ifsc_code'],
                'family_members' => $ownerData['family_members'],
                'status' => 'active',
                'notes' => $ownerData['notes'],
                'created_at' => now()->subDays(rand(1, 365)),
                'updated_at' => now()->subDays(rand(1, 30)),
            ]);

            $this->command->info("Created owner: {$ownerData['name']} ({$ownerData['property_type']})");
        }

        $this->command->info('✅ Successfully created 10 Owner Management dummy records!');
        $this->command->line('');
        $this->command->info('Owner Summary:');
        $this->command->line('- 6 Apartment Owners');
        $this->command->line('- 4 Villa Owners');
        $this->command->line('- Diverse locations across India');
        $this->command->line('- Complete family member information');
        $this->command->line('- Banking details for all owners');
        $this->command->line('- Realistic contact information');
    }
}