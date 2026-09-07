<?php

namespace App\Http\Controllers\VillaOwner;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\Owner;
use App\Models\Building;
use App\Models\Resident;
use App\Models\VillaArea;
use App\Models\Visitor;
use App\Models\FacilityBooking;
use App\Models\Complaint;
use App\Models\MaintenanceBill;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get villa with comprehensive fallback logic (same as main DashboardController)
        $villa = $this->getVillaForUser($user);

        if (!$villa) {
            return view('villa-owner.no-villa');
        }

        // Get dashboard stats
        $pendingDues = $villa->getPendingDues();
        
        $todayVisitors = Visitor::where('flat_id', $villa->id)
            ->whereDate('visit_date', today())
            ->count();

        $upcomingBookings = FacilityBooking::where('flat_id', $villa->id)
            ->where('booking_date', '>=', today())
            ->where('status', 'approved')
            ->count();

        $openComplaints = Complaint::where('flat_id', $villa->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        // Recent visitors
        $recentVisitors = Visitor::where('flat_id', $villa->id)
            ->latest()
            ->take(5)
            ->get();

        // Recent complaints
        $recentComplaints = Complaint::where('flat_id', $villa->id)
            ->latest()
            ->take(5)
            ->get();

        // Upcoming facility bookings
        $facilityBookings = FacilityBooking::where('flat_id', $villa->id)
            ->where('booking_date', '>=', today())
            ->with('facility')
            ->orderBy('booking_date')
            ->take(5)
            ->get();

        // Pending bills
        $pendingBills = MaintenanceBill::where('flat_id', $villa->id)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->latest()
            ->take(5)
            ->get();

        return view('villa-owner.dashboard', compact(
            'villa',
            'pendingDues',
            'todayVisitors',
            'upcomingBookings',
            'openComplaints',
            'recentVisitors',
            'recentComplaints',
            'facilityBookings',
            'pendingBills'
        ));
    }

    /**
     * Get villa for user with comprehensive fallback logic
     */
    private function getVillaForUser($user)
    {
        $villa = null;
        
        // Method 1: Through ownedVilla relationship (primary method)
        $villa = $user->ownedVilla;
        
        // Method 2: Through ownedProperties relationship (villa type)
        if (!$villa) {
            $villa = $user->ownedProperties()
                ->with(['building', 'villaArea'])
                ->where('flats.property_type', 'villa')
                ->first();
        }
        
        // Method 3: Through residents table (active residents)
        if (!$villa) {
            $villa = Flat::with(['building', 'villaArea'])
                ->where('flats.property_type', 'villa')
                ->whereHas('residents', fn($q) => $q->where('user_id', $user->id)->where('status', 'active'))
                ->first();
        }
        
        // Method 4: Through Owner table by email/name matching
        if (!$villa) {
            $owner = Owner::where('email', $user->email)
                ->orWhere('name', $user->name)
                ->first();
                
            if ($owner) {
                $villa = Flat::with(['building', 'villaArea'])
                    ->where('owner_id', $owner->id)
                    ->where('flats.property_type', 'villa')
                    ->first();
                    
                // Auto-fix the relationship
                if (!$user->owner_id) {
                    $user->update(['owner_id' => $owner->id]);
                }
            }
        }
        
        // Method 5: Create villa if user has Villa Owner role but no villa (auto-fix)
        if (!$villa && $user->hasRole('Villa Owner')) {
            $villa = $this->createVillaForOwner($user);
        }

        // Debug information
        \Log::info('Villa Owner Dashboard Debug', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'owner_id' => $user->owner_id,
            'user_roles' => $user->getRoleNames()->toArray(),
            'villa_found' => $villa ? $villa->id : null,
            'villa_number' => $villa ? $villa->flat_number : null,
        ]);

        return $villa;
    }

    /**
     * Create a villa for an owner user who doesn't have one assigned
     */
    private function createVillaForOwner($user)
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
                    'property_type' => 'villa',
                    'status' => 'active',
                ]);
            }

            // Link user to owner
            if ($user->owner_id !== $owner->id) {
                $user->update(['owner_id' => $owner->id]);
            }

            // Create a villa for the owner
            $villaArea = $this->findOrCreateVillaArea($user->society_id);
            $villaNumber = $this->generateUniqueVillaNumber($user->society_id);
            
            $villa = Flat::create([
                'society_id' => $user->society_id,
                'villa_area_id' => $villaArea->id,
                'owner_id' => $owner->id,
                'flat_number' => $villaNumber,
                'villa_name' => "Villa {$villaNumber}",
                'property_type' => 'villa',
                'bedrooms' => rand(2, 4),
                'bathrooms' => rand(2, 3),
                'area_sqft' => rand(1500, 3000),
                'plot_area' => rand(2000, 4000),
                'built_up_area' => rand(1500, 2500),
                'status' => 'occupied'
            ]);

            // Create resident record
            Resident::create([
                'society_id' => $user->society_id,
                'user_id' => $user->id,
                'flat_id' => $villa->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '9876543210',
                'type' => 'owner',
                'move_in_date' => now()->subMonths(6),
                'status' => 'active',
            ]);

            \Log::info("Auto-created villa for owner: {$user->name} - Villa: {$villa->flat_number}");
            
            return $villa;
        } catch (\Exception $e) {
            \Log::error("Failed to create villa for owner {$user->name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find or create a villa area for the society
     */
    private function findOrCreateVillaArea($societyId)
    {
        $villaArea = VillaArea::where('society_id', $societyId)->first();
        
        if (!$villaArea) {
            $villaArea = VillaArea::create([
                'society_id' => $societyId,
                'name' => 'Green Valley Villas',
                'description' => 'Premium villa area',
                'total_villas' => 50,
                'status' => 'active'
            ]);
        }

        return $villaArea;
    }

    /**
     * Generate a unique villa number for the society
     */
    private function generateUniqueVillaNumber($societyId)
    {
        $existingNumbers = Flat::where('society_id', $societyId)
            ->where('flats.property_type', 'villa')
            ->pluck('flat_number')
            ->toArray();

        // Try common villa numbers first
        $commonNumbers = ['V1', 'V2', 'V3', 'V4', 'V5', 'V6', 'V7', 'V8', 'V9', 'V10'];
        
        foreach ($commonNumbers as $number) {
            if (!in_array($number, $existingNumbers)) {
                return $number;
            }
        }

        // Generate numbered villas
        for ($i = 1; $i <= 100; $i++) {
            $villaNumber = 'V' . $i;
            if (!in_array($villaNumber, $existingNumbers)) {
                return $villaNumber;
            }
        }

        // Last resort - random number
        return 'V' . rand(101, 999);
    }
}
