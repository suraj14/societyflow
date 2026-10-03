<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Flat;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Occupancy management for tenants already on record (Tenant Management
 * creates them). This screen used to operate on a completely different
 * table - Resident (type='tenant') - than Tenant Management, which reads
 * and writes tenants.flat_id directly. The two were never synchronized:
 * assigning a unit here created a Resident row and left the matching
 * Tenant row's flat_id untouched, so Tenant Management kept showing
 * "Not Assigned" for a tenant this screen showed as "Assigned", and
 * Property & Occupancy's "On Rent" count (which reads flats.status =
 * 'on_rent') stayed at zero because this screen set flats.status to
 * 'occupied' instead. Both screens now read and write the same
 * `tenants` table and the same 'on_rent' status value.
 */
class UserTenantAssignmentController extends BaseController
{
    public function index()
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $societyId = $this->getSocietyId();

        $unassignedTenants = Tenant::bySociety($societyId)
            ->where('status', 'active')
            ->whereNull('flat_id')
            ->get();

        // Same eligibility rule as Tenant Management's own "create" screen:
        // any unit with no active tenant (owner-occupied units may be rented
        // out; the stored status is a derived cache, not a gate).
        $availableUnits = Flat::bySociety($societyId)
            ->whereDoesntHave('tenants', function ($query) {
                $query->where('status', 'active');
            })
            ->with(['building', 'villaArea'])
            ->orderBy('property_type')
            ->orderBy('flat_number')
            ->get();

        $assignedTenants = Tenant::bySociety($societyId)
            ->where('status', 'active')
            ->whereNotNull('flat_id')
            ->with(['flat.building', 'flat.villaArea'])
            ->get();

        return view('admin.tenant-assignments.index', compact(
            'unassignedTenants',
            'availableUnits',
            'assignedTenants'
        ));
    }

    public function assign(Request $request)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'unit_id' => 'required|exists:flats,id',
        ]);

        $societyId = $this->getSocietyId();
        $tenant = Tenant::findOrFail($request->tenant_id);
        $unit = Flat::findOrFail($request->unit_id);

        if ((int) $tenant->society_id !== (int) $societyId || (int) $unit->society_id !== (int) $societyId) {
            return back()->with('error', 'Tenant and unit must belong to your society.');
        }

        if ($tenant->flat_id) {
            return back()->with('error', 'This tenant already has an active unit assignment.');
        }

        $existingTenant = $unit->tenants()->where('status', 'active')->first();
        if ($existingTenant) {
            return back()->with('error', "This unit is currently occupied by {$existingTenant->name}.");
        }

        $tenant->update([
            'flat_id' => $unit->id,
            'move_in_date' => $tenant->move_in_date ?? Carbon::now(),
            'move_out_date' => null,
        ]);
        $unit->update(['status' => 'on_rent']);

        $unitName = $unit->flat_number ?? $unit->villa_name;
        return back()->with('success', "Unit {$unitName} has been assigned to tenant {$tenant->name} successfully!");
    }

    public function unassign(Request $request)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $tenant = Tenant::findOrFail($request->tenant_id);

        if ((int) $tenant->society_id !== (int) $this->getSocietyId()) {
            return back()->with('error', 'Tenant not found.');
        }

        if (!$tenant->flat_id) {
            return back()->with('error', 'Tenant does not have an active unit assignment.');
        }

        $unit = $tenant->flat;
        $unitName = $unit?->flat_number ?? $unit?->villa_name;

        // Move-out: close the occupancy but keep the tenant and every past
        // rent/payment record tied to them - only the current unit link is
        // cleared, nothing is deleted.
        $tenant->update([
            'flat_id' => null,
            'move_out_date' => Carbon::now(),
        ]);

        if ($unit && $unit->tenants()->where('status', 'active')->count() === 0) {
            $unit->update(['status' => 'vacant']);
        }

        return back()->with('success', "Unit {$unitName} has been unassigned from tenant {$tenant->name} successfully!");
    }

    public function reassign(Request $request)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $request->validate([
            'current_tenant_id' => 'required|exists:tenants,id',
            'new_unit_id' => 'required|exists:flats,id',
        ]);

        $societyId = $this->getSocietyId();
        $tenant = Tenant::findOrFail($request->current_tenant_id);
        $newUnit = Flat::findOrFail($request->new_unit_id);

        if ((int) $tenant->society_id !== (int) $societyId || (int) $newUnit->society_id !== (int) $societyId) {
            return back()->with('error', 'Tenant and unit must belong to your society.');
        }

        if (!$tenant->flat_id) {
            return back()->with('error', 'Tenant does not have an active unit assignment.');
        }

        $existingTenant = $newUnit->tenants()->where('status', 'active')->where('id', '!=', $tenant->id)->first();
        if ($existingTenant) {
            return back()->with('error', "The new unit is currently occupied by {$existingTenant->name}.");
        }

        $currentUnit = $tenant->flat;
        $currentUnitName = $currentUnit?->flat_number ?? $currentUnit?->villa_name;
        $newUnitName = $newUnit->flat_number ?? $newUnit->villa_name;

        $tenant->update(['flat_id' => $newUnit->id]);
        $newUnit->update(['status' => 'on_rent']);

        if ($currentUnit && $currentUnit->id !== $newUnit->id
            && $currentUnit->tenants()->where('status', 'active')->count() === 0) {
            $currentUnit->update(['status' => 'vacant']);
        }

        return back()->with('success', "Tenant reassigned successfully! {$tenant->name} moved from {$currentUnitName} to {$newUnitName}.");
    }
}
