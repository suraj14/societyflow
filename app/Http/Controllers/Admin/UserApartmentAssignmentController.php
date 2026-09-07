<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Flat;
use App\Models\Building;
use App\Models\Resident;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserApartmentAssignmentController extends BaseController
{
    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $societyId = $this->getSocietyId();
        
        // Get apartment owners without assigned apartments
        $unassignedApartmentOwners = User::bySociety($societyId)
            ->role('Apartment Owner')
            ->whereDoesntHave('ownedFlat')
            ->with('resident')
            ->get();
        
        // Get available apartments (not assigned to any owner)
        $availableApartments = Flat::bySociety($societyId)
            ->apartments()
            ->whereNull('owner_id')
            ->with('building')
            ->get();
        
        // Get assigned apartment owners
        $assignedApartmentOwners = User::bySociety($societyId)
            ->role('Apartment Owner')
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
        
        // Verify user is an apartment owner
        if (!$user->hasRole('Apartment Owner')) {
            return back()->with('error', 'User must have Apartment Owner role to be assigned an apartment.');
        }
        
        // Verify apartment is available
        if ($apartment->owner_id) {
            return back()->with('error', 'This apartment is already assigned to another owner.');
        }
        
        // Verify apartment belongs to the same society
        if ($apartment->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Apartment must belong to the same society.');
        }
        
        // Verify it's actually an apartment
        if (!$apartment->isApartment()) {
            return back()->with('error', 'Selected unit is not an apartment.');
        }
        
        // Assign apartment to user
        $apartment->update([
            'owner_id' => $user->id,
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
        $apartment = $user->ownedFlat;
        
        if (!$apartment) {
            return back()->with('error', 'User does not have an assigned apartment.');
        }
        
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
        $currentApartment = $currentUser->ownedFlat;
        $newApartment = Flat::findOrFail($request->new_apartment_id);
        
        if (!$currentApartment) {
            return back()->with('error', 'User does not have an assigned apartment.');
        }
        
        if ($newApartment->owner_id) {
            return back()->with('error', 'The new apartment is already assigned to another owner.');
        }
        
        // Verify it's actually an apartment
        if (!$newApartment->isApartment()) {
            return back()->with('error', 'Selected unit is not an apartment.');
        }
        
        // Unassign current apartment
        $currentApartment->update([
            'owner_id' => null,
            'status' => 'vacant'
        ]);
        
        // Assign new apartment
        $newApartment->update([
            'owner_id' => $currentUser->id,
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