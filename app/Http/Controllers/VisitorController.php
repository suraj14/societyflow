<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Models\Flat;
use App\Models\Society;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class VisitorController extends BaseController
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

        $query = Visitor::with(['flat.building', 'host']);
        
        // Apply society filter (Super Admin sees all, others see only their society)
        if ($societyId) {
            $query->where('society_id', $societyId);
        }

        // Role-based filtering: Admin/Staff/Guard see all society visitors, others see only their own
        $user = auth()->user();
        if (!$user->hasRole(['Admin', 'Super Admin', 'Staff', 'Guard'])) {
            // Get all flat IDs this user is associated with
            $allFlatIds = collect();

            // Via residents table (Tenant, Resident)
            $residentFlatIds = $user->flats()->pluck('flats.id');
            $allFlatIds = $allFlatIds->merge($residentFlatIds);

            // Via owner record (Villa Owner, Apartment Owner)
            if ($user->owner_id) {
                $owner = \App\Models\Owner::find($user->owner_id);
                if ($owner) {
                    // Find flat by villa area + villa number
                    if ($owner->villa_area_id && $owner->villa_no) {
                        $flatIds = Flat::where('villa_area_id', $owner->villa_area_id)
                            ->where('flat_number', $owner->villa_no)
                            ->pluck('id');
                        $allFlatIds = $allFlatIds->merge($flatIds);
                    }
                    // Find flat by building + flat number
                    if ($owner->building_id && $owner->flat_no) {
                        $flatIds = Flat::where('building_id', $owner->building_id)
                            ->where('flat_number', $owner->flat_no)
                            ->pluck('id');
                        $allFlatIds = $allFlatIds->merge($flatIds);
                    }
                }
            }

            $allFlatIds = $allFlatIds->unique()->values()->toArray();

            $query->where(function ($q) use ($user, $allFlatIds) {
                $q->where('host_user_id', $user->id);
                if (!empty($allFlatIds)) {
                    $q->orWhereIn('flat_id', $allFlatIds);
                }
            });
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('visitor_name', 'like', '%' . $search . '%')
                  ->orWhere('visitor_phone', 'like', '%' . $search . '%')
                  ->orWhereHas('flat', function ($fq) use ($search) {
                      $fq->where('flat_number', 'like', '%' . $search . '%');
                  });
            });
        }

        // Visitor type filter
        if ($request->filled('visitor_type')) {
            $query->where('visitor_type', $request->visitor_type);
        }

        // Status filter
        if ($request->filled('status')) {
            if ($request->status === 'checked_in') {
                $query->where('entry_status', 'entered');
            } elseif ($request->status === 'checked_out') {
                $query->where('entry_status', 'exited');
            } else {
                $query->where('approval_status', $request->status);
            }
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('expected_entry_time', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expected_entry_time', '<=', $request->date_to);
        }

        // Quick date filters
        if ($request->filled('date_filter')) {
            switch ($request->date_filter) {
                case 'today':
                    $query->whereDate('expected_entry_time', today());
                    break;
                case 'yesterday':
                    $query->whereDate('expected_entry_time', today()->subDay());
                    break;
                case 'last_7_days':
                    $query->whereDate('expected_entry_time', '>=', today()->subDays(7));
                    break;
                case 'last_30_days':
                    $query->whereDate('expected_entry_time', '>=', today()->subDays(30));
                    break;
            }
        }

        $visitors = $query->latest()->paginate(15);

        // Stats should also be filtered by role and society
        $statsQuery = Visitor::query();
        if ($societyId) {
            $statsQuery->where('society_id', $societyId);
        }

        // Apply same role-based filtering to stats
        if (!$user->hasRole(['Admin', 'Super Admin', 'Staff', 'Guard'])) {
            $statsFlatIds = collect();
            $residentFlatIds = $user->flats()->pluck('flats.id');
            $statsFlatIds = $statsFlatIds->merge($residentFlatIds);

            if ($user->owner_id) {
                $owner = \App\Models\Owner::find($user->owner_id);
                if ($owner) {
                    if ($owner->villa_area_id && $owner->villa_no) {
                        $statsFlatIds = $statsFlatIds->merge(
                            Flat::where('villa_area_id', $owner->villa_area_id)
                                ->where('flat_number', $owner->villa_no)->pluck('id')
                        );
                    }
                    if ($owner->building_id && $owner->flat_no) {
                        $statsFlatIds = $statsFlatIds->merge(
                            Flat::where('building_id', $owner->building_id)
                                ->where('flat_number', $owner->flat_no)->pluck('id')
                        );
                    }
                }
            }

            $statsFlatIds = $statsFlatIds->unique()->values()->toArray();

            $statsQuery->where(function ($q) use ($user, $statsFlatIds) {
                $q->where('host_user_id', $user->id);
                if (!empty($statsFlatIds)) {
                    $q->orWhereIn('flat_id', $statsFlatIds);
                }
            });
        }

        $stats = [
            'total' => $statsQuery->count(),
            'today' => (clone $statsQuery)->whereDate('expected_entry_time', today())->count(),
            'pending' => (clone $statsQuery)->where('approval_status', 'pending')->count(),
            'allowed' => (clone $statsQuery)->where('approval_status', 'allowed')->count(),
            'denied' => (clone $statsQuery)->where('approval_status', 'denied')->count(),
            'checked_in' => (clone $statsQuery)->where('entry_status', 'entered')->count(),
            'checked_out' => (clone $statsQuery)->where('entry_status', 'exited')->count(),
        ];

        // Flats dropdown should also be filtered by society
        $flatsQuery = Flat::with('building');
        if ($societyId) {
            $flatsQuery->where('society_id', $societyId);
        }
        $flats = $flatsQuery->get();

        return view('visitors.index', compact('visitors', 'stats', 'flats'));
    }

    public function create()
    {
        $flats = Flat::with('building')->get();
        $societies = Society::where('status', 'active')->get();
        return view('visitors.create', compact('flats', 'societies'));
    }

    public function store(Request $request)
    {
        return $this->handleFormSubmission(function () use ($request) {
            $validated = $request->validate([
                'flat_id' => 'required|exists:flats,id',
                'visitor_name' => 'required|string|max:255',
                'visitor_phone' => 'required|string|max:15',
                'visitor_type' => 'required|in:guest,delivery,cab,service,other',
                'purpose' => 'nullable|string|max:500',
                'expected_count' => 'nullable|integer|min:1|max:50',
                'vehicle_number' => 'nullable|string|max:20',
                'expected_entry_time' => 'required|date',
                'in_time' => 'required|date_format:H:i',
                'expected_exit_time' => 'nullable|date|after_or_equal:expected_entry_time',
                'out_time' => 'nullable|date_format:H:i',
                'photo' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $flat = Flat::findOrFail($validated['flat_id']);

            // Verify user belongs to the same society as the flat
            if ($flat->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
                abort(403, 'Access denied: You can only register visitors for properties in your society.');
            }

            // Combine date and time for entry
            $entryDateTime = $validated['expected_entry_time'] . ' ' . $validated['in_time'];
            
            // Combine date and time for exit if provided
            $exitDateTime = null;
            if ($validated['expected_exit_time'] && $validated['out_time']) {
                $exitDateTime = $validated['expected_exit_time'] . ' ' . $validated['out_time'];
            }

            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $uploadPath = public_path('storage/visitor-photos');
                @mkdir($uploadPath, 0777, true);
                $filename = time() . '_' . $file->getClientOriginalName();
                try {
                    $file->move($uploadPath, $filename);
                } catch (\Exception $e) {
                    @chmod($uploadPath, 0777);
                    $file->move($uploadPath, $filename);
                }
                $photoPath = 'visitor-photos/' . $filename;
            }

            $visitor = Visitor::create([
                'society_id' => $flat->society_id,
                'flat_id' => $validated['flat_id'],
                'host_user_id' => auth()->id(),
                'visitor_name' => $validated['visitor_name'],
                'visitor_phone' => $validated['visitor_phone'],
                'visitor_type' => $validated['visitor_type'],
                'purpose' => $validated['purpose'],
                'expected_count' => $validated['expected_count'] ?? 1,
                'vehicle_number' => $validated['vehicle_number'],
                'expected_entry_time' => $entryDateTime,
                'expected_exit_time' => $exitDateTime,
                'photo' => $photoPath,
                'approval_status' => 'pending',
                'entry_status' => 'pending',
            ]);
            
            \Illuminate\Support\Facades\Log::info('Visitor created successfully', [
                'visitor_id' => $visitor->id,
                'visitor_name' => $visitor->visitor_name,
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
            ]);
            
            return $visitor;
        }, 'Visitor registered successfully.', 'visitors.index', false);
    }

    public function show(Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        $visitor->load(['flat.building', 'host', 'society', 'logs.user']);
        return view('visitors.show', compact('visitor'));
    }

    public function edit(Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        // Only Admin and Super Admin can edit visitors (NOT Staff)
        if (!auth()->user()->hasRole(['Admin', 'Super Admin'])) {
            return redirect()->route('visitors.index')
                ->with('error', 'Access denied: Only administrators can edit visitor records.');
        }

        $flats = Flat::with('building')->get();
        return view('visitors.edit', compact('visitor', 'flats'));
    }

    public function update(Request $request, Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        // Only Admin and Super Admin can update visitors
        if (!auth()->user()->hasRole(['Admin', 'Super Admin'])) {
            if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => 'Access denied: Only administrators can update visitor records.'], 403);
            }
            return redirect()->route('visitors.index')
                ->with('error', 'Access denied: Only administrators can update visitor records.');
        }

        return $this->handleFormSubmission(function () use ($request, $visitor) {
            $validated = $request->validate([
                'visitor_name' => 'required|string|max:255',
                'visitor_phone' => 'required|string|max:15',
                'visitor_type' => 'required|in:guest,delivery,cab,service,other',
                'flat_id' => 'required|exists:flats,id',
                'purpose' => 'nullable|string|max:500',
                'expected_count' => 'nullable|integer|min:1|max:50',
                'vehicle_number' => 'nullable|string|max:20',
                'expected_entry_time' => 'required|date',
                'in_time' => 'required|date_format:H:i',
                'expected_exit_time' => 'nullable|date',
                'out_time' => 'nullable|date_format:H:i',
                'approval_status' => 'nullable|in:pending,allowed,denied',
                'entry_status' => 'nullable|in:pending,entered,exited',
                'photo' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Combine date and time for entry (both are required)
            $validated['expected_entry_time'] = $validated['expected_entry_time'] . ' ' . $validated['in_time'];
            
            // Combine date and time for exit if both provided
            if ($validated['expected_exit_time'] && $validated['out_time']) {
                $validated['expected_exit_time'] = $validated['expected_exit_time'] . ' ' . $validated['out_time'];
            } else {
                // If exit date/time not provided, keep existing or set to null
                unset($validated['expected_exit_time']);
            }

            // Handle photo upload
            if ($request->hasFile('photo')) {
                // Delete old photo if exists - skip deletion to avoid fileinfo error
                $file = $request->file('photo');
                $uploadPath = public_path('storage/visitor-photos');
                @mkdir($uploadPath, 0777, true);
                $filename = time() . '_' . $file->getClientOriginalName();
                try {
                    $file->move($uploadPath, $filename);
                } catch (\Exception $e) {
                    @chmod($uploadPath, 0777);
                    $file->move($uploadPath, $filename);
                }
                $validated['photo'] = 'visitor-photos/' . $filename;
            }

            // Remove the separate time fields from validated data
            unset($validated['in_time'], $validated['out_time']);

            $visitor->update($validated);
            
            return $visitor;
        }, 'Visitor updated successfully.', 'visitors.index', false);
    }

    public function destroy(Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        // Only Admin and Super Admin can delete visitors (NOT Staff)
        if (!auth()->user()->hasRole(['Admin', 'Super Admin'])) {
            if (request()->expectsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'message' => 'Access denied: Only administrators can delete visitor records.'], 403);
            }
            return redirect()->route('visitors.index')
                ->with('error', 'Access denied: Only administrators can delete visitor records.');
        }

        return $this->handleFormSubmission(function () use ($visitor) {
            $visitor->delete();
            return $visitor;
        }, 'Visitor deleted successfully.', 'visitors.index', false);
    }

    public function checkIn(Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        // Only Admin and Staff can check in visitors
        if (!auth()->user()->hasRole(['Admin', 'Staff', 'Super Admin'])) {
            abort(403, 'Access denied: Only administrators and staff can check in visitors.');
        }

        $visitor->update([
            'entry_status' => 'entered',
            'actual_entry_time' => now(),
        ]);

        return redirect()->route('visitors.index')
            ->with('success', 'Visitor checked in successfully.');
    }

    public function checkOut(Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        // Only Admin and Staff can check out visitors
        if (!auth()->user()->hasRole(['Admin', 'Staff', 'Super Admin'])) {
            abort(403, 'Access denied: Only administrators and staff can check out visitors.');
        }

        $visitor->update([
            'entry_status' => 'exited',
            'actual_exit_time' => now(),
        ]);

        return redirect()->route('visitors.index')
            ->with('success', 'Visitor checked out successfully.');
    }

    public function updateStatus(Request $request, Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        // Only Admin and Staff can update visitor status
        if (!auth()->user()->hasRole(['Admin', 'Staff', 'Super Admin'])) {
            abort(403, 'Access denied: Only administrators and staff can update visitor status.');
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,allowed,denied,entered,exited',
        ]);

        if (in_array($validated['status'], ['pending', 'allowed', 'denied'])) {
            $visitor->update(['approval_status' => $validated['status']]);
        } else {
            $visitor->update([
                'entry_status' => $validated['status'],
                'actual_entry_time' => $validated['status'] === 'entered' ? now() : $visitor->actual_entry_time,
                'actual_exit_time' => $validated['status'] === 'exited' ? now() : $visitor->actual_exit_time,
            ]);
        }

        return redirect()->route('visitors.index')
            ->with('success', 'Visitor status updated successfully.');
    }

    public function allow(Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        // Only Admin and Staff can approve visitors
        if (!auth()->user()->hasRole(['Admin', 'Staff', 'Super Admin'])) {
            abort(403, 'Access denied: Only administrators and staff can approve visitors.');
        }

        $visitor->update([
            'approval_status' => 'allowed',
            'approved_by' => auth()->id(),
        ]);

        // Dispatch event to trigger email notification
        \App\Events\VisitorApproved::dispatch($visitor);

        return redirect()->route('visitors.index')
            ->with('success', 'Visitor allowed successfully.');
    }

    public function deny(Request $request, Visitor $visitor)
    {
        // Verify visitor belongs to user's society
        if ($visitor->society_id !== auth()->user()->society_id && !auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Access denied: This visitor does not belong to your society.');
        }

        // Only Admin and Staff can deny visitors
        if (!auth()->user()->hasRole(['Admin', 'Staff', 'Super Admin'])) {
            abort(403, 'Access denied: Only administrators and staff can deny visitors.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $visitor->update([
            'approval_status' => 'denied',
            'rejection_reason' => $validated['reason'],
        ]);

        // Send visitor denial email
        \App\Services\EmailNotificationService::sendVisitorNotification($visitor, 'denied');

        return redirect()->route('visitors.index')
            ->with('success', 'Visitor denied successfully.');
    }

    /**
     * Quick stats endpoint for real-time web auto-refresh
     */
    public function stats(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        $base = Visitor::query();
        if ($societyId) $base->where('society_id', $societyId);

        if (!$user->hasRole(['Admin', 'Super Admin', 'Staff', 'Guard'])) {
            $flatIds = collect();
            $flatIds = $flatIds->merge($user->flats()->pluck('flats.id'));
            if ($user->owner_id) {
                $owner = \App\Models\Owner::find($user->owner_id);
                if ($owner) {
                    if ($owner->villa_area_id && $owner->villa_no) {
                        $flatIds = $flatIds->merge(Flat::where('villa_area_id', $owner->villa_area_id)->where('flat_number', $owner->villa_no)->pluck('id'));
                    }
                    if ($owner->building_id && $owner->flat_no) {
                        $flatIds = $flatIds->merge(Flat::where('building_id', $owner->building_id)->where('flat_number', $owner->flat_no)->pluck('id'));
                    }
                }
            }
            $flatIds = $flatIds->unique()->values()->toArray();
            $base->where(function ($q) use ($user, $flatIds) {
                $q->where('host_user_id', $user->id);
                if (!empty($flatIds)) $q->orWhereIn('flat_id', $flatIds);
            });
        }

        return response()->json([
            'success' => true,
            'stats' => [
                'total'       => (clone $base)->count(),
                'today'       => (clone $base)->whereDate('expected_entry_time', today())->count(),
                'pending'     => (clone $base)->where('approval_status', 'pending')->count(),
                'allowed'     => (clone $base)->where('approval_status', 'allowed')->count(),
                'denied'      => (clone $base)->where('approval_status', 'denied')->count(),
                'checked_in'  => (clone $base)->where('entry_status', 'entered')->count(),
                'checked_out' => (clone $base)->where('entry_status', 'exited')->count(),
            ],
        ]);
    }

    /**
     * Export visitors to CSV
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // Check access
        if (!$user->hasRole(['Admin', 'Super Admin'])) {
            abort(403, 'Unauthorized access to export.');
        }

        // Get visitors based on filters
        $query = Visitor::where('society_id', $societyId)->with(['flat.building', 'host']);

        // Apply filters if provided
        if ($request->filled('status')) {
            $query->where('approval_status', $request->status);
        }

        if ($request->filled('visitor_type')) {
            $query->where('visitor_type', $request->visitor_type);
        }

        $visitors = $query->orderBy('expected_entry_time', 'desc')->get();

        // Create CSV content
        $csv = "Visitor Name,Phone,Type,Apartment,Expected Entry,Expected Exit,Approval Status,Entry Status\n";

        foreach ($visitors as $visitor) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $visitor->visitor_name,
                $visitor->visitor_phone,
                $visitor->visitor_type,
                ($visitor->flat->building->name ?? '') . ' - ' . ($visitor->flat->flat_number ?? ''),
                $visitor->expected_entry_time ? $visitor->expected_entry_time->format('M d, Y H:i') : '',
                $visitor->expected_exit_time ? $visitor->expected_exit_time->format('M d, Y H:i') : '',
                $visitor->approval_status,
                $visitor->entry_status
            );
        }

        // Return CSV file
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="visitors_' . now()->format('Y-m-d_H-i-s') . '.csv"',
        ]);
    }
}
