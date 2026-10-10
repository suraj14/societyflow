<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Concerns\ResolvesPropertyOwner;
use App\Models\Owner;
use App\Models\User;
use App\Models\Flat;
use App\Models\Building;
use App\Models\Resident;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserApartmentAssignmentController extends BaseController
{
    use ResolvesPropertyOwner;

    /**
     * 'Owner' is canonical; 'Apartment Owner' is kept for users not yet
     * migrated. Matched through the roles relation, NOT Spatie's role()
     * scope, which throws RoleDoesNotExist (a 500 for the whole screen) when
     * any listed role is missing from the roles table.
     */
    private const OWNER_ROLES = ['Owner', 'Apartment Owner'];

    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');

        // Scope by default, exempt Super Admin explicitly (CLAUDE.md §3):
        // getSocietyId() is null for Super Admin and "WHERE society_id =
        // NULL" matched nothing, leaving them an empty screen.
        $societyId = $this->getSocietyId();

        $owners = fn () => User::query()
            ->when($societyId !== null, fn ($q) => $q->bySociety($societyId))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::OWNER_ROLES));

        // Get apartment owners without assigned apartments
        $unassignedApartmentOwners = $owners()
            ->whereDoesntHave('ownedFlat')
            ->with('resident')
            ->get();

        // Get available apartments (not assigned to any owner)
        $availableApartments = Flat::query()
            ->when($societyId !== null, fn ($q) => $q->bySociety($societyId))
            ->apartments()
            ->whereNull('owner_id')
            ->with('building')
            ->get();
        $this->annotateDeclaredOwners($availableApartments);

        // See UserVillaAssignmentController::index(): an owner whose own record
        // names an apartment is not "unassigned" just because owner_id is unset.
        foreach ($unassignedApartmentOwners as $ownerUser) {
            $declared = \App\Support\UserFlatResolver::ownedFlatIds($ownerUser);
            $ownerUser->setRelation('declaredUnit', $declared->isEmpty() ? null : Flat::apartments()->whereIn('id', $declared)->first());
        }

        // Get assigned apartment owners
        $assignedApartmentOwners = $owners()
            ->whereHas('ownedFlat')
            ->with(['ownedFlat.building', 'resident'])
            ->get();
        
        return view('admin.apartment-assignments.index', compact(
            'unassignedApartmentOwners',
            'availableApartments', 
            'assignedApartmentOwners'
        ));
    }
    
    public function assign(Request $request)
    {
        // Only admin and super admin can assign apartments
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'apartment_id' => 'required|exists:flats,id',
        ]);
        
        $user = User::findOrFail($request->user_id);
        $apartment = Flat::findOrFail($request->apartment_id);
        
        // Verify user is an owner
        if (!$user->hasAnyRole(self::OWNER_ROLES)) {
            return back()->with('error', 'User must have the Owner role to be assigned an apartment.');
        }

        // Verify apartment is available
        if ($apartment->owner_id) {
            return back()->with('error', 'This apartment is already assigned to another owner.');
        }

        // Verify apartment belongs to the acting admin's society. Super Admin
        // has none (getSocietyId() is null) so was refused every apartment;
        // the owner check below pins the owner to the apartment's society.
        $mySociety = $this->getSocietyId();
        if ($mySociety !== null && (int) $apartment->society_id !== (int) $mySociety) {
            return back()->with('error', 'Apartment must belong to the same society.');
        }
        
        // Verify it's actually an apartment
        if (!$apartment->isApartment()) {
            return back()->with('error', 'Selected unit is not an apartment.');
        }
        
        // Resolve the owner record. flats.owner_id is a foreign key to
        // owners.id, so writing $user->id here attributes the apartment to
        // whichever owner happens to share that integer. The owner must
        // belong to the APARTMENT's society.
        $owner = $this->resolveOwnerFor($user, (int) $apartment->society_id);

        if (!$owner instanceof Owner) {
            return $owner; // a redirect carrying the reason
        }

        if ($refusal = $this->refuseIfDeclaredToAnother($apartment, $owner, 'apartment')) {
            return $refusal;
        }

        // Assign apartment to user
        $apartment->update([
            'owner_id' => $owner->id,
            'status' => 'occupied'
        ]);
        
        // Create or update resident record
        Resident::updateOrCreate(
            ['user_id' => $user->id],
            [
                'society_id' => $apartment->society_id,
                'flat_id' => $apartment->id,
                'type' => 'owner',
                'move_in_date' => Carbon::now(),
                'status' => 'active',
            ]
        );
        
        return back()->with('success', "Apartment {$apartment->flat_number} has been assigned to {$user->name} successfully!");
    }
    
    public function unassign(Request $request)
    {
        // Only admin and super admin can unassign apartments
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        
        $user = User::findOrFail($request->user_id);

        // user_id is client input: without this an Admin of one society could
        // vacate (and close the residency of) an owner in ANOTHER society by
        // posting that owner's id. Super Admin is exempt.
        $this->authorizeSocietyAccess($user);

        $apartment = $user->ownedFlat;

        if (!$apartment) {
            return back()->with('error', 'User does not have an assigned apartment.');
        }

        $this->authorizeSocietyAccess($apartment);

        // Unassign apartment
        $apartment->update([
            'owner_id' => null,
            'status' => 'vacant'
        ]);
        
        // Update resident record
        $resident = Resident::where('user_id', $user->id)->first();
        if ($resident) {
            $resident->update([
                'move_out_date' => Carbon::now(),
                'status' => 'inactive',
            ]);
        }
        
        return back()->with('success', "Apartment {$apartment->flat_number} has been unassigned from {$user->name} successfully!");
    }
    
    public function reassign(Request $request)
    {
        // Only admin and super admin can reassign apartments
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'current_user_id' => 'required|exists:users,id',
            'new_apartment_id' => 'required|exists:flats,id',
        ]);
        
        $currentUser = User::findOrFail($request->current_user_id);
        $this->authorizeSocietyAccess($currentUser); // client-supplied id; see unassign()

        $currentApartment = $currentUser->ownedFlat;
        $newApartment = Flat::findOrFail($request->new_apartment_id);

        if (!$currentApartment) {
            return back()->with('error', 'User does not have an assigned apartment.');
        }

        $this->authorizeSocietyAccess($currentApartment);

        if ($newApartment->owner_id) {
            return back()->with('error', 'The new apartment is already assigned to another owner.');
        }
        
        // Verify it's actually an apartment
        if (!$newApartment->isApartment()) {
            return back()->with('error', 'Selected unit is not an apartment.');
        }

        // The destination gets the same society check as a fresh assignment;
        // a transfer carries a client-supplied id too. It must be in the
        // acting admin's society (Super Admin has none, so is exempt) and in
        // the SAME society as the apartment being vacated.
        $mySociety = $this->getSocietyId();
        if (($mySociety !== null && (int) $newApartment->society_id !== (int) $mySociety)
            || (int) $newApartment->society_id !== (int) $currentApartment->society_id) {
            return back()->with('error', 'Apartment must belong to the same society.');
        }

        $owner = $this->resolveOwnerFor($currentUser, (int) $newApartment->society_id);

        if (!$owner instanceof Owner) {
            return $owner;
        }

        if ($refusal = $this->refuseIfDeclaredToAnother($newApartment, $owner, 'apartment')) {
            return $refusal;
        }

        // Unassign current apartment
        $currentApartment->update([
            'owner_id' => null,
            'status' => 'vacant'
        ]);

        // Assign new apartment
        $newApartment->update([
            'owner_id' => $owner->id,
            'status' => 'occupied'
        ]);
        
        // Update resident record
        Resident::updateOrCreate(
            ['user_id' => $currentUser->id],
            [
                'society_id' => $newApartment->society_id,
                'flat_id' => $newApartment->id,
                'type' => 'owner',
                'move_in_date' => Carbon::now(),
                'status' => 'active',
            ]
        );
        
        return back()->with('success', "Apartment reassigned successfully! {$currentUser->name} moved from {$currentApartment->flat_number} to {$newApartment->flat_number}.");
    }
}