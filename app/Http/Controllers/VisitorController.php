<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Models\Flat;
use App\Support\VisitorPropertyScope;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class VisitorController extends BaseController
{
    use HandlesFormSubmissions;
    public function index(Request $request)
    {
        $user = auth()->user();

        // If user has no society_id, deny access (except Super Admin)
        if (!$user->society_id && !$user->hasRole('Super Admin')) {
            abort(403, 'Access denied: No society assigned.');
        }

        // Admin/Staff/Guard see the whole society; tenants and owners see
        // only their own properties (and visitors they registered).
        $query = VisitorPropertyScope::scopeQuery(
            Visitor::with(['flat.building', 'flat.villaArea', 'host']),
            $user
        );

        // Property filter
        if ($request->filled('flat_id')) {
            $query->where('flat_id', (int) $request->flat_id);
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
                    $query->whereBetween('expected_entry_time', \App\Support\SocietyClock::dayBounds());
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

        // Stats use the same visibility scope as the list
        $statsQuery = VisitorPropertyScope::scopeQuery(Visitor::query(), $user);

        $stats = [
            'total' => $statsQuery->count(),
            'today' => (clone $statsQuery)->whereBetween('expected_entry_time', \App\Support\SocietyClock::dayBounds())->count(),
            'pending' => (clone $statsQuery)->where('approval_status', 'pending')->count(),
            'allowed' => (clone $statsQuery)->where('approval_status', 'allowed')->count(),
            'denied' => (clone $statsQuery)->where('approval_status', 'denied')->count(),
            'checked_in' => (clone $statsQuery)->where('entry_status', 'entered')->count(),
            'checked_out' => (clone $statsQuery)->where('entry_status', 'exited')->count(),
        ];

        // Property filter options: the whole society for Admin/Staff/Guard,
        // only the user's own properties for tenants and owners.
        $properties = VisitorPropertyScope::labelledOptions(VisitorPropertyScope::selectableFlats($user));

        // Which rows this user may Allow/Deny from the list.
        $canApproveAny = $user->hasRole(['Admin', 'Staff', 'Super Admin']);
        $approvableFlatIds = VisitorPropertyScope::isSocietyWide($user)
            ? []
            : VisitorPropertyScope::residentFlatIds($user);

        return view('visitors.index', compact('visitors', 'stats', 'properties', 'canApproveAny', 'approvableFlatIds'));
    }

    /**
     * Admin/Staff pick any property in their society; a tenant or owner with
     * one property never picks anything - the destination is already known
     * and is shown read-only. Villa Area is never asked for: the property
     * itself identifies the destination.
     */
    public function create()
    {
        $user = auth()->user();
        $flats = VisitorPropertyScope::selectableFlats($user);

        if ($flats->isEmpty() && !VisitorPropertyScope::isSocietyWide($user)) {
            return redirect()->route('visitors.index')
                ->with('error', 'No property is assigned to your account yet. Please contact your society admin.');
        }

        $fixedProperty = (!VisitorPropertyScope::isSocietyWide($user) && $flats->count() === 1)
            ? $flats->first()
            : null;

        $properties = $fixedProperty ? [] : VisitorPropertyScope::labelledOptions($flats);
        $fixedPropertyLabel = $fixedProperty ? VisitorPropertyScope::unitLabel($fixedProperty) : null;
        $isResident = !VisitorPropertyScope::isSocietyWide($user);

        return view('visitors.create', compact('properties', 'fixedProperty', 'fixedPropertyLabel', 'isResident'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'flat_id' => 'nullable|integer',
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

        // Resolved and authorised before the transaction wrapper, which
        // would otherwise swallow a 403 into a generic error redirect.
        $flat = $this->resolveDestination($user, $validated['flat_id'] ?? null);

        // A tenant or owner inviting their own guest is pre-approved (the
        // Visitor model issues the gate QR token on approval); anything an
        // Admin/Staff member registers still waits for the resident.
        $isResident = !VisitorPropertyScope::isSocietyWide($user);

        return $this->handleFormSubmission(function () use ($request, $validated, $flat, $user, $isResident) {
            // Combine date and time for entry
            $entryDateTime = $validated['expected_entry_time'] . ' ' . $validated['in_time'];
            
            // Combine date and time for exit if provided
            $exitDateTime = null;
            if (!empty($validated['expected_exit_time']) && !empty($validated['out_time'])) {
                $exitDateTime = $validated['expected_exit_time'] . ' ' . $validated['out_time'];
            }

            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $this->handleFileUpload($request, 'photo', 'visitor-photos', ['jpeg', 'png', 'jpg', 'gif']);
            }

            $visitor = Visitor::create([
                'society_id' => $flat->society_id,
                'flat_id' => $flat->id,
                'host_user_id' => $user->id,
                'visitor_name' => $validated['visitor_name'],
                'visitor_phone' => $validated['visitor_phone'],
                'visitor_type' => $validated['visitor_type'],
                'purpose' => $validated['purpose'] ?? null,
                'expected_count' => $validated['expected_count'] ?? 1,
                'vehicle_number' => $validated['vehicle_number'] ?? null,
                'expected_entry_time' => $entryDateTime,
                'expected_exit_time' => $exitDateTime,
                'photo' => $photoPath,
                'approval_status' => $isResident ? 'allowed' : 'pending',
                'approved_by' => $isResident ? $user->id : null,
                'entry_status' => 'pending',
            ]);

            return $visitor;
        }, $isResident
            ? 'Visitor invited and pre-approved. Security can verify them at the gate.'
            : 'Visitor registered successfully.', 'visitors.index', false);
    }

    /**
     * The property a new visitor is going to, resolved server-side.
     *
     * A tenant/owner with a single property needs to submit nothing; any
     * submitted flat_id - from any role - must be one the user may manage,
     * so it cannot be swapped for a neighbour's or another society's unit.
     */
    private function resolveDestination(\App\Models\User $user, $flatId): Flat
    {
        if ($flatId === null && !VisitorPropertyScope::isSocietyWide($user)) {
            $own = VisitorPropertyScope::residentFlatIds($user);

            // No property at all: there is nothing this account may invite a
            // visitor to, so this is a permission problem, not a form error.
            if ($own === []) {
                abort(403, 'No property is assigned to your account yet. Please contact your society admin.');
            }

            if (count($own) === 1) {
                $flatId = $own[0];
            }
        }

        if ($flatId === null) {
            throw ValidationException::withMessages(['flat_id' => 'Please select the property being visited.']);
        }

        $flat = Flat::find((int) $flatId);

        if (!$flat) {
            throw ValidationException::withMessages(['flat_id' => 'The selected property does not exist.']);
        }

        if (!VisitorPropertyScope::canManageFlat($user, $flat)) {
            abort(403, 'Access denied: You can only register visitors for your own property.');
        }

        return $flat;
    }

    public function show(Visitor $visitor)
    {
        // Admin/Staff/Guard: any visitor in their society. Tenants/owners:
        // visitors to their own property, or ones they registered.
        if (!VisitorPropertyScope::canViewVisitor(auth()->user(), $visitor)) {
            abort(403, 'Access denied: This visitor is not for your property.');
        }

        $visitor->load(['flat.building', 'flat.villaArea', 'host', 'society', 'logs.user']);
        $canDecide = $visitor->entry_status === 'pending'
            && $this->approvalRole($visitor) !== null;

        return view('visitors.show', compact('visitor', 'canDecide'));
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
                $oldPhoto = $visitor->photo;
                $validated['photo'] = $this->handleFileUpload($request, 'photo', 'visitor-photos', ['jpeg', 'png', 'jpg', 'gif']);
                if ($oldPhoto) {
                    $this->deleteUploadedFile($oldPhoto);
                }
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

    /**
     * Who may Allow/Deny this visitor:
     *   'staff'    - Admin/Staff/Super Admin, any visitor in their society;
     *   'resident' - a tenant/owner, only visitors to their own property;
     *   null       - nobody else (Guard records the decision, it doesn't make it).
     */
    private function approvalRole(Visitor $visitor): ?string
    {
        $user = auth()->user();

        if ($user->hasRole(['Admin', 'Staff', 'Super Admin'])) {
            return VisitorPropertyScope::canManageVisitor($user, $visitor) ? 'staff' : null;
        }

        if (!VisitorPropertyScope::isSocietyWide($user) && VisitorPropertyScope::canManageVisitor($user, $visitor)) {
            return 'resident';
        }

        return null;
    }

    public function allow(Visitor $visitor)
    {
        $role = $this->approvalRole($visitor);

        if ($role === null) {
            abort(403, 'Access denied: You can only approve visitors for your own property.');
        }

        if ($role === 'resident' && $visitor->entry_status !== 'pending') {
            return redirect()->route('visitors.index')
                ->with('error', 'This visitor has already arrived; the decision can no longer be changed.');
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
        $role = $this->approvalRole($visitor);

        if ($role === null) {
            abort(403, 'Access denied: You can only deny visitors for your own property.');
        }

        if ($role === 'resident' && $visitor->entry_status !== 'pending') {
            return redirect()->route('visitors.index')
                ->with('error', 'This visitor has already arrived; the decision can no longer be changed.');
        }

        // Staff must record why; a resident turning away their own visitor
        // need not explain themselves.
        $validated = $request->validate([
            'reason' => ($role === 'staff' ? 'required' : 'nullable') . '|string|max:500',
        ]);

        $visitor->update([
            'approval_status' => 'denied',
            'rejection_reason' => $validated['reason'] ?? 'Denied by resident',
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
        $base = VisitorPropertyScope::scopeQuery(Visitor::query(), auth()->user());

        return response()->json([
            'success' => true,
            'stats' => [
                'total'       => (clone $base)->count(),
                'today'       => (clone $base)->whereBetween('expected_entry_time', \App\Support\SocietyClock::dayBounds())->count(),
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
