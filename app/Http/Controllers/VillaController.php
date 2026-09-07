<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\VillaArea;
use App\Models\Society;
use App\Models\User;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class VillaController extends BaseController
{
    use HandlesFormSubmissions;
    public function index()
    {
        // Get user's society_id for data isolation
        $societyId = auth()->user()->society_id;
        
        // If user has no society_id, deny access (except Super Admin)
        if (!$societyId && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: No society assigned.');
        }

        $query = Flat::villas()
            ->with(['society', 'villaArea', 'ownerModel']);
            
        // Apply society filter (Super Admin sees all, others see only their society)
        if ($societyId) {
            $query->where('society_id', $societyId);
        }

        $villas = $query->latest()->paginate(10);

        return view('villas.index', compact('villas'));
    }

    public function create()
    {
        $user = auth()->user();
        
        // If user is Super Admin, show all societies; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::active()->get();
            $villaAreas = VillaArea::active()->get();
        } else {
            // For Society Admin, only show their own society and villa areas
            $societies = collect([$user->society]);
            $villaAreas = VillaArea::active()->where('society_id', $user->society_id)->get();
        }
        
        // No need to load owners for create form since owner dropdown is hidden
        $owners = collect(); // Empty collection for consistency
        
        return view('villas.create', compact('societies', 'villaAreas', 'owners'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Validation rules
        $rules = [
            'villa_area_id' => 'required|exists:villa_areas,id',
            'owner_id' => 'nullable|exists:owners,id',
            'flat_number' => 'required|string|max:50',
            'villa_name' => 'nullable|string|max:255',
            'plot_area' => 'nullable|numeric|min:0',
            'built_up_area' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'has_garden' => 'boolean',
            'has_parking' => 'boolean',
            'parking_slots' => 'nullable|integer|min:0',
            'maintenance_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:vacant,occupied,on_rent,under_maintenance',
            'description' => 'nullable|string',
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

        $validated['property_type'] = 'villa';
        $validated['has_garden'] = $request->boolean('has_garden');
        $validated['has_parking'] = $request->boolean('has_parking');

        $villa = Flat::create($validated);

        // Assign Villa Owner role if owner is set
        if ($villa->owner_id) {
            $owner = User::find($villa->owner_id);
            if ($owner && !$owner->hasRole('Villa Owner')) {
                $owner->assignRole('Villa Owner');
            }
        }

        return redirect()->route('villas.index')
            ->with('success', 'Villa created successfully.');
    }

    public function show(Flat $villa)
    {
        if (!$villa->isVilla()) {
            abort(404);
        }

        $villa->load(['society', 'villaArea', 'ownerUser', 'residents', 'visitors', 'complaints', 'facilityBookings']);
        
        return view('villas.show', compact('villa'));
    }

    public function edit(Flat $villa)
    {
        if (!$villa->isVilla()) {
            abort(404);
        }

        $user = auth()->user();
        
        // If user is Super Admin, show all societies; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::active()->get();
            $villaAreas = VillaArea::active()->get();
        } else {
            // For Society Admin, only show their own society and villa areas
            $societies = collect([$user->society]);
            $villaAreas = VillaArea::active()->where('society_id', $user->society_id)->get();
        }
        
        // No need to load owners since owner dropdown is hidden
        $owners = collect(); // Empty collection for consistency
        
        return view('villas.edit', compact('villa', 'societies', 'villaAreas', 'owners'));
    }

    public function update(Request $request, Flat $villa)
    {
        if (!$villa->isVilla()) {
            abort(404);
        }

        $user = auth()->user();
        
        // Validation rules
        $rules = [
            'villa_area_id' => 'required|exists:villa_areas,id',
            'owner_id' => 'nullable|exists:owners,id',
            'flat_number' => 'required|string|max:50',
            'villa_name' => 'nullable|string|max:255',
            'plot_area' => 'nullable|numeric|min:0',
            'built_up_area' => 'nullable|numeric|min:0',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'has_garden' => 'boolean',
            'has_parking' => 'boolean',
            'parking_slots' => 'nullable|integer|min:0',
            'maintenance_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:vacant,occupied,on_rent,under_maintenance',
            'description' => 'nullable|string',
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

        $validated['has_garden'] = $request->boolean('has_garden');
        $validated['has_parking'] = $request->boolean('has_parking');

        // Handle owner change
        $oldOwnerId = $villa->owner_id;
        $villa->update($validated);

        // Assign Villa Owner role to new owner
        if ($villa->owner_id && $villa->owner_id !== $oldOwnerId) {
            $owner = User::find($villa->owner_id);
            if ($owner && !$owner->hasRole('Villa Owner')) {
                $owner->assignRole('Villa Owner');
            }
        }

        return redirect()->route('villas.index')
            ->with('success', 'Villa updated successfully.');
    }

    public function destroy(Flat $villa)
    {
        if (!$villa->isVilla()) {
            abort(404);
        }

        $villa->delete();

        return redirect()->route('villas.index')
            ->with('success', 'Villa deleted successfully.');
    }
}
