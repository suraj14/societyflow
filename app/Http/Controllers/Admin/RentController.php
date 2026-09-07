<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\MaintenanceBill;
use App\Models\Flat;
use App\Models\Tenant;
use App\Models\Society;
use App\Models\Payment;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RentController extends BaseController
{
    use HandlesFormSubmissions;
    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $societyId = $this->getSocietyId();
        
        // Get all rent records (maintenance bills) for tenants
        $query = MaintenanceBill::whereHas('flat', function($query) use ($societyId) {
                $query->where('society_id', $societyId);
            })
            ->with(['flat.building', 'flat.villaArea', 'flat.tenants' => function($query) {
                $query->where('status', 'active');
            }, 'payments']);
            
        // Filter by bill_type = 'rent' only if the column exists
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('maintenance_bills', 'bill_type')) {
                $query->where('bill_type', 'rent');
            }
        } catch (\Exception $e) {
            // If we can't check the column, just get all maintenance bills
            Log::warning('Could not filter by bill_type: ' . $e->getMessage());
        }
        
        $rentRecords = $query->orderBy('bill_date', 'desc')->paginate(15);
        
        return view('admin.rents.index', compact('rentRecords'));
    }
    
    public function create()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $societyId = $this->getSocietyId();
        
        // Get society settings for rent due days (default 30 if not set)
        $society = Society::find($societyId);
        $rentDueDays = $society->rent_due_days ?? 30;
        
        return view('admin.rents.create', compact('rentDueDays'));
    }
    
    public function store(Request $request)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        return $this->handleFormSubmission(function() use ($request) {
            $request->validate([
                'property_type' => 'required|in:apartment,villa',
                'unit_id' => 'required|exists:flats,id',
                'tenant_id' => 'required|exists:tenants,id',
                'rent_amount' => 'required|numeric|min:1',
                'bill_date' => 'required|date',
                'due_date' => 'required|date|after:bill_date',
                'status' => 'required|in:pending,paid',
                'description' => 'nullable|string|max:500',
                'payment_date' => 'nullable|date',
                'payment_method' => 'nullable|string',
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'payment_notes' => 'nullable|string|max:255',
            ]);
            
            $societyId = $this->getSocietyId();
            
            // Validate unit belongs to society and is on rent
            $flat = Flat::where('id', $request->unit_id)
                ->where('society_id', $societyId)
                ->where('status', 'on_rent')
                ->first();
                
            if (!$flat) {
                throw new \Exception('Selected unit is not available for rent.');
            }
            
            // Validate tenant belongs to the unit
            $tenant = Tenant::where('id', $request->tenant_id)
                ->where('flat_id', $request->unit_id)
                ->where('status', 'active')
                ->first();
                
            if (!$tenant) {
                throw new \Exception('Selected tenant is not assigned to this unit.');
            }
            
            // Check for duplicate rent record (same unit, month, year)
            $billDate = Carbon::parse($request->bill_date);
            $existingRent = MaintenanceBill::where('flat_id', $request->unit_id)
                ->where('bill_type', 'rent')
                ->whereMonth('bill_date', $billDate->month)
                ->whereYear('bill_date', $billDate->year)
                ->exists();
                
            if ($existingRent) {
                throw new \Exception('Rent record already exists for this unit in ' . $billDate->format('F Y') . '.');
            }
            
            // Create rent record - handle missing columns gracefully
            $rentData = [
                'society_id' => $societyId,
                'flat_id' => $request->unit_id,
                'bill_number' => 'RENT-' . $societyId . '-' . $request->unit_id . '-' . $billDate->format('Ym') . '-' . time(),
                'month' => $billDate->format('F'),
                'year' => $billDate->year,
                'maintenance_amount' => $request->rent_amount,
                'total_amount' => $request->rent_amount,
                'bill_date' => $request->bill_date,
                'due_date' => $request->due_date,
                'status' => $request->status,
                'notes' => $request->description ?? 'Monthly Rent',
            ];

            // If status is paid, set paid_amount to the full amount
            if ($request->status === 'paid') {
                $rentData['paid_amount'] = $request->rent_amount;
            } else {
                $rentData['paid_amount'] = 0;
            }

            // Only add bill_type and paid_date if columns exist in database
            try {
                $columnNames = \Illuminate\Support\Facades\Schema::getColumnListing('maintenance_bills');
                
                if (in_array('bill_type', $columnNames)) {
                    $rentData['bill_type'] = 'rent';
                }
                
                if (in_array('paid_date', $columnNames) && $request->status === 'paid') {
                    $rentData['paid_date'] = now();
                }
            } catch (\Exception $e) {
                Log::warning('Could not check database columns: ' . $e->getMessage());
            }

            $rent = MaintenanceBill::create($rentData);
            
            // Create payment record if payment details are provided
            if ($request->payment_date && $request->status === 'paid') {
                // Handle file upload for payment proof
                $receiptFilePath = null;
                if ($request->hasFile('payment_proof')) {
                    $file = $request->file('payment_proof');
                    $fileName = 'rent_payment_' . $rent->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                    $receiptFilePath = $file->storeAs('payment_receipts', $fileName, 'public');
                }

                // Create payment record
                Payment::create([
                    'society_id' => $societyId,
                    'flat_id' => $request->unit_id,
                    'maintenance_bill_id' => $rent->id,
                    'user_id' => auth()->id(),
                    'payment_id' => Payment::generatePaymentId(),
                    'amount' => $request->rent_amount,
                    'payment_method' => $request->payment_method ?? 'cash',
                    'status' => 'completed',
                    'payment_date' => $request->payment_date,
                    'bill_type' => 'rent',
                    'notes' => $request->payment_notes,
                    'receipt_file_path' => $receiptFilePath,
                ]);
            }
            
            return $rent;
        }, 'Rent record created successfully!', 'admin.rents.index');
    }
    
    public function edit(MaintenanceBill $rent)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Load the rent record with necessary relationships
        $rent->load(['flat.building', 'flat.villaArea', 'flat.tenants' => function($query) {
            $query->where('status', 'active')->with('user');
        }, 'payments']);
        
        // Verify rent record belongs to the same society
        if ($rent->flat->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Rent record not found.');
        }
        
        $societyId = $this->getSocietyId();
        
        // Get all flats with active tenants for the dropdown
        $tenantFlats = Flat::where('society_id', $societyId)
            ->where('status', 'on_rent')
            ->whereHas('tenants', function($query) {
                $query->where('status', 'active');
            })
            ->with(['building', 'villaArea', 'tenants' => function($query) {
                $query->where('status', 'active')->with('user');
            }])
            ->get();
        
        return view('admin.rents.edit', compact('rent', 'tenantFlats'));
    }
    
    public function update(Request $request, MaintenanceBill $rent)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Verify rent record belongs to the same society
        if ($rent->flat->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Rent record not found.');
        }
        
        $request->validate([
            'flat_id' => 'required|exists:flats,id',
            'rent_amount' => 'required|numeric|min:1',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:pending,paid,partial,overdue',
        ]);
        
        // Business rule: Once paid, status cannot be changed
        if ($rent->status === 'paid' && $request->status !== 'paid') {
            return back()->withErrors(['status' => 'Cannot change status of a paid rent record.'])->withInput();
        }
        
        DB::beginTransaction();
        try {
            $billDate = Carbon::parse($request->bill_date);
            
            // Update rent record
            $updateData = [
                'flat_id' => $request->flat_id,
                'month' => $billDate->format('F'),
                'year' => $billDate->year,
                'maintenance_amount' => $request->rent_amount,
                'total_amount' => $request->rent_amount,
                'bill_date' => $request->bill_date,
                'due_date' => $request->due_date,
                'notes' => $request->description ?? 'Monthly Rent',
                'status' => $request->status,
            ];
            
            // Handle paid_amount based on status
            if ($request->status === 'paid') {
                // Fully paid — zero out balance
                $updateData['paid_amount'] = $request->rent_amount;
                $updateData['balance_amount'] = 0;
            } elseif ($request->status === 'pending' || $request->status === 'overdue') {
                // Not paid yet - keep existing paid_amount or set to 0
                $updateData['paid_amount'] = $rent->paid_amount ?? 0;
                $updateData['balance_amount'] = $request->rent_amount - ($rent->paid_amount ?? 0);
            } elseif ($request->status === 'partial') {
                // Partially paid - keep existing paid_amount
                $updateData['paid_amount'] = $rent->paid_amount ?? 0;
                $updateData['balance_amount'] = $request->rent_amount - ($rent->paid_amount ?? 0);
            }
            
            $rent->update($updateData);
            
            DB::commit();
            
            return redirect()->route('admin.rents.index')
                ->with('success', 'Rent record updated successfully!');
                
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating rent record: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return back()->withErrors(['error' => 'Failed to update rent record: ' . $e->getMessage()])->withInput();
        }
    }
    
    public function destroy(MaintenanceBill $rent)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Verify rent record belongs to the same society
        if ($rent->flat->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Rent record not found.');
        }
        
        DB::beginTransaction();
        try {
            // Delete associated payments first
            $rent->payments()->delete();
            
            // Delete rent record
            $rent->delete();
            
            DB::commit();
            
            return redirect()->route('admin.rents.index')
                ->with('success', 'Rent record deleted successfully!');
                
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting rent record: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete rent record.');
        }
    }

    /**
     * API endpoint to get units on rent for the dropdown
     */
    public function getUnitsOnRent(Request $request)
    {
        try {
            $societyId = $this->getSocietyId();
            $propertyType = $request->get('property_type');
            
            Log::info("Rent API called with property_type: {$propertyType}, society_id: {$societyId}");
            
            if (!in_array($propertyType, ['apartment', 'villa'])) {
                Log::error("Invalid property type: {$propertyType}");
                return response()->json(['error' => 'Invalid property type'], 400);
            }
            
            // First, let's update flat statuses for active tenants
            $this->updateFlatStatusesForActiveTenants();
            
            // Get units that are on rent with active tenants
            $query = Flat::where('society_id', $societyId)
                ->where('status', 'on_rent')
                ->whereHas('tenants', function($q) {
                    $q->where('status', 'active');
                })
                ->with(['tenants' => function($q) {
                    $q->where('status', 'active')->with('user');
                }]);
                
            if ($propertyType === 'apartment') {
                $query->where('property_type', 'apartment')
                      ->with('building');
            } else {
                $query->where('property_type', 'villa')
                      ->with('villaArea');
            }
            
            $flats = $query->get();
            Log::info("Found {$flats->count()} flats for property type: {$propertyType}");
            
            $units = $flats->map(function($flat) {
                $tenant = $flat->tenants->first();
                $tenantName = $tenant && $tenant->user ? $tenant->user->name : 'Unknown Tenant';
                
                if ($flat->property_type === 'apartment') {
                    $unitLabel = ($flat->building ? $flat->building->name : 'Building') . 
                               ' - Floor ' . $flat->floor . 
                               ' - Flat ' . $flat->flat_number;
                } else {
                    $unitLabel = ($flat->villaArea ? $flat->villaArea->name : 'Villa Area') . 
                               ' - ' . ($flat->villa_name ?: $flat->flat_number);
                }
                
                return [
                    'id' => $flat->id,
                    'label' => $unitLabel,
                    'tenant_id' => $tenant ? $tenant->id : null,
                    'tenant_name' => $tenantName,
                    'monthly_rent' => $flat->maintenance_amount ?? 0,
                ];
            });

            Log::info("Returning {$units->count()} units to frontend");
            return response()->json($units);
            
        } catch (\Exception $e) {
            Log::error('Error loading units on rent: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to load units'], 500);
        }
    }
    
    /**
     * API endpoint to validate rent record creation
     */
    public function validateRentRecord(Request $request)
    {
        try {
            $unitId = $request->get('unit_id');
            $billDate = $request->get('bill_date');
            
            if (!$unitId || !$billDate) {
                return response()->json(['valid' => false, 'message' => 'Missing required parameters']);
            }
            
            $billDate = Carbon::parse($billDate);
            
            // Check for existing rent record
            $existingRent = MaintenanceBill::where('flat_id', $unitId)
                ->where('bill_type', 'rent')
                ->whereMonth('bill_date', $billDate->month)
                ->whereYear('bill_date', $billDate->year)
                ->exists();
                
            if ($existingRent) {
                return response()->json([
                    'valid' => false, 
                    'message' => 'Rent record already exists for ' . $billDate->format('F Y')
                ]);
            }
            
            return response()->json(['valid' => true]);
            
        } catch (\Exception $e) {
            Log::error('Error validating rent record: ' . $e->getMessage());
            return response()->json(['valid' => false, 'message' => 'Validation failed']);
        }
    }
    
    /**
     * Add payment to a rent record
     */
    public function addPayment(Request $request)
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

            $societyId = $this->getSocietyId();
            
            // Get the rent record
            $rent = MaintenanceBill::findOrFail($request->rent_id);
            
            // Verify rent belongs to the same society
            if ($rent->flat->society_id !== $societyId) {
                return response()->json(['success' => false, 'message' => 'Rent record not found.'], 404);
            }

            DB::beginTransaction();

            // Handle file upload
            $receiptFilePath = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $fileName = 'rent_payment_' . $rent->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $receiptFilePath = $file->storeAs('payment_receipts', $fileName, 'public');
            }

            // Create payment record
            $payment = Payment::create([
                'society_id' => $societyId,
                'flat_id' => $rent->flat_id,
                'maintenance_bill_id' => $rent->id,
                'user_id' => auth()->id(),
                'payment_id' => Payment::generatePaymentId(),
                'amount' => $request->payment_amount,
                'payment_method' => $request->payment_method,
                'status' => 'success',
                'payment_date' => $request->payment_date,
                'bill_type' => 'rent',
                'due_date' => $request->payment_date, // Use payment date as due date for rent payments
                'notes' => $request->notes,
                'receipt_file_path' => $receiptFilePath,
            ]);

            // Update rent record
            $rent->paid_amount = ($rent->paid_amount ?? 0) + $request->payment_amount;
            $rent->balance_amount = $rent->total_amount - $rent->paid_amount;
            
            // Update status based on payment
            if ($rent->balance_amount <= 0) {
                $rent->status = 'paid';
                try {
                    $columnNames = \Illuminate\Support\Facades\Schema::getColumnListing('maintenance_bills');
                    if (in_array('paid_date', $columnNames)) {
                        $rent->paid_date = $request->payment_date;
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not set paid_date: ' . $e->getMessage());
                }
            } elseif ($rent->paid_amount > 0) {
                $rent->status = 'partial';
            }
            
            $rent->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment added successfully!',
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error adding rent payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment. Please try again.'
            ], 500);
        }
    }

    /**
     * Helper method to update flat statuses for active tenants
     */
    private function updateFlatStatusesForActiveTenants()
    {
        try {
            $activeTenants = Tenant::where('status', 'active')
                ->whereNotNull('flat_id')
                ->with('flat')
                ->get();

            $updatedCount = 0;
            foreach ($activeTenants as $tenant) {
                if ($tenant->flat && $tenant->flat->status !== 'on_rent') {
                    $tenant->flat->update(['status' => 'on_rent']);
                    $updatedCount++;
                    Log::info("Updated flat {$tenant->flat->flat_number} (ID: {$tenant->flat->id}) to on_rent status");
                }
            }
            
            if ($updatedCount > 0) {
                Log::info("Updated {$updatedCount} flats to on_rent status");
            }
        } catch (\Exception $e) {
            Log::error('Error updating flat statuses: ' . $e->getMessage());
        }
    }

    /**
     * Show rent receipt for admin
     */
    public function receipt(MaintenanceBill $rent)
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        // Verify rent record belongs to the same society
        if ($rent->flat->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Rent record not found.');
        }
        
        // Load necessary relationships
        $rent->load([
            'flat.building', 
            'flat.villaArea', 
            'flat.tenants' => function($query) {
                $query->where('status', 'active')->with('user');
            }, 
            'payments' => function($query) {
                $query->orderBy('payment_date', 'desc');
            },
            'society'
        ]);
        
        // Check if rent has payments
        if (!$rent->payments || $rent->payments->count() === 0) {
            return back()->with('error', 'No payments found for this rent record.');
        }
        
        return view('admin.rents.receipt', compact('rent'));
    }
}