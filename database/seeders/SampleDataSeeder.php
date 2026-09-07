<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Society;
use App\Models\Building;
use App\Models\Flat;
use App\Models\Resident;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Payment;
use App\Models\MaintenanceBill;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing societies or create sample ones
        $societies = Society::take(3)->get();
        
        if ($societies->count() < 3) {
            // Create additional societies if needed
            for ($i = $societies->count(); $i < 3; $i++) {
                $societies->push(Society::create([
                    'name' => 'Sample Society ' . ($i + 1),
                    'email' => 'society' . ($i + 1) . '@example.com',
                    'phone' => '+91-' . rand(9000000000, 9999999999),
                    'address' => 'Sample Address ' . ($i + 1),
                    'city' => ['Mumbai', 'Delhi', 'Bangalore'][$i],
                    'state' => ['Maharashtra', 'Delhi', 'Karnataka'][$i],
                    'country' => 'India',
                    'pincode' => rand(100000, 999999),
                    'status' => 'active',
                ]));
            }
        }

        foreach ($societies as $society) {
            // Skip if buildings already exist for this society
            if (Building::where('society_id', $society->id)->exists()) {
                continue;
            }

            // Create buildings for each society
            for ($b = 1; $b <= 2; $b++) {
                $totalFloors = rand(5, 10);
                $flatsPerFloor = rand(4, 8);
                
                $building = Building::create([
                    'society_id' => $society->id,
                    'name' => 'Building ' . chr(64 + $b), // A, B, C...
                    'total_floors' => $totalFloors,
                    'total_flats' => $totalFloors * $flatsPerFloor,
                    'description' => 'Sample building ' . chr(64 + $b) . ' in ' . $society->name,
                    'status' => 'active',
                ]);

                // Create flats for each building
                for ($floor = 1; $floor <= $building->total_floors; $floor++) {
                    for ($flat = 1; $flat <= $flatsPerFloor; $flat++) {
                        $flatNumber = $floor . str_pad($flat, 2, '0', STR_PAD_LEFT);
                        $flatTypes = ['1BHK', '2BHK', '3BHK', '4BHK'];
                        $flatType = $flatTypes[array_rand($flatTypes)];
                        
                        $flatRecord = Flat::create([
                            'building_id' => $building->id,
                            'society_id' => $society->id,
                            'flat_number' => $flatNumber,
                            'floor' => $floor,
                            'type' => $flatType,
                            'carpet_area' => rand(800, 2000),
                            'built_up_area' => rand(1000, 2500),
                            'maintenance_amount' => rand(2000, 8000),
                            'status' => rand(0, 10) < 7 ? 'occupied' : 'vacant', // 70% occupied
                        ]);

                        // Create residents for occupied flats
                        if ($flatRecord->status === 'occupied') {
                            // Create user first
                            $user = \App\Models\User::create([
                                'society_id' => $society->id,
                                'name' => 'Resident ' . $flatNumber,
                                'email' => 'resident' . $flatNumber . '@' . strtolower(str_replace(' ', '', $society->name)) . '.com',
                                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                                'phone' => '+91-' . rand(9000000000, 9999999999),
                                'status' => 'active',
                            ]);

                            $resident = Resident::create([
                                'society_id' => $society->id,
                                'user_id' => $user->id,
                                'flat_id' => $flatRecord->id,
                                'type' => rand(0, 10) < 8 ? 'owner' : 'tenant', // 80% owners
                                'move_in_date' => now()->subDays(rand(30, 365)),
                                'monthly_rent' => rand(0, 10) < 3 ? rand(15000, 50000) : null, // 30% have rent (tenants)
                                'security_deposit' => rand(0, 10) < 3 ? rand(20000, 100000) : null,
                                'family_members' => json_encode([
                                    ['name' => 'Family Member 1', 'relation' => 'Spouse', 'age' => rand(25, 60)],
                                    ['name' => 'Family Member 2', 'relation' => 'Child', 'age' => rand(5, 18)],
                                ]),
                                'emergency_contact' => '+91-' . rand(9000000000, 9999999999),
                                'status' => 'active',
                            ]);

                            // Create maintenance bills for residents
                            for ($month = 1; $month <= 3; $month++) {
                                $monthName = now()->subMonths(3 - $month)->format('F');
                                $year = now()->subMonths(3 - $month)->year;
                                $dueDate = now()->subMonths(3 - $month)->endOfMonth();
                                
                                $bill = MaintenanceBill::create([
                                    'society_id' => $society->id,
                                    'flat_id' => $flatRecord->id,
                                    'bill_number' => 'MB' . date('Y') . str_pad($month, 2, '0', STR_PAD_LEFT) . $flatNumber,
                                    'month' => $monthName,
                                    'year' => $year,
                                    'bill_date' => $dueDate->copy()->startOfMonth(),
                                    'due_date' => $dueDate,
                                    'maintenance_amount' => $flatRecord->maintenance_amount,
                                    'water_charges' => rand(500, 1500),
                                    'electricity_charges' => rand(800, 2000),
                                    'status' => rand(0, 10) < 8 ? 'paid' : 'pending', // 80% paid
                                    'notes' => 'Monthly maintenance for ' . $monthName . ' ' . $year,
                                ]);

                                // Create payments for paid bills
                                if ($bill->status === 'paid') {
                                    Payment::create([
                                        'society_id' => $society->id,
                                        'user_id' => $user->id,
                                        'maintenance_bill_id' => $bill->id,
                                        'payment_id' => 'PAY' . rand(100000, 999999),
                                        'amount' => $bill->total_amount,
                                        'payment_method' => ['cash', 'online', 'cheque'][array_rand(['cash', 'online', 'cheque'])],
                                        'transaction_id' => 'TXN' . rand(100000, 999999),
                                        'status' => 'success',
                                        'payment_date' => $bill->due_date->subDays(rand(1, 10)),
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            // Create sample complaints
            $categories = ComplaintCategory::where('society_id', $society->id)->get();
            $users = \App\Models\User::where('society_id', $society->id)->get();
            
            if ($categories->count() > 0 && $users->count() > 0) {
                for ($c = 1; $c <= rand(5, 15); $c++) {
                    $user = $users->random();
                    $category = $categories->random();
                    $resident = $user->resident;
                    
                    if ($resident) {
                        Complaint::create([
                            'society_id' => $society->id,
                            'complaint_category_id' => $category->id,
                            'flat_id' => $resident->flat_id,
                            'created_by' => $user->id,
                            'complaint_number' => 'CMP' . date('Ym') . str_pad($c, 4, '0', STR_PAD_LEFT),
                            'title' => $this->getRandomComplaintTitle($category->name),
                            'description' => $this->getRandomComplaintDescription($category->name),
                            'priority' => ['low', 'medium', 'high', 'urgent'][array_rand(['low', 'medium', 'high', 'urgent'])],
                            'status' => ['open', 'in_progress', 'resolved'][array_rand(['open', 'in_progress', 'resolved'])],
                            'created_at' => now()->subDays(rand(1, 30)),
                        ]);
                    }
                }
            }
        }
    }

    private function getRandomComplaintTitle($category)
    {
        $titles = [
            'Maintenance' => ['Broken door handle', 'Paint peeling off walls', 'Window not closing properly'],
            'Plumbing' => ['Water leakage in bathroom', 'Low water pressure', 'Blocked drain'],
            'Electrical' => ['Power outage in flat', 'Faulty switch', 'Flickering lights'],
            'Security' => ['Gate not working', 'CCTV not functioning', 'Security guard absent'],
            'Noise' => ['Loud music from neighbor', 'Construction noise', 'Dog barking continuously'],
            'Parking' => ['Car parked in wrong spot', 'Parking space too small', 'No parking available'],
            'Cleanliness' => ['Garbage not collected', 'Dirty common areas', 'Pest control needed'],
            'Lift/Elevator' => ['Lift not working', 'Lift making noise', 'Lift door stuck'],
            'Common Area' => ['Garden needs maintenance', 'Swimming pool dirty', 'Gym equipment broken'],
            'Other' => ['General inquiry', 'Suggestion for improvement', 'Request for new facility'],
        ];

        $categoryTitles = $titles[$category] ?? $titles['Other'];
        return $categoryTitles[array_rand($categoryTitles)];
    }

    private function getRandomComplaintDescription($category)
    {
        $descriptions = [
            'Maintenance' => 'The issue has been persisting for a few days and needs immediate attention.',
            'Plumbing' => 'This is causing inconvenience to daily activities and needs urgent repair.',
            'Electrical' => 'Safety concern that requires immediate electrical work.',
            'Security' => 'This is a security issue that affects the safety of residents.',
            'Noise' => 'The noise is disturbing and affecting quality of life.',
            'Parking' => 'This parking issue is causing problems for residents.',
            'Cleanliness' => 'The cleanliness issue needs to be addressed for hygiene reasons.',
            'Lift/Elevator' => 'The lift issue is causing inconvenience to residents, especially elderly.',
            'Common Area' => 'The common area facility needs attention for better resident experience.',
            'Other' => 'This is a general request that would benefit the community.',
        ];

        return $descriptions[$category] ?? $descriptions['Other'];
    }
}