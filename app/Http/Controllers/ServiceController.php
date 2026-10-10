<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceAttendance;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends BaseController
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // Get service filter from request
        $serviceFilter = $request->get('service_filter');

        // Get services grouped by category
        $services = Service::enabled()
            ->with(['providers' => function($query) use ($societyId) {
                $query->bySociety($societyId)->active();
            }])
            ->ordered()
            ->get()
            ->groupBy('category');

        // Get filtered providers based on service selection
        if ($serviceFilter && $serviceFilter !== 'all') {
            // Find service by name
            $selectedService = Service::where('name', $serviceFilter)->first();
            if ($selectedService) {
                $providers = ServiceProvider::bySociety($societyId)
                    ->byService($selectedService->id)
                    ->active()
                    ->with('service')
                    ->get();
            } else {
                $providers = collect();
            }
        } else {
            // Show all providers if no filter or "all" is selected
            $providers = ServiceProvider::bySociety($societyId)
                ->active()
                ->with('service')
                ->get();
        }

        // Apply search filter if provided
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $providers = $providers->filter(function($provider) use ($searchTerm) {
                return stripos($provider->name, $searchTerm) !== false ||
                       stripos($provider->contact_number, $searchTerm) !== false;
            });
        }

        // Get today's attendance for clock-in/out
        $todayAttendance = ServiceAttendance::bySociety($societyId)
            ->today()
            ->with(['serviceProvider.service', 'createdBy'])
            ->get();

        return view('services.index', compact('services', 'providers', 'todayAttendance'));
    }

    public function providers()
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        $providers = ServiceProvider::bySociety($societyId)
            ->with('service')
            ->when(request('search'), function($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('contact_number', 'like', "%{$search}%");
            })
            ->when(request('service_id'), function($query, $serviceId) {
                $query->byService($serviceId);
            })
            ->when(request('status'), function($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(15);

        $services = Service::enabled()->ordered()->get();

        return view('services.providers', compact('providers', 'services'));
    }

    public function clockIn(Request $request)
    {
        $request->validate([
            'service_provider_id' => 'required|exists:service_providers,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $provider = ServiceProvider::findOrFail($request->service_provider_id);
        $this->authorizeAttendanceFor($provider);

        // Check if already clocked in today
        $existingAttendance = ServiceAttendance::where('service_provider_id', $provider->id)
            ->where('attendance_date', today())
            ->first();

        if ($existingAttendance && $existingAttendance->clock_in_time) {
            return back()->with('error', 'This service provider is already clocked in today.');
        }

        // Create or update attendance record
        ServiceAttendance::updateOrCreate(
            [
                'service_provider_id' => $provider->id,
                'attendance_date' => today(),
            ],
            [
                'society_id' => $provider->society_id,
                'created_by' => $user->id,
                'clock_in_time' => now()->format('H:i'),
                'notes' => $request->notes,
            ]
        );

        return back()->with('success', "{$provider->name} has been clocked in successfully.");
    }

    public function clockOut(Request $request)
    {
        $request->validate([
            'service_provider_id' => 'required|exists:service_providers,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $provider = ServiceProvider::findOrFail($request->service_provider_id);
        $this->authorizeAttendanceFor($provider);

        $attendance = ServiceAttendance::where('service_provider_id', $provider->id)
            ->where('attendance_date', today())
            ->first();

        if (!$attendance || !$attendance->clock_in_time) {
            return back()->with('error', 'This service provider is not clocked in today.');
        }

        if ($attendance->clock_out_time) {
            return back()->with('error', 'This service provider is already clocked out today.');
        }

        $attendance->update([
            'clock_out_time' => now()->format('H:i'),
            'notes' => $request->notes ? ($attendance->notes . "\n" . $request->notes) : $attendance->notes,
        ]);

        return back()->with('success', "{$provider->name} has been clocked out successfully.");
    }

    /**
     * Who may mark a service provider in or out: exactly the people the providers page offers the
     * buttons to (Admin, Staff, and owners, who log their own help's arrival), and only for a
     * provider of their own society. These two routes sit under "all authenticated users" with no
     * check at all, so any resident - or anyone in another society, by guessing an id - could write
     * attendance for any provider.
     */
    private function authorizeAttendanceFor(ServiceProvider $provider): void
    {
        abort_unless(
            auth()->user()->hasAnyRole(['Super Admin', 'Admin', 'Staff', 'Owner', 'Villa Owner', 'Apartment Owner']),
            403,
            'You are not allowed to record service provider attendance.'
        );

        $this->authorizeSocietyAccess($provider);
    }
    public function attendance(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        $date = $request->get('date', today()->format('Y-m-d'));

        $attendance = ServiceAttendance::bySociety($societyId)
            ->byDate($date)
            ->with(['serviceProvider.service', 'createdBy'])
            ->latest()
            ->get();

        return view('services.attendance', compact('attendance', 'date'));
    }

    /**
     * Service requests: pending -> assigned -> in_progress -> completed, or
     * cancelled from pending/assigned. Staff manage every request in their
     * society; a resident manages (views, cancels) only their own.
     *
     * This was entirely missing before: ServiceApiController::requestService()
     * could create a row, but nothing - web or mobile - ever read it back.
     */
    public function requests(Request $request)
    {
        $user      = auth()->user();
        $societyId = $user->society_id;
        $isStaff   = $user->hasAnyRole(['Super Admin', 'Admin', 'Staff']);

        $query = ServiceRequest::where('society_id', $societyId)
            ->with(['service', 'user', 'assignedProvider']);

        if (!$isStaff) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(20)->withQueryString();

        $providers = ServiceProvider::bySociety($societyId)->active()->with('service')->get();

        return view('services.requests', compact('requests', 'providers', 'isStaff'));
    }

    /**
     * Assign a request to a provider of the same society AND the same
     * service. The record itself is checked here, not just the
     * 'manage_services' permission - see ServiceProviderController's
     * history: a role permission with no model attached is not
     * society-scoped, and this route is reached by a request id.
     */
    public function assignRequest(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeSocietyAccess($serviceRequest);

        if (!auth()->user()->hasAnyRole(['Super Admin', 'Admin'])) {
            abort(403, 'Only society admins can assign service requests.');
        }

        $request->validate([
            'assigned_provider_id' => [
                'required',
                Rule::exists('service_providers', 'id')
                    ->where('society_id', $serviceRequest->society_id)
                    ->where('service_id', $serviceRequest->service_id),
            ],
        ]);

        // The lifecycle rules (still open, an active provider of this service)
        // live in ServiceRequestWorkflow, shared with the mobile admin API.
        app(\App\Services\ServiceRequestWorkflow::class)
            ->assign($serviceRequest, (int) $request->assigned_provider_id, auth()->user());

        return back()->with('success', 'Service request assigned.');
    }

    /**
     * Move a request forward (staff only, beyond cancel) or cancel it
     * (staff, or the resident who raised it, while it is still open).
     */
    public function updateRequestStatus(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeSocietyAccess($serviceRequest);

        $user = auth()->user();

        $request->validate([
            'status'               => ['required', Rule::in(ServiceRequest::STATUSES)],
            'cancellation_reason'  => 'nullable|string|max:500',
        ]);

        app(\App\Services\ServiceRequestWorkflow::class)
            ->changeStatus($serviceRequest, $request->status, $user, $request->cancellation_reason);

        return back()->with('success', 'Service request updated.');
    }
}
