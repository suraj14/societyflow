<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\Society;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FacilityController extends BaseController
{
    use HandlesFormSubmissions;
    public function index(Request $request)
    {
        // Get user's society_id for data isolation
        $societyId = auth()->user()->society_id;
        
        // If user has no society_id, deny access (except Super Admin)
        if (!$societyId && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: No society assigned.');
        }

        $query = Facility::with('society');
        
        // Apply society filter (Super Admin sees all, others see only their society)
        if ($societyId) {
            $query->where('society_id', $societyId);
        }

        // Filters
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $facilities = $query->latest()->paginate(10);

        // Stats should also be filtered by society
        $statsQuery = Facility::query();
        if ($societyId) {
            $statsQuery->where('society_id', $societyId);
        }

        $stats = [
            'total' => $statsQuery->count(),
            'active' => (clone $statsQuery)->where('status', 'active')->count(),
            'inactive' => (clone $statsQuery)->where('status', 'inactive')->count(),
            'maintenance' => (clone $statsQuery)->where('status', 'maintenance')->count(),
        ];

        return view('facilities.index', compact('facilities', 'stats'));
    }

    public function create()
    {
        return view('facilities.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:clubhouse,gym,swimming_pool,hall,playground,parking,other',
            'booking_charge' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'available_days' => 'nullable|array',
            'advance_booking_days' => 'nullable|integer|min:1|max:365',
            'max_booking_hours' => 'nullable|integer|min:1|max:24',
            'requires_approval' => 'boolean',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:active,inactive,maintenance',
        ]);

        // Auto-fill society_id from logged-in user
        $validated['society_id'] = auth()->user()->society_id;

        if ($request->hasFile('image')) {
            // Store image filename only, don't use Storage::disk
            $file = $request->file('image');
            $uploadPath = public_path('storage/facilities');
            @mkdir($uploadPath, 0777, true);
            $filename = time() . '_' . $file->getClientOriginalName();
            try {
                $file->move($uploadPath, $filename);
            } catch (\Exception $e) {
                @chmod($uploadPath, 0777);
                $file->move($uploadPath, $filename);
            }
            $validated['image'] = 'facilities/' . $filename;
        }

        $validated['requires_approval'] = $request->has('requires_approval');
        $validated['available_days'] = $request->available_days ?? [];

        Facility::create($validated);

        return redirect()->route('facilities.index')
            ->with('success', 'Facility created successfully.');
    }

    public function show(Facility $facility)
    {
        $facility->load(['society', 'bookings' => function ($query) {
            $query->latest()->take(10);
        }]);

        $stats = [
            'total_bookings' => $facility->bookings()->count(),
            'pending_bookings' => $facility->bookings()->where('status', 'pending')->count(),
            'approved_bookings' => $facility->bookings()->where('status', 'approved')->count(),
            'completed_bookings' => $facility->bookings()->where('status', 'completed')->count(),
        ];

        return view('facilities.show', compact('facility', 'stats'));
    }

    public function edit(Facility $facility)
    {
        return view('facilities.edit', compact('facility'));
    }

    public function update(Request $request, Facility $facility)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:clubhouse,gym,swimming_pool,hall,playground,parking,other',
            'booking_charge' => 'nullable|numeric|min:0',
            'capacity' => 'nullable|integer|min:1',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'available_days' => 'nullable|array',
            'advance_booking_days' => 'nullable|integer|min:1|max:365',
            'max_booking_hours' => 'nullable|integer|min:1|max:24',
            'requires_approval' => 'boolean',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'required|in:active,inactive,maintenance',
        ]);

        if ($request->hasFile('image')) {
            if ($facility->image) {
                // Don't try to delete - just skip it
            }
            // Store image filename only, don't use Storage::disk
            $file = $request->file('image');
            $uploadPath = public_path('storage/facilities');
            @mkdir($uploadPath, 0777, true);
            $filename = time() . '_' . $file->getClientOriginalName();
            try {
                $file->move($uploadPath, $filename);
            } catch (\Exception $e) {
                @chmod($uploadPath, 0777);
                $file->move($uploadPath, $filename);
            }
            $validated['image'] = 'facilities/' . $filename;
        }

        $validated['requires_approval'] = $request->has('requires_approval');
        $validated['available_days'] = $request->available_days ?? [];

        $facility->update($validated);

        return redirect()->route('facilities.index')
            ->with('success', 'Facility updated successfully.');
    }

    public function destroy(Facility $facility)
    {
        if ($facility->bookings()->whereIn('status', ['pending', 'approved'])->exists()) {
            return redirect()->route('facilities.index')
                ->with('error', 'Cannot delete facility with active bookings.');
        }

        if ($facility->image) {
            // Don't try to delete - just skip it
        }

        $facility->delete();

        return redirect()->route('facilities.index')
            ->with('success', 'Facility deleted successfully.');
    }
}
