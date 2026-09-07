<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Owner;
use App\Models\Flat;
use App\Models\Building;
use App\Models\Society;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class TenantController extends BaseController
{
    use HandlesFormSubmissions;
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request)
    {
        $societyId = $this->getSocietyId();
        
        $query = Tenant::bySociety($societyId)->with(['flat.building', 'owner', 'society']);

        // Filter by owner
        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        // Filter by flat
        if ($request->filled('flat_id')) {
            $query->where('flat_id', $request->flat_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $tenants = $query->latest()->paginate(15);
        $owners = Owner::bySociety($societyId)->get();
        $flats = Flat::bySociety($societyId)->get();

        return view('tenants.index', compact('tenants', 'owners', 'flats'));
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create()
    {
        $societyId = $this->getSocietyId();
        
        $owners = Owner::bySociety($societyId)->get();
        $flats = Flat::bySociety($societyId)
            ->where('status', 'occupied')
            ->with(['building', 'villaArea'])
            ->get();

        return view('tenants.create', compact('owners', 'flats', 'societyId'));
    }

    /**
     * Store a newly created tenant in storage.
     */
    public function store(Request $request)
    {
        return $this->handleFormSubmission(function() use ($request) {
            $societyId = $this->getSocietyId();
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:tenants,email',
                'phone' => 'required|string|max:15',
                'flat_id' => 'required|exists:flats,id',
                'owner_id' => 'required|exists:owners,id',
                'move_in_date' => 'required|date',
                'monthly_rent' => 'required|numeric|min:0',
                'security_deposit' => 'nullable|numeric|min:0',
            ]);

            // Verify flat and owner belong to the same society
            $flat = Flat::findOrFail($validated['flat_id']);
            $owner = Owner::findOrFail($validated['owner_id']);

            if ($flat->society_id !== $societyId || $owner->society_id !== $societyId) {
                throw new \Exception('Selected flat and owner must belong to the same society.');
            }

            // Create tenant
            $tenant = Tenant::create([
                'society_id' => $societyId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'flat_id' => $validated['flat_id'],
                'owner_id' => $validated['owner_id'],
                'move_in_date' => $validated['move_in_date'],
                'monthly_rent' => $validated['monthly_rent'],
                'security_deposit' => $validated['security_deposit'] ?? 0,
                'status' => 'active',
            ]);

            // Dispatch event to trigger email notification
            \App\Events\TenantOwnerAdded::dispatch($tenant);
            
            return true;
        }, 'Tenant created successfully!', 'tenants.index');
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant)
    {
        $tenant->load(['flat.building', 'owner', 'society']);

        return view('tenants.show', compact('tenant'));
    }

    /**
     * Show the form for editing the specified tenant.
     */
    public function edit(Tenant $tenant)
    {
        $societyId = $this->getSocietyId();
        
        // Verify tenant belongs to the same society
        if ($tenant->society_id !== $societyId) {
            return back()->with('error', 'Tenant not found.');
        }
        
        $tenant->load(['flat.building', 'flat.villaArea', 'owner', 'society']);
        $owners = Owner::bySociety($societyId)->get();
        $flats = Flat::bySociety($societyId)->with(['building', 'villaArea'])->get();

        return view('tenants.edit', compact('tenant', 'owners', 'flats'));
    }

    /**
     * Update the specified tenant in storage.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'required|string|max:15',
            'flat_id' => 'required|exists:flats,id',
            'owner_id' => 'required|exists:owners,id',
            'move_in_date' => 'required|date',
            'monthly_rent' => 'required|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive',
        ]);

        // Update tenant
        $tenant->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'flat_id' => $validated['flat_id'],
            'owner_id' => $validated['owner_id'],
            'move_in_date' => $validated['move_in_date'],
            'monthly_rent' => $validated['monthly_rent'],
            'security_deposit' => $validated['security_deposit'] ?? 0,
            'status' => $validated['status'],
        ]);

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant updated successfully!');
    }

    /**
     * Remove the specified tenant from storage.
     */
    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return redirect()->route('tenants.index')
            ->with('success', 'Tenant deleted successfully!');
    }
}
