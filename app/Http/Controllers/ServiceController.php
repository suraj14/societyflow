<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceAttendance;
use Illuminate\Http\Request;

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
                'society_id' => $user->society_id,
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
}