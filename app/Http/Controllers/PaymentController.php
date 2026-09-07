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
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use HandlesFormSubmissions;
    public function index()
    {
        $user = auth()->user();
        $query = Payment::with(['flat.building', 'flat.villaArea']);

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
            $directVillas = \App\Models\Flat::where('owner_id', $user->id)
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
            $directApartments = \App\Models\Flat::where('owner_id', $user->id)
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
        // Super Admin sees all payments

        // Get paginated payments
        $payments = $query->latest()->paginate(15);
        
        // Get statistics (all payments, not just current page)
        $statsQuery = Payment::query();
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
            $directVillas = \App\Models\Flat::where('owner_id', $user->id)
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
            $directApartments = \App\Models\Flat::where('owner_id', $user->id)
                                               ->where('property_type', 'apartment')
                                               ->pluck('id');
            
            $apartments = $apartments->merge($directApartments)->unique();
            
            if ($apartments->count() > 0) {
                $statsQuery->whereIn('flat_id', $apartments);
            } else {
                $statsQuery->whereIn('flat_id', []);
            }
        }
        
        $stats = [
            'total_amount' => $statsQuery->sum('amount'),
            'total_count' => $statsQuery->count(),
            'this_month_amount' => (clone $statsQuery)->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'today_amount' => (clone $statsQuery)->where('created_at', '>=', now()->startOfDay())->sum('amount'),
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
        elseif ($user->hasRole('Villa Owner') || $user->hasRole('Apartment Owner')) {
            // Get user's owned villas
            $villas = collect();
            if ($user->owner_id) {
                $ownedVilla = $user->ownedVilla;
                if ($ownedVilla) {
                    $villas->push($ownedVilla);
                }
            }
            
            $directVillas = Flat::with('villaArea')
                            ->where('owner_id', $user->id)
                            ->where('property_type', 'villa')
                            ->where('society_id', $societyId)
                            ->get();
            
            $villas = $villas->merge($directVillas)->unique('id');
            
            // Get user's owned apartments - check both owner_id and through Resident model
            $apartments = Flat::with('building')
                            ->where('property_type', 'apartment')
                            ->where('society_id', $societyId)
                            ->where('owner_id', $user->id)
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
            
            // Determine property type label based on what user owns
            if ($villas->count() > 0 && $apartments->count() === 0) {
                $propertyType = 'villa';
            } elseif ($apartments->count() > 0 && $villas->count() === 0) {
                $propertyType = 'apartment';
            } else if ($properties->count() > 0) {
                // Mixed properties or has properties - use generic label
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
                'property_type' => 'required|in:apartment,villa',
                'amount' => 'required|numeric|min:0',
                'payment_method' => 'required|in:cash,cheque,online,upi',
                'payment_date' => 'required|date',
                'bill_type' => 'required|string|max:255',
                'due_date' => 'required|date',
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
            
            // Verify flat exists and belongs to user's society (for Admin users)
            $flat = \App\Models\Flat::find($flatId);
            if (!$flat) {
                return back()->withErrors(['error' => 'Invalid property selected'])->withInput();
            }
            
            if ($user->hasRole('Admin') && $flat->society_id != $user->society_id) {
                return back()->withErrors(['error' => 'Invalid property selected'])->withInput();
            }
            
            // Create payment data
            $paymentData = [
                'society_id' => $user->hasRole('Admin') ? $user->society_id : $flat->society_id,
                'flat_id' => $flatId,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'bill_type' => $request->bill_type,
                'due_date' => $request->due_date,
                'notes' => $request->notes,
                'status' => 'success',
            ];
            
            // Handle file upload
            if ($request->hasFile('receipt_file')) {
                try {
                    $file = $request->file('receipt_file');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs('payment-receipts', $filename, 'public');
                    $paymentData['receipt_file_path'] = $filePath;
                } catch (\Exception $e) {
                    // File upload failed, but continue without it
                }
            }
            
            $payment = Payment::create($paymentData);

            // Dispatch event for payment confirmation email
            PaymentReceived::dispatch($payment);

            return redirect()->route('payments.index')->with('success', 'Payment recorded successfully!');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while processing your request. Please try again.'])->withInput();
        }
    }

    public function show(Payment $payment)
    {
        $user = auth()->user();
        
        // Verify payment belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $payment->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        // Load necessary relationships
        $payment->load(['flat.building', 'flat.villaArea', 'society']);
        
        return view('payments.show', compact('payment'));
    }

    public function edit(Payment $payment)
    {
        $user = auth()->user();
        
        // Verify payment belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $payment->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        // Eager load the flat relationship
        $payment->load(['flat.building', 'flat.villaArea']);
        
        return view('payments.edit', compact('payment'));
    }

    public function update(Request $request, Payment $payment)
    {
        $user = auth()->user();
        
        // Verify payment belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $payment->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        try {
            $request->validate([
                'property_type' => 'required|in:apartment,villa',
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
            
            // Verify flat exists and belongs to user's society (for Admin users)
            $flat = \App\Models\Flat::find($flatId);
            if (!$flat) {
                return back()->withErrors(['error' => 'Invalid property selected'])->withInput();
            }
            
            if ($user->hasRole('Admin') && $flat->society_id != $user->society_id) {
                return back()->withErrors(['error' => 'Invalid property selected'])->withInput();
            }
            
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
            
            // Handle file upload
            if ($request->hasFile('receipt_file')) {
                try {
                    // Delete old file if exists
                    if ($payment->receipt_file_path && \Storage::disk('public')->exists($payment->receipt_file_path)) {
                        \Storage::disk('public')->delete($payment->receipt_file_path);
                    }
                    
                    $file = $request->file('receipt_file');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs('payment-receipts', $filename, 'public');
                    $paymentData['receipt_file_path'] = $filePath;
                } catch (\Exception $e) {
                    // File upload failed, but continue without it
                }
            }
            
            $payment->update($paymentData);

            return redirect()->route('payments.index')->with('success', 'Payment updated successfully!');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while processing your request. Please try again.'])->withInput();
        }
    }

    public function destroy(Payment $payment)
    {
        $user = auth()->user();
        
        // Verify payment belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $payment->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        $payment->delete();

        return redirect()->route('payments.index')
            ->with('success', 'Payment deleted successfully!');
    }

    /**
     * Show printable payment receipt
     */
    public function receipt(Payment $payment)
    {
        $user = auth()->user();
        
        // Authorization check based on user role
        if ($user->hasRole('Admin')) {
            // Admin can view receipts from their society
            if ($payment->society_id !== $user->society_id) {
                abort(403, 'Unauthorized access.');
            }
        } elseif ($user->hasRole('Tenant')) {
            // Tenant can only view their own payment receipts
            if ($payment->user_id !== $user->id) {
                abort(403, 'Unauthorized access.');
            }
        } elseif ($user->hasRole('Owner') || $user->hasRole('Villa Owner')) {
            // Owner can view receipts for their properties
            $userFlats = [];
            if ($user->owner) {
                $userFlats = $user->owner->flats->pluck('id')->toArray();
            }
            if (!in_array($payment->flat_id, $userFlats)) {
                abort(403, 'Unauthorized access.');
            }
        } else {
            // Other roles not allowed
            abort(403, 'Unauthorized access.');
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