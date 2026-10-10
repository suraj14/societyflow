<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\MaintenanceBill;
use App\Models\Resident;
use App\Models\Tenant;
use App\Models\Flat;
use App\Models\Building;
use App\Models\VillaArea;
use App\Events\PaymentReceived;
use App\Http\Controllers\Concerns\AuthorizesSocietyAccess;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use HandlesFormSubmissions, AuthorizesSocietyAccess;
    public function index()
    {
        $user = auth()->user();
        $query = Payment::with(['flat.building', 'flat.villaArea']);

        // Scope by default; exempt Super Admin explicitly (CLAUDE.md §3).
        //
        // The role chain below names Admin, Tenant, Villa Owner and Apartment
        // Owner and then falls through to "Super Admin sees all" - so Guard
        // and Accountant, named nowhere, listed every payment in every
        // society. RestrictStaffAccess hid this for Staff only; its first
        // guard returns early for every role that is not Staff.
        //
        // This scope only ever narrows: the branches below still apply their
        // own per-role filters on top of it.
        if (!$user->hasRole('Super Admin')) {
            $query->where('society_id', $user->society_id);
        }

        // Filter by society for Admin users
        if ($user->hasRole('Admin')) {
            $query->where('society_id', $user->society_id);
        }
        // Tenant: See only their own flat's bills
        elseif ($user->hasRole('Tenant')) {
            $tenant = Tenant::where('user_id', $user->id)->first();
            if ($tenant) {
                $query->where('flat_id', $tenant->flat_id);
            } else {
                // No tenant record, show empty result
                $query->whereIn('flat_id', []);
            }
        }
        // Villa Owner: See only their own villas' bills
        elseif ($user->hasRole('Villa Owner')) {
            $villas = collect();
            
            // Try to get villa through ownedVilla relationship (if user has owner_id)
            if ($user->owner_id) {
                $ownedVilla = $user->ownedVilla;
                if ($ownedVilla) {
                    $villas->push($ownedVilla->id);
                }
            }
            
            // Also check for villas with direct owner_id match
            $directVillas = \App\Models\Flat::ownedBy($user->owner_id)
                                           ->where('property_type', 'villa')
                                           ->pluck('id');
            
            $villas = $villas->merge($directVillas)->unique();
            
            if ($villas->count() > 0) {
                $query->whereIn('flat_id', $villas);
            } else {
                // No villas owned, show empty result
                $query->whereIn('flat_id', []);
            }
        }
        // Apartment Owner: See only their own apartments' bills
        elseif ($user->hasRole('Apartment Owner')) {
            $apartments = collect();
            
            // Try to get apartment through ownedFlat relationship (if user has owner_id)
            if ($user->owner_id) {
                $ownedFlat = $user->ownedFlat;
                if ($ownedFlat) {
                    $apartments->push($ownedFlat->id);
                }
            }
            
            // Also check for apartments with direct owner_id match
            $directApartments = \App\Models\Flat::ownedBy($user->owner_id)
                                               ->where('property_type', 'apartment')
                                               ->pluck('id');
            
            $apartments = $apartments->merge($directApartments)->unique();
            
            if ($apartments->count() > 0) {
                $query->whereIn('flat_id', $apartments);
            } else {
                // No apartments owned, show empty result
                $query->whereIn('flat_id', []);
            }
        }
        // Owner (canonical role): see bills for every property they own,
        // villa or apartment - property type is a flat attribute, not a
        // separate role, so this is one branch instead of two.
        elseif ($user->hasRole('Owner')) {
            $flatIds = \App\Models\Flat::ownedBy($user->owner_id)->pluck('id');

            if ($flatIds->count() > 0) {
                $query->whereIn('flat_id', $flatIds);
            } else {
                $query->whereIn('flat_id', []);
            }
        }
        // Super Admin sees all payments

        // Get paginated payments
        $payments = $query->latest()->paginate(15);
        
        // Get statistics (all payments, not just current page)
        $statsQuery = Payment::query();

        // Same default scoping as the list above - the headline totals leaked
        // other societies' revenue to Guard and Accountant independently of
        // the table.
        if (!$user->hasRole('Super Admin')) {
            $statsQuery->where('society_id', $user->society_id);
        }

        if ($user->hasRole('Admin')) {
            $statsQuery->where('society_id', $user->society_id);
        }
        // Tenant: See only their own flat's bills
        elseif ($user->hasRole('Tenant')) {
            $tenant = Tenant::where('user_id', $user->id)->first();
            if ($tenant) {
                $statsQuery->where('flat_id', $tenant->flat_id);
            } else {
                $statsQuery->whereIn('flat_id', []);
            }
        }
        // Villa Owner: See only their own villas' bills
        elseif ($user->hasRole('Villa Owner')) {
            $villas = collect();
            
            // Try to get villa through ownedVilla relationship (if user has owner_id)
            if ($user->owner_id) {
                $ownedVilla = $user->ownedVilla;
                if ($ownedVilla) {
                    $villas->push($ownedVilla->id);
                }
            }
            
            // Also check for villas with direct owner_id match
            $directVillas = \App\Models\Flat::ownedBy($user->owner_id)
                                           ->where('property_type', 'villa')
                                           ->pluck('id');
            
            $villas = $villas->merge($directVillas)->unique();
            
            if ($villas->count() > 0) {
                $statsQuery->whereIn('flat_id', $villas);
            } else {
                $statsQuery->whereIn('flat_id', []);
            }
        }
        // Apartment Owner: See only their own apartments' bills
        elseif ($user->hasRole('Apartment Owner')) {
            $apartments = collect();
            
            // Try to get apartment through ownedFlat relationship (if user has owner_id)
            if ($user->owner_id) {
                $ownedFlat = $user->ownedFlat;
                if ($ownedFlat) {
                    $apartments->push($ownedFlat->id);
                }
            }
            
            // Also check for apartments with direct owner_id match
            $directApartments = \App\Models\Flat::ownedBy($user->owner_id)
                                               ->where('property_type', 'apartment')
                                               ->pluck('id');
            
            $apartments = $apartments->merge($directApartments)->unique();
            
            if ($apartments->count() > 0) {
                $statsQuery->whereIn('flat_id', $apartments);
            } else {
                $statsQuery->whereIn('flat_id', []);
            }
        }
        // Owner (canonical role): same combined villa+apartment scope as the
        // list query above.
        elseif ($user->hasRole('Owner')) {
            $flatIds = \App\Models\Flat::ownedBy($user->owner_id)->pluck('id');

            if ($flatIds->count() > 0) {
                $statsQuery->whereIn('flat_id', $flatIds);
            } else {
                $statsQuery->whereIn('flat_id', []);
            }
        }

        $stats = [
            'total_amount' => $statsQuery->sum('amount'),
            'total_count' => $statsQuery->count(),
            'this_month_amount' => (clone $statsQuery)->where('created_at', '>=', \App\Support\SocietyClock::monthBounds()[0])->sum('amount'),
            'today_amount' => (clone $statsQuery)->where('created_at', '>=', \App\Support\SocietyClock::dayBounds()[0])->sum('amount'),
        ];
        
        return view('payments.index', compact('payments', 'stats'));
    }

    public function create()
    {
        $user = auth()->user();
        $societyId = $user->society_id;
        $propertyType = null;
        $properties = collect();
        
        // For Tenant - auto-populate their flat
        if ($user->hasRole('Tenant')) {
            $tenant = Tenant::where('user_id', $user->id)->first();
            if ($tenant && $tenant->flat) {
                $flat = $tenant->flat;
                $propertyType = $flat->property_type;
                
                if ($flat->property_type === 'villa') {
                    $villaName = !empty($flat->villa_name) ? $flat->villa_name : $flat->flat_number;
                    $areaName = $flat->villaArea ? $flat->villaArea->name : '';
                    $properties->push([
                        'id' => $flat->id,
                        'display_name' => "Villa {$villaName}" . ($areaName ? " - {$areaName}" : ''),
                        'type' => 'Villa'
                    ]);
                } else {
                    $buildingName = $flat->building ? $flat->building->name : 'Unknown';
                    $properties->push([
                        'id' => $flat->id,
                        'display_name' => "Apartment {$flat->flat_number} - {$buildingName}",
                        'type' => 'Apartment'
                    ]);
                }
            }
        }
        // For owners - auto-populate their properties (apartments and/or villas)
        elseif ($user->hasAnyRole(['Owner', 'Villa Owner', 'Apartment Owner'])) {
            // Get user's owned villas
            $villas = collect();
            if ($user->owner_id) {
                $ownedVilla = $user->ownedVilla;
                if ($ownedVilla) {
                    $villas->push($ownedVilla);
                }
            }
            
            $directVillas = Flat::with('villaArea')
                            ->ownedBy($user->owner_id)
                            ->where('property_type', 'villa')
                            ->where('society_id', $societyId)
                            ->get();
            
            $villas = $villas->merge($directVillas)->unique('id');
            
            // Get user's owned apartments - check both owner_id and through Resident model
            $apartments = Flat::with('building')
                            ->where('property_type', 'apartment')
                            ->where('society_id', $societyId)
                            ->ownedBy($user->owner_id)
                            ->get();
            
            // If no apartments found via owner_id, check through Resident model
            if ($apartments->isEmpty()) {
                $apartments = Flat::with('building')
                                ->where('property_type', 'apartment')
                                ->where('society_id', $societyId)
                                ->whereHas('residents', function($q) use ($user) {
                                    $q->where('user_id', $user->id)
                                      ->where('type', 'owner')
                                      ->where('status', 'active');
                                })
                                ->get();
            }
            
            // Combine all properties
            $allProperties = collect();
            
            // Add villas
            foreach ($villas as $villa) {
                $villaName = !empty($villa->villa_name) ? $villa->villa_name : $villa->flat_number;
                $areaName = $villa->villaArea ? $villa->villaArea->name : '';
                $allProperties->push([
                    'id' => $villa->id,
                    'display_name' => "Villa {$villaName}" . ($areaName ? " - {$areaName}" : ''),
                    'type' => 'Villa'
                ]);
            }
            
            // Add apartments
            foreach ($apartments as $apartment) {
                $buildingName = $apartment->building ? $apartment->building->name : 'Unknown';
                $allProperties->push([
                    'id' => $apartment->id,
                    'display_name' => "Apartment {$apartment->flat_number} - {$buildingName}",
                    'type' => 'Apartment'
                ]);
            }
            
            $properties = $allProperties;
            
            // Determine the property type label based on what the user owns.
            //
            // 'mixed' is a LABEL ONLY - it is deliberately not one of the
            // PropertyType values. An owner holding both a villa and an
            // apartment picks the specific unit from one combined list, and
            // store() derives the concrete type from that unit. The view
            // must not submit 'mixed' as property_type: it fails the
            // in:apartment,villa rule, and because the field is hidden in
            // this branch the owner had no way to correct it.
            if ($villas->count() > 0 && $apartments->count() === 0) {
                $propertyType = 'villa';
            } elseif ($apartments->count() > 0 && $villas->count() === 0) {
                $propertyType = 'apartment';
            } else if ($properties->count() > 0) {
                $propertyType = 'mixed';
            }
        }
        
        // Filter data by society for Admin users
        if ($user->hasRole('Admin')) {
            $maintenanceBills = MaintenanceBill::where('society_id', $user->society_id)
                ->whereIn('status', ['pending', 'partial'])
                ->with(['flat'])
                ->get();
            $residents = Resident::whereHas('flat', function($query) use ($user) {
                $query->where('society_id', $user->society_id);
            })->with(['flat'])->get();
        } else {
            // Super Admin sees all
            $maintenanceBills = MaintenanceBill::whereIn('status', ['pending', 'partial'])
                ->with(['flat'])
                ->get();
            $residents = Resident::with(['flat'])->get();
        }
        
        return view('payments.create', compact('maintenanceBills', 'residents', 'propertyType', 'properties'));
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                // Nullable: an owner holding both kinds picks the unit from one
                // combined list and submits no type. The selected flat is the
                // authority either way - this value only routes which input
                // carries the id, and is never persisted.
                'property_type' => 'nullable|in:apartment,villa',
                // Bill-first flow: reuses the existing maintenance_bills
                // table rather than adding a parallel one. When a bill is
                // picked, bill_type/due_date are resolved from it
                // server-side below rather than trusted as submitted.
                'maintenance_bill_id' => 'nullable|exists:maintenance_bills,id',
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'required|in:cash,cheque,online,upi',
                'payment_date' => 'required|date',
                'bill_type' => 'required_without:maintenance_bill_id|nullable|string|max:255',
                'due_date' => 'required_without:maintenance_bill_id|nullable|date',
                'receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'notes' => 'nullable|string',
            ]);

            $user = auth()->user();

            // Get the flat_id based on property type
            $flatId = null;
            if ($request->property_type === 'apartment') {
                $flatId = $request->get('flat_id');
            } elseif ($request->property_type === 'villa') {
                $flatId = $request->get('villa_id'); // For villas, villa_id is the flat_id
            }
            
            // Also check if flat_id was directly provided (from JavaScript)
            if (!$flatId) {
                $flatId = $request->get('flat_id');
            }
            
            if (!$flatId) {
                return back()->withErrors(['error' => 'Please select a property'])->withInput();
            }
            
            // Verify the flat exists and belongs to the acting user's society.
            $flat = \App\Models\Flat::find($flatId);
            if (!$flat) {
                return back()->withErrors(['error' => 'Invalid property selected'])->withInput();
            }

            // Was `if ($user->hasRole('Admin') && $flat->society_id != ...)`:
            // the && meant every non-Admin role (Owner, Tenant, Guard,
            // Accountant, Staff) skipped the check, so any authenticated
            // resident could post another society's flat_id and create a
            // payment row inside that society. Scope by default instead.
            $this->authorizeSocietyAccess($flat);

            // create() only ever lists the acting resident's own flat(s) as
            // options, but the field is still a plain form input - a
            // same-society resident could otherwise hand-craft a request
            // naming a neighbour's flat_id and record a payment against it.
            if (!$user->hasAnyRole(['Admin', 'Super Admin']) && !\App\Support\UserFlatResolver::userHasFlat($user, $flat->id)) {
                return back()->withErrors(['error' => 'You can only record a payment for your own property.'])->withInput();
            }

            // Bill-first: never trust bill_type/due_date typed alongside a
            // chosen bill - resolve them from the bill itself, and check the
            // bill actually belongs to the flat being paid against so a
            // request cannot pair one flat's bill with another's flat_id.
            $bill = null;
            if ($request->filled('maintenance_bill_id')) {
                $bill = MaintenanceBill::where('id', $request->maintenance_bill_id)
                    ->where('flat_id', $flat->id)
                    ->first();

                if (!$bill) {
                    return back()->withErrors(['error' => 'Selected bill does not belong to this property.'])->withInput();
                }
            }

            // Create payment data
            $paymentData = [
                'society_id' => $user->hasRole('Admin') ? $user->society_id : $flat->society_id,
                'flat_id' => $flatId,
                'maintenance_bill_id' => $bill?->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'bill_type' => $bill ? ($bill->bill_type ?: 'Maintenance') : $request->bill_type,
                'due_date' => $bill ? $bill->due_date : $request->due_date,
                'notes' => $request->notes,
                'status' => 'success',
            ];
            
            // Handle file upload. Previously built the stored filename from
            // the client's own filename and silently swallowed any
            // failure - a receipt photo with a colon in its name (a common
            // phone export/share-sheet name) crashed the underlying
            // Storage write exactly like the avatar bug in UserController,
            // and the resident had no way to know their receipt never
            // attached. handleFileUpload() never uses the client's
            // filename; a genuine failure now reaches store()'s own
            // try/catch below, which reports it instead of hiding it.
            if ($request->hasFile('receipt_file')) {
                $paymentData['receipt_file_path'] = $this->handleFileUpload($request, 'receipt_file', 'payment-receipts', ['pdf', 'jpg', 'jpeg', 'png']);
            }
            
            $payment = Payment::create($paymentData);

            // Dispatch event for payment confirmation email
            PaymentReceived::dispatch($payment);

            return redirect()->route('payments.index')->with('success', 'Payment recorded successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // abort(403) from the society check is an \Exception too; without
            // this it would be swallowed into the generic "an error occurred"
            // redirect below, hiding a blocked cross-society write.
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while processing your request. Please try again.'])->withInput();
        }
    }

    public function show(Payment $payment)
    {
        // Was `if ($user->hasRole('Admin') && $payment->society_id !== ...)`:
        // the && meant every non-Admin role skipped the check entirely, so any
        // authenticated resident could read any payment in any society.
        $this->authorizeOwnedRecord($payment);

        // Load necessary relationships
        $payment->load(['flat.building', 'flat.villaArea', 'society']);

        return view('payments.show', compact('payment'));
    }

    public function edit(Payment $payment)
    {
        $this->authorizeOwnedRecord($payment);

        // Eager load the flat relationship
        $payment->load(['flat.building', 'flat.villaArea']);

        return view('payments.edit', compact('payment'));
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorizeOwnedRecord($payment);

        $user = auth()->user();

        try {
            $request->validate([
                // Nullable: an owner holding both kinds picks the unit from one
                // combined list and submits no type. The selected flat is the
                // authority either way - this value only routes which input
                // carries the id, and is never persisted.
                'property_type' => 'nullable|in:apartment,villa',
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'required|in:cash,cheque,online,upi',
                'payment_date' => 'required|date',
                'bill_type' => 'required|string|max:255',
                'due_date' => 'required|date',
                'receipt_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'notes' => 'nullable|string',
            ]);

            // flat_id is sent as a hidden field directly on edit
            $flatId = $request->get('flat_id') ?? $payment->flat_id;
            
            // Verify the flat exists and belongs to the acting user's society.
            $flat = \App\Models\Flat::find($flatId);
            if (!$flat) {
                return back()->withErrors(['error' => 'Invalid property selected'])->withInput();
            }

            // Same `&&` short-circuit as store() had: re-pointing an existing
            // payment at another society's flat was open to every non-Admin
            // role. authorizeSocietyAccess() scopes by default.
            $this->authorizeSocietyAccess($flat);

            // Create payment data
            $paymentData = [
                'flat_id' => $flatId,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'bill_type' => $request->bill_type,
                'due_date' => $request->due_date,
                'notes' => $request->notes,
            ];
            
            // Handle file upload - see store() for why this no longer
            // builds the filename from the client's own filename or
            // silently discards a failure.
            if ($request->hasFile('receipt_file')) {
                $oldReceipt = $payment->receipt_file_path;
                $paymentData['receipt_file_path'] = $this->handleFileUpload($request, 'receipt_file', 'payment-receipts', ['pdf', 'jpg', 'jpeg', 'png']);
                if ($oldReceipt) {
                    $this->deleteUploadedFile($oldReceipt);
                }
            }
            
            $payment->update($paymentData);

            return redirect()->route('payments.index')->with('success', 'Payment updated successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // See store(): keep the 403 from the society check intact.
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while processing your request. Please try again.'])->withInput();
        }
    }

    public function destroy(Payment $payment)
    {
        $this->authorizeOwnedRecord($payment);

        // The Payment model's deleted hook re-derives the linked bill.
        $payment->delete();

        return redirect()->route('payments.index')
            ->with('success', 'Payment deleted successfully!');
    }

    /**
     * Verify a resident-submitted (pending) payment. Only now does it count
     * towards the bill - the Payment model's saved hook re-derives the bill
     * from its ledger when the status flips to success.
     */
    public function approve(Payment $payment)
    {
        $this->authorizeSocietyAccess($payment);
        abort_unless(auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Accountant']), 403);

        if ($payment->status === 'success') {
            return back()->with('info', 'This payment is already verified.');
        }
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only a pending payment can be verified.');
        }

        $payment->update(['status' => 'success']);

        \App\Models\ActivityLog::recordFor($payment, 'payment.approved', ['amount' => (float) $payment->amount, 'maintenance_bill_id' => $payment->maintenance_bill_id]);

        return back()->with('success', 'Payment verified and applied to the bill.');
    }

    /** Decline a resident-submitted payment; the bill is left untouched. */
    public function reject(Payment $payment)
    {
        $this->authorizeSocietyAccess($payment);
        abort_unless(auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Accountant']), 403);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only a pending payment can be rejected.');
        }

        $payment->update(['status' => 'cancelled']);

        \App\Models\ActivityLog::recordFor($payment, 'payment.rejected', ['amount' => (float) $payment->amount, 'maintenance_bill_id' => $payment->maintenance_bill_id]);

        return back()->with('success', 'Payment rejected.');
    }

    /**
     * A UPI deep link for one bill (brief §12).
     *
     * The amount, UPI id and payee name are all resolved server-side by
     * UpiPaymentService from the bill and the society's stored settings -
     * this endpoint accepts nothing from the caller except which bill.
     * Opening the returned link only pre-fills the resident's UPI app; it
     * does not create or touch any Payment record, so it cannot mark
     * anything paid.
     */
    public function upiLink(\App\Models\MaintenanceBill $bill, \App\Services\UpiPaymentService $upi)
    {
        $this->authorizeSocietyAccess($bill);

        $user = auth()->user();

        // Admin/Accountant may generate a link for any bill in their own
        // society (already enforced above); anyone else must be paying
        // their own bill.
        if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Accountant'])) {
            $ownFlatIds = \App\Support\UserFlatResolver::flatIdsFor($user);

            abort_unless(in_array($bill->flat_id, $ownFlatIds, true), 403, 'This is not your bill.');
        }

        try {
            $link = $upi->buildLink($bill);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'link' => $link]);
    }

    /**
     * Show printable payment receipt
     */
    public function receipt(Payment $payment)
    {
        $user = auth()->user();

        // Society isolation first, for every role.
        $this->authorizeSocietyAccess($payment);

        if (!$user->hasAnyRole(['Super Admin', 'Admin'])) {
            // The payer always sees their own receipt.
            $isPayer = $payment->user_id !== null && (int) $payment->user_id === (int) $user->id;

            // Owners also see receipts raised against a unit they own.
            // Previously this branch tested hasRole('Owner'), a role that does
            // not exist in this application - the real roles are
            // 'Apartment Owner' and 'Villa Owner' - so every apartment owner
            // fell through to the default deny and got a 403 on their own
            // receipt.
            $ownsUnit = false;
            if (!$isPayer && $payment->flat_id && $user->owner) {
                $ownsUnit = $user->owner->flats()
                    ->whereKey($payment->flat_id)
                    ->exists();
            }

            if (!$isPayer && !$ownsUnit) {
                abort(403, 'Unauthorized access.');
            }
        }

        // Load necessary relationships
        $payment->load(['flat.building', 'flat.villaArea', 'society', 'user']);
        
        return view('payments.receipt', compact('payment'));
    }

    /**
     * API endpoint to get buildings for cascading dropdown
     */
    public function getBuildings()
    {
        try {
            $user = auth()->user();
            $societyId = $user->society_id;
            
            if (!$societyId) {
                return response()->json([]);
            }

            $buildings = \App\Models\Building::where('society_id', $societyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json($buildings);
        } catch (\Exception $e) {
            \Log::error('Error loading buildings: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load buildings'], 500);
        }
    }

    /**
     * API endpoint to get floors for a building
     */
    public function getFloors(Request $request)
    {
        try {
            $buildingId = $request->get('building_id');
            
            if (!$buildingId) {
                return response()->json([]);
            }

            $floors = \App\Models\Flat::where('building_id', $buildingId)
                ->distinct()
                ->pluck('floor')
                ->filter()
                ->sort()
                ->values();

            return response()->json($floors);
        } catch (\Exception $e) {
            \Log::error('Error loading floors: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load floors'], 500);
        }
    }

    /**
     * API endpoint to get flats for a building and floor
     */
    public function getFlats(Request $request)
    {
        try {
            $buildingId = $request->get('building_id');
            $floor = $request->get('floor');
            
            if (!$buildingId || !$floor) {
                return response()->json([]);
            }

            $flats = \App\Models\Flat::where('building_id', $buildingId)
                ->where('floor', $floor)
                ->select('id', 'flat_number')
                ->orderBy('flat_number')
                ->get();

            return response()->json($flats);
        } catch (\Exception $e) {
            \Log::error('Error loading flats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load flats'], 500);
        }
    }

    /**
     * API endpoint to get villa areas for cascading dropdown
     */
    public function getVillaAreas()
    {
        try {
            $user = auth()->user();
            $societyId = $user->society_id;
            
            if (!$societyId) {
                return response()->json([]);
            }

            $villaAreas = \App\Models\VillaArea::where('society_id', $societyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return response()->json($villaAreas);
        } catch (\Exception $e) {
            \Log::error('Error loading villa areas: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load villa areas'], 500);
        }
    }

    /**
     * API endpoint to get villas for a villa area
     */
    public function getVillas(Request $request)
    {
        try {
            $villaAreaId = $request->get('villa_area_id');
            
            if (!$villaAreaId) {
                return response()->json([]);
            }

            $villas = \App\Models\Flat::where('villa_area_id', $villaAreaId)
                ->where('property_type', 'villa')
                ->select('id', 'flat_number', 'villa_name')
                ->orderBy('flat_number')
                ->get()
                ->map(function($villa) {
                    return [
                        'id' => $villa->id,
                        'villa_number' => $villa->villa_name ?: $villa->flat_number ?: 'Villa ' . $villa->id
                    ];
                });

            return response()->json($villas);
        } catch (\Exception $e) {
            \Log::error('Error loading villas: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load villas'], 500);
        }
    }

    /**
     * API endpoint listing a flat's unpaid/partial maintenance bills, so the
     * Record Payment form can go bill-first: once a property is chosen, its
     * outstanding bills populate a select and choosing one fills bill type,
     * amount and due date instead of asking the admin to retype them.
     */
    public function getOutstandingBills(Request $request)
    {
        $flatId = $request->get('flat_id');

        if (!$flatId) {
            return response()->json([]);
        }

        $flat = \App\Models\Flat::find($flatId);

        if (!$flat) {
            return response()->json([]);
        }

        // Same rule as store(): scope by default, and a non-admin resident
        // may only ever see their own property's bills, not a neighbour's.
        $this->authorizeSocietyAccess($flat);

        $user = auth()->user();
        if (!$user->hasAnyRole(['Super Admin', 'Admin']) && !\App\Support\UserFlatResolver::userHasFlat($user, $flat->id)) {
            abort(403);
        }

        $bills = MaintenanceBill::where('flat_id', $flat->id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date')
            ->get()
            ->map(function ($bill) {
                $outstanding = $bill->total_amount - $bill->paid_amount;

                return [
                    'id' => $bill->id,
                    'bill_type' => $bill->bill_type ?: 'Maintenance',
                    'label' => ($bill->bill_type ? ucfirst($bill->bill_type) : 'Maintenance')
                        . ' - ' . ($bill->month ? $bill->month . ' ' . $bill->year : $bill->bill_date?->format('M Y'))
                        . ' (Due ' . optional($bill->due_date)->format('d M Y') . ')',
                    'total_amount' => (float) $bill->total_amount,
                    'paid_amount' => (float) $bill->paid_amount,
                    'outstanding_amount' => (float) $outstanding,
                    'due_date' => optional($bill->due_date)->format('Y-m-d'),
                ];
            });

        return response()->json($bills);
    }

    /**
     * API endpoint to get resident for a flat
     */
    public function getResidentForFlat(Request $request)
    {
        $flatId = $request->get('flat_id');
        
        if (!$flatId) {
            return response()->json([]);
        }

        $flat = \App\Models\Flat::with(['owners', 'tenants'])->find($flatId);
        
        if (!$flat) {
            return response()->json([]);
        }

        $residents = [];
        
        // Add owners
        foreach ($flat->owners as $owner) {
            if ($owner->user) {
                $residents[] = [
                    'id' => $owner->user->id,
                    'name' => $owner->user->name,
                    'email' => $owner->user->email,
                    'type' => 'Owner'
                ];
            }
        }
        
        // Add tenants
        foreach ($flat->tenants as $tenant) {
            if ($tenant->user) {
                $residents[] = [
                    'id' => $tenant->user->id,
                    'name' => $tenant->user->name,
                    'email' => $tenant->user->email,
                    'type' => 'Tenant'
                ];
            }
        }

        return response()->json($residents);
    }

    /**
     * API endpoint to get maintenance bills for a flat
     */
    public function getMaintenanceBills(Request $request)
    {
        $flatId = $request->get('flat_id');
        
        if (!$flatId) {
            return response()->json([]);
        }

        $bills = \App\Models\MaintenanceBill::where('flat_id', $flatId)
            ->whereIn('status', ['pending', 'partial'])
            ->select('id', 'month', 'year', 'total_amount', 'status')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return response()->json($bills);
    }
}