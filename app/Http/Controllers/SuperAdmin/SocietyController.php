<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Society;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SocietyController extends Controller
{
    public function index(Request $request)
    {
        $query = Society::with(['admin', 'subscriptionPlan', 'subscription.subscriptionPlan']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereHas('admin', function ($adminQuery) use ($search) {
                      $adminQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $societies = $query->latest()->paginate(15);

        return view('super-admin.societies.index', compact('societies'));
    }

    public function create()
    {
        $subscriptionPlans = SubscriptionPlan::where('status', 'active')->get();
        return view('super-admin.societies.create', compact('subscriptionPlans'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:societies,email',
            'phone' => 'required|string|max:15',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'pincode' => 'required|string|max:10',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        DB::beginTransaction();
        
        try {
            // Get subscription plan for trial calculation
            $subscriptionPlan = SubscriptionPlan::findOrFail($request->subscription_plan_id);
            
            // Create society
            $society = Society::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'subdomain' => Str::slug($request->name),
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country ?? 'India',
                'pincode' => $request->pincode,
                'subscription_plan_id' => $request->subscription_plan_id,
                'status' => 'active',
                'trial_ends_at' => now()->addDays($subscriptionPlan->trial_days ?? 30),
            ]);

            // Create admin user
            $admin = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'society_id' => $society->id,
                'status' => 'active',
            ]);

            // Assign Admin role to the user
            $admin->assignRole('Admin');

            // Link admin to society (ONE Society = ONE Admin)
            $society->update(['admin_id' => $admin->id]);

            // Create initial subscription record
            Subscription::create([
                'society_id' => $society->id,
                'subscription_plan_id' => $subscriptionPlan->id,
                'billing_cycle' => 'monthly',
                'amount' => $subscriptionPlan->monthly_price,
                'start_date' => now(),
                'end_date' => now()->addDays($subscriptionPlan->trial_days ?? 30),
                'next_billing_date' => now()->addDays($subscriptionPlan->trial_days ?? 30),
                'status' => 'active',
                'auto_renew' => true,
            ]);

            DB::commit();

            return redirect()->route('super-admin.societies.index')
                ->with('success', 'Society and Admin created successfully! Trial period: ' . ($subscriptionPlan->trial_days ?? 30) . ' days.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withInput()->withErrors([
                'error' => 'Failed to create society: ' . $e->getMessage()
            ]);
        }
    }

    public function show(Society $society)
    {
        $society->load([
            'admin', 
            'subscriptionPlan', 
            'subscription.subscriptionPlan', 
            'buildings', 
            'residents', 
            'payments'
        ]);
        
        $stats = [
            'total_buildings' => $society->buildings()->count(),
            'total_flats' => $society->flats()->count(),
            'total_residents' => $society->residents()->count(),
            'total_users' => $society->users()->count(),
            'monthly_revenue' => $society->payments()->whereMonth('created_at', now()->month)->sum('amount'),
            'pending_complaints' => $society->complaints()->where('status', 'open')->count(),
            'active_facilities' => $society->facilities()->where('status', 'active')->count(),
            'trial_days_left' => $society->isOnTrial() ? $society->trial_ends_at->diffInDays(now()) : 0,
        ];

        return view('super-admin.societies.show', compact('society', 'stats'));
    }

    public function edit(Society $society)
    {
        $subscriptionPlans = SubscriptionPlan::where('status', 'active')->get();
        $society->load(['admin', 'subscriptionPlan']);
        
        return view('super-admin.societies.edit', compact('society', 'subscriptionPlans'));
    }

    public function update(Request $request, Society $society)
    {
        // Debug logging
        \Log::info('Society Update Request', [
            'society_id' => $society->id,
            'old_subscription_plan_id' => $society->subscription_plan_id,
            'new_subscription_plan_id' => $request->subscription_plan_id,
            'request_data' => $request->all()
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:societies,email,' . $society->id,
            'phone' => 'required|string|max:15',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'pincode' => 'required|string|max:10',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        DB::beginTransaction();
        
        try {
            // Store old subscription plan ID for comparison
            $oldSubscriptionPlanId = $society->subscription_plan_id;
            
            // Update society
            $updateData = $request->only([
                'name', 'email', 'phone', 'address', 'city', 'state', 'country', 'pincode', 'subscription_plan_id', 'status'
            ]);
            
            \Log::info('Updating society with data', $updateData);
            
            $society->update($updateData);
            
            // Refresh the model to get updated values
            $society->refresh();
            
            \Log::info('Society updated', [
                'old_plan_id' => $oldSubscriptionPlanId,
                'new_plan_id' => $society->subscription_plan_id,
                'was_changed' => $oldSubscriptionPlanId != $society->subscription_plan_id
            ]);

            // Update active subscription if subscription plan changed
            if ($oldSubscriptionPlanId != $society->subscription_plan_id) {
                $newPlan = SubscriptionPlan::findOrFail($society->subscription_plan_id);
                
                // Update or create subscription record
                if ($society->subscription) {
                    $society->subscription->update([
                        'subscription_plan_id' => $newPlan->id,
                        'amount' => $newPlan->monthly_price,
                    ]);
                    \Log::info('Updated existing subscription record');
                } else {
                    // Create new subscription record if none exists
                    Subscription::create([
                        'society_id' => $society->id,
                        'subscription_plan_id' => $newPlan->id,
                        'billing_cycle' => 'monthly',
                        'amount' => $newPlan->monthly_price,
                        'start_date' => now(),
                        'end_date' => now()->addDays($newPlan->trial_days ?? 30),
                        'next_billing_date' => now()->addDays($newPlan->trial_days ?? 30),
                        'status' => 'active',
                        'auto_renew' => true,
                    ]);
                    \Log::info('Created new subscription record');
                }
            }

            // Update slug if name changed
            if ($society->wasChanged('name')) {
                $society->update([
                    'slug' => Str::slug($society->name),
                    'subdomain' => Str::slug($society->name),
                ]);
            }

            DB::commit();

            return redirect()->route('super-admin.societies.index')
                ->with('success', 'Society updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withInput()->withErrors([
                'error' => 'Failed to update society: ' . $e->getMessage()
            ]);
        }
    }

    public function destroy(Society $society)
    {
        DB::beginTransaction();
        
        try {
            // Remove admin role from user before deleting society
            if ($society->admin) {
                $society->admin->removeRole('Admin');
                $society->admin->update(['society_id' => null]);
            }

            // Delete society (cascades to related data)
            $society->delete();

            DB::commit();

            return redirect()->route('super-admin.societies.index')
                ->with('success', 'Society deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'error' => 'Failed to delete society: ' . $e->getMessage()
            ]);
        }
    }

    public function approve(Society $society)
    {
        $society->update([
            'status' => 'active',
            'trial_ends_at' => now()->addDays(30),
        ]);

        return back()->with('success', 'Society approved successfully!');
    }

    public function reject(Society $society)
    {
        $society->update(['status' => 'suspended']);

        return back()->with('success', 'Society rejected!');
    }

    /**
     * Reset Society Admin Password (Super Admin only)
     */
    public function resetAdminPassword(Request $request, Society $society)
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!$society->admin) {
            return back()->withErrors(['error' => 'No admin assigned to this society.']);
        }

        try {
            $society->resetAdminPassword($request->new_password);

            return back()->with('success', 'Admin password reset successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Assign new admin to society (if no admin exists)
     */
    public function assignAdmin(Request $request, Society $society)
    {
        $request->validate([
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        if (!$society->canAssignAdmin()) {
            return back()->withErrors(['error' => 'Society already has an admin assigned.']);
        }

        DB::beginTransaction();
        
        try {
            // Create new admin user
            $admin = User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'society_id' => $society->id,
                'status' => 'active',
            ]);

            // Assign admin to society
            $society->assignAdmin($admin);

            DB::commit();

            return back()->with('success', 'Admin assigned successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove admin from society
     */
    public function removeAdmin(Society $society)
    {
        if (!$society->hasAdmin()) {
            return back()->withErrors(['error' => 'No admin assigned to this society.']);
        }

        try {
            $society->removeAdmin();

            return back()->with('success', 'Admin removed successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}