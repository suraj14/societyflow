<?php

namespace App\Http\Controllers\VillaOwner;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityBooking;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get villa through ownedVilla relationship or direct owner_id
        $villa = null;
        
        if ($user->owner_id) {
            $villa = $user->ownedVilla;
        }
        
        // If not found through relationship, try direct owner_id match
        if (!$villa) {
            $villa = \App\Models\Flat::where('owner_id', $user->id)
                                    ->where('property_type', 'villa')
                                    ->first();
        }

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard')
                ->with('error', 'No villa assigned to your account.');
        }

        $facilities = Facility::where('society_id', $villa->society_id)
            ->where('status', 'active')
            ->get();

        return view('villa-owner.facilities.index', compact('facilities', 'villa'));
    }

    public function book(Facility $facility)
    {
        $user = auth()->user();
        
        // Get villa through ownedVilla relationship or direct owner_id
        $villa = null;
        
        if ($user->owner_id) {
            $villa = $user->ownedVilla;
        }
        
        // If not found through relationship, try direct owner_id match
        if (!$villa) {
            $villa = \App\Models\Flat::where('owner_id', $user->id)
                                    ->where('property_type', 'villa')
                                    ->first();
        }

        if (!$villa || $facility->society_id !== $villa->society_id) {
            abort(403);
        }

        return view('villa-owner.facilities.book', compact('facility', 'villa'));
    }

    public function storeBooking(Request $request, Facility $facility)
    {
        $user = auth()->user();
        
        // Get villa through ownedVilla relationship or direct owner_id
        $villa = null;
        
        if ($user->owner_id) {
            $villa = $user->ownedVilla;
        }
        
        // If not found through relationship, try direct owner_id match
        if (!$villa) {
            $villa = \App\Models\Flat::where('owner_id', $user->id)
                                    ->where('property_type', 'villa')
                                    ->first();
        }

        if (!$villa || $facility->society_id !== $villa->society_id) {
            abort(403);
        }

        $validated = $request->validate([
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'purpose' => 'nullable|string|max:255',
            'guests_count' => 'nullable|integer|min:1',
        ]);

        // Check for conflicts
        $conflict = FacilityBooking::where('facility_id', $facility->id)
            ->where('booking_date', $validated['booking_date'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']]);
            })
            ->exists();

        if ($conflict) {
            return back()->with('error', 'This time slot is already booked. Please choose another time.');
        }

        FacilityBooking::create([
            'society_id' => $villa->society_id,
            'facility_id' => $facility->id,
            'flat_id' => $villa->id,
            'user_id' => $user->id,
            'booking_date' => $validated['booking_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'purpose' => $validated['purpose'] ?? null,
            'guests_count' => $validated['guests_count'] ?? 1,
            'status' => $facility->requires_approval ? 'pending' : 'approved',
            'booking_amount' => $facility->booking_charge ?? 0,
        ]);

        $message = $facility->requires_approval 
            ? 'Booking request submitted. Waiting for approval.' 
            : 'Facility booked successfully!';

        return redirect()->route('villa-owner.bookings')
            ->with('success', $message);
    }

    public function bookings()
    {
        $user = auth()->user();
        
        // Get villa through ownedVilla relationship or direct owner_id
        $villa = null;
        
        if ($user->owner_id) {
            $villa = $user->ownedVilla;
        }
        
        // If not found through relationship, try direct owner_id match
        if (!$villa) {
            $villa = \App\Models\Flat::where('owner_id', $user->id)
                                    ->where('property_type', 'villa')
                                    ->first();
        }

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard')
                ->with('error', 'No villa assigned to your account.');
        }

        $bookings = FacilityBooking::where('flat_id', $villa->id)
            ->with('facility')
            ->latest()
            ->paginate(10);

        return view('villa-owner.facilities.bookings', compact('bookings', 'villa'));
    }

    public function cancelBooking(FacilityBooking $booking)
    {
        $user = auth()->user();
        
        // Get villa through ownedVilla relationship or direct owner_id
        $villa = null;
        
        if ($user->owner_id) {
            $villa = $user->ownedVilla;
        }
        
        // If not found through relationship, try direct owner_id match
        if (!$villa) {
            $villa = \App\Models\Flat::where('owner_id', $user->id)
                                    ->where('property_type', 'villa')
                                    ->first();
        }

        if (!$villa || $booking->flat_id !== $villa->id) {
            abort(403);
        }

        if ($booking->status === 'cancelled') {
            return back()->with('error', 'This booking is already cancelled.');
        }

        $booking->update(['status' => 'cancelled']);

        return back()->with('success', 'Booking cancelled successfully.');
    }
}
