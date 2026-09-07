<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Flat;
use App\Models\VillaArea;
use App\Models\Resident;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserVillaAssignmentController extends BaseController
{
    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $societyId = $this->getSocietyId();
        
        // Get villa owners without assigned villas
        $unassignedVillaOwners = User::bySociety($societyId)
            ->role('Villa Owner')
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
            ->role('Villa Owner')
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
        
        // Verify user is a villa owner
        if (!$user->hasRole('Villa Owner')) {
            return back()->with('error', 'User must have Villa Owner role to be assigned a villa.');
        }
        
        // Verify villa is available
        if ($villa->owner_id) {
            return back()->with('error', 'This villa is already assigned to another owner.');
        }
        
        // Verify villa belongs to the same society
        if ($villa->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Villa must belong to the same society.');
        }
        
        // Assign villa to user
        $villa->update([
            'owner_id' => $user->id,
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
        
        // Unassign current villa
        $currentVilla->update([
            'owner_id' => null,
            'status' => 'vacant'
        ]);
        
        // Assign new villa
        $newVilla->update([
            'owner_id' => $currentUser->id,
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