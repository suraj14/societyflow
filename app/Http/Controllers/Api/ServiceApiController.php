<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Services are global (no society_id) — return all enabled/active services
        $services = Service::where(function ($q) {
                $q->where('is_enabled', true)
                  ->orWhereNull('is_enabled');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'description' => null,
                'category'    => $s->category,
                'charges'     => 0.0,
                'status'      => 'active',
            ]);

        return response()->json(['success' => true, 'data' => $services]);
    }

    public function providers(Request $request): JsonResponse
    {
        $providers = ServiceProvider::where('society_id', $request->user()->society_id)
            ->where('status', 'active')
            ->with('service')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'service_id'   => $p->service_id,
                'name'         => $p->name,
                'phone'        => $p->contact_number, // correct field name
                'service_name' => $p->service?->name ?? 'General',
                'category'     => $p->service?->category ?? 'General',
                'charges'      => (float) ($p->price ?? 0),
                'price_type'   => $p->price_type ?? '',
                'availability' => $p->availability,
                'is_daily_help'=> (bool) $p->is_daily_help,
                'rating'       => 0, // no rating column — default 0
                'status'       => $p->status,
            ]);

        return response()->json(['success' => true, 'data' => $providers]);
    }

    public function requestService(Request $request): JsonResponse
    {
        $request->validate([
            'service_id'  => 'required|exists:services,id',
            'description' => 'required|string|max:500',
            'preferred_date' => 'nullable|date|after_or_equal:today',
        ]);

        $user = $request->user();

        // A resident (or the office itself) asks for a service; gate staff and
        // other society workers, who have no home here, do not. "Resident"
        // means having a unit - by role, or by residency for an account whose
        // role was never assigned (a role-name list alone locked those out).
        $isOffice   = $user->hasAnyRole(['Admin', 'Super Admin']);
        $isResident = $user->hasAnyRole(['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'])
            || \App\Support\UserFlatResolver::flatIdsFor($user) !== [];
        if (!$isOffice && !$isResident) {
            return response()->json(['success' => false, 'message' => 'Service requests are made by residents.'], 403);
        }

        $serviceRequest = ServiceRequest::create([
            'society_id'     => $user->society_id,
            'user_id'        => $user->id,
            'service_id'     => $request->service_id,
            'description'    => $request->description,
            'preferred_date' => $request->preferred_date,
            'status'         => 'pending',
        ]);
        \App\Support\BusinessNotifications::serviceRequestCreated($serviceRequest);

        return response()->json([
            'success' => true,
            'message' => 'Service request submitted successfully!',
            'data'    => $this->formatServiceRequest($serviceRequest),
        ], 201);
    }

    /**
     * The requester's own service requests. Staff coordination (assign,
     * start, complete) is the web admin flow for now - the gap this fills
     * is that a resident could create a request but never see it again.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $requests = ServiceRequest::where('user_id', $request->user()->id)
            ->with(['service', 'assignedProvider'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $requests->map(fn ($r) => $this->formatServiceRequest($r)),
            'meta'    => [
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
                'total'        => $requests->total(),
            ],
        ]);
    }

    /** Cancel a still-open request of one's own. */
    public function cancelRequest(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $serviceRequest = ServiceRequest::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$serviceRequest) {
            return response()->json(['success' => false, 'message' => 'Service request not found'], 404);
        }

        if (!$serviceRequest->isOpen()) {
            return response()->json(['success' => false, 'message' => 'This request is already ' . $serviceRequest->status . '.'], 422);
        }

        $serviceRequest->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        \App\Services\EmailNotificationService::sendServiceRequestNotification($serviceRequest, 'cancelled');
        \App\Support\BusinessNotifications::serviceRequestUpdated($serviceRequest, 'cancelled');

        return response()->json(['success' => true, 'message' => 'Service request cancelled.']);
    }

    private function formatServiceRequest(ServiceRequest $r): array
    {
        return [
            'id'               => $r->id,
            'service_name'     => $r->service?->name,
            'description'      => $r->description,
            'preferred_date'   => optional($r->preferred_date)->toDateString(),
            'status'           => $r->status,
            'assigned_provider'=> $r->assignedProvider?->name,
            'assigned_at'      => optional($r->assigned_at)->toIso8601String(),
            'completed_at'     => optional($r->completed_at)->toIso8601String(),
            'cancelled_at'     => optional($r->cancelled_at)->toIso8601String(),
            'created_at'       => $r->created_at->toIso8601String(),
        ];
    }
}
