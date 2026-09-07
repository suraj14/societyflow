<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Society;
use Illuminate\Http\Request;
use App\Traits\HandlesFormSubmissions;

class BuildingController extends BaseController
{
    use HandlesFormSubmissions;

    public function index()
    {
        $buildings = Building::where('society_id', $this->getSocietyId())
            ->with(['society'])
            ->latest()
            ->paginate(15);
        return view('buildings.index', compact('buildings'));
    }

    public function create()
    {
        // Only show current society for non-super-admin users
        if (auth()->user()->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
        } else {
            $societies = Society::where('id', $this->getSocietyId())
                ->where('status', 'active')
                ->get();
        }
        return view('buildings.create', compact('societies'));
    }

    public function store(Request $request)
    {
        // Ensure society_id belongs to current user's society (unless Super Admin)
        if (!auth()->user()->hasRole('Super Admin')) {
            $request->merge(['society_id' => $this->getSocietyId()]);
        }

        return $this->handleFormSubmission(function() use ($request) {
            $request->validate([
                'society_id' => 'required|exists:societies,id',
                'name' => 'required|string|max:255|unique:buildings,name,NULL,id,society_id,' . $request->society_id,
                'total_floors' => 'required|integer|min:1|max:100',
                'flats_per_floor' => 'required|integer|min:1|max:20',
                'description' => 'nullable|string',
            ]);

            $building = Building::create([
                'society_id' => $request->society_id,
                'name' => $request->name,
                'description' => $request->description,
                'total_floors' => $request->total_floors,
                'total_flats' => $request->total_floors * $request->flats_per_floor,
                'status' => 'active',
            ]);

            // Auto-generate flats
            $this->generateFlats($building, $request->flats_per_floor);
            return $building;
        }, 'Building created successfully with flats!', 'buildings.index');
    }

    public function show(Building $building)
    {
        // Ensure building belongs to current user's society (unless Super Admin)
        if (!auth()->user()->hasRole('Super Admin') && $building->society_id !== $this->getSocietyId()) {
            abort(403, 'Unauthorized access to building.');
        }

        $building->load(['society', 'flats.activeResident']);
        
        $stats = [
            'total_flats' => $building->flats()->count(),
            'occupied_flats' => $building->flats()->where('status', 'occupied')->count(),
            'vacant_flats' => $building->flats()->where('status', 'vacant')->count(),
            'maintenance_flats' => $building->flats()->where('status', 'maintenance')->count(),
        ];

        return view('buildings.show', compact('building', 'stats'));
    }

    public function edit(Building $building)
    {
        // Ensure building belongs to current user's society (unless Super Admin)
        if (!auth()->user()->hasRole('Super Admin') && $building->society_id !== $this->getSocietyId()) {
            abort(403, 'Unauthorized access to building.');
        }

        // Only show current society for non-super-admin users
        if (auth()->user()->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
        } else {
            $societies = Society::where('id', $this->getSocietyId())
                ->where('status', 'active')
                ->get();
        }
        return view('buildings.edit', compact('building', 'societies'));
    }

    public function update(Request $request, Building $building)
    {
        // Ensure building belongs to current user's society (unless Super Admin)
        if (!auth()->user()->hasRole('Super Admin') && $building->society_id !== $this->getSocietyId()) {
            abort(403, 'Unauthorized access to building.');
        }

        // Ensure society_id belongs to current user's society (unless Super Admin)
        if (!auth()->user()->hasRole('Super Admin')) {
            $request->merge(['society_id' => $this->getSocietyId()]);
        }

        return $this->handleFormSubmission(function() use ($request, $building) {
            $request->validate([
                'society_id' => 'required|exists:societies,id',
                'name' => 'required|string|max:255|unique:buildings,name,' . $building->id . ',id,society_id,' . $request->society_id,
                'total_floors' => 'required|integer|min:1|max:100',
                'description' => 'nullable|string',
            ]);

            $building->update([
                'society_id' => $request->society_id,
                'name' => $request->name,
                'description' => $request->description,
                'total_floors' => $request->total_floors,
            ]);

            return $building;
        }, 'Building updated successfully!', 'buildings.index');
    }

    public function destroy(Building $building)
    {
        // Ensure building belongs to current user's society (unless Super Admin)
        if (!auth()->user()->hasRole('Super Admin') && $building->society_id !== $this->getSocietyId()) {
            abort(403, 'Unauthorized access to building.');
        }

        if ($building->flats()->where('status', 'occupied')->count() > 0) {
            return back()->with('error', 'Cannot delete building with occupied flats!');
        }

        $building->delete();

        return redirect()->route('buildings.index')
            ->with('success', 'Building deleted successfully!');
    }

    private function generateFlats(Building $building, $flatsPerFloor)
    {
        for ($floor = 1; $floor <= $building->total_floors; $floor++) {
            for ($flat = 1; $flat <= $flatsPerFloor; $flat++) {
                $flatNumber = $floor . str_pad($flat, 2, '0', STR_PAD_LEFT);
                
                \App\Models\Flat::create([
                    'building_id' => $building->id,
                    'society_id' => $building->society_id,
                    'flat_number' => $flatNumber,
                    'floor' => $floor,
                    'type' => $this->determineFlatType($flat),
                    'carpet_area' => $this->determineFlatArea($flat),
                    'maintenance_amount' => rand(2000, 8000),
                    'status' => 'vacant',
                ]);
            }
        }
        
        // Update building totals
        $building->updateTotals();
    }

    private function determineFlatType($flatNumber)
    {
        // Simple logic to assign flat types
        if ($flatNumber <= 2) {
            return '2BHK';
        } elseif ($flatNumber <= 4) {
            return '3BHK';
        } else {
            return '1BHK';
        }
    }

    private function determineFlatArea($flatNumber)
    {
        // Simple logic to assign flat areas
        if ($flatNumber <= 2) {
            return 1200; // 2BHK
        } elseif ($flatNumber <= 4) {
            return 1500; // 3BHK
        } else {
            return 800; // 1BHK
        }
    }
}