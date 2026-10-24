<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Owner;
use App\Models\Society;
use App\Models\Building;
use App\Models\VillaArea;
use App\Models\Flat;
use App\Http\Controllers\Concerns\ResolvesSocietyPropertyType;
use App\Traits\HandlesFormSubmissions;
use App\Traits\EnsuresDataConsistency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerController extends BaseController
{
    use HandlesFormSubmissions, EnsuresDataConsistency, ResolvesSocietyPropertyType;
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
            $societies = Society::where('status', 'active')->orderBy('name')->get();
            $buildings = Building::all();
            $villaAreas = VillaArea::all();
        } else {
            $societies = collect([$user->society])->filter();
            $buildings = Building::where('society_id', $user->society_id)->get();
            $villaAreas = VillaArea::where('society_id', $user->society_id)->get();
        }

        // The form must offer only the property types the society actually
        // contains. For a Super Admin the choice depends on which society
        // they pick, so ship the whole map and let the page switch on it.
        $propertyTypeOptions = $this->propertyTypeOptionsFor($user, $societies);
        $societyPropertyConfig = $societies->mapWithKeys(
            fn (Society $s) => [$s->id => $s->propertyConfig()]
        );

        return view('owners.create', compact(
            'societies', 'buildings', 'villaAreas', 'propertyTypeOptions', 'societyPropertyConfig'
        ));
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
            // Replaced below with a rule built from the society's own
            // configuration - see resolveSocietyIdForWrite().
            'property_type' => 'nullable|in:apartment,villa',
            'building_id' => 'nullable|exists:buildings,id',
            'floor' => 'nullable|integer',
            'flat_no' => 'nullable|string|max:50',
            'villa_area_id' => 'nullable|exists:villa_areas,id',
            'villa_no' => 'nullable|string|max:50',
            'document_path' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'profile_image' => \App\Support\ProfileImage::rules(),
        ];

        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        // Resolve the society BEFORE validating, so property_type can be
        // constrained to what that society actually contains. The old flat
        // `in:apartment,villa` let an apartment-only society be given a villa
        // owner from any client that bypassed the dropdown.
        $societyId = $this->resolveSocietyIdForWrite($request, $user);
        $rules['property_type'] = $this->propertyTypeRuleFor($societyId);

        $validated = $request->validate($rules);

        // Always the resolved value, never the raw request, so an Admin
        // cannot post another society's id.
        $validated['society_id'] = $societyId;

        // Handle document upload
        if ($request->hasFile('document_path')) {
            $file = $request->file('document_path');
            $path = $file->store('owners/documents', 'public');
            $validated['document_path'] = $path;
        }

        // Profile picture: stored once and shared by the owner record and the
        // owner's login account (previously owners.profile_image only, so the
        // owner's own app and header never showed it).
        unset($validated['profile_image']);
        if ($request->hasFile('profile_image')) {
            $validated['profile_image'] = \App\Support\ProfileImage::store($request->file('profile_image'));
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

        // The password is random and emailed to the owner by the UserCreated
        // listener; it is never echoed back to the admin's screen.
        $userPassword = \App\Helpers\TemporaryPassword::generate();

        // Owner record, login account and role assignment are one unit of
        // work. Without the transaction a failure at the role step (a missing
        // role raises RoleDoesNotExist) left an orphaned Owner plus a User
        // that could log in with no role and no permissions.
        try {
        $ownerUser = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $userPassword) {
            $owner = Owner::create($validated);

            $ownerUser = \App\Models\User::create([
                'society_id' => $validated['society_id'],
                'owner_id' => $owner->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => bcrypt($userPassword),
                'status' => $validated['status'],
                'avatar' => $validated['profile_image'] ?? null,
            ]);

            // Owner is the canonical business role now - property type
            // (apartment/villa) lives on the flat/owner record, not the
            // role, so every new owner gets the same role regardless.
            // firstOrCreate guards a DB that hasn't run
            // RolesAndPermissionsSeeder yet (fresh test DBs, an install
            // mid-migration) - assignRole('Owner') would otherwise throw
            // RoleDoesNotExist and 500 the whole request.
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
            $ownerUser->assignRole('Owner');

            return $ownerUser;
        });
        } catch (\Throwable $e) {
            // The rows rolled back, so the picture stored above is an orphan.
            \App\Support\ProfileImage::delete($validated['profile_image'] ?? null);

            throw $e;
        }

        // Dispatched after commit: a rolled-back create must not send a
        // welcome email carrying credentials for an account that no longer
        // exists (the queue runs sync, so this listener fires inline).
        \App\Events\UserCreated::dispatch($ownerUser, $userPassword);

        return redirect()->route('owners.index')
            ->with('success', 'Owner created successfully! ' . \App\Helpers\TemporaryPassword::noticeFor($validated['email']));
    }

    public function show(Owner $owner)
    {
        $this->authorizeSocietyAccess($owner);

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
        $this->authorizeSocietyAccess($owner);

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

        // The owner's society fixes the choices. If the record already holds a
        // type the society no longer supports (an admin narrowed it later),
        // keep that option visible and flagged rather than silently rewriting
        // the owner's property on the next save.
        $propertyTypeOptions = $this->propertyTypeOptionsForSociety($owner->society);
        $current = $owner->property_type;
        if ($current && !isset($propertyTypeOptions[$current])) {
            $label = PropertyType::tryFromMixed($current)?->label() ?? ucfirst($current);
            $propertyTypeOptions[$current] = $label . ' (no longer supported by this society)';
        }

        return view('owners.edit', compact(
            'owner', 'societies', 'buildings', 'villaAreas', 'propertyTypeOptions'
        ));
    }

    public function update(Request $request, Owner $owner)
    {
        $this->authorizeSocietyAccess($owner);

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
            // Constrained below to the owning society's configuration.
            'building_id' => 'nullable|exists:buildings,id',
            'floor' => 'nullable|integer',
            'flat_no' => 'nullable|string|max:50',
            'villa_area_id' => 'nullable|exists:villa_areas,id',
            'villa_no' => 'nullable|string|max:50',
            'document_path' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'profile_image' => \App\Support\ProfileImage::rules(),
        ];

        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        // An edit keeps the owner in its existing society - moving an owner
        // between societies is not an edit operation - so the rule is built
        // from the owner's own society.
        $rules['property_type'] = $this->propertyTypeRuleFor($owner->society_id);

        $validated = $request->validate($rules);

        $validated['society_id'] = $owner->society_id;

        // Handle document upload
        if ($request->hasFile('document_path')) {
            $file = $request->file('document_path');
            $path = $file->store('owners/documents', 'public');
            $validated['document_path'] = $path;
        }

        // Profile picture is applied below, after the record is saved, by
        // ProfileImage::change() - the uploaded file must not reach the
        // model's attribute array.
        unset($validated['profile_image']);

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

        // The building / villa area has to belong to the owner's own society: `exists:` above only proves the row exists.
        if (!empty($validated['building_id']) && !Building::where('id', $validated['building_id'])->where('society_id', $owner->society_id)->exists()) {
            throw ValidationException::withMessages(['building_id' => 'The selected building does not belong to this owner\'s society.']);
        }
        if (!empty($validated['villa_area_id']) && !VillaArea::where('id', $validated['villa_area_id'])->where('society_id', $owner->society_id)->exists()) {
            throw ValidationException::withMessages(['villa_area_id' => 'The selected villa area does not belong to this owner\'s society.']);
        }

        if (empty($validated['property_type'])) {
            // Clear property information if property_type is empty
            $validated['property_type'] = null;
            $validated['building_id'] = null;
            $validated['floor'] = null;
            $validated['flat_no'] = null;
            $validated['villa_area_id'] = null;
            $validated['villa_no'] = null;
        } elseif ($validated['property_type'] === $owner->property_type) {
            // The unit pickers are filled in by script. When they have not loaded (or the form was saved before they
            // did) the browser posts blanks, and a blank must not wipe the unit the owner already holds - that is how
            // a villa owner lost their villa. To remove a unit, change or empty the property type.
            foreach (['building_id', 'floor', 'flat_no', 'villa_area_id', 'villa_no'] as $field) {
                if (($validated[$field] ?? null) === null || $validated[$field] === '') {
                    $validated[$field] = $owner->{$field};
                }
            }
        }

        // Execute update with transaction and verification
        $this->executeWithVerification(function () use ($owner, $validated, $request) {
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

                // Owner is the canonical role - this used to clear all roles
                // and reassign Villa Owner/Apartment Owner by property type,
                // which would silently strip 'Owner' back to a legacy role
                // on every edit. Property type lives on the flat/owner
                // record now, not the role.
                \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
                $ownerUser->syncRoles(['Owner']);

                // Clear user cache
                $this->clearModelCache($ownerUser);
            }

            // New file replaces the picture on the owner AND the login
            // account; "remove" clears both; neither keeps the current one.
            \App\Support\ProfileImage::change(
                $owner,
                $request->file('profile_image'),
                $request->boolean('remove_profile_image') && !$request->hasFile('profile_image'),
            );

            Log::info('Owner updated successfully', [
                'owner_id' => $owner->id,
                'updated_by' => auth()->id(),
                'timestamp' => now()
            ]);
        }, 'owner_update');

        return redirect()->route('owners.index')
            ->with('success', 'Owner updated successfully!');
    }

    /**
     * Admin/Super Admin sets a new password for the Owner's login account.
     * Never reads or displays the existing password - only replaces it.
     * Renting the property never touches this account; this is the
     * separate, explicit path for when the owner's own password is lost.
     */
    public function resetPassword(Request $request, Owner $owner, \App\Services\AdminPasswordResetService $passwordReset)
    {
        $this->authorizeSocietyAccess($owner);

        $validated = $request->validate([
            'password' => \App\Support\PasswordPolicy::required(\App\Support\PasswordPolicy::forSociety((int) $owner->society_id)),
        ]);

        $ownerUser = $owner->user;

        if (!$ownerUser) {
            return back()->with('error', 'This owner has no login account to reset.');
        }

        $passwordReset->reset($ownerUser, $validated['password']);

        return back()->with('success', 'Password updated. The owner can log in with the new password immediately.');
    }

    public function destroy(Owner $owner)
    {
        $this->authorizeSocietyAccess($owner);

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
        $buildings = Building::where('society_id', $this->lookupSocietyId($request))->get(['id', 'name']);
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

        $floors = $this->lookupFlats()
            ->where('building_id', $buildingId)
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

        $flats = $this->lookupFlats()
            ->where('building_id', $buildingId)
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
        $villaAreas = VillaArea::where('society_id', $this->lookupSocietyId($request))->get(['id', 'name']);
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

        $villaNumbers = $this->lookupFlats()
            ->where('villa_area_id', $villaAreaId)
            ->where('property_type', 'villa')
            ->pluck('flat_number')
            ->sort()
            ->values();

        return response()->json($villaNumbers);
    }

    /**
     * The society a dropdown lookup may read. Only a Super Admin may name another society; everyone else gets their
     * own whatever `society_id` the request carries (the owner form sends it, but it is not to be trusted).
     */
    private function lookupSocietyId(Request $request): ?int
    {
        $user = auth()->user();

        if ($user->hasRole('Super Admin')) {
            $requested = $request->query('society_id');
            return is_numeric($requested) ? (int) $requested : $user->society_id;
        }

        return $user->society_id;
    }

    /** Flats the signed-in user may list: every society for a Super Admin, otherwise their own. */
    private function lookupFlats()
    {
        $query = Flat::query();

        if (!auth()->user()->hasRole('Super Admin')) {
            $query->where('society_id', auth()->user()->society_id);
        }

        return $query;
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

        // Another society's resident is not ours to convert (this had no society check at all).
        $this->authorizeSocietyAccess($resident);

        // Only convert if type is 'owner'
        if ($resident->type !== 'owner') {
            return back()->with('error', 'Only residents with type "owner" can be converted.');
        }

        // Conversion must never create a second identity or silently take over an existing link.
        if ($resident->user_id && $resident->user && $resident->user->owner_id) {
            return back()->with('error', 'This person already has an Owner record. Open it from Owner Management instead of creating another.');
        }
        if ($resident->flat && $resident->flat->owner_id) {
            return back()->with('error', 'This unit already has a recorded owner. Resolve that in Owner Management first; nothing was changed.');
        }
        // owners.email is unique across the whole system (not per society): checking only this society let
        // the insert throw a raw database error whenever the address was used anywhere else.
        if ($resident->email && Owner::where('email', $resident->email)->exists()) {
            return back()->with('error', 'An Owner record already uses this email address, so a second one cannot be created. Link the existing record instead.');
        }

        // All or nothing: a half-converted resident (owner created, flat not linked, resident gone) is
        // worse than no conversion.
        $owner = \Illuminate\Support\Facades\DB::transaction(function () use ($resident) {
            $owner = Owner::create([
                'society_id' => $resident->society_id,
                'name' => $resident->name,
                'email' => $resident->email,
                'phone' => $resident->phone,
                'status' => $resident->status,
            ]);

            if ($resident->flat) {
                $resident->flat->update(['owner_id' => $owner->id]);
            }

            if ($resident->user_id && $resident->user) {
                $resident->user->update(['owner_id' => $owner->id]);
            }

            $resident->delete();

            return $owner;
        });

        return redirect()->route('owners.show', $owner)
            ->with('success', 'Resident successfully converted to Owner!');
    }
}
