<?php

namespace App\Http\Controllers;

use App\Models\Owner;
use App\Models\Society;
use App\Models\Building;
use App\Models\VillaArea;
use App\Models\Flat;
use App\Traits\HandlesFormSubmissions;
use App\Traits\EnsuresDataConsistency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OwnerController extends BaseController
{
    use HandlesFormSubmissions, EnsuresDataConsistency;
    public function index(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // If user has no society_id, deny access (except Super Admin)
        if (!$societyId && !$user->hasRole('Super Admin')) {
            abort(403, 'Access denied: No society assigned.');
        }

        $query = Owner::with('society');

        // Apply society filter (Super Admin sees all, others see only their society)
        if ($societyId) {
            $query->where('society_id', $societyId);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $owners = $query->latest()->paginate(15);

        return view('owners.index', compact('owners'));
    }

    public function create()
    {
        $user = auth()->user();

        // If user is Super Admin, show all societies; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
            $buildings = Building::all();
            $villaAreas = VillaArea::all();
        } else {
            $societies = collect([$user->society]);
            $buildings = Building::where('society_id', $user->society_id)->get();
            $villaAreas = VillaArea::where('society_id', $user->society_id)->get();
        }

        return view('owners.create', compact('societies', 'buildings', 'villaAreas'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:owners,email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'id_type' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string',
            'property_type' => 'nullable|in:apartment,villa',
            'building_id' => 'nullable|exists:buildings,id',
            'floor' => 'nullable|integer',
            'flat_no' => 'nullable|string|max:50',
            'villa_area_id' => 'nullable|exists:villa_areas,id',
            'villa_no' => 'nullable|string|max:50',
            'document_path' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'profile_image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ];

        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        $validated = $request->validate($rules);

        // Set society_id for non-Super Admin users
        if (!$user->hasRole('Super Admin')) {
            $validated['society_id'] = $user->society_id;
        }

        // Handle document upload
        if ($request->hasFile('document_path')) {
            $file = $request->file('document_path');
            $path = $file->store('owners/documents', 'public');
            $validated['document_path'] = $path;
        }

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $path = $file->store('owners', 'public');
            $validated['profile_image'] = $path;
        }

        // Handle family members
        if ($request->has('family_members')) {
            $familyMembers = [];
            foreach ($request->input('family_members', []) as $member) {
                if (!empty($member['name']) || !empty($member['relationship']) || !empty($member['phone'])) {
                    $familyMembers[] = [
                        'name' => $member['name'] ?? '',
                        'relationship' => $member['relationship'] ?? '',
                        'phone' => $member['phone'] ?? '',
                    ];
                }
            }
            $validated['family_members'] = $familyMembers;
        }

        // Create the owner record
        $owner = Owner::create($validated);

        // Create corresponding user record for login
        $userPassword = 'password'; // Default password
        $ownerUser = \App\Models\User::create([
            'society_id' => $validated['society_id'],
            'owner_id' => $owner->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => bcrypt($userPassword),
            'status' => $validated['status'],
        ]);

        // Assign appropriate role based on property type
        if ($validated['property_type'] === 'apartment') {
            $ownerUser->assignRole('Apartment Owner');
        } elseif ($validated['property_type'] === 'villa') {
            $ownerUser->assignRole('Villa Owner');
        } else {
            // If no property type specified, assign both roles for flexibility
            $ownerUser->assignRole(['Apartment Owner', 'Villa Owner']);
        }

        return redirect()->route('owners.index')
            ->with('success', 'Owner created successfully! Login credentials - Email: ' . $validated['email'] . ', Password: ' . $userPassword);
    }

    public function show(Owner $owner)
    {
        $owner->load(['society', 'flats', 'building', 'villaArea']);

        // Fetch flat data for displaying property details
        $apartmentFlat = null;
        $villaFlat = null;

        if ($owner->property_type === 'apartment' && $owner->building_id && $owner->flat_no) {
            $apartmentFlat = Flat::where('building_id', $owner->building_id)
                ->where('flat_number', $owner->flat_no)
                ->first();
        } elseif ($owner->property_type === 'villa' && $owner->villa_area_id && $owner->villa_no) {
            $villaFlat = Flat::where('villa_area_id', $owner->villa_area_id)
                ->where('flat_number', $owner->villa_no)
                ->first();
        }

        $stats = [
            'total_properties' => $owner->getPropertiesCount(),
            'occupied_properties' => $owner->getOccupiedPropertiesCount(),
            'vacant_properties' => $owner->getVacantPropertiesCount(),
        ];

        return view('owners.show', compact('owner', 'stats', 'apartmentFlat', 'villaFlat'));
    }

    public function edit(Owner $owner)
    {
        $user = auth()->user();

        // If user is Super Admin, show all societies; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
            $buildings = Building::all();
            $villaAreas = VillaArea::all();
        } else {
            $societies = collect([$user->society]);
            $buildings = Building::where('society_id', $user->society_id)->get();
            $villaAreas = VillaArea::where('society_id', $user->society_id)->get();
        }

        return view('owners.edit', compact('owner', 'societies', 'buildings', 'villaAreas'));
    }

    public function update(Request $request, Owner $owner)
    {
        $user = auth()->user();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:owners,email,' . $owner->id,
            'phone' => 'nullable|string|max:20',
            'id_type' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string',
            'property_type' => 'nullable|in:apartment,villa',
            'building_id' => 'nullable|exists:buildings,id',
            'floor' => 'nullable|integer',
            'flat_no' => 'nullable|string|max:50',
            'villa_area_id' => 'nullable|exists:villa_areas,id',
            'villa_no' => 'nullable|string|max:50',
            'document_path' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'profile_image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ];

        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        $validated = $request->validate($rules);

        // Set society_id for non-Super Admin users
        if (!$user->hasRole('Super Admin')) {
            $validated['society_id'] = $user->society_id;
        }

        // Handle document upload
        if ($request->hasFile('document_path')) {
            $file = $request->file('document_path');
            $path = $file->store('owners/documents', 'public');
            $validated['document_path'] = $path;
        }

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $path = $file->store('owners', 'public');
            $validated['profile_image'] = $path;
        }

        // Handle family members
        if ($request->has('family_members')) {
            $familyMembers = [];
            foreach ($request->input('family_members', []) as $member) {
                if (!empty($member['name']) || !empty($member['relationship']) || !empty($member['phone'])) {
                    $familyMembers[] = [
                        'name' => $member['name'] ?? '',
                        'relationship' => $member['relationship'] ?? '',
                        'phone' => $member['phone'] ?? '',
                    ];
                }
            }
            $validated['family_members'] = $familyMembers;
        }

        // Clear property information if property_type is empty
        if (empty($validated['property_type'])) {
            $validated['property_type'] = null;
            $validated['building_id'] = null;
            $validated['floor'] = null;
            $validated['flat_no'] = null;
            $validated['villa_area_id'] = null;
            $validated['villa_no'] = null;
        }

        // Execute update with transaction and verification
        $this->executeWithVerification(function () use ($owner, $validated) {
            // Update owner with data consistency verification
            $this->ensureModelPersisted($owner, $validated);
            
            // Clear model cache
            $this->clearModelCache($owner);
            
            // Update corresponding user record if it exists
            $ownerUser = \App\Models\User::where('owner_id', $owner->id)->first();
            if ($ownerUser) {
                // Update user basic info
                $ownerUser->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'status' => $validated['status'],
                ]);

                // Update user roles based on property type
                $ownerUser->syncRoles([]); // Clear existing roles
                
                if ($validated['property_type'] === 'apartment') {
                    $ownerUser->assignRole('Apartment Owner');
                } elseif ($validated['property_type'] === 'villa') {
                    $ownerUser->assignRole('Villa Owner');
                } else {
                    // If no property type specified, assign both roles for flexibility
                    $ownerUser->assignRole(['Apartment Owner', 'Villa Owner']);
                }
                
                // Clear user cache
                $this->clearModelCache($ownerUser);
            }
            
            Log::info('Owner updated successfully', [
                'owner_id' => $owner->id,
                'updated_by' => auth()->id(),
                'timestamp' => now()
            ]);
        }, 'owner_update');

        return redirect()->route('owners.index')
            ->with('success', 'Owner updated successfully!');
    }

    public function destroy(Owner $owner)
    {
        // Check if owner has any assigned properties in flats table
        $assignedProperties = $owner->flats()->count();
        if ($assignedProperties > 0) {
            return back()->with('error', "Cannot delete owner with {$assignedProperties} assigned properties! Please remove property assignments first.");
        }

        // Check if owner has property information in owners table
        if ($owner->property_type !== null) {
            return back()->with('error', 'Cannot delete owner with property information! Please edit the owner and select "Select Property Type" to clear property information, then save.');
        }

        // Delete associated user record if it exists
        $ownerUser = \App\Models\User::where('owner_id', $owner->id)->first();
        if ($ownerUser) {
            $ownerUser->delete();
        }

        $owner->delete();

        return redirect()->route('owners.index')
            ->with('success', 'Owner deleted successfully!');
    }

    /**
     * Get buildings for a society (API endpoint)
     */
    public function getBuildings(Request $request)
    {
        $societyId = $request->query('society_id') ?? auth()->user()->society_id;
        $buildings = Building::where('society_id', $societyId)->get(['id', 'name']);
        return response()->json($buildings);
    }

    /**
     * Get floors for a building (API endpoint)
     */
    public function getFloors(Request $request)
    {
        $buildingId = $request->query('building_id');
        if (!$buildingId) {
            return response()->json([]);
        }
        
        $floors = Flat::where('building_id', $buildingId)
            ->distinct()
            ->pluck('floor')
            ->sort()
            ->values();
        
        return response()->json($floors);
    }

    /**
     * Get flats for a building and floor (API endpoint)
     */
    public function getFlats(Request $request)
    {
        $buildingId = $request->query('building_id');
        $floor = $request->query('floor');
        
        if (!$buildingId || $floor === null) {
            return response()->json([]);
        }
        
        $flats = Flat::where('building_id', $buildingId)
            ->where('floor', $floor)
            ->pluck('flat_number')
            ->sort()
            ->values();
        
        return response()->json($flats);
    }

    /**
     * Get villa areas for a society (API endpoint)
     */
    public function getVillaAreas(Request $request)
    {
        $societyId = $request->query('society_id') ?? auth()->user()->society_id;
        $villaAreas = VillaArea::where('society_id', $societyId)->get(['id', 'name']);
        return response()->json($villaAreas);
    }

    /**
     * Get villa numbers for a villa area (API endpoint)
     */
    public function getVillaNumbers(Request $request)
    {
        $villaAreaId = $request->query('villa_area_id');
        if (!$villaAreaId) {
            return response()->json([]);
        }
        
        $villaNumbers = Flat::where('villa_area_id', $villaAreaId)
            ->where('property_type', 'villa')
            ->pluck('flat_number')
            ->sort()
            ->values();
        
        return response()->json($villaNumbers);
    }

    /**
     * Convert a Resident to Owner (one-click action)
     */
    public function convertFromResident(Request $request)
    {
        $validated = $request->validate([
            'resident_id' => 'required|exists:residents,id',
        ]);

        $resident = \App\Models\Resident::findOrFail($validated['resident_id']);

        // Only convert if type is 'owner'
        if ($resident->type !== 'owner') {
            return back()->with('error', 'Only residents with type "owner" can be converted.');
        }

        // Create Owner from Resident
        $owner = Owner::create([
            'society_id' => $resident->society_id,
            'name' => $resident->name,
            'email' => $resident->email,
            'phone' => $resident->phone,
            'status' => $resident->status,
        ]);

        // Update flat owner_id
        if ($resident->flat_id) {
            $resident->flat->update(['owner_id' => $owner->id]);
        }

        // Link user to owner if exists
        if ($resident->user_id) {
            $resident->user->update(['owner_id' => $owner->id]);
        }

        // Delete resident record
        $resident->delete();

        return redirect()->route('owners.show', $owner)
            ->with('success', 'Resident successfully converted to Owner!');
    }
}
