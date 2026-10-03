<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\MaintenanceBill;
use App\Models\Flat;
use App\Models\Tenant;
use App\Models\Society;
use App\Models\Payment;
use App\Http\Controllers\Concerns\ResolvesSocietyPropertyType;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RentController extends BaseController
{
    use HandlesFormSubmissions, ResolvesSocietyPropertyType;
    public function index()
    {
        // Admin/Super Admin manage rent; Accountant has read-only access
        // (the route itself now grants this - see routes/web.php).
        $this->authorizeRole('Super Admin', 'Admin', 'Accountant');
        
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
                'property_type' => $this->propertyTypeRuleFor($this->getSocietyId(), true),
                'unit_id' => 'required|exists:flats,id',
                'tenant_id' => 'required|exists:tenants,id',
                'rent_amount' => 'required|numeric|min:1',
                'bill_date' => 'required|date',
                'due_date' => 'required|date|after:bill_date',
                'status' => 'required|in:pending,paid',
                'description' => 'nullable|string|max:500',
                // "Paid" is a statement that money was received, and that
                // is recorded in the payments ledger - so a paid rent must
                // say when and how. It used to accept "paid" with no
                // payment details and set paid_amount by hand, leaving a
                // bill that said paid with no payment behind it.
                'payment_date' => 'required_if:status,paid|nullable|date',
                'payment_method' => ['required_if:status,paid', 'nullable', Rule::in(Payment::METHODS)],
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'payment_notes' => 'nullable|string|max:255',
            ], [
                'payment_date.required_if' => 'Enter the payment date to record this rent as paid.',
                'payment_method.required_if' => 'Select the payment method to record this rent as paid.',
            ]);

            $societyId = $this->getSocietyId();

            // Validate unit belongs to society and has an active tenant
            // (the tenant check below pins which one).
            $flat = Flat::where('id', $request->unit_id)
                ->where('society_id', $societyId)
                ->whereHas('tenants', fn ($q) => $q->where('status', 'active'))
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
            
            // The bill itself is always created unpaid; the payment below
            // settles it through the ledger (bill_type/paid_date columns
            // are unconditionally present - see MaintenanceBill::$fillable).
            $rent = MaintenanceBill::create([
                'society_id' => $societyId,
                'flat_id' => $request->unit_id,
                'bill_number' => 'RENT-' . $societyId . '-' . $request->unit_id . '-' . $billDate->format('Ym') . '-' . time(),
                'month' => $billDate->format('F'),
                'year' => $billDate->year,
                'maintenance_amount' => $request->rent_amount,
                'total_amount' => $request->rent_amount,
                'paid_amount' => 0,
                'bill_date' => $request->bill_date,
                'due_date' => $request->due_date,
                'bill_type' => 'rent',
                'notes' => $request->description ?? 'Monthly Rent',
            ]);

            if ($request->status === 'paid') {
                // Handle file upload for payment proof
                $receiptFilePath = null;
                if ($request->hasFile('payment_proof')) {
                    $file = $request->file('payment_proof');
                    $fileName = 'rent_payment_' . $rent->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                    $receiptFilePath = $file->storeAs('payment_receipts', $fileName, 'public');
                }

                // The Payment model's saved hook marks the bill paid from
                // the ledger. Status is 'success' - 'completed' is not a
                // value the payments.status column accepts on MySQL.
                Payment::create([
                    'society_id' => $societyId,
                    'flat_id' => $request->unit_id,
                    'maintenance_bill_id' => $rent->id,
                    'user_id' => auth()->id(),
                    'payment_id' => Payment::generatePaymentId(),
                    'amount' => $request->rent_amount,
                    'payment_method' => $request->payment_method,
                    'status' => 'success',
                    'payment_date' => $request->payment_date,
                    'bill_type' => 'rent',
                    'due_date' => $request->due_date,
                    'notes' => $request->payment_notes,
                    'receipt_file_path' => $receiptFilePath,
                ]);

                $rent->refresh();
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
        
        // No `status` here: a rent record's status is derived from the
        // payments recorded against it (MaintenanceBill::syncFromLedger).
        // Marking it paid happens through "Add Payment", which writes the
        // ledger row the status is then computed from. The edit form used to
        // accept status=paid and set paid_amount to the total with no payment
        // behind it.
        $request->validate([
            'flat_id' => ['required', Rule::exists('flats', 'id')->where('society_id', $this->getSocietyId())],
            'rent_amount' => 'required|numeric|min:1',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'description' => 'nullable|string|max:500',
        ]);

        if ((float) $request->rent_amount < (float) $rent->paid_amount) {
            return back()->withErrors(['rent_amount' => 'Rent amount cannot be less than the ₹' . number_format($rent->paid_amount, 2) . ' already paid against this record.'])->withInput();
        }

        DB::beginTransaction();
        try {
            $billDate = Carbon::parse($request->bill_date);

            // paid_amount is untouched; the model recomputes balance and
            // status from it on save.
            $rent->update([
                'flat_id' => $request->flat_id,
                'month' => $billDate->format('F'),
                'year' => $billDate->year,
                'maintenance_amount' => $request->rent_amount,
                'total_amount' => $request->rent_amount,
                'bill_date' => $request->bill_date,
                'due_date' => $request->due_date,
                'notes' => $request->description ?? 'Monthly Rent',
            ]);
            
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
            $society = Society::find($societyId);

            // A single-type society never sends property_type - the browser
            // never showed that select, so there is nothing to trust or
            // distrust from it. Resolve the only type the society supports
            // server-side instead of rejecting the request.
            $propertyType = $request->get('property_type');
            if (!$propertyType && $society && !$society->isMixedProperty()) {
                $propertyType = $society->propertyType()->allows()[0]->value;
            }

            if (!in_array($propertyType, ['apartment', 'villa'])) {
                return response()->json(['error' => 'Invalid property type'], 400);
            }

            // Units with an active tenant. The stored flats.status is a
            // cache the Flat model keeps in sync with the tenant/owner
            // records; the active tenant is the fact, so filter on that.
            $query = Flat::where('society_id', $societyId)
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
                'payment_method' => ['required', Rule::in(Payment::METHODS)],
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'notes' => 'nullable|string|max:500',
                // Idempotency: the form stamps one token per open modal, so
                // a double click or a browser retry replays the same token
                // and finds the payment it already created.
                'idempotency_key' => 'nullable|string|max:64',
            ]);

            $societyId = $this->getSocietyId();

            // Get the rent record
            $rent = MaintenanceBill::findOrFail($request->rent_id);

            // Verify rent belongs to the same society
            if ($rent->flat->society_id !== $societyId) {
                return response()->json(['success' => false, 'message' => 'Rent record not found.'], 404);
            }

            if ($request->filled('idempotency_key')) {
                $existing = Payment::where('maintenance_bill_id', $rent->id)
                    ->where('transaction_id', 'IDEMP-' . $request->idempotency_key)
                    ->first();
                if ($existing) {
                    return response()->json(['success' => true, 'message' => 'Payment already recorded.', 'payment' => $existing]);
                }
            }

            if ((float) $request->payment_amount > (float) $rent->balance_amount + 0.005) {
                return response()->json(['success' => false, 'message' => 'Payment amount cannot exceed the outstanding balance of ₹' . number_format($rent->balance_amount, 2) . '.'], 422);
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
                'due_date' => $rent->due_date,
                'transaction_id' => $request->filled('idempotency_key') ? 'IDEMP-' . $request->idempotency_key : null,
                'notes' => $request->notes,
                'receipt_file_path' => $receiptFilePath,
            ]);

            // The bill's paid/balance/status/paid_date were re-derived from
            // the ledger by the Payment model's saved hook.
            $rent->refresh();

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
     * Show rent receipt for admin
     */
    public function receipt(MaintenanceBill $rent)
    {
        // Admin/Super Admin manage rent; Accountant has read-only access.
        $this->authorizeRole('Super Admin', 'Admin', 'Accountant');
        
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