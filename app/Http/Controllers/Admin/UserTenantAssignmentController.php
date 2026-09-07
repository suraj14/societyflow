<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Flat;
use App\Models\Resident;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserTenantAssignmentController extends BaseController
{
    public function index()
    {
        // Only admin and super admin can access this
        $this->authorizeRole('Super Admin', 'Admin');
        
        $societyId = $this->getSocietyId();
        
        // Get tenants without assigned units
        $unassignedTenants = User::bySociety($societyId)
            ->role('Tenant')
            ->whereDoesntHave('residents', function($query) {
                $query->where('status', 'active');
            })
            ->get();
        
        // Get available units (not assigned to any tenant and not owned)
        $availableUnits = Flat::bySociety($societyId)
            ->where(function($query) {
                $query->whereNull('owner_id')
                      ->orWhere('status', 'vacant');
            })
            ->whereDoesntHave('residents', function($query) {
                $query->where('type', 'tenant')->where('status', 'active');
            })
            ->with(['building', 'villaArea'])
            ->get();
        
        // Get assigned tenants
        $assignedTenants = User::bySociety($societyId)
            ->role('Tenant')
            ->whereHas('residents', function($query) {
                $query->where('type', 'tenant')->where('status', 'active');
            })
            ->with(['residents' => function($query) {
                $query->where('type', 'tenant')->where('status', 'active')->with(['flat.building', 'flat.villaArea']);
            }])
            ->get()
            ->map(function($user) {
                // Get the active resident record
                $user->resident = $user->residents->first();
                return $user;
            });
        
        return view('admin.tenant-assignments.index', compact(
            'unassignedTenants',
            'availableUnits', 
            'assignedTenants'
        ));
    }
    
    public function assign(Request $request)
    {
        // Only admin and super admin can assign units
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'unit_id' => 'required|exists:flats,id',
        ]);
        
        $user = User::findOrFail($request->user_id);
        $unit = Flat::findOrFail($request->unit_id);
        
        // Verify user is a tenant
        if (!$user->hasRole('Tenant')) {
            return back()->with('error', 'User must have Tenant role to be assigned a unit.');
        }
        
        // Verify unit is available for tenant
        $existingTenant = $unit->residents()->where('type', 'tenant')->where('status', 'active')->first();
        if ($existingTenant) {
            return back()->with('error', 'This unit already has an active tenant.');
        }
        
        // Verify unit belongs to the same society
        if ($unit->society_id !== $this->getSocietyId()) {
            return back()->with('error', 'Unit must belong to the same society.');
        }
        
        // Check if tenant already has an active assignment
        $existingAssignment = $user->residents()->where('status', 'active')->first();
        if ($existingAssignment) {
            return back()->with('error', 'Tenant already has an active unit assignment.');
        }
        
        // Create resident record for tenant
        Resident::create([
            'user_id' => $user->id,
            'society_id' => $unit->society_id,
            'flat_id' => $unit->id,
            'type' => 'tenant',
            'move_in_date' => Carbon::now(),
            'status' => 'active',
        ]);
        
        // Update unit status
        $unit->update(['status' => 'occupied']);
        
        $unitName = $unit->flat_number ?? $unit->villa_name;
        return back()->with('success', "Unit {$unitName} has been assigned to tenant {$user->name} successfully!");
    }
    
    public function unassign(Request $request)
    {
        // Only admin and super admin can unassign units
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        
        $user = User::findOrFail($request->user_id);
        $resident = $user->residents()->where('status', 'active')->first();
        
        if (!$resident) {
            return back()->with('error', 'Tenant does not have an active unit assignment.');
        }
        
        $unit = $resident->flat;
        $unitName = $unit->flat_number ?? $unit->villa_name;
        
        // Update resident record
        $resident->update([
            'move_out_date' => Carbon::now(),
            'status' => 'inactive',
        ]);
        
        // Check if unit has other active residents
        $otherActiveResidents = $unit->residents()->where('status', 'active')->count();
        if ($otherActiveResidents == 0) {
            $unit->update(['status' => 'vacant']);
        }
        
        return back()->with('success', "Unit {$unitName} has been unassigned from tenant {$user->name} successfully!");
    }
    
    public function reassign(Request $request)
    {
        // Only admin and super admin can reassign units
        $this->authorizeRole('Super Admin', 'Admin');
        
        $request->validate([
            'current_user_id' => 'required|exists:users,id',
            'new_unit_id' => 'required|exists:flats,id',
        ]);
        
        $currentUser = User::findOrFail($request->current_user_id);
        $currentResident = $currentUser->residents()->where('status', 'active')->first();
        $newUnit = Flat::findOrFail($request->new_unit_id);
        
        if (!$currentResident) {
            return back()->with('error', 'Tenant does not have an active unit assignment.');
        }
        
        // Check if new unit has active tenant
        $existingTenant = $newUnit->residents()->where('type', 'tenant')->where('status', 'active')->first();
        if ($existingTenant) {
            return back()->with('error', 'The new unit already has an active tenant.');
        }
        
        $currentUnit = $currentResident->flat;
        $currentUnitName = $currentUnit->flat_number ?? $currentUnit->villa_name;
        $newUnitName = $newUnit->flat_number ?? $newUnit->villa_name;
        
        // Update current resident record to inactive
        $currentResident->update([
            'move_out_date' => Carbon::now(),
            'status' => 'inactive',
        ]);
        
        // Create new resident record
        Resident::create([
            'user_id' => $currentUser->id,
            'society_id' => $newUnit->society_id,
            'flat_id' => $newUnit->id,
            'type' => 'tenant',
            'move_in_date' => Carbon::now(),
            'status' => 'active',
        ]);
        
        // Update unit statuses
        $currentUnit->update(['status' => 'vacant']);
        $newUnit->update(['status' => 'occupied']);
        
        return back()->with('success', "Tenant reassigned successfully! {$currentUser->name} moved from {$currentUnitName} to {$newUnitName}.");
    }
}