<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;

class ServiceProviderController extends BaseController
{
    public function index()
    {
        $societyId = $this->getSocietyId();
        
        // If still no society, return empty results
        if (!$societyId) {
            $providers = collect();
            $services = Service::enabled()->ordered()->get();
            return view('service-providers.index', compact('providers', 'services'));
        }

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

        return view('service-providers.index', compact('providers', 'services'));
    }

    public function create()
    {
        // Only admin and super admin can create service providers
        $this->authorize('manage_services');
        
        $services = Service::enabled()->ordered()->get();
        
        // Debug: Log the services count
        \Log::info('Services loaded for dropdown: ' . $services->count());
        foreach ($services as $service) {
            \Log::info('Service: ' . $service->name . ' (enabled: ' . ($service->is_enabled ? 'yes' : 'no') . ')');
        }
        
        return view('service-providers.create', compact('services'));
    }

    public function store(Request $request)
    {
        // Only admin and super admin can create service providers
        $this->authorize('manage_services');

        $request->validate([
            'service_id' => 'required|exists:services,id',
            'name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
            'website' => 'nullable|url|max:255',
            'photo' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'availability' => 'required|in:available,not_available',
            'is_daily_help' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'price_type' => 'required|in:per_day,per_month,per_visit',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        $societyId = $this->getSocietyId();
        
        $data = [
            'society_id' => $societyId,
            'service_id' => $request->service_id,
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'website' => $request->website,
            'availability' => $request->availability,
            'is_daily_help' => $request->boolean('is_daily_help'),
            'price' => $request->price,
            'price_type' => $request->price_type,
            'status' => $request->status,
            'notes' => $request->notes,
        ];

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $photoPath = $photo->store('service-providers', 'public');
            $data['photo'] = $photoPath;
        }

        ServiceProvider::create($data);

        return redirect()->route('service-providers.index')
            ->with('success', 'Service provider added successfully!');
    }

    public function show(ServiceProvider $serviceProvider)
    {
        $this->authorize('view', $serviceProvider);
        
        $serviceProvider->load(['service', 'attendance' => function($query) {
            $query->latest()->take(10);
        }]);

        return view('service-providers.show', compact('serviceProvider'));
    }

    public function edit(ServiceProvider $serviceProvider)
    {
        // Only admin and super admin can edit service providers
        $this->authorize('manage_services');
        
        $services = Service::enabled()->ordered()->get();
        return view('service-providers.edit', compact('serviceProvider', 'services'));
    }

    public function update(Request $request, ServiceProvider $serviceProvider)
    {
        // Only admin and super admin can update service providers
        $this->authorize('manage_services');

        $request->validate([
            'service_id' => 'required|exists:services,id',
            'name' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
            'website' => 'nullable|url|max:255',
            'photo' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'availability' => 'required|in:available,not_available',
            'is_daily_help' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'price_type' => 'required|in:per_day,per_month,per_visit',
            'status' => 'required|in:active,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        $data = [
            'service_id' => $request->service_id,
            'name' => $request->name,
            'contact_number' => $request->contact_number,
            'website' => $request->website,
            'availability' => $request->availability,
            'is_daily_help' => $request->boolean('is_daily_help'),
            'price' => $request->price,
            'price_type' => $request->price_type,
            'status' => $request->status,
            'notes' => $request->notes,
        ];

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($serviceProvider->photo && \Storage::disk('public')->exists($serviceProvider->photo)) {
                \Storage::disk('public')->delete($serviceProvider->photo);
            }
            
            $photo = $request->file('photo');
            $photoPath = $photo->store('service-providers', 'public');
            $data['photo'] = $photoPath;
        }

        $serviceProvider->update($data);

        return redirect()->route('service-providers.index')
            ->with('success', 'Service provider updated successfully!');
    }

    public function destroy(ServiceProvider $serviceProvider)
    {
        // Only admin and super admin can delete service providers
        $this->authorize('manage_services');
        
        $serviceProvider->delete();

        return redirect()->route('service-providers.index')
            ->with('success', 'Service provider deleted successfully!');
    }

    public function quickUpdate(Request $request, ServiceProvider $serviceProvider)
    {
        // Only admin and super admin can update service providers
        $this->authorize('manage_services');

        $request->validate([
            'availability' => 'required|in:available,not_available',
            'price' => 'nullable|numeric|min:0',
            'price_type' => 'required|in:per_day,per_month,per_visit,per_hour',
            'notes' => 'nullable|string|max:1000',
        ]);

        $serviceProvider->update([
            'availability' => $request->availability,
            'price' => $request->price,
            'price_type' => $request->price_type,
            'notes' => $request->notes ? ($serviceProvider->notes . "\n" . $request->notes) : $serviceProvider->notes,
        ]);

        return redirect()->route('services.index')
            ->with('success', 'Service provider updated successfully!');
    }

    public function toggleAvailability(Request $request, ServiceProvider $serviceProvider)
    {
        // Only admin and super admin can update service providers
        $this->authorize('manage_services');

        $request->validate([
            'availability' => 'required|in:available,not_available',
        ]);

        $serviceProvider->update([
            'availability' => $request->availability,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Availability updated successfully!',
            'availability' => $serviceProvider->availability
        ]);
    }
}