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

    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $societyId = $this->getSocietyId();
        
        // Get villa owners without assigned villas ('Owner' is canonical;
        // 'Villa Owner' kept for users not yet migrated)
        $unassignedVillaOwners = User::bySociety($societyId)
            ->role(['Owner', 'Villa Owner'])
            ->whereDoesntHave('ownedVilla')
            ->with('resident')
            ->get();

        // Get available villas (not assigned to any owner)
        $availableVillas = Flat::bySociety($societyId)
            ->villas()
            ->whereNull('owner_id')
            ->with('villaArea')
            ->get();

        // Get assigned villa owners
        $assignedVillaOwners = User::bySociety($societyId)
            ->role(['Owner', 'Villa Owner'])
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
        if (!$user->hasAnyRole(['Owner', 'Villa Owner'])) {
            return back()->with('error', 'User must have the Owner role to be assigned a villa.');
        }

        // Verify villa is available
        if ($villa->owner_id) {
            return back()->with('error', 'This villa is already assigned to another owner.');
        }

        // Verify villa belongs to the same society
        if ($villa->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Villa must belong to the same society.');
        }

        // Verify it is actually a villa. Without this the villa screen would
        // happily take an apartment's id and record it as a villa.
        if (!$villa->isVilla()) {
            return back()->with('error', 'Selected unit is not a villa.');
        }

        // Resolve the owner record. flats.owner_id is a foreign key to
        // owners.id, so writing $user->id here attributes the villa to
        // whichever owner happens to share that integer.
        $owner = $this->resolveOwnerFor($user);

        if (!$owner instanceof Owner) {
            return $owner; // a redirect carrying the reason
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
        $villa = $user->ownedVilla;
        
        if (!$villa) {
            return back()->with('error', 'User does not have an assigned villa.');
        }
        
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
        $currentVilla = $currentUser->ownedVilla;
        $newVilla = Flat::findOrFail($request->new_villa_id);
        
        if (!$currentVilla) {
            return back()->with('error', 'User does not have an assigned villa.');
        }
        
        if ($newVilla->owner_id) {
            return back()->with('error', 'The new villa is already assigned to another owner.');
        }

        // The destination gets the same scrutiny as a fresh assignment:
        // route-model ids are client input on a transfer too.
        if ($newVilla->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Villa must belong to the same society.');
        }

        if (!$newVilla->isVilla()) {
            return back()->with('error', 'Selected unit is not a villa.');
        }

        $owner = $this->resolveOwnerFor($currentUser);

        if (!$owner instanceof Owner) {
            return $owner;
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