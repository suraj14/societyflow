<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Concerns\ResolvesPropertyOwner;
use App\Models\Owner;
use App\Models\User;
use App\Models\Flat;
use App\Models\VillaArea;
use App\Models\Resident;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserVillaAssignmentController extends BaseController
{
    use ResolvesPropertyOwner;

    /**
     * 'Owner' is canonical; 'Villa Owner' is kept for users not yet migrated.
     * Matched through the roles relation, NOT Spatie's role() scope: that
     * scope throws RoleDoesNotExist when any listed role is missing from the
     * roles table, which took the whole screen down (500) on any database
     * that has no legacy 'Villa Owner' role.
     */
    private const OWNER_ROLES = ['Owner', 'Villa Owner'];

    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');

        // Scope by default, exempt Super Admin explicitly (CLAUDE.md §3).
        // getSocietyId() is null for Super Admin, and "WHERE society_id =
        // NULL" matched nothing, so Super Admin used to see an empty screen.
        $societyId = $this->getSocietyId();

        $owners = fn () => User::query()
            ->when($societyId !== null, fn ($q) => $q->bySociety($societyId))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::OWNER_ROLES));

        // Get villa owners without assigned villas
        $unassignedVillaOwners = $owners()
            ->whereDoesntHave('ownedVilla')
            ->with('resident')
            ->get();

        // Get available villas (not assigned to any owner)
        $availableVillas = Flat::query()
            ->when($societyId !== null, fn ($q) => $q->bySociety($societyId))
            ->villas()
            ->whereNull('owner_id')
            ->with('villaArea')
            ->get();
        $this->annotateDeclaredOwners($availableVillas);

        // An owner whose own record names a villa is not "unassigned" even
        // though flats.owner_id was never filled in: show which villa, so the
        // admin can link it deliberately instead of handing out another one.
        foreach ($unassignedVillaOwners as $owner) {
            $declared = \App\Support\UserFlatResolver::ownedFlatIds($owner);
            $owner->setRelation('declaredUnit', $declared->isEmpty() ? null : Flat::villas()->whereIn('id', $declared)->first());
        }

        // Get assigned villa owners
        $assignedVillaOwners = $owners()
            ->whereHas('ownedVilla')
            ->with(['ownedVilla.villaArea', 'resident'])
            ->get();
        
        return view('admin.villa-assignments.index', compact(
            'unassignedVillaOwners',
            'availableVillas', 
            'assignedVillaOwners'
        ));
    }
    
    public function assign(Request $request)
    {
        // Only admin and super admin can assign villas
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'villa_id' => 'required|exists:flats,id',
        ]);
        
        $user = User::findOrFail($request->user_id);
        $villa = Flat::findOrFail($request->villa_id);

        // Verify user is an owner
        if (!$user->hasAnyRole(self::OWNER_ROLES)) {
            return back()->with('error', 'User must have the Owner role to be assigned a villa.');
        }

        // Verify villa is available
        if ($villa->owner_id) {
            return back()->with('error', 'This villa is already assigned to another owner.');
        }

        // Verify villa belongs to the acting admin's society. Super Admin has
        // no society of their own (getSocietyId() is null), so for them this
        // used to refuse every villa; they may act on any society - the
        // owner-side check below then pins the owner to the villa's society.
        $mySociety = $this->getSocietyId();
        if ($mySociety !== null && (int) $villa->society_id !== (int) $mySociety) {
            return back()->with('error', 'Villa must belong to the same society.');
        }

        // Verify it is actually a villa. Without this the villa screen would
        // happily take an apartment's id and record it as a villa.
        if (!$villa->isVilla()) {
            return back()->with('error', 'Selected unit is not a villa.');
        }

        // Resolve the owner record. flats.owner_id is a foreign key to
        // owners.id, so writing $user->id here attributes the villa to
        // whichever owner happens to share that integer. The owner must
        // belong to the VILLA's society.
        $owner = $this->resolveOwnerFor($user, (int) $villa->society_id);

        if (!$owner instanceof Owner) {
            return $owner; // a redirect carrying the reason
        }

        if ($refusal = $this->refuseIfDeclaredToAnother($villa, $owner, 'villa')) {
            return $refusal;
        }

        // Assign villa to user
        $villa->update([
            'owner_id' => $owner->id,
            'status' => 'occupied'
        ]);
        
        // Create or update resident record
        Resident::updateOrCreate(
            ['user_id' => $user->id],
            [
                'society_id' => $villa->society_id,
                'flat_id' => $villa->id,
                'type' => 'owner',
                'move_in_date' => Carbon::now(),
                'status' => 'active',
            ]
        );
        
        return back()->with('success', "Villa {$villa->villa_name} has been assigned to {$user->name} successfully!");
    }
    
    public function unassign(Request $request)
    {
        // Only admin and super admin can unassign villas
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        
        $user = User::findOrFail($request->user_id);

        // user_id is client input: without this an Admin of one society could
        // free (and close the residency of) an owner in ANOTHER society just
        // by posting that owner's id. Super Admin is exempt, everyone else is
        // pinned to their own society.
        $this->authorizeSocietyAccess($user);

        $villa = $user->ownedVilla;

        if (!$villa) {
            return back()->with('error', 'User does not have an assigned villa.');
        }

        $this->authorizeSocietyAccess($villa);

        // Unassign villa
        $villa->update([
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
        
        return back()->with('success', "Villa {$villa->villa_name} has been unassigned from {$user->name} successfully!");
    }
    
    public function reassign(Request $request)
    {
        // Only admin and super admin can reassign villas
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'current_user_id' => 'required|exists:users,id',
            'new_villa_id' => 'required|exists:flats,id',
        ]);
        
        $currentUser = User::findOrFail($request->current_user_id);
        $this->authorizeSocietyAccess($currentUser); // client-supplied id; see unassign()

        $currentVilla = $currentUser->ownedVilla;
        $newVilla = Flat::findOrFail($request->new_villa_id);

        if (!$currentVilla) {
            return back()->with('error', 'User does not have an assigned villa.');
        }

        $this->authorizeSocietyAccess($currentVilla);

        if ($newVilla->owner_id) {
            return back()->with('error', 'The new villa is already assigned to another owner.');
        }

        // The destination gets the same scrutiny as a fresh assignment:
        // route-model ids are client input on a transfer too. It must be in
        // the acting admin's society (Super Admin has none, so is exempt) and
        // in the SAME society as the villa being vacated - an owner is never
        // moved between societies.
        $mySociety = $this->getSocietyId();
        if (($mySociety !== null && (int) $newVilla->society_id !== (int) $mySociety)
            || (int) $newVilla->society_id !== (int) $currentVilla->society_id) {
            return back()->with('error', 'Villa must belong to the same society.');
        }

        if (!$newVilla->isVilla()) {
            return back()->with('error', 'Selected unit is not a villa.');
        }

        $owner = $this->resolveOwnerFor($currentUser, (int) $newVilla->society_id);

        if (!$owner instanceof Owner) {
            return $owner;
        }

        if ($refusal = $this->refuseIfDeclaredToAnother($newVilla, $owner, 'villa')) {
            return $refusal;
        }

        // Unassign current villa
        $currentVilla->update([
            'owner_id' => null,
            'status' => 'vacant'
        ]);

        // Assign new villa
        $newVilla->update([
            'owner_id' => $owner->id,
            'status' => 'occupied'
        ]);
        
        // Update resident record
        Resident::updateOrCreate(
            ['user_id' => $currentUser->id],
            [
                'society_id' => $newVilla->society_id,
                'flat_id' => $newVilla->id,
                'type' => 'owner',
                'move_in_date' => Carbon::now(),
                'status' => 'active',
            ]
        );
        
        return back()->with('success', "Villa reassigned successfully! {$currentUser->name} moved from {$currentVilla->villa_name} to {$newVilla->villa_name}.");
    }
}