<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Models\Building;
use App\Models\Complaint;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\MaintenanceBill;
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

        // Redirect based on role - PRIORITY ORDER MATTERS
        if ($user->hasRole('Super Admin')) {
            return redirect()->route('super-admin.dashboard');
        }

        // Admin role takes priority over owner roles
        if ($user->hasRole('Admin')) {
            return $this->adminDashboard();
        }

        // A Guard gets the gate, not the society's books. Before this, Guard
        // matched no branch above and fell through to "Default: Admin
        // dashboard" at the bottom, which showed monthly revenue, pending
        // dues, occupancy and resident counts to anyone on gate duty.
        if ($user->hasRole('Guard')) {
            return redirect()->route('guard.dashboard');
        }

        // Check for multiple owner roles - if user has both Villa Owner and Apartment Owner,
        // treat them as a general Owner and show owner dashboard
        if ($user->hasRole('Villa Owner') && $user->hasRole('Apartment Owner')) {
            return $this->ownerDashboard();
        }

        // Single owner role - show specific dashboard
        if ($user->hasRole('Villa Owner')) {
            return redirect()->route('villa-owner.dashboard');
        }

        // Canonical 'Owner' role: which dashboard depends on what they
        // actually own, not on a role name - a villa-only owner gets the
        // villa-specific dashboard, everyone else (apartment-only, both, or
        // not yet linked to a flat) gets the general owner dashboard.
        if ($user->hasRole('Owner')) {
            if ($user->ownedVilla && !$user->ownedFlat) {
                return redirect()->route('villa-owner.dashboard');
            }

            return $this->ownerDashboard();
        }

        if ($user->hasRole('Staff')) {
            return $this->staffDashboard();
        }

        // Accountant: finance-only view. Previously unmatched by any branch
        // here, so every Accountant fell through to the Admin dashboard
        // below - correct data (it's real finance figures) but bundled with
        // resident/property/ticket counts that aren't theirs to see, and
        // paired with a sidebar that (before this fix) showed nothing but
        // "Dashboard" for the same reason - no branch in this controller or
        // the sidebar named the role.
        if ($user->hasRole('Accountant')) {
            return $this->accountantDashboard();
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

        // The society's configured property type decides which figures mean
        // anything: a villa township has no buildings to count, and an
        // apartment society has no villas. A mixed society sees both, split
        // out, so "120 villas / 80 apartments" is legible rather than a
        // single opaque "200 units".
        $society = $user->society;
        $propertyStats = $this->propertyStats($societyId);

        $residents = $this->residentCounts($societyId);
        $dues = MaintenanceBill::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->selectRaw('COALESCE(SUM(balance_amount), 0) as sum, COUNT(*) as cnt')
            ->first();

        $stats = [
            'total_buildings' => Building::when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'total_units' => Flat::when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'total_villas' => $propertyStats['villas'],
            'total_apartments' => $propertyStats['apartments'],
            'vacant_units' => $propertyStats['vacant'],
            // Residents = active owners + active tenants (the two canonical
            // person records; `residents` is the legacy table). One person
            // holding both is counted once, matched on email.
            'total_residents' => $residents['total'],
            'active_owners' => $residents['owners'],
            'active_tenants' => $residents['tenants'],
            'occupied_units' => $propertyStats['occupied'],
            'owner_occupied_units' => $propertyStats['owner_occupied'],
            'tenant_occupied_units' => $propertyStats['tenant_occupied'],
            // Open + in progress: the same "still unresolved" set the
            // complaints list and Complaint::scopeOpenTickets use.
            'open_tickets' => Complaint::when($societyId, fn($q) => $q->where('society_id', $societyId))->openTickets()->count(),
            // Outstanding balance on unpaid maintenance bills (ledger-derived
            // by MaintenanceBill::syncFromLedger) - the same figure the
            // Accountant dashboard and the financial report show. It used to
            // sum *pending payments*, which are unverified claims, not dues.
            'pending_dues' => (float) $dues->sum,
            'pending_bills_count' => (int) $dues->cnt,
            // Was ->where('status','paid')->whereMonth('paid_at', ...).
            // payments has no paid_at column and no row ever has status
            // 'paid' - the real values are 'pending' and 'success', and the
            // date column is payment_date. On SQLite a double-quoted unknown
            // column degrades to a string literal, so this silently summed to
            // zero instead of erroring; on MySQL it is a hard failure.
            'monthly_revenue' => Payment::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->success()
                ->whereMonth('payment_date', \App\Support\SocietyClock::now()->month)
                ->whereYear('payment_date', \App\Support\SocietyClock::now()->year)
                ->sum('amount'),
            // Society/common expenses only - a property's own expense is
            // never society spend (see FinancialReportService).
            'monthly_expenses' => $this->monthlySocietyExpenses($societyId),
            // visitors has no visit_date column; the scheduled arrival is
            // expected_entry_time.
            'visitors_today' => Visitor::when($societyId, fn($q) => $q->where('society_id', $societyId))->whereBetween('expected_entry_time', \App\Support\SocietyClock::dayBounds())->count(),
        ];
        $stats['monthly_net'] = $stats['monthly_revenue'] - $stats['monthly_expenses'];

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

        return view('admin.dashboard', compact('stats', 'recentTickets', 'recentVisitors', 'notices', 'society'));
    }

    /**
     * Unit counts split by property type, in one pass.
     *
     * A single grouped query rather than four counts, so adding the
     * property-type figures to the dashboard does not add round trips.
     *
     * @return array{villas:int,apartments:int,occupied:int,vacant:int}
     */
    /**
     * Active owners + active tenants of the society, one person counted once
     * (an owner who also rents elsewhere appears in both tables under the
     * same email). Two cheap pluck queries, no models hydrated.
     *
     * @return array{total:int,owners:int,tenants:int}
     */
    private function residentCounts(?int $societyId): array
    {
        $owners = Owner::when($societyId, fn($q) => $q->where('society_id', $societyId))->where('status', 'active')->pluck('email');
        $tenants = \App\Models\Tenant::when($societyId, fn($q) => $q->where('society_id', $societyId))->where('status', 'active')->pluck('email');

        $distinct = $owners->merge($tenants)->map(fn ($e) => strtolower(trim((string) $e)))->filter()->unique()->count();
        $blank = $owners->filter(fn ($e) => trim((string) $e) === '')->count() + $tenants->filter(fn ($e) => trim((string) $e) === '')->count();

        return ['total' => $distinct + $blank, 'owners' => $owners->count(), 'tenants' => $tenants->count()];
    }

    private function propertyStats(?int $societyId): array
    {
        // The same canonical occupancy rule as Property & Occupancy, the
        // Villas/Flats pages and the owner screens - not the stored status.
        $c = \App\Support\PropertyOccupancy::counts($societyId);

        return [
            'villas'          => $c['villas'],
            'apartments'      => $c['apartments'],
            'occupied'        => $c['occupied'],
            'owner_occupied'  => $c['owner_occupied'],
            'tenant_occupied' => $c['tenant_occupied'],
            'vacant'          => $c['vacant'],
        ];
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

        // Method 1b: flats.owner_id is unset on most rows (P1-7), so Method 1
        // above finds nothing for most real owners even though they own a
        // unit by address. Owner::resolvedFlats() applies the same
        // villa_area_id+villa_no / building_id+flat_no fallback the property
        // pages already use. Without this, Method 5 below used to conclude
        // "no property" and fabricate a brand new fake one for a real owner
        // whose unit was simply unlinked - and a rented unit is exactly the
        // case where this matters most: it must still show for its owner.
        if ($properties->isEmpty() && $user->owner_id) {
            $owner = Owner::find($user->owner_id);
            if ($owner) {
                $properties = collect($owner->resolvedFlats());
            }
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
        
        // Method 4: Through Owner table by email match.
        //
        // This used to be ->where('email', ...)->orWhere('name', ...), which
        // linked an account to any owner sharing a display name - and then
        // persisted that link with $user->update(['owner_id' => ...]) below,
        // permanently attaching someone to another person's property. Email
        // is unique on owners; name is not. Also scoped to the user's own
        // society, which the original query was not.
        if ($properties->isEmpty()) {
            $owner = Owner::where('email', $user->email)
                ->when($user->society_id, fn($q) => $q->where('society_id', $user->society_id))
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
        if ($properties->isEmpty() && $user->hasAnyRole(['Owner', 'Apartment Owner', 'Villa Owner'])) {
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
            'upcoming_visitors' => Visitor::where('host_user_id', $user->id)->where('expected_entry_time', '>=', today())->count(),
        ];

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

    /**
     * Finance-only dashboard for Accountant - bills, payments, rent, dues.
     * No resident/property/ticket counts (that's the Admin dashboard's job).
     */
    private function accountantDashboard()
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        $stats = [
            'total_bills' => MaintenanceBill::when($societyId, fn($q) => $q->where('society_id', $societyId))->count(),
            'pending_bills' => MaintenanceBill::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->count(),
            'pending_dues' => MaintenanceBill::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->sum('balance_amount'),
            'monthly_collection' => Payment::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->success()
                ->whereMonth('payment_date', \App\Support\SocietyClock::now()->month)
                ->whereYear('payment_date', \App\Support\SocietyClock::now()->year)
                ->sum('amount'),
            'total_collected' => Payment::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->success()
                ->sum('amount'),
            'rent_tenants' => \App\Models\Tenant::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->where('status', 'active')
                ->count(),
            'monthly_expenses' => $this->monthlySocietyExpenses($societyId),
        ];
        $stats['monthly_net'] = $stats['monthly_collection'] - $stats['monthly_expenses'];

        $recentPayments = Payment::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->with(['flat.building', 'flat.villaArea'])
            ->latest('payment_date')
            ->take(10)
            ->get();

        $pendingBills = MaintenanceBill::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->with('flat')
            ->latest('due_date')
            ->take(10)
            ->get();

        return view('accountant.dashboard', compact('stats', 'recentPayments', 'pendingBills'));
    }

    /** This month's approved society-scoped expenses (never property ones). */
    private function monthlySocietyExpenses(?int $societyId): float
    {
        // Full-day bounds: see FinancialReportService::bounds().
        // expense_date is a DATE column: calendar month on the society's clock.
        $now   = \App\Support\SocietyClock::now($societyId);
        $start = $now->copy()->startOfMonth()->toDateTimeString();
        $end   = $now->copy()->endOfMonth()->toDateTimeString();

        return (float) \App\Models\Expense::when($societyId, fn($q) => $q->where('society_id', $societyId))
            ->where('scope', \App\Models\Expense::SCOPE_SOCIETY)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');
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
                ->whereBetween('expected_entry_time', \App\Support\SocietyClock::dayBounds())
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
            ->whereBetween('expected_entry_time', \App\Support\SocietyClock::dayBounds())
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
            'upcoming_visitors' => Visitor::where('host_user_id', $user->id)->where('expected_entry_time', '>=', today())->count(),
        ];

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
                // Villa Owner/Apartment Owner used to say which type to
                // create; the canonical 'Owner' role carries no such signal,
                // so fall back to what the society actually supports (a
                // villa-only society has nothing else to create).
                $society = $user->society;
                $propertyType = $user->hasRole('Villa Owner')
                    ? 'villa'
                    : (($society && $society->supportsVillas() && !$society->supportsApartments()) ? 'villa' : 'apartment');

                $owner = Owner::create([
                    'society_id' => $user->society_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '9876543210',
                    'property_type' => $propertyType,
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
