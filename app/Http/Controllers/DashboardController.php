<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Complaint;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\Notice;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Resident;
use App\Models\Society;
use App\Models\Visitor;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Debug: Log user roles for troubleshooting
        \Log::info('Dashboard Access Debug', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->getRoleNames()->toArray(),
            'has_villa_owner' => $user->hasRole('Villa Owner'),
            'has_apartment_owner' => $user->hasRole('Apartment Owner'),
            'has_both_roles' => $user->hasRole('Villa Owner') && $user->hasRole('Apartment Owner')
        ]);

        // Redirect based on role - PRIORITY ORDER MATTERS
        if ($user->hasRole('Super Admin')) {
            return redirect()->route('super-admin.dashboard');
        }

        // Admin role takes priority over owner roles
        if ($user->hasRole('Admin')) {
            return $this->adminDashboard();
        }

        // Check for multiple owner roles - if user has both Villa Owner and Apartment Owner,
        // treat them as a general Owner and show owner dashboard
        if ($user->hasRole('Villa Owner') && $user->hasRole('Apartment Owner')) {
            \Log::info('Multi-property owner detected - routing to owner dashboard');
            return $this->ownerDashboard();
        }

        // Single owner role - show specific dashboard
        if ($user->hasRole('Villa Owner')) {
            \Log::info('Single Villa Owner detected - routing to villa dashboard');
            return redirect()->route('villa-owner.dashboard');
        }

        if ($user->hasRole('Staff')) {
            return $this->staffDashboard();
        }

        // Tenant role - show tenant dashboard
        if ($user->hasRole('Tenant')) {
            return $this->tenantDashboard();
        }

        // Apartment Owner role - show owner dashboard
        if ($user->hasRole('Apartment Owner')) {
            return $this->ownerDashboard();
        }

        // Default: Admin dashboard
        return $this->adminDashboard();
    }

    private function adminDashboard()
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        $stats = [
            'total_buildings' => Building::when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'total_units' => Flat::when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'total_residents' => Resident::when($societyId, fn($q) => $q->whereHas('flat', fn($q2) => $q2->where('society_id', $societyId)))->count(),
            'occupied_units' => Flat::when($societyId, fn($q) => $q->where('society_id', $societyId))->where('status', 'occupied')->count(),
            'open_tickets' => Complaint::when($societyId, fn($q) => $q->where('society_id', $societyId))->where('status', 'open')->count(),
            'pending_dues' => Payment::when($societyId, fn($q) => $q->where('society_id', $societyId))->where('status', 'pending')->sum('amount'),
            'monthly_revenue' => Payment::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->sum('amount'),
            'visitors_today' => Visitor::when($societyId, fn($q) => $q->where('society_id', $societyId))->whereDate('visit_date', today())->count(),
        ];

        $recentTickets = Complaint::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->with('createdBy')
            ->latest()
            ->take(5)
            ->get();

        $recentVisitors = Visitor::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->with('flat')
            ->latest()
            ->take(5)
            ->get();

        $notices = Notice::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentTickets', 'recentVisitors', 'notices'));
    }

    private function ownerDashboard()
    {
        $user = auth()->user();
        
        // Get all user's properties through multiple comprehensive methods
        $properties = collect();
        $unit = null;
        
        // Method 1: Through Owner relationship (primary method)
        if ($user->owner_id) {
            $properties = Flat::with(['building', 'villaArea'])
                ->where('owner_id', $user->owner_id)
                ->get();
        }
        
        // Method 2: Through ownedProperties relationship
        if ($properties->isEmpty()) {
            $properties = $user->ownedProperties()->with(['building', 'villaArea'])->get();
        }
        
        // Method 3: Through residents table (active residents)
        if ($properties->isEmpty()) {
            $properties = Flat::with(['building', 'villaArea'])
                ->whereHas('residents', fn($q) => $q->where('user_id', $user->id)->where('status', 'active'))
                ->get();
        }
        
        // Method 4: Through Owner table by email/name matching
        if ($properties->isEmpty()) {
            $owner = Owner::where('email', $user->email)
                ->orWhere('name', $user->name)
                ->first();
                
            if ($owner) {
                $properties = Flat::with(['building', 'villaArea'])
                    ->where('owner_id', $owner->id)
                    ->get();
                    
                // Auto-fix the relationship
                if (!$user->owner_id) {
                    $user->update(['owner_id' => $owner->id]);
                }
            }
        }
        
        // Method 5: Create property if user has owner role but no property (auto-fix)
        if ($properties->isEmpty() && $user->hasAnyRole(['Apartment Owner', 'Villa Owner'])) {
            $this->createPropertyForOwner($user);
            // Reload properties after creation
            $properties = $user->fresh()->ownedProperties()->with(['building', 'villaArea'])->get();
        }

        // Get primary unit (first property for display)
        $unit = $properties->first();

        // Enhanced stats for multi-property owners
        $stats = [
            'total_properties' => $properties->count(),
            'apartments' => $properties->where('property_type', 'apartment')->count(),
            'villas' => $properties->where('property_type', 'villa')->count(),
            'pending_bills' => Payment::where('user_id', $user->id)->where('status', 'pending')->count(),
            'total_due' => Payment::where('user_id', $user->id)->where('status', 'pending')->sum('amount'),
            'my_tickets' => Complaint::where('created_by', $user->id)->count(),
            'open_tickets' => Complaint::where('created_by', $user->id)->where('status', 'open')->count(),
            'my_bookings' => FacilityBooking::where('user_id', $user->id)->where('status', 'approved')->count(),
            'upcoming_visitors' => Visitor::where('host_user_id', $user->id)->where('visit_date', '>=', today())->count(),
        ];

        // Debug information (can be removed in production)
        \Log::info('Owner Dashboard Debug', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'owner_id' => $user->owner_id,
            'user_roles' => $user->getRoleNames()->toArray(),
            'properties_count' => $properties->count(),
            'properties' => $properties->pluck('id', 'flat_number')->toArray(),
            'stats' => $stats
        ]);

        $myBills = Payment::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $myTickets = Complaint::where('created_by', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $notices = Notice::where('society_id', $user->society_id)
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get();

        $upcomingBookings = FacilityBooking::where('user_id', $user->id)
            ->where('booking_date', '>=', today())
            ->with('facility')
            ->take(3)
            ->get();

        return view('owner.dashboard', compact('unit', 'properties', 'stats', 'myBills', 'myTickets', 'notices', 'upcomingBookings'));
    }

    private function staffDashboard()
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // STAFF DASHBOARD - LIMITED VIEW (NO FINANCIAL DATA)
        // Only show: Visitors Today, Pending Tickets, Assigned Services, Today's Events
        $stats = [
            // 1. Visitors Today - Core feature for staff
            'visitors_today' => Visitor::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->whereDate('visit_date', today())
                ->count(),
            
            // 2. Pending Tickets assigned to staff or general pending tickets
            'pending_tickets' => Complaint::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->where(function($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                          ->orWhereIn('status', ['open', 'in_progress']);
                })
                ->whereIn('status', ['open', 'in_progress'])
                ->count(),
            
            // 3. Assigned Services (if any) - placeholder for future implementation
            'assigned_services' => 0, // TODO: Implement when service assignment feature is added
            
            // 4. Today's Events
            'todays_events' => \App\Models\Event::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->whereDate('event_date', today())
                ->where('status', 'published')
                ->count(),
        ];

        // Recent tickets assigned to this staff member or general tickets
        $assignedTickets = Complaint::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->where(function($query) use ($user) {
                $query->where('assigned_to', $user->id)
                      ->orWhereIn('status', ['open', 'in_progress']);
            })
            ->whereIn('status', ['open', 'in_progress'])
            ->with(['createdBy', 'category'])
            ->latest()
            ->take(10)
            ->get();

        // Today's visitors for staff to manage
        $todayVisitors = Visitor::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->whereDate('visit_date', today())
            ->with(['flat.building'])
            ->latest()
            ->get();

        // Recent notices (read-only for staff)
        $notices = \App\Models\Notice::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get();

        return view('staff.dashboard', compact('stats', 'assignedTickets', 'todayVisitors', 'notices'));
    }

    private function tenantDashboard()
    {
        $user = auth()->user();
        
        // Get tenant record with multiple fallback methods
        $tenant = null;
        $unit = null;
        
        // Method 1: Direct tenant relationship
        if ($user->tenant_id) {
            $tenant = \App\Models\Tenant::find($user->tenant_id);
        }
        
        // Method 2: Find tenant by user_id
        if (!$tenant) {
            $tenant = \App\Models\Tenant::where('user_id', $user->id)->first();
        }
        
        // Method 3: Find tenant by email/name matching
        if (!$tenant) {
            $tenant = \App\Models\Tenant::where('email', $user->email)
                ->orWhere('name', $user->name)
                ->first();
                
            // Auto-fix the relationship
            if ($tenant && !$user->tenant_id) {
                $user->update(['tenant_id' => $tenant->id]);
            }
        }
        
        // Method 4: Create tenant if user has tenant role but no tenant record
        if (!$tenant && $user->hasRole('Tenant')) {
            $tenant = $this->createTenantForUser($user);
        }

        // Get tenant's unit
        if ($tenant && $tenant->flat_id) {
            $unit = Flat::with(['building', 'villaArea'])->find($tenant->flat_id);
        }

        // Tenant-specific stats
        $stats = [
            'pending_bills' => Payment::where('user_id', $user->id)->where('status', 'pending')->count(),
            'total_due' => Payment::where('user_id', $user->id)->where('status', 'pending')->sum('amount'),
            'my_tickets' => Complaint::where('created_by', $user->id)->count(),
            'open_tickets' => Complaint::where('created_by', $user->id)->where('status', 'open')->count(),
            'my_bookings' => FacilityBooking::where('user_id', $user->id)->where('status', 'approved')->count(),
            'upcoming_visitors' => Visitor::where('host_user_id', $user->id)->where('visit_date', '>=', today())->count(),
        ];

        // Debug information
        \Log::info('Tenant Dashboard Debug', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'tenant_found' => $tenant ? $tenant->id : null,
            'unit_found' => $unit ? $unit->flat_number : null,
            'user_roles' => $user->getRoleNames()->toArray(),
        ]);

        $myBills = Payment::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $myTickets = Complaint::where('created_by', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $notices = Notice::where('society_id', $user->society_id)
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get();

        $upcomingBookings = FacilityBooking::where('user_id', $user->id)
            ->where('booking_date', '>=', today())
            ->with('facility')
            ->take(3)
            ->get();

        return view('tenant.dashboard', compact('unit', 'tenant', 'stats', 'myBills', 'myTickets', 'notices', 'upcomingBookings'));
    }

    /**
     * Create a property for an owner user who doesn't have one assigned
     */
    private function createPropertyForOwner($user)
    {
        try {
            // Find or create Owner record
            $owner = Owner::where('email', $user->email)
                ->orWhere('name', $user->name)
                ->first();

            if (!$owner) {
                $owner = Owner::create([
                    'society_id' => $user->society_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '9876543210',
                    'property_type' => $user->hasRole('Villa Owner') ? 'villa' : 'apartment',
                    'status' => 'active',
                ]);
            }

            // Link user to owner
            if ($user->owner_id !== $owner->id) {
                $user->update(['owner_id' => $owner->id]);
            }

            // Create a flat for the owner
            $building = $this->findOrCreateBuilding($user->society_id);
            $flatNumber = $this->generateUniqueFlatNumber($user->society_id);
            
            $flat = Flat::create([
                'society_id' => $user->society_id,
                'building_id' => $building->id,
                'owner_id' => $owner->id,
                'flat_number' => $flatNumber,
                'floor' => rand(1, 5),
                'property_type' => $owner->property_type,
                'bedrooms' => rand(1, 3),
                'bathrooms' => rand(1, 2),
                'area_sqft' => rand(800, 1500),
                'status' => 'occupied'
            ]);

            // Create resident record
            Resident::create([
                'society_id' => $user->society_id,
                'user_id' => $user->id,
                'flat_id' => $flat->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '9876543210',
                'type' => 'owner',
                'move_in_date' => now()->subMonths(6),
                'status' => 'active',
            ]);

            \Log::info("Auto-created property for owner: {$user->name} - Flat: {$flat->flat_number}");
            
            return $flat;
        } catch (\Exception $e) {
            \Log::error("Failed to create property for owner {$user->name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find or create a building for the society
     */
    private function findOrCreateBuilding($societyId)
    {
        $building = Building::where('society_id', $societyId)->first();
        
        if (!$building) {
            $building = Building::create([
                'society_id' => $societyId,
                'name' => 'Tower A',
                'floors' => 10,
                'flats_per_floor' => 4,
                'status' => 'active'
            ]);
        }

        return $building;
    }

    /**
     * Generate a unique flat number for the society
     */
    private function generateUniqueFlatNumber($societyId)
    {
        $existingNumbers = Flat::where('society_id', $societyId)
            ->pluck('flat_number')
            ->toArray();

        // Try common flat numbers first
        $commonNumbers = ['1A', '1B', '2A', '2B', '3A', '3B', '101', '102', '103', '104', '201', '202'];
        
        foreach ($commonNumbers as $number) {
            if (!in_array($number, $existingNumbers)) {
                return $number;
            }
        }

        // Generate numbered flats
        for ($i = 1; $i <= 100; $i++) {
            $flatNumber = str_pad($i, 2, '0', STR_PAD_LEFT);
            if (!in_array($flatNumber, $existingNumbers)) {
                return $flatNumber;
            }
        }

        // Last resort - random number
        return rand(101, 999);
    }

    /**
     * Create a tenant record for a user who doesn't have one assigned
     */
    private function createTenantForUser($user)
    {
        try {
            // Find an available flat or create one
            $flat = $this->findOrCreateAvailableFlat($user);
            
            $tenant = \App\Models\Tenant::create([
                'society_id' => $user->society_id,
                'user_id' => $user->id,
                'flat_id' => $flat->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '9876543210',
                'move_in_date' => now()->subMonths(3),
                'lease_start_date' => now()->subMonths(3),
                'lease_end_date' => now()->addMonths(9),
                'monthly_rent' => rand(15000, 25000),
                'security_deposit' => rand(30000, 50000),
                'status' => 'active',
            ]);

            // Link user to tenant
            $user->update(['tenant_id' => $tenant->id]);

            // Create resident record
            Resident::create([
                'society_id' => $user->society_id,
                'user_id' => $user->id,
                'flat_id' => $flat->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '9876543210',
                'type' => 'tenant',
                'move_in_date' => $tenant->move_in_date,
                'status' => 'active',
            ]);

            \Log::info("Auto-created tenant record for user: {$user->name} - Flat: {$flat->flat_number}");
            
            return $tenant;
        } catch (\Exception $e) {
            \Log::error("Failed to create tenant for user {$user->name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find or create an available flat for tenant
     */
    private function findOrCreateAvailableFlat($user)
    {
        // Try to find an unoccupied flat
        $flat = Flat::where('society_id', $user->society_id)
            ->where('status', 'vacant')
            ->first();

        if (!$flat) {
            // Create a new flat
            $building = $this->findOrCreateBuilding($user->society_id);
            $flatNumber = $this->generateUniqueFlatNumber($user->society_id);
            
            $flat = Flat::create([
                'society_id' => $user->society_id,
                'building_id' => $building->id,
                'flat_number' => $flatNumber,
                'floor' => rand(1, 5),
                'property_type' => 'apartment',
                'bedrooms' => rand(1, 3),
                'bathrooms' => rand(1, 2),
                'area_sqft' => rand(800, 1500),
                'status' => 'occupied'
            ]);
        } else {
            // Mark as occupied
            $flat->update(['status' => 'occupied']);
        }

        return $flat;
    }
}
