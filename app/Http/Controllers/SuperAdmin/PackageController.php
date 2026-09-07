<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    public function index()
    {
        $packages = SubscriptionPlan::withCount(['subscriptions'])->get();
        return view('super-admin.packages.index', compact('packages'));
    }

    public function create()
    {
        return view('super-admin.packages.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'max_flats' => 'nullable|integer|min:0',
            'features' => 'required|array',
            'status' => 'required|in:active,inactive',
        ]);

        // Prepare data with proper field mapping and explicit JSON encoding
        $baseSlug = \Str::slug($request->name);
        $slug = $baseSlug;
        $counter = 1;
        
        // Ensure unique slug
        while (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        $data = [
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'monthly_price' => $request->monthly_price,
            'yearly_price' => $request->annual_price, // Map annual_price to yearly_price
            'max_flats' => $request->max_flats ?? 0,
            'max_users' => 0, // Default value
            'max_staff' => 0, // Default value
            'features' => json_encode($request->features), // Explicitly encode as JSON
            'limitations' => json_encode([]), // Explicitly encode empty array as JSON
            'trial_days' => 0, // Default value
            'is_popular' => false, // Default value
            'status' => $request->status,
        ];

        SubscriptionPlan::create($data);

        return redirect()->route('super-admin.packages.index')
            ->with('success', 'Package created successfully!');
    }

    public function show(SubscriptionPlan $package)
    {
        $package->load(['subscriptions.society']);
        return view('super-admin.packages.show', compact('package'));
    }

    public function edit(SubscriptionPlan $package)
    {
        return view('super-admin.packages.edit', compact('package'));
    }

    public function update(Request $request, SubscriptionPlan $package)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'max_flats' => 'nullable|integer|min:0',
            'features' => 'required|array',
            'status' => 'required|in:active,inactive',
        ]);

        // Prepare data with proper field mapping and explicit JSON encoding
        $baseSlug = \Str::slug($request->name);
        $slug = $baseSlug;
        $counter = 1;
        
        // Ensure unique slug (excluding current package)
        while (SubscriptionPlan::where('slug', $slug)->where('id', '!=', $package->id)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        $data = [
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'monthly_price' => $request->monthly_price,
            'yearly_price' => $request->annual_price, // Map annual_price to yearly_price
            'max_flats' => $request->max_flats ?? 0,
            'features' => json_encode($request->features), // Explicitly encode as JSON
            'status' => $request->status,
        ];

        $package->update($data);

        return redirect()->route('super-admin.packages.index')
            ->with('success', 'Package updated successfully!');
    }

    public function destroy(SubscriptionPlan $package)
    {
        if ($package->subscriptions()->count() > 0) {
            return back()->with('error', 'Cannot delete package with active subscriptions!');
        }

        $package->delete();

        return redirect()->route('super-admin.packages.index')
            ->with('success', 'Package deleted successfully!');
    }
}