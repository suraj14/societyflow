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
use App\Models\ServiceRequest;
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

        $society = $user->society;

        // One definition of the headline figures, shared with the mobile admin
        // dashboard (App\Support\SocietyOverview) so the two cannot disagree.
        $stats = \App\Support\SocietyOverview::kpis($societyId);

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

        $needsAttention = $this->needsAttention($societyId);

        return view('admin.dashboard', compact('stats', 'recentTickets', 'recentVisitors', 'notices', 'society', 'needsAttention'));
    }

    /**
     * Action-first counts for the dashboard's "Needs Attention" panel -
     * five counts, one query each, no rows hydrated. Deliberately separate
     * from `$stats` above: those are totals/history for the month, this is
     * "what has not been dealt with yet, right now" - a brand-new society
     * with zero history still sees this panel and it is correctly empty,
     * not a dashboard that only shows after enough data accumulates.
     *
     * Each entry links straight to the filtered list, not to the module's
     * own home page - clicking "3 pending bookings" lands an admin on the
     * 3 bookings, not on every booking the society has ever had.
     *
     * @return array<int, array{label:string, count:int, url:string, icon:string}>
     */
    private function needsAttention(?int $societyId): array
    {
        $items = [
            [
                'label' => 'Visitors waiting for approval',
                'count' => Visitor::when($societyId, fn ($q) => $q->where('society_id', $societyId))
                    ->where('approval_status', 'pending')->count(),
                'url'   => route('visitors.index', ['status' => 'pending']),
                'icon'  => 'user-clock',
            ],
            [
                'label' => 'New complaints',
                'count' => Complaint::when($societyId, fn ($q) => $q->where('society_id', $societyId))
                    ->where('status', 'open')->count(),
                'url'   => route('complaints.index', ['status' => 'open']),
                'icon'  => 'exclamation-circle',
            ],
            [
                'label' => 'Service requests to assign',
                'count' => ServiceRequest::when($societyId, fn ($q) => $q->where('society_id', $societyId))
                    ->where('status', 'pending')->count(),
                'url'   => route('service-requests.index', ['status' => 'pending']),
                'icon'  => 'tools',
            ],
            [
                'label' => 'Overdue bills',
                'count' => MaintenanceBill::when($societyId, fn ($q) => $q->where('society_id', $societyId))
                    ->overdue()->count(),
                'url'   => route('admin.rents.index'),
                'icon'  => 'file-invoice-dollar',
            ],
            [
                'label' => 'Facility bookings to review',
                'count' => FacilityBooking::when($societyId, fn ($q) => $q->where('society_id', $societyId))
                    ->where('status', 'pending')->count(),
                'url'   => route('facility-bookings.index', ['status' => 'pending']),
                'icon'  => 'calendar-check',
            ],
        ];

        // Zero-count rows are kept out of the view, not hidden with CSS -
        // an admin with nothing pending should see an empty-state message,
        // not a row of cards all reading "0".
        return array_values(array_filter($items, fn ($item) => $item['count'] > 0));
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
        
        // There is deliberately no "create a property for an owner with none"
        // step. It used to invent a flat (random floor, bedrooms, bathrooms
        // and area, in a building it created if need be) and assign it to the
        // owner, plus a Resident row, merely because the owner opened their
        // dashboard. An owner whose unit has not been assigned yet sees an
        // honest empty state; the office assigns the real unit.

        // Get primary unit (first property for display)
        $unit = $properties->first();

        // Outstanding bills on the user's own units - the same rule as the mobile dashboard (Api\DashboardApiController);
        // this used to count the user's own pending Payment rows, so an unpaid maintenance bill showed as 0 due.
        $outstanding = $this->outstandingBillsFor($user);

        // Enhanced stats for multi-property owners
        $stats = [
            'total_properties' => $properties->count(),
            'apartments' => $properties->where('property_type', 'apartment')->count(),
            'villas' => $properties->where('property_type', 'villa')->count(),
            'pending_bills' => $outstanding->count(),
            'total_due' => (float) $outstanding->sum('balance_amount'),
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
        return \App\Support\SocietyOverview::monthlySocietyExpenses($societyId);
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
            // The society's calendar day (like visitors_today above), not the server's UTC date: an event
            // overlaps today when it starts before the day ends and ends after the day began.
            'todays_events' => \App\Models\Event::when($societyId, fn($q) => $q->where('society_id', $societyId))
                ->where('start_date', '<=', \App\Support\SocietyClock::dayBounds()[1])
                ->where('end_date', '>=', \App\Support\SocietyClock::dayBounds()[0])
                ->where('status', 'pending') // events.status is pending|completed|cancelled; pending = still scheduled
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

    /** Unpaid maintenance/rent bills on the units this user lives in or owns (pending, partial, overdue). */
    private function outstandingBillsFor($user)
    {
        $flatIds = \App\Support\UserFlatResolver::flatIdsFor($user);
        if (!$flatIds) {
            return collect();
        }

        return MaintenanceBill::whereIn('flat_id', $flatIds)
            ->where('society_id', $user->society_id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->get(['id', 'balance_amount']);
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
        
        // Method 3: link by the account's own e-mail - and ONLY that.
        //
        // This used to be ->where('email', ...)->orWhere('name', ...), with
        // no society filter, and then persisted tenant_id. Any tenant in any
        // society who happened to share a display name was permanently
        // attached to this login (the same flaw the owner lookup above
        // already fixed). A name proves nothing; the e-mail is unique on
        // tenants, so it is matched inside this user's OWN society, and never
        // taken over from a tenant record that already belongs to another
        // login.
        if (!$tenant && $user->society_id && $user->email) {
            $tenant = \App\Models\Tenant::where('society_id', $user->society_id)
                ->where('email', $user->email)
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
                ->first();

            if ($tenant && !$user->tenant_id) {
                $user->update(['tenant_id' => $tenant->id]);
            }
        }

        // There is deliberately no "create a tenant if none" step. It used to
        // invent a tenancy (random rent and deposit) on the first vacant flat
        // of the society and a matching Resident row, just because a tenant
        // login opened its dashboard. A login with no tenancy gets an honest
        // empty dashboard until the office assigns one.

        // The tenant's unit is the one on an ACTIVE tenancy - the same rule My Rent, requests and the mobile app use. A
        // tenancy that has ended (or was moved) must not keep showing a home he no longer rents.
        $unitIds = \App\Support\UserFlatResolver::tenantFlatIds($user);
        if ($unitIds !== []) {
            $unit = Flat::with(['building', 'villaArea'])->where('society_id', $user->society_id)->whereIn('id', $unitIds)->orderByDesc('id')->first();
        }
        if (!$unit) {
            $tenant = null;   // no active tenancy: the dashboard says so instead of describing a past one
        }

        // Outstanding bills on the user's own units - the same rule as the mobile dashboard (Api\DashboardApiController);
        // this used to count the user's own pending Payment rows, so an unpaid maintenance bill showed as 0 due.
        $outstanding = $this->outstandingBillsFor($user);

        // Tenant-specific stats
        $stats = [
            'pending_bills' => $outstanding->count(),
            'total_due' => (float) $outstanding->sum('balance_amount'),
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

}
