<?php

namespace App\Http\Controllers\VillaOwner;

use App\Exceptions\FacilityBookingException;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\User;
use App\Services\FacilityBookingService;
use App\Support\BusinessNotifications;
use Illuminate\Http\Request;

/**
 * The legacy villa-owner portal (/my/facilities, /my/bookings).
 *
 * Two things were wrong with it and are fixed here:
 *
 *  1. "My villa" fell back to Flat::where('owner_id', $user->owner_id) - with
 *     NO owner link that is `owner_id IS NULL`, i.e. the first unassigned
 *     villa of ANY society. Any logged-in account (tenant, guard, staff...)
 *     was treated as that villa's owner and could list another society's
 *     facilities, book them, and cancel bookings made on that villa. An
 *     account with no owner link now owns no villa.
 *
 *  2. It kept its own copy of the booking rules (a time-overlap check that
 *     misses a booking which contains another, no opening hours, no advance
 *     limit, no maximum length) and never told the office. Booking now goes
 *     through FacilityBookingService - the one definition the web form and
 *     the mobile API already share - and the admin is notified exactly as
 *     they are for those.
 */
class FacilityController extends Controller
{
    /** The villa this account owns, or null. */
    private function villaFor(User $user): ?Flat
    {
        if (!$user->owner_id) {
            return null;
        }

        return $user->ownedVilla ?? Flat::ownedBy($user->owner_id)->villas()->first();
    }

    public function index()
    {
        $villa = $this->villaFor(auth()->user());

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
        $villa = $this->villaFor(auth()->user());

        if (!$villa || (int) $facility->society_id !== (int) $villa->society_id) {
            abort(403);
        }

        return view('villa-owner.facilities.book', compact('facility', 'villa'));
    }

    public function storeBooking(Request $request, Facility $facility)
    {
        $user  = auth()->user();
        $villa = $this->villaFor($user);

        if (!$villa || (int) $facility->society_id !== (int) $villa->society_id) {
            abort(403);
        }

        $validated = $request->validate([
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'purpose' => 'nullable|string|max:255',
            'guests_count' => 'nullable|integer|min:1',
        ]);

        try {
            $booking = FacilityBookingService::create(
                (int) $villa->society_id,
                $user,
                $facility->id,
                $villa->id,
                [
                    'booking_date'    => $validated['booking_date'],
                    'start_time'      => $validated['start_time'],
                    'end_time'        => $validated['end_time'],
                    'purpose'         => $validated['purpose'] ?? null,
                    'expected_guests' => $validated['guests_count'] ?? 1,
                ]
            );
        } catch (FacilityBookingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        BusinessNotifications::facilityBookingRequested($booking->load('facility'));

        $message = $booking->status === 'approved'
            ? 'Facility booked successfully!'
            : 'Booking request submitted. Waiting for approval.';

        return redirect()->route('villa-owner.bookings')
            ->with('success', $message);
    }

    public function bookings()
    {
        $villa = $this->villaFor(auth()->user());

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
        $villa = $this->villaFor(auth()->user());

        if (!$villa || (int) $booking->flat_id !== (int) $villa->id) {
            abort(403);
        }

        if ($booking->status === 'cancelled') {
            return back()->with('error', 'This booking is already cancelled.');
        }

        if (!in_array($booking->status, ['pending', 'approved'], true)) {
            return back()->with('error', 'This booking can no longer be cancelled.');
        }

        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        BusinessNotifications::facilityBookingCancelled($booking->load('facility'));

        return back()->with('success', 'Booking cancelled successfully.');
    }
}
