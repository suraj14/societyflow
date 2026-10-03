<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use App\Models\User;
use App\Models\Society;
use App\Models\Flat;
use App\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Traits\HandlesFormSubmissions;

/**
 * The standalone "Resident" CRUD screens - superseded, but not dead code.
 *
 * Investigated rather than assumed. Two things are true at once:
 *
 *  1. The `residents` table is very much ALIVE. Owner/Tenant/Villa
 *     assignment (Admin\User{Apartment,Villa,Tenant}AssignmentController)
 *     write a Resident row as part of assigning a property, dashboards read
 *     it, and UserFlatResolver falls back to it. It is the "legacy" person
 *     model CLAUDE.md §5 documents, kept for backward compatibility and
 *     still load-bearing - it must not be deleted or ignored.
 *
 *  2. This standalone CONTROLLER's index route is dead: routes/web.php
 *     registers `Route::resource('residents', ...)` but a later
 *     `Route::redirect('/residents', '/owners')` (comment: "Phase 8 refactor
 *     - Residents deprecated") intercepts the bare index path first, so
 *     ResidentController::index() itself is unreachable. No live
 *     navigation links anywhere else in this controller either (the two
 *     legacy navigation partials that once linked residents.index were
 *     removed as dead code on 2026-10-03) - create/show/
 *     edit/update/destroy are reachable only by a direct URL that nothing
 *     in the app links to.
 *
 * Net effect: orphaned UI over an active data model. Left in place rather
 * than removed, per "preserve existing data and explain its relationship
 * before making any change" - Owner/Tenant creation is the supported path
 * for adding a resident today, and this file is not it. Authorization was
 * confirmed and fixed separately (show/update/destroy previously had none
 * at all); a reachable-by-URL screen still had to be safe on its own,
 * dead navigation link or not.
 */
class ResidentController extends BaseController
{
    use HandlesFormSubmissions;

    /**
     * Display a listing of residents.
     */
    public function index(Request $request)
    {
        $societyId = $this->getSocietyId();
        
        $query = Resident::bySociety($societyId)->with(['flat.building', 'society']);

        // Filter by building
        if ($request->filled('building_id')) {
            $query->whereHas('flat', function ($q) use ($request) {
                $q->where('building_id', $request->building_id);
            });
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

        $residents = $query->latest()->paginate(15);
        $buildings = Building::bySociety($societyId)->get();
        $flats = Flat::bySociety($societyId)->get();

        return view('residents.index', compact('residents', 'buildings', 'flats'));
    }

    /**
     * Show the form for creating a new resident.
     */
    public function create()
    {
        $user = auth()->user();
        
        // For Super Admin, show all societies; for others, use their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
            $societyId = request('society_id') ?: $societies->first()?->id;
        } else {
            $societyId = $this->getSocietyId();
            $societies = Society::where('id', $societyId)->where('status', 'active')->get();
        }
        
        $buildings = Building::where('society_id', $societyId)->get();
        
        // Only get vacant flats for new residents
        $flats = Flat::where('society_id', $societyId)
            ->where('status', 'vacant')
            ->with(['building', 'villaArea'])
            ->get();
        
        return view('residents.create', compact('societies', 'buildings', 'flats', 'societyId'));
    }

    /**
     * Store a newly created resident in storage.
     */
    public function store(Request $request)
    {
        $societyId = $this->getSocietyId();
        
        return $this->handleFormSubmission(function() use ($request, $societyId) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:residents,email',
                'phone' => 'required|string|max:15',
                'flat_id' => 'required|exists:flats,id',
                'move_in_date' => 'required|date',
                'monthly_rent' => 'nullable|numeric|min:0',
                'security_deposit' => 'nullable|numeric|min:0',
            ]);

            // Verify flat belongs to the same society
            $flat = Flat::findOrFail($validated['flat_id']);
            if ($flat->society_id !== $societyId) {
                throw new \Exception('Selected flat must belong to the same society.');
            }

            // Create resident (standalone, not linked to users table)
            // Default type to 'owner' for backward compatibility
            $resident = Resident::create([
                'society_id' => $societyId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'flat_id' => $validated['flat_id'],
                'type' => 'owner', // Default to owner
                'move_in_date' => $validated['move_in_date'],
                'monthly_rent' => $validated['monthly_rent'] ?? 0,
                'security_deposit' => $validated['security_deposit'] ?? 0,
                'status' => 'active',
            ]);

            // Update flat status to occupied
            $flat->update(['status' => 'occupied']);
            return $resident;
        }, 'Resident created successfully!', 'residents.index');
    }

    /**
     * Display the specified resident.
     */
    public function show(Resident $resident)
    {
        // Route-model binding resolves any id in the table; nothing here
        // checked the resident belonged to the viewer's own society, so any
        // signed-in Admin could read another society's resident by id.
        $this->authorizeSocietyAccess($resident);

        $resident->load(['flat.building', 'society']);

        return view('residents.show', compact('resident'));
    }

    /**
     * Show the form for editing the specified resident.
     */
    public function edit(Resident $resident)
    {
        $societyId = $this->getSocietyId();
        
        // Verify resident belongs to the same society
        if ($resident->society_id !== $societyId) {
            return back()->with('error', 'Resident not found.');
        }
        
        $resident->load(['flat.building', 'flat.villaArea', 'society']);
        $societies = Society::where('status', 'active')->get();
        $buildings = Building::bySociety($societyId)->get();
        $flats = Flat::bySociety($societyId)->with(['building', 'villaArea'])->get();

        return view('residents.edit', compact('resident', 'societies', 'buildings', 'flats'));
    }

    /**
     * Update the specified resident in storage.
     */
    public function update(Request $request, Resident $resident)
    {
        // Must belong to the current user's society before anything else.
        $this->authorizeSocietyAccess($resident);

        return $this->handleFormSubmission(function() use ($request, $resident) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:residents,email,' . $resident->id,
                'phone' => 'required|string|max:15',
                // flat_id is scoped to the resident's own society below -
                // exists:flats,id alone only proves the flat is real
                // *somewhere*, not that it is this resident's society's.
                'flat_id' => [
                    'required',
                    Rule::exists('flats', 'id')->where('society_id', $resident->society_id),
                ],
                'move_in_date' => 'required|date',
                'monthly_rent' => 'nullable|numeric|min:0',
                'security_deposit' => 'nullable|numeric|min:0',
                'status' => 'required|in:active,inactive',
            ]);

            // Update old flat status if flat changed
            if ($resident->flat_id != $validated['flat_id']) {
                $oldFlat = Flat::find($resident->flat_id);
                if ($oldFlat) {
                    $oldFlat->update(['status' => 'vacant']);
                }

                // Update new flat status
                $newFlat = Flat::find($validated['flat_id']);
                if ($newFlat) {
                    $newFlat->update(['status' => 'occupied']);
                }
            }

            // Update resident (keep existing type). society_id is never
            // taken from the request - the previous code trusted
            // $validated['society_id'] outright, which let this form
            // reassign a resident to any society whose id a client posted.
            $resident->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'flat_id' => $validated['flat_id'],
                'move_in_date' => $validated['move_in_date'],
                'monthly_rent' => $validated['monthly_rent'] ?? 0,
                'security_deposit' => $validated['security_deposit'] ?? 0,
                'status' => $validated['status'],
            ]);

            return $resident;
        }, 'Resident updated successfully!', 'residents.index');
    }

    /**
     * Remove the specified resident from storage.
     */
    public function destroy(Resident $resident)
    {
        $this->authorizeSocietyAccess($resident);

        // Update flat status to vacant
        $flat = Flat::find($resident->flat_id);
        if ($flat) {
            $flat->update(['status' => 'vacant']);
        }

        // Delete resident (this will also trigger user deletion if cascade is set)
        $resident->delete();

        return redirect()->route('residents.index')
            ->with('success', 'Resident deleted successfully!');
    }
}
