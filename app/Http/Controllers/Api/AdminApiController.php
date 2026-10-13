<?php

namespace App\Http\Controllers\Api;

use App\Events\FacilityBookingStatusUpdated;
use App\Events\TicketUpdated;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\FacilityBooking;
use App\Models\MaintenanceBill;
use App\Models\ServiceProvider;
use App\Models\ServiceRequest;
use App\Models\ShiftAssignment;
use App\Models\Visitor;
use App\Services\ServiceRequestWorkflow;
use App\Support\SocietyClock;
use App\Support\SocietyOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What a Society Admin does from the phone: see the society at a glance, then
 * deal with what is waiting - approve a facility booking, move a complaint on,
 * assign a service request. The same numbers and the same workflows as the web
 * (SocietyOverview, ServiceRequestWorkflow, the complaint/booking events), but
 * with society-wide, not own-data, scope.
 *
 * Every query starts from the signed-in admin's own society_id - there is no
 * way to name another society - and a record outside it is a plain 404.
 */
class AdminApiController extends Controller
{
    /** The admin's society id, or null (and a 403 to return) when this account is not a society admin. */
    private function societyId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasRole('Admin') && $user->society_id ? (int) $user->society_id : null;
    }

    private function denied(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'This is for society admins.'], 403);
    }

    // ── dashboard ───────────────────────────────────────────────────────────

    public function dashboard(Request $request, \App\Services\GuardShiftService $shifts): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }

        $society = $request->user()->society;
        $k       = SocietyOverview::kpis($sid);

        $pendingVisitors  = Visitor::where('society_id', $sid)->where('approval_status', 'pending')->count();
        $openComplaints   = Complaint::where('society_id', $sid)->where('status', 'open')->count();
        $pendingBookings  = FacilityBooking::where('society_id', $sid)->where('status', 'pending')->count();
        $overdueBills     = MaintenanceBill::where('society_id', $sid)->overdue()->count();

        // What has not been dealt with yet, right now - zero rows are left out.
        $attention = array_values(array_filter([
            ['key' => 'bookings',         'label' => 'Facility bookings to review',    'count' => $pendingBookings],
            ['key' => 'complaints',       'label' => 'New complaints',                 'count' => $openComplaints],
            ['key' => 'visitors',         'label' => 'Visitors waiting for approval',  'count' => $pendingVisitors],
            ['key' => 'bills',            'label' => 'Overdue bills',                  'count' => $overdueBills],
        ], fn ($i) => $i['count'] > 0));

        $now = SocietyClock::now($sid);
        $onDuty = ShiftAssignment::where('society_id', $sid)
            ->whereIn('status', [ShiftAssignment::STATUS_PRESENT, ShiftAssignment::STATUS_LATE])
            ->whereNotNull('checked_in_at')->whereNull('checked_out_at')
            ->with(['guardUser:id,name', 'gate:id,name', 'shift:id,name'])
            ->orderBy('checked_in_at')
            ->get()
            ->map(fn (ShiftAssignment $a) => [
                'guard' => $a->guardUser?->name,
                'gate'  => $a->gate?->name,
                'shift' => $a->shift?->name,
                'since' => $a->checked_in_at ? SocietyClock::local($a->checked_in_at, $sid)->format('H:i') : null,
            ])->values();

        $duty = $shifts->dutyBoard($sid, $now);

        return response()->json([
            'success' => true,
            'data'    => [
                'society' => ['name' => $society?->name, 'property_type' => $society?->propertyType()->value],
                'today'   => $now->toDateString(),
                'kpis'    => [
                    'residents'        => $k['total_residents'],
                    'owners'           => $k['active_owners'],
                    'tenants'          => $k['active_tenants'],
                    'units'            => $k['total_units'],
                    'occupied_units'   => $k['occupied_units'],
                    'vacant_units'     => $k['vacant_units'],
                    'visitors_today'   => $k['visitors_today'],
                    'open_tickets'     => $k['open_tickets'],
                    'pending_dues'     => $k['pending_dues'],
                    'pending_bills'    => $k['pending_bills_count'],
                    'monthly_revenue'  => (float) $k['monthly_revenue'],
                    'monthly_expenses' => $k['monthly_expenses'],
                ],
                'attention'     => $attention,
                'guards_on_duty' => $onDuty,   // kept for older app versions: checked-in guards only
                // Today's whole roster with a duty state per row (see GuardShiftService::dutyBoard); the first rows only.
                'guard_duty'    => ['date' => $duty['date'], 'counts' => $duty['counts'], 'items' => $duty['items']->take(6)->values()],
                'unread_notifications' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    // ── facility bookings ───────────────────────────────────────────────────

    public function facilityBookings(Request $request): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $request->validate(['status' => 'nullable|in:pending,approved,rejected,cancelled,completed,all']);

        $status = $request->input('status', 'pending');
        $page = FacilityBooking::where('society_id', $sid)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['facility:id,name', 'user:id,name,phone', 'flat:id,flat_number'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderBy('booking_date')->orderBy('start_time')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $page->map(fn (FacilityBooking $b) => $this->formatBooking($b))->values(),
            'meta'    => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function facilityBookingStatus(Request $request, int $id): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $data = $request->validate([
            'status'      => ['required', Rule::in(['approved', 'rejected', 'cancelled', 'completed'])],
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $booking = FacilityBooking::where('society_id', $sid)->with(['facility:id,name', 'user:id,name,phone', 'flat:id,flat_number'])->findOrFail($id);

        // pending -> approved | rejected | cancelled ; approved -> cancelled | completed.
        // Anything else (re-approving a cancelled booking, completing a pending
        // one) is a mistake the office should not be able to make by a mis-tap.
        $allowed = ['pending' => ['approved', 'rejected', 'cancelled'], 'approved' => ['cancelled', 'completed']][$booking->status] ?? [];
        if (!in_array($data['status'], $allowed, true)) {
            return response()->json(['success' => false, 'message' => "A {$booking->status} booking cannot be marked {$data['status']}."], 422);
        }

        if ($conflict = \App\Services\FacilityBookingService::transitionConflict($booking, $data['status'])) {
            return response()->json(['success' => false, 'message' => $conflict], 409);
        }

        $previous = $booking->status;
        $booking->update([
            'status'       => $data['status'],
            'admin_notes'  => $data['admin_notes'] ?? $booking->admin_notes,
            'approved_at'  => $data['status'] === 'approved' ? now() : $booking->approved_at,
            'cancelled_at' => $data['status'] === 'cancelled' ? now() : $booking->cancelled_at,
        ]);
        FacilityBookingStatusUpdated::dispatch($booking, $previous);

        return response()->json(['success' => true, 'message' => 'Booking ' . $data['status'] . '.', 'data' => $this->formatBooking($booking->fresh(['facility', 'user', 'flat']))]);
    }

    // ── complaints ──────────────────────────────────────────────────────────

    public function complaints(Request $request): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $request->validate(['status' => ['nullable', Rule::in([...Complaint::STATUSES, 'unresolved', 'all'])]]);

        $status = $request->input('status', 'unresolved');
        $page = Complaint::where('society_id', $sid)
            ->when($status === 'unresolved', fn ($q) => $q->whereIn('status', Complaint::OPEN_STATUSES))
            ->when(!in_array($status, ['unresolved', 'all'], true), fn ($q) => $q->where('status', $status))
            ->with(['category:id,name', 'createdBy:id,name,phone', 'assignedTo:id,name', 'flat:id,flat_number'])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $page->map(fn (Complaint $c) => $this->formatComplaint($c))->values(),
            'meta'    => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Move a complaint on, with an optional note the resident sees. */
    public function complaintStatus(Request $request, int $id): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $data = $request->validate([
            'status'  => ['required', Rule::in(Complaint::STATUSES)],
            'message' => 'nullable|string|max:1000',
        ]);

        $complaint = Complaint::where('society_id', $sid)->findOrFail($id);

        if ($data['status'] === $complaint->status && empty($data['message'])) {
            return response()->json(['success' => false, 'message' => "This complaint is already {$complaint->status}."], 422);
        }

        $old = $complaint->status;
        $label = ucwords(str_replace('_', ' ', $data['status']));
        $complaint->addUpdate($request->user(), $data['message'] ?? "Status changed to {$label}.", 'status_change');

        if ($data['status'] !== $old) {
            $complaint->update(['status' => $data['status']]);
            $complaint->syncResolutionTimestamp($old);
            TicketUpdated::dispatch($complaint);
        }

        return response()->json(['success' => true, 'message' => "Complaint {$label}.", 'data' => $this->formatComplaint($complaint->fresh(['category', 'createdBy', 'assignedTo', 'flat']))]);
    }

    // ── service requests ────────────────────────────────────────────────────

    public function serviceRequests(Request $request): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $request->validate(['status' => ['nullable', Rule::in([...ServiceRequest::STATUSES, 'open', 'all'])]]);

        $status = $request->input('status', 'open');
        $page = ServiceRequest::where('society_id', $sid)
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ServiceRequest::OPEN_STATUSES))
            ->when(!in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->with(['service:id,name', 'user:id,name,phone', 'assignedProvider:id,name,contact_number'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'assigned' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate(20);

        // Providers offered for assignment: this society's active ones, by service.
        $providers = ServiceProvider::where('society_id', $sid)->where('status', 'active')
            ->get(['id', 'service_id', 'name', 'contact_number'])
            ->groupBy('service_id');

        return response()->json([
            'success' => true,
            'data'    => $page->map(fn (ServiceRequest $r) => $this->formatServiceRequest($r, $providers->get($r->service_id, collect())))->values(),
            'meta'    => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function assignServiceRequest(Request $request, int $id, ServiceRequestWorkflow $workflow): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $data = $request->validate(['provider_id' => 'required|integer']);

        $serviceRequest = ServiceRequest::where('society_id', $sid)->findOrFail($id);
        $workflow->assign($serviceRequest, (int) $data['provider_id'], $request->user());

        return response()->json(['success' => true, 'message' => 'Service request assigned.', 'data' => $this->formatServiceRequest($serviceRequest->fresh(['service', 'user', 'assignedProvider']), collect())]);
    }

    public function serviceRequestStatus(Request $request, int $id, ServiceRequestWorkflow $workflow): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $data = $request->validate([
            'status'              => ['required', Rule::in(['in_progress', 'completed', 'cancelled'])],
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $serviceRequest = ServiceRequest::where('society_id', $sid)->findOrFail($id);
        $workflow->changeStatus($serviceRequest, $data['status'], $request->user(), $data['cancellation_reason'] ?? null);

        return response()->json(['success' => true, 'message' => 'Service request updated.', 'data' => $this->formatServiceRequest($serviceRequest->fresh(['service', 'user', 'assignedProvider']), collect())]);
    }

    // ── formatters ──────────────────────────────────────────────────────────

    private function formatBooking(FacilityBooking $b): array
    {
        return [
            'id'             => $b->id,
            'booking_number' => $b->booking_number,
            'facility'       => $b->facility?->name,
            'resident'       => ['name' => $b->user?->name, 'phone' => $b->user?->phone],
            'property'       => $b->flat?->flat_number,
            'booking_date'   => $b->booking_date?->toDateString(),
            'start_time'     => $b->start_time?->format('H:i'),
            'end_time'       => $b->end_time?->format('H:i'),
            'purpose'        => $b->purpose,
            'expected_guests' => (int) $b->expected_guests,
            'amount'         => (float) ($b->booking_amount ?? 0),
            'status'         => $b->status,
            'admin_notes'    => $b->admin_notes,
            'actions'        => ['pending' => ['approved', 'rejected', 'cancelled'], 'approved' => ['cancelled', 'completed']][$b->status] ?? [],
            'created_at'     => $b->created_at?->toIso8601String(),
        ];
    }

    private function formatComplaint(Complaint $c): array
    {
        return [
            'id'               => $c->id,
            'complaint_number' => $c->complaint_number,
            'title'            => $c->title,
            'description'      => $c->description,
            'category'         => $c->category?->name,
            'priority'         => $c->priority,
            'status'           => $c->status,
            'resident'         => ['name' => $c->createdBy?->name, 'phone' => $c->createdBy?->phone],
            'property'         => $c->flat?->flat_number,
            'assigned_to'      => $c->assignedTo?->name,
            'created_at'       => $c->created_at?->toIso8601String(),
        ];
    }

    private function formatServiceRequest(ServiceRequest $r, $providers): array
    {
        return [
            'id'             => $r->id,
            'service'        => $r->service?->name,
            'description'    => $r->description,
            'preferred_date' => $r->preferred_date?->toDateString(),
            'status'         => $r->status,
            'resident'       => ['name' => $r->user?->name, 'phone' => $r->user?->phone],
            'provider'       => $r->assignedProvider ? ['id' => $r->assignedProvider->id, 'name' => $r->assignedProvider->name, 'phone' => $r->assignedProvider->contact_number] : null,
            'providers'      => $providers->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'phone' => $p->contact_number])->values(),
            'created_at'     => $r->created_at?->toIso8601String(),
        ];
    }
}
