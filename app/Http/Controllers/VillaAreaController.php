<?php

namespace App\Http\Controllers;

use App\Models\VillaArea;
use App\Models\Society;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;

class VillaAreaController extends BaseController
{
    use HandlesFormSubmissions;
    public function __construct()
    {
        // Authorize all actions through the policy
        $this->authorizeResource(VillaArea::class);
    }

    public function index()
    {
        $user = auth()->user();
        
        // Build query based on role
        if ($user->hasRole('Super Admin')) {
            // Super Admin sees all villa areas
            $villaAreas = VillaArea::with(['society', 'villas'])
                ->latest()
                ->paginate(10);
        } else {
            // Admin sees only their society's villa areas
            $villaAreas = VillaArea::where('society_id', $user->society_id)
                ->with(['society', 'villas'])
                ->latest()
                ->paginate(10);
        }

        return view('villa-areas.index', compact('villaAreas'));
    }

    public function create()
    {
        $user = auth()->user();
        
        // If user is Super Admin, show all societies; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::active()->get();
        } else {
            // For Society Admin, only show their own society
            $societies = collect([$user->society]);
        }
        
        return view('villa-areas.create', compact('societies'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Validation rules
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
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

        VillaArea::create($validated);

        return redirect()->route('villa-areas.index')
            ->with('success', 'Villa Area created successfully.');
    }

    public function show(VillaArea $villaArea)
    {
        // Policy authorization is handled by __construct
        $villaArea->load(['society', 'villas.ownerUser']);
        return view('villa-areas.show', compact('villaArea'));
    }

    public function edit(VillaArea $villaArea)
    {
        // Policy authorization is handled by __construct
        $user = auth()->user();
        
        // If user is Super Admin, show all societies; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::active()->get();
        } else {
            // For Society Admin, only show their own society
            $societies = collect([$user->society]);
        }
        
        return view('villa-areas.edit', compact('villaArea', 'societies'));
    }

    public function update(Request $request, VillaArea $villaArea)
    {
        // Policy authorization is handled by __construct
        $user = auth()->user();
        
        // Validation rules
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
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

        $villaArea->update($validated);

        return redirect()->route('villa-areas.index')
            ->with('success', 'Villa Area updated successfully.');
    }

    public function destroy(VillaArea $villaArea)
    {
        // Policy authorization is handled by __construct
        
        if ($villaArea->villas()->count() > 0) {
            return redirect()->route('villa-areas.index')
                ->with('error', 'Cannot delete villa area with existing villas.');
        }

        $villaArea->delete();

        return redirect()->route('villa-areas.index')
            ->with('success', 'Villa Area deleted successfully.');
    }
}
