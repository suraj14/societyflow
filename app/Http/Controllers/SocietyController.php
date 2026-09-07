<?php

namespace App\Http\Controllers;

use App\Models\Society;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\HandlesFormSubmissions;

class SocietyController extends BaseController
{
    use HandlesFormSubmissions;

    /**
     * Display a listing of societies.
     */
    public function index()
    {
        $societies = Society::latest()->paginate(10);
        
        return view('societies.index', compact('societies'));
    }

    /**
     * Show the form for creating a new society.
     */
    public function create()
    {
        return view('societies.create');
    }

    /**
     * Store a newly created society in storage.
     */
    public function store(Request $request)
    {
        return $this->handleFormSubmission(function() use ($request) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'country' => 'required|string|max:100',
                'pincode' => 'required|string|max:10',
                'phone' => 'nullable|string|max:15',
                'email' => 'nullable|email|max:255|unique:societies,email',
            ]);

            $validated['status'] = 'active';
            $validated['slug'] = Str::slug($validated['name']);

            return Society::create($validated);
        }, 'Society created successfully!', 'societies.index');
    }

    /**
     * Display the specified society.
     */
    public function show(Society $society)
    {
        $society->load(['buildings', 'residents']);
        
        $stats = [
            'total_buildings' => $society->buildings()->count(),
            'total_flats' => \App\Models\Flat::where('society_id', $society->id)->count(),
            'total_residents' => $society->residents()->count(),
            'occupied_flats' => \App\Models\Flat::where('society_id', $society->id)->where('status', 'occupied')->count(),
        ];

        return view('societies.show', compact('society', 'stats'));
    }

    /**
     * Show the form for editing the specified society.
     */
    public function edit(Society $society)
    {
        return view('societies.edit', compact('society'));
    }

    /**
     * Update the specified society in storage.
     */
    public function update(Request $request, Society $society)
    {
        return $this->handleFormSubmission(function() use ($request, $society) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'country' => 'required|string|max:100',
                'pincode' => 'required|string|max:10',
                'phone' => 'nullable|string|max:15',
                'email' => 'nullable|email|max:255|unique:societies,email,' . $society->id,
            ]);

            $validated['slug'] = Str::slug($validated['name']);
            $society->update($validated);
            return $society;
        }, 'Society updated successfully!', 'societies.index');
    }

    /**
     * Remove the specified society from storage.
     */
    public function destroy(Society $society)
    {
        // Check if society has buildings
        if ($society->buildings()->count() > 0) {
            return redirect()->route('societies.index')
                ->with('error', 'Cannot delete society with existing buildings. Please delete all buildings first.');
        }

        $society->delete();

        return redirect()->route('societies.index')
            ->with('success', 'Society deleted successfully!');
    }
}
