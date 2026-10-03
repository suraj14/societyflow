<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\VillaArea;
use App\Models\Society;
use App\Models\User;
use App\Http\Controllers\Concerns\ResolvesPropertyOwner;
use App\Http\Controllers\Concerns\ResolvesSocietyPropertyType;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class VillaController extends BaseController
{
    use HandlesFormSubmissions, ResolvesPropertyOwner, ResolvesSocietyPropertyType;

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

        // Canonical occupancy (tenant / owner / vacant), shared with the
        // Property & Occupancy hub and the dashboard.
        $occupancy = \App\Support\PropertyOccupancy::statesFor($villas->getCollection());

        return view('villas.index', compact('villas', 'occupancy'));
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

        // Resolve the society before validating, so the villa area and owner
        // must belong to it rather than merely existing somewhere.
        $societyId = $this->resolveSocietyIdForWrite($request, $user);

        // Validation rules
        $rules = [
            'villa_area_id' => ['required', $this->existsInSociety('villa_areas', $societyId)],
            'owner_id' => ['nullable', $this->existsInSociety('owners', $societyId)],
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
            // Occupancy is derived from owner/tenant records (Flat model);
            // only "under maintenance" is a state a person chooses.
            'status' => 'nullable|in:vacant,occupied,on_rent,maintenance,under_maintenance',
            'description' => 'nullable|string',
        ];

        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        $validated = $request->validate($rules);
        $validated['status'] = FlatController::storedStatusFromRequest($validated['status'] ?? null);

        // Set society_id for non-Super Admin users
        if (!$user->hasRole('Super Admin')) {
            $validated['society_id'] = $user->society_id;
        }

        $validated['property_type'] = 'villa';
        $validated['has_garden'] = $request->boolean('has_garden');
        $validated['has_parking'] = $request->boolean('has_parking');

        $villa = Flat::create($validated);

        // Assign the Owner role if a login account exists for this owner.
        //
        // villa.owner_id is a foreign key to owners.id, never users.id (see
        // 4e35ffa and the identical fix below in update()) -
        // User::find($villa->owner_id) read an owners.id as a users.id and,
        // because the two id spaces overlap, could silently grant Owner
        // access to an unrelated person who happened to share that number.
        if ($villa->owner_id) {
            $owner = \App\Models\Owner::find($villa->owner_id);
            $ownerUser = $owner?->user;
            if ($ownerUser && !$ownerUser->hasRole('Owner')) {
                \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
                $ownerUser->assignRole('Owner');
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

        // Route-model binding resolves any flat id; nothing here checked the
        // villa belonged to the viewer's own society, so any Admin could
        // read another society's villa - including its residents, visitors
        // and complaints - just by guessing the id.
        $this->authorizeSocietyAccess($villa);

        $villa->load(['society', 'villaArea', 'ownerModel', 'residents', 'visitors', 'complaints', 'facilityBookings']);

        return view('villas.show', compact('villa'));
    }

    public function edit(Flat $villa)
    {
        if (!$villa->isVilla()) {
            abort(404);
        }

        $this->authorizeSocietyAccess($villa);

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

        $this->authorizeSocietyAccess($villa);

        $user = auth()->user();

        // The villa is already its own society's; the area and owner must match.
        $societyId = (int) $villa->society_id;

        // Validation rules
        $rules = [
            'villa_area_id' => ['required', $this->existsInSociety('villa_areas', $societyId)],
            'owner_id' => ['nullable', $this->existsInSociety('owners', $societyId)],
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
            // See store(): occupancy is derived, only maintenance is chosen.
            'status' => 'nullable|in:vacant,occupied,on_rent,maintenance,under_maintenance',
            'description' => 'nullable|string',
        ];

        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        $validated = $request->validate($rules);
        $validated['status'] = FlatController::storedStatusFromRequest($validated['status'] ?? null);

        // Set society_id for non-Super Admin users
        if (!$user->hasRole('Super Admin')) {
            $validated['society_id'] = $user->society_id;
        }

        $validated['has_garden'] = $request->boolean('has_garden');
        $validated['has_parking'] = $request->boolean('has_parking');

        // Handle owner change
        $oldOwnerId = $villa->owner_id;
        $villa->update($validated);

        // Assign Villa Owner role to new owner.
        //
        // villa.owner_id is a foreign key to owners.id, never users.id (see
        // 4e35ffa) - User::find($villa->owner_id) read an owners.id as a
        // users.id and, because the two id spaces overlap, could silently
        // grant "Villa Owner" to an unrelated person who happened to share
        // that number. The login account is reached through the Owner
        // record, which may not have one (villaOwnerUser can be null).
        if ($villa->owner_id && $villa->owner_id !== $oldOwnerId) {
            $owner = \App\Models\Owner::find($villa->owner_id);
            $ownerUser = $owner?->user;
            if ($ownerUser && !$ownerUser->hasRole('Owner')) {
                \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
                $ownerUser->assignRole('Owner');
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

        $this->authorizeSocietyAccess($villa);

        $villa->delete();

        return redirect()->route('villas.index')
            ->with('success', 'Villa deleted successfully.');
    }
}
