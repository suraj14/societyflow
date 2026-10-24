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
        
        // The tenant's unit comes from their ACTIVE TENANCY (tenants.flat_id),
        // the canonical record, resolved through the same UserFlatResolver the
        // rest of the app uses. It used to come from the legacy `residents`
        // row, which is never closed when a tenancy moves: a tenant moved from
        // villa 160 to 159 was shown (and could pay rent for) villa 160, a unit
        // they no longer rent - and after that row was correctly closed, was
        // told "No apartment assigned" while renting 159.
        //
        // Viewing a page must never write ownership or tenancy data (an earlier
        // "Method 4" invented a tenancy on the first vacant flat). An account
        // with no active tenancy is told so, below.
        $flat = $this->currentFlat($user);

        if (!$flat) {
            return redirect()->route('tenant.dashboard')
                ->with('error', 'No apartment assigned to your account. Please contact the administrator.');
        }
        // Get all maintenance bills for this tenant's flat
        $rentRecords = MaintenanceBill::where('society_id', $user->society_id)->where('flat_id', $flat->id)
            ->where('bill_type', 'rent')
            ->with(['payments', 'flat.building', 'flat.villaArea'])
            ->orderBy('bill_date', 'desc')
            ->get();
        
        // Calculate statistics
        // total_amount, not amount: MaintenanceBill has no `amount`
        // attribute, so this summed nothing and every tenant saw a rent
        // total of 0. A collection sum fails silently rather than erroring,
        // which is why no engine caught it.
        $totalRent = $rentRecords->sum('total_amount');
        $totalPaid = $rentRecords->sum(function($bill) {
            return $bill->payments->sum('amount');
        });
        $pendingAmount = $totalRent - $totalPaid;
        $unpaidBills = $rentRecords->where('status', '!=', 'paid')->count();
        
        return view('tenant.rent.index', compact(
            'rentRecords', 
            'flat', 
            'totalRent', 
            'totalPaid', 
            'pendingAmount', 
            'unpaidBills'
        ));
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
                'payment_method' => ['required', \Illuminate\Validation\Rule::in(\App\Models\Payment::METHODS)],
                'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
                'notes' => 'nullable|string|max:500',
                'idempotency_key' => 'nullable|string|max:64',
            ]);

            $user = auth()->user();
            
            // Get the rent record
            $rent = MaintenanceBill::findOrFail($request->rent_id);
            
            // Verify rent belongs to a unit this tenant actively rents
            if (!$this->rentsFlat($user, $rent->flat_id)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to rent record.'], 403);
            }

            // Verify payment amount doesn't exceed balance
            if ($request->payment_amount > $rent->balance_amount) {
                return response()->json(['success' => false, 'message' => 'Payment amount cannot exceed outstanding balance.'], 400);
            }

            // A double tap or a retried request carries the same key and
            // must not file the same payment twice.
            if ($request->filled('idempotency_key')) {
                $existing = \App\Models\Payment::where('maintenance_bill_id', $rent->id)
                    ->where('user_id', $user->id)
                    ->where('transaction_id', 'IDEMP-' . $request->idempotency_key)
                    ->first();
                if ($existing) {
                    return response()->json(['success' => true, 'message' => 'Payment already submitted.', 'payment_id' => $existing->payment_id]);
                }
            }

            \DB::beginTransaction();

            // Handle file upload
            $receiptFilePath = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $fileName = \App\Support\SafeUploadName::make($file, 'tenant_payment', $rent->id, ['jpg', 'png', 'pdf'], 'payment_proof');
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
                'due_date' => $rent->due_date,
                'transaction_id' => $request->filled('idempotency_key') ? 'IDEMP-' . $request->idempotency_key : null,
                'notes' => $request->notes,
                'receipt_file_path' => $receiptFilePath,
            ]);

            // Nothing on the bill changes yet: a pending payment is a claim,
            // not money received. It used to be added to paid_amount here -
            // and the bill was marked "paid" - before any admin had
            // verified it. The bill is re-derived from the ledger when an
            // admin approves the payment (PaymentController::approve).

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
            
            // Verify rent belongs to a unit this tenant actively rents
            if (!$this->rentsFlat($user, $rent->flat_id)) {
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

    /** The unit the user actively rents (their most recent active tenancy), or null. */
    private function currentFlat(\App\Models\User $user): ?\App\Models\Flat
    {
        $ids = \App\Support\UserFlatResolver::tenantFlatIds($user);

        return $ids === [] ? null : \App\Models\Flat::with(['building', 'villaArea'])
            ->where('society_id', $user->society_id)->whereIn('id', $ids)->orderByDesc('id')->first();
    }

    /** Whether $flatId is a unit the user actively rents. */
    private function rentsFlat(\App\Models\User $user, $flatId): bool
    {
        return $flatId !== null && in_array((int) $flatId, \App\Support\UserFlatResolver::tenantFlatIds($user), true);
    }}
