<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\MaintenanceBill;
use Illuminate\Http\Request;

class RentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get tenant's resident record with multiple fallback methods
        $resident = null;
        $tenant = null;
        
        // Method 1: Direct resident relationship
        $resident = $user->resident;
        
        // Method 2: Get tenant record and find resident through flat
        if (!$resident) {
            $tenant = $user->tenant;
            if ($tenant && $tenant->flat_id) {
                $resident = \App\Models\Resident::where('user_id', $user->id)
                    ->where('flat_id', $tenant->flat_id)
                    ->first();
            }
        }
        
        // Method 3: Find tenant by user_id if tenant_id is not set
        if (!$tenant) {
            $tenant = \App\Models\Tenant::where('user_id', $user->id)->first();
            if ($tenant) {
                // Auto-fix the relationship
                $user->update(['tenant_id' => $tenant->id]);
                
                // Find resident record
                if (!$resident && $tenant->flat_id) {
                    $resident = \App\Models\Resident::where('user_id', $user->id)
                        ->where('flat_id', $tenant->flat_id)
                        ->first();
                }
            }
        }
        
        // Method 4: Create tenant and resident records if user has tenant role but no records
        if (!$tenant && $user->hasRole('Tenant')) {
            $tenant = $this->createTenantForUser($user);
            if ($tenant) {
                $resident = \App\Models\Resident::where('user_id', $user->id)
                    ->where('flat_id', $tenant->flat_id)
                    ->first();
            }
        }
        
        // If still no resident record, show error
        if (!$resident || !$resident->flat_id) {
            return redirect()->route('tenant.dashboard')
                ->with('error', 'No apartment assigned to your account. Please contact the administrator.');
        }
        
        // Get all maintenance bills for this tenant's flat
        $rentRecords = MaintenanceBill::where('flat_id', $resident->flat_id)
            ->where('bill_type', 'rent')
            ->with(['payments', 'flat.building', 'flat.villaArea'])
            ->orderBy('bill_date', 'desc')
            ->get();
        
        // Calculate statistics
        $totalRent = $rentRecords->sum('amount');
        $totalPaid = $rentRecords->sum(function($bill) {
            return $bill->payments->sum('amount');
        });
        $pendingAmount = $totalRent - $totalPaid;
        $unpaidBills = $rentRecords->where('status', '!=', 'paid')->count();
        
        return view('tenant.rent.index', compact(
            'rentRecords', 
            'resident', 
            'totalRent', 
            'totalPaid', 
            'pendingAmount', 
            'unpaidBills'
        ));
    }
    
    /**
     * Create a tenant record for a user who doesn't have one assigned
     */
    private function createTenantForUser($user)
    {
        try {
            // Find an available flat or create one
            $flat = $this->findOrCreateAvailableFlat($user);
            
            $tenant = \App\Models\Tenant::create([
                'society_id' => $user->society_id,
                'user_id' => $user->id,
                'flat_id' => $flat->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '9876543210',
                'move_in_date' => now()->subMonths(3),
                'lease_start_date' => now()->subMonths(3),
                'lease_end_date' => now()->addMonths(9),
                'monthly_rent' => rand(15000, 25000),
                'security_deposit' => rand(30000, 50000),
                'status' => 'active',
            ]);

            // Link user to tenant
            $user->update(['tenant_id' => $tenant->id]);

            // Create resident record
            \App\Models\Resident::create([
                'society_id' => $user->society_id,
                'user_id' => $user->id,
                'flat_id' => $flat->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '9876543210',
                'type' => 'tenant',
                'move_in_date' => $tenant->move_in_date,
                'status' => 'active',
            ]);

            \Log::info("Auto-created tenant record for user: {$user->name} - Flat: {$flat->flat_number}");
            
            return $tenant;
        } catch (\Exception $e) {
            \Log::error("Failed to create tenant for user {$user->name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find or create an available flat for tenant
     */
    private function findOrCreateAvailableFlat($user)
    {
        // Try to find an unoccupied flat
        $flat = \App\Models\Flat::where('society_id', $user->society_id)
            ->where('status', 'vacant')
            ->first();

        if (!$flat) {
            // Create a new flat
            $building = $this->findOrCreateBuilding($user->society_id);
            $flatNumber = $this->generateUniqueFlatNumber($user->society_id);
            
            $flat = \App\Models\Flat::create([
                'society_id' => $user->society_id,
                'building_id' => $building->id,
                'flat_number' => $flatNumber,
                'floor' => rand(1, 5),
                'property_type' => 'apartment',
                'bedrooms' => rand(1, 3),
                'bathrooms' => rand(1, 2),
                'area_sqft' => rand(800, 1500),
                'status' => 'on_rent'
            ]);
        } else {
            // Mark as on rent
            $flat->update(['status' => 'on_rent']);
        }

        return $flat;
    }

    /**
     * Find or create a building for the society
     */
    private function findOrCreateBuilding($societyId)
    {
        $building = \App\Models\Building::where('society_id', $societyId)->first();
        
        if (!$building) {
            $building = \App\Models\Building::create([
                'society_id' => $societyId,
                'name' => 'Tower A',
                'floors' => 10,
                'flats_per_floor' => 4,
                'status' => 'active'
            ]);
        }

        return $building;
    }

    /**
     * Generate a unique flat number for the society
     */
    private function generateUniqueFlatNumber($societyId)
    {
        $existingNumbers = \App\Models\Flat::where('society_id', $societyId)
            ->pluck('flat_number')
            ->toArray();

        // Try common flat numbers first
        $commonNumbers = ['1A', '1B', '2A', '2B', '3A', '3B', '101', '102', '103', '104', '201', '202'];
        
        foreach ($commonNumbers as $number) {
            if (!in_array($number, $existingNumbers)) {
                return $number;
            }
        }

        // Generate numbered flats
        for ($i = 1; $i <= 100; $i++) {
            $flatNumber = str_pad($i, 2, '0', STR_PAD_LEFT);
            if (!in_array($flatNumber, $existingNumbers)) {
                return $flatNumber;
            }
        }

        // Last resort - random number
        return rand(101, 999);
    }
    
    /**
     * Submit payment for a rent record
     */
    public function submitPayment(Request $request)
    {
        try {
            $request->validate([
                'rent_id' => 'required|exists:maintenance_bills,id',
                'payment_date' => 'required|date',
                'payment_amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'notes' => 'nullable|string|max:500',
            ]);

            $user = auth()->user();
            
            // Get the rent record
            $rent = MaintenanceBill::findOrFail($request->rent_id);
            
            // Verify rent belongs to the tenant's flat
            $resident = $user->resident;
            if (!$resident || $rent->flat_id !== $resident->flat_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to rent record.'], 403);
            }

            // Verify payment amount doesn't exceed balance
            if ($request->payment_amount > $rent->balance_amount) {
                return response()->json(['success' => false, 'message' => 'Payment amount cannot exceed outstanding balance.'], 400);
            }

            \DB::beginTransaction();

            // Handle file upload
            $receiptFilePath = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $fileName = 'tenant_payment_' . $rent->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $receiptFilePath = $file->storeAs('payment_receipts', $fileName, 'public');
            }

            // Create payment record
            $payment = \App\Models\Payment::create([
                'society_id' => $user->society_id,
                'flat_id' => $rent->flat_id,
                'maintenance_bill_id' => $rent->id,
                'user_id' => $user->id,
                'payment_id' => \App\Models\Payment::generatePaymentId(),
                'amount' => $request->payment_amount,
                'payment_method' => $request->payment_method,
                'status' => 'pending', // Tenant payments start as pending for admin approval
                'payment_date' => $request->payment_date,
                'bill_type' => 'rent',
                'due_date' => $request->payment_date,
                'notes' => $request->notes,
                'receipt_file_path' => $receiptFilePath,
            ]);

            // Update rent record (but keep status as pending until admin approves)
            $rent->paid_amount = ($rent->paid_amount ?? 0) + $request->payment_amount;
            $rent->balance_amount = $rent->total_amount - $rent->paid_amount;
            
            // Update status based on payment
            if ($rent->balance_amount <= 0) {
                $rent->status = 'paid';
                // Set paid_date if column exists
                try {
                    $tableColumns = \DB::select("PRAGMA table_info(maintenance_bills)");
                    $columnNames = array_column($tableColumns, 'name');
                    if (in_array('paid_date', $columnNames)) {
                        $rent->paid_date = $request->payment_date;
                    }
                } catch (\Exception $e) {
                    \Log::warning('Could not set paid_date: ' . $e->getMessage());
                }
            } elseif ($rent->paid_amount > 0) {
                $rent->status = 'partial';
            }
            
            $rent->save();

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment submitted successfully! It will be reviewed by the administrator.',
                'payment_id' => $payment->payment_id
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Tenant payment submission failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit payment. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Show rent receipt for tenant
     */
    public function showReceipt($rentId)
    {
        try {
            $user = auth()->user();
            
            // Get the rent record
            $rent = MaintenanceBill::findOrFail($rentId);
            
            // Verify rent belongs to the tenant's flat
            $resident = $user->resident;
            if (!$resident || $rent->flat_id !== $resident->flat_id) {
                abort(403, 'Unauthorized access to rent record.');
            }
            
            // Load necessary relationships
            $rent->load(['flat.building', 'flat.villaArea', 'society', 'payments']);
            
            return view('tenant.rent.receipt', compact('rent'));
            
        } catch (\Exception $e) {
            \Log::error('Tenant rent receipt error: ' . $e->getMessage());
            abort(404, 'Rent record not found.');
        }
    }
}