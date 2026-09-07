<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\Building;
use App\Models\Society;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class FlatController extends BaseController
{
    use HandlesFormSubmissions;
    public function apiIndex()
    {
        $user = auth()->user();
        $societyId = $user->society_id;
        
        // Get flats with relationships
        $query = Flat::with(['building', 'villaArea']);
        
        // Filter by society if user has one
        if ($societyId) {
            $query->where('society_id', $societyId);
        }
        
        $flats = $query->get();
        
        // Log for debugging
        \Log::info('Visitor API - Flats loaded', [
            'user_id' => $user->id,
            'society_id' => $societyId,
            'total_flats' => $flats->count(),
            'apartments_with_buildings' => $flats->filter(function($flat) {
                return $flat->property_type === 'apartment' && $flat->building;
            })->count(),
            'villas_with_areas' => $flats->filter(function($flat) {
                return $flat->property_type === 'villa' && $flat->villaArea;
            })->count()
        ]);
        
        return response()->json($flats);
    }

    public function index(Request $request)
    {
        // Get user's society_id for data isolation
        $societyId = auth()->user()->society_id;
        
        // If user has no society_id, deny access (except Super Admin)
        if (!$societyId && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: No society assigned.');
        }

        $query = Flat::with(['building', 'society', 'ownerModel']);
        
        // Apply society filter (Super Admin sees all, others see only their society)
        if ($societyId) {
            $query->where('society_id', $societyId);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('flat_number', 'like', "%{$search}%")
                  ->orWhereHas('building', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Building filter (also needs society filtering)
        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        $flats = $query->latest()->paginate(15);
        
        // Buildings dropdown should also be filtered by society
        $buildingsQuery = Building::query();
        if ($societyId) {
            $buildingsQuery->where('society_id', $societyId);
        }
        $buildings = $buildingsQuery->get();

        return view('flats.index', compact('flats', 'buildings'));
    }

    public function create()
    {
        $user = auth()->user();
        
        // If user is Super Admin, show all societies and buildings; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
            $buildings = Building::with('society')->get();
        } else {
            // For Society Admin, only show their own society and buildings
            $societies = collect([$user->society]);
            $buildings = Building::with('society')->where('society_id', $user->society_id)->get();
        }
        
        // No need to load owners for create form since owner dropdown is hidden
        $owners = collect(); // Empty collection for consistency
        
        return view('flats.create', compact('buildings', 'societies', 'owners'));
    }

    public function store(Request $request)
    {
        return $this->handleFormSubmission(function () use ($request) {
            $user = auth()->user();
            
            // Validation rules
            $rules = [
                'building_id' => 'required|exists:buildings,id',
                'flat_number' => 'required|string|max:10',
                'floor' => 'required|integer|min:0|max:100',
                'type' => 'required|in:1BHK,2BHK,3BHK,4BHK,5BHK,Studio,Penthouse,Shop,Office',
                'carpet_area' => 'nullable|numeric|min:100|max:10000',
                'built_up_area' => 'nullable|numeric|min:100|max:10000',
                'maintenance_amount' => 'nullable|numeric|min:0',
                'status' => 'required|in:vacant,occupied,on_rent,maintenance',
                'description' => 'nullable|string',
                'owner_id' => 'nullable|exists:owners,id',
            ];
            
            // Only Super Admin can select society, others use their own society
            if ($user->hasRole('Super Admin')) {
                $rules['society_id'] = 'required|exists:societies,id';
            }

            $validated = $request->validate($rules);

            // Set society_id for non-Super Admin users
            if (!$user->hasRole('Super Admin')) {
                $validated['society_id'] = $user->society_id;
            }

            // Check for duplicate flat number in the same building
            $exists = Flat::where('building_id', $validated['building_id'])
                         ->where('flat_number', $validated['flat_number'])
                         ->exists();

            if ($exists) {
                return back()->withErrors(['flat_number' => 'Flat number already exists in this building.']);
            }

            Flat::create($validated);
        }, 'Flat created successfully!', 'flats.index');
    }

    public function show(Flat $flat)
    {
        $flat->load(['building.society', 'activeResident', 'maintenanceBills']);
        
        $stats = [
            'total_bills' => $flat->maintenanceBills()->count(),
            'paid_bills' => $flat->maintenanceBills()->where('status', 'paid')->count(),
            'pending_bills' => $flat->maintenanceBills()->where('status', 'pending')->count(),
            'total_paid' => $flat->maintenanceBills()->where('status', 'paid')->sum('amount'),
            'total_pending' => $flat->maintenanceBills()->where('status', 'pending')->sum('amount'),
        ];

        return view('flats.show', compact('flat', 'stats'));
    }

    public function edit(Flat $flat)
    {
        $user = auth()->user();
        
        // If user is Super Admin, show all societies and buildings; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
            $buildings = Building::with('society')->get();
        } else {
            // For Society Admin, only show their own society and buildings
            $societies = collect([$user->society]);
            $buildings = Building::with('society')->where('society_id', $user->society_id)->get();
        }
        
        // No need to load owners since owner dropdown is hidden
        $owners = collect(); // Empty collection for consistency
        
        return view('flats.edit', compact('flat', 'buildings', 'societies', 'owners'));
    }

    public function update(Request $request, Flat $flat)
    {
        $user = auth()->user();
        
        // Validation rules
        $rules = [
            'building_id' => 'required|exists:buildings,id',
            'flat_number' => 'required|string|max:10',
            'floor' => 'required|integer|min:0|max:100',
            'type' => 'required|in:1BHK,2BHK,3BHK,4BHK,5BHK,Studio,Penthouse,Shop,Office',
            'carpet_area' => 'nullable|numeric|min:100|max:10000',
            'built_up_area' => 'nullable|numeric|min:100|max:10000',
            'maintenance_amount' => 'nullable|numeric|min:0',
            'status' => 'required|in:vacant,occupied,on_rent,maintenance',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:owners,id',
        ];
        
        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        $validated = $request->validate($rules);

        // Set society_id for non-Super Admin users
        if (!$user->hasRole('Super Admin')) {
            $validated['society_id'] = $user->society_id;
        }

        // Check for duplicate flat number in the same building (excluding current flat)
        $exists = Flat::where('building_id', $validated['building_id'])
                     ->where('flat_number', $validated['flat_number'])
                     ->where('id', '!=', $flat->id)
                     ->exists();

        if ($exists) {
            return back()->withErrors(['flat_number' => 'Flat number already exists in this building.']);
        }

        $flat->update($validated);

        return redirect()->route('flats.index')
            ->with('success', 'Flat updated successfully!');
    }

    public function destroy(Flat $flat)
    {
        if ($flat->status === 'occupied') {
            return back()->with('error', 'Cannot delete occupied flat!');
        }

        if ($flat->maintenanceBills()->where('status', 'pending')->count() > 0) {
            return back()->with('error', 'Cannot delete flat with pending bills!');
        }

        $flat->delete();

        return redirect()->route('flats.index')
            ->with('success', 'Flat deleted successfully!');
    }

    public function changeStatus(Request $request, Flat $flat)
    {
        $request->validate([
            'status' => 'required|in:vacant,occupied,on_rent,maintenance',
        ]);

        $flat->update(['status' => $request->status]);

        return back()->with('success', 'Flat status updated successfully!');
    }
}