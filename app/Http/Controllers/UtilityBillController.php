<?php

namespace App\Http\Controllers;

use App\Models\UtilityBill;
use App\Models\Tenant;
use App\Models\Flat;
use App\Models\Building;
use App\Models\VillaArea;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class UtilityBillController extends BaseController
{
    use HandlesFormSubmissions;
    public function index()
    {
        $user = auth()->user();
        $societyId = $this->getSocietyId();
        
        if (!$societyId) {
            return view('utility-bills.index', ['bills' => collect(), 'flats' => collect()]);
        }

        $query = UtilityBill::bySociety($societyId)->with(['flat', 'society']);
        
        // Filter by tenant's flat
        if ($user->hasRole('Tenant')) {
            $tenant = Tenant::where('user_id', $user->id)->first();
            if ($tenant) {
                $query->where('flat_id', $tenant->flat_id);
            } else {
                $query->whereIn('flat_id', []);
            }
        }
        // Filter by owner's properties
        elseif ($user->hasRole('Villa Owner') || $user->hasRole('Apartment Owner')) {
            $ownedFlats = Flat::where('owner_id', $user->id)->pluck('id');
            
            // Also get flats where user is owner through Resident model
            $residentFlats = Flat::whereHas('residents', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('type', 'owner')
                  ->where('status', 'active');
            })->pluck('id');
            
            $allFlats = $ownedFlats->merge($residentFlats)->unique();
            $query->whereIn('flat_id', $allFlats);
        }
        // Admin and Super Admin see all
        
        $bills = $query
            ->when(request('search'), function($q, $search) {
                $q->whereHas('flat', function($sq) use ($search) {
                    $sq->where('flat_number', 'like', "%{$search}%");
                })->orWhere('bill_type', 'like', "%{$search}%");
            })
            ->when(request('flat_id'), function($q, $flatId) {
                $q->byFlat($flatId);
            })
            ->when(request('status'), function($q, $status) {
                $q->byStatus($status);
            })
            ->when(request('bill_type'), function($q, $billType) {
                $q->where('bill_type', $billType);
            })
            ->latest()
            ->paginate(15);

        $flats = Flat::bySociety($societyId)->get();

        return view('utility-bills.index', compact('bills', 'flats'));
    }

    public function create()
    {
        $user = auth()->user();
        $societyId = $this->getSocietyId();
        
        if (!$societyId) {
            return redirect()->route('utility-bills.index')
                ->with('error', 'Please select a society first');
        }

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
            
            // Also check for villas with direct owner_id match
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
        else {
            // For Admin/other roles - get all flats
            $flats = Flat::bySociety($societyId)->orderBy('flat_number')->get();
            
            if ($flats->isEmpty()) {
                return redirect()->route('utility-bills.index')
                    ->with('warning', 'No apartments found for your society. Please add apartments first before creating utility bills.');
            }
            
            $billTypes = UtilityBill::getBillTypes();
            return view('utility-bills.create', compact('flats', 'billTypes'));
        }
        
        $billTypes = UtilityBill::getBillTypes();

        return view('utility-bills.create', compact('propertyType', 'properties', 'billTypes'));
    }

    public function store(Request $request)
    {
        return $this->handleFormSubmission(function() use ($request) {
            $request->validate([
                'flat_id' => 'required|exists:flats,id',
                'bill_type' => 'required|string|max:255',
                'bill_amount' => 'required|numeric|min:0',
                'bill_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:bill_date',
                'bill_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'status' => 'required|in:unpaid,partial,paid',
                'notes' => 'nullable|string|max:1000',
            ]);

            $societyId = $this->getSocietyId();
            $flat = Flat::findOrFail($request->flat_id);

            // Verify flat belongs to society
            if ($flat->society_id != $societyId) {
                throw new \Exception('Invalid flat selected');
            }

            $data = [
                'society_id' => $societyId,
                'flat_id' => $request->flat_id,
                'bill_type' => $request->bill_type,
                'bill_amount' => $request->bill_amount,
                'bill_date' => $request->bill_date,
                'due_date' => $request->due_date,
                'status' => $request->status,
                'notes' => $request->notes,
            ];

            // Handle file upload
            if ($request->hasFile('bill_file')) {
                $filePath = $this->handleFileUpload($request, 'bill_file', 'utility-bills');
                $data['bill_file_path'] = $filePath;
            }

            UtilityBill::create($data);
            
            return true;
        }, 'Utility bill added successfully!', 'utility-bills.index', $request->expectsJson());
    }

    public function show(UtilityBill $utilityBill)
    {
        $this->authorize('view', $utilityBill);
        
        return view('utility-bills.show', compact('utilityBill'));
    }

    public function edit(UtilityBill $utilityBill)
    {
        $this->authorize('update', $utilityBill);
        
        $societyId = $this->getSocietyId();
        $flats = Flat::bySociety($societyId)->orderBy('flat_number')->get();
        $billTypes = UtilityBill::getBillTypes();

        return view('utility-bills.edit', compact('utilityBill', 'flats', 'billTypes'));
    }

    public function update(Request $request, UtilityBill $utilityBill)
    {
        $this->authorize('update', $utilityBill);

        return $this->handleFormSubmission(function() use ($request, $utilityBill) {
            $request->validate([
                'flat_id' => 'required|exists:flats,id',
                'bill_type' => 'required|string|max:255',
                'bill_amount' => 'required|numeric|min:0',
                'bill_date' => 'required|date',
                'due_date' => 'required|date|after_or_equal:bill_date',
                'bill_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'status' => 'required|in:unpaid,partial,paid',
                'notes' => 'nullable|string|max:1000',
            ]);

            $data = [
                'flat_id' => $request->flat_id,
                'bill_type' => $request->bill_type,
                'bill_amount' => $request->bill_amount,
                'bill_date' => $request->bill_date,
                'due_date' => $request->due_date,
                'status' => $request->status,
                'notes' => $request->notes,
            ];

            // Handle file upload
            if ($request->hasFile('bill_file')) {
                // Delete old file if exists
                if ($utilityBill->bill_file_path && \Storage::disk('public')->exists($utilityBill->bill_file_path)) {
                    \Storage::disk('public')->delete($utilityBill->bill_file_path);
                }
                
                $filePath = $this->handleFileUpload($request, 'bill_file', 'utility-bills');
                $data['bill_file_path'] = $filePath;
            }

            $utilityBill->update($data);
            
            return true;
        }, 'Utility bill updated successfully!', 'utility-bills.index', $request->expectsJson());
    }

    public function destroy(UtilityBill $utilityBill)
    {
        $this->authorize('delete', $utilityBill);

        // Delete file if exists
        if ($utilityBill->bill_file_path && \Storage::disk('public')->exists($utilityBill->bill_file_path)) {
            \Storage::disk('public')->delete($utilityBill->bill_file_path);
        }

        $utilityBill->delete();

        return redirect()->route('utility-bills.index')
            ->with('success', 'Utility bill deleted successfully!');
    }

    public function addPayment(Request $request, UtilityBill $utilityBill)
    {
        $this->authorize('update', $utilityBill);

        $request->validate([
            'payment_date' => 'required|date',
            'payment_proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $data = [
            'payment_date' => $request->payment_date,
            'status' => 'paid',
        ];

        // Handle payment proof upload
        if ($request->hasFile('payment_proof')) {
            $file = $request->file('payment_proof');
            $filePath = $file->store('utility-bills/payments', 'public');
            $data['payment_proof_path'] = $filePath;
        }

        $utilityBill->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Payment details added successfully!'
            ]);
        }

        return redirect()->route('utility-bills.index')
            ->with('success', 'Payment details added successfully!');
    }

    /**
     * API endpoint to get buildings for cascading dropdown
     */
    public function getBuildings()
    {
        $societyId = $this->getSocietyId();
        
        if (!$societyId) {
            return response()->json([]);
        }

        $buildings = \App\Models\Building::where('society_id', $societyId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($buildings);
    }

    /**
     * API endpoint to get floors for a building
     */
    public function getFloors(Request $request)
    {
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
    }

    /**
     * API endpoint to get flats for a building and floor
     */
    public function getFlats(Request $request)
    {
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
    }

    /**
     * API endpoint to get villa areas for cascading dropdown
     */
    public function getVillaAreas()
    {
        $societyId = $this->getSocietyId();
        
        if (!$societyId) {
            return response()->json([]);
        }

        $villaAreas = \App\Models\VillaArea::where('society_id', $societyId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($villaAreas);
    }

    /**
     * API endpoint to get villas for a villa area
     */
    public function getVillas(Request $request)
    {
        $villaAreaId = $request->get('villa_area_id');
        
        if (!$villaAreaId) {
            return response()->json([]);
        }

        $villas = \App\Models\Flat::where('villa_area_id', $villaAreaId)
            ->where('property_type', 'villa')
            ->select('id', 'villa_number')
            ->orderBy('villa_number')
            ->get();

        return response()->json($villas);
    }
}