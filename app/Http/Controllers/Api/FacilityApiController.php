<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FacilityBookingException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiImageHelper;
use App\Http\Controllers\Api\Concerns\ResolvesUserFlats;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\User;
use App\Services\FacilityBookingService;
use App\Support\BusinessNotifications;
use App\Support\SocietyClock;
use App\Support\UserFlatResolver;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facilities for residents: see them, see what is free on a day, book, cancel.
 *
 * The rules (hours, available days, how far ahead, longest booking, approval,
 * charge) live in FacilityBookingService, the same code the web form uses.
 * Booking is a resident action: an Owner, Villa/Apartment Owner or Tenant (the
 * book_facility permission). The office manages bookings from the web or the
 * admin endpoints; gate and finance staff have no use for a booking and get a
 * 403 here rather than relying on the app hiding the button.
 */
class FacilityApiController extends Controller
{
    use ResolvesUserFlats;

    private const RESIDENT_ROLES = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'];

    private function canBook(User $user): bool
    {
        return $user->can('book_facility') || $user->hasAnyRole(self::RESIDENT_ROLES);
    }

    private function forbidBooking(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Facility booking is for residents.'], 403);
    }

    private function activeFacility(Request $request, int $id): ?Facility
    {
        return Facility::where('id', $id)
            ->where('society_id', $request->user()->society_id)
            ->where('status', 'active')
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        $facilities = Facility::where('society_id', $request->user()->society_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => $this->formatFacility($f));

        return response()->json(['success' => true, 'data' => $facilities]);
    }

    /** One day of a facility as a booking grid: which hours are free and for how long. */
    public function availability(Request $request, int $id): JsonResponse
    {
        $request->validate(['date' => 'required|date_format:Y-m-d']);

        $facility = $this->activeFacility($request, $id);
        if (!$facility) {
            return response()->json(['success' => false, 'message' => 'Facility not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => ['facility' => $this->formatFacility($facility)] + FacilityBookingService::availability($facility, $request->date),
        ]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = FacilityBooking::where('user_id', $request->user()->id)
            ->where('society_id', $request->user()->society_id)
            ->with(['facility', 'flat'])
            ->orderByDesc('booking_date')
            ->orderByDesc('start_time')
            ->paginate(20);

        // Every booking on this page belongs to the same society (scoped
        // above), so "now" on that society's clock is one value for the
        // whole page, not a per-row lookup. Resolving it against the
        // already-authenticated user's own society (a model, not an int)
        // also means SocietyClock::timezoneFor() takes its no-query branch
        // - see hasStarted()'s docblock for what this replaced.
        $now = SocietyClock::now($request->user()->society);

        return response()->json([
            'success' => true,
            'data'    => $bookings->map(fn ($b) => $this->formatBooking($b, $now)),
            'meta'    => ['current_page' => $bookings->currentPage(), 'last_page' => $bookings->lastPage(), 'total' => $bookings->total()],
        ]);
    }

    public function book(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->canBook($user)) {
            return $this->forbidBooking();
        }

        $request->validate([
            'facility_id'     => 'required|integer',
            'booking_date'    => 'required|date_format:Y-m-d',
            'start_time'      => 'required|date_format:H:i,H:i:s',
            'end_time'        => 'required|date_format:H:i,H:i:s|after:start_time',
            'purpose'         => 'nullable|string|max:500',
            'expected_guests' => 'nullable|integer|min:0|max:10000',
            'flat_id'         => 'nullable|integer',
        ]);

        // The booking is for one of the caller's own units - never a
        // neighbour's, whatever flat_id the request names.
        $flatId = $request->filled('flat_id')
            ? (UserFlatResolver::userHasFlat($user, (int) $request->flat_id) ? (int) $request->flat_id : null)
            : ($this->userFlatIds($user)[0] ?? null);

        if ($request->filled('flat_id') && $flatId === null) {
            return response()->json(['success' => false, 'message' => 'You can only book a facility for your own property.'], 403);
        }
        if ($flatId === null) {
            return response()->json(['success' => false, 'message' => 'No property is linked to your account yet. Please contact the society office.'], 422);
        }

        try {
            $booking = FacilityBookingService::create(
                (int) $user->society_id,
                $user,
                (int) $request->facility_id,
                $flatId,
                $request->only(['booking_date', 'start_time', 'end_time', 'purpose', 'expected_guests'])
            );
        } catch (FacilityBookingException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }

        $booking->load(['facility', 'flat']);
        BusinessNotifications::facilityBookingRequested($booking);

        return response()->json([
            'success' => true,
            'message' => $booking->status === 'approved'
                ? 'Facility booked successfully!'
                : 'Booking request sent. You will be notified once the office approves it.',
            'data'    => $this->formatBooking($booking, SocietyClock::now($user->society)),
        ], 201);
    }

    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $booking = FacilityBooking::where('user_id', $user->id)
            ->where('society_id', $user->society_id)
            ->with('facility')
            ->findOrFail($id);

        if (!in_array($booking->status, ['pending', 'approved'], true)) {
            return response()->json(['success' => false, 'message' => 'This booking can no longer be cancelled.'], 422);
        }

        $now = SocietyClock::now($user->society);

        if ($this->hasStarted($booking, $now)) {
            return response()->json(['success' => false, 'message' => 'This booking has already started.'], 422);
        }

        $booking->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $request->filled('reason') ? mb_substr((string) $request->reason, 0, 500) : null,
        ]);
        BusinessNotifications::facilityBookingCancelled($booking);

        return response()->json(['success' => true, 'message' => 'Booking cancelled.', 'data' => $this->formatBooking($booking->fresh(['facility', 'flat']), $now)]);
    }

    /**
     * $now is the society's current instant, resolved once by the caller -
     * never re-derived here. hasStarted()/formatBooking() used to call
     * SocietyClock::now($booking->society_id) twice per booking, and that
     * overload queries the societies table fresh every time for an int id
     * (SocietyClock::timezoneFor()) - on the list endpoint (bookings()),
     * every row in the page shares the same society, so this was a plain
     * N+1: 2 queries x N rows, all fetching the identical row (confirmed:
     * query count scaled 9 -> 44 queries as rows went from 2 to 20,
     * matching 2 x 20 almost exactly). "Now" is the same instant for every
     * booking on the same society within one request regardless of which
     * row is being formatted, so resolving it once per request is not an
     * approximation - it is the same value the old code recomputed N times.
     */
    private function hasStarted(FacilityBooking $booking, CarbonInterface $now): bool
    {
        $start = $now->copy()->setDateFrom($booking->booking_date)
            ->setTimeFromTimeString($booking->start_time->format('H:i:s'));

        return $now->greaterThanOrEqualTo($start);
    }

    /**
     * The JSON keys are the installed app's contract and stay as they are
     * (new keys are additions); the columns behind three of them were wrong.
     *
     * $f->charges, ->open_time and ->close_time are not columns on
     * facilities - they are booking_charge, opening_time and closing_time -
     * so every facility reached the app with 0.00 charges and no opening
     * hours.
     */
    private function formatFacility(Facility $f): array
    {
        [$open, $close] = FacilityBookingService::window($f);

        return [
            'id'                   => $f->id,
            'name'                 => $f->name,
            'description'          => $f->description,
            'type'                 => $f->type,
            'capacity'             => $f->capacity,
            'charges'              => (float) ($f->booking_charge ?? 0),
            'image'                => ApiImageHelper::storageUrl($f->image ?? null),
            'status'               => $f->status,
            'open_time'            => FacilityBookingService::clock($open),
            'close_time'           => FacilityBookingService::clock($close),
            'available_days'       => $f->available_days ?: [],
            'advance_booking_days' => (int) ($f->advance_booking_days ?: 30),
            'max_booking_hours'    => (int) ($f->max_booking_hours ?: 4),
            'requires_approval'    => (bool) $f->requires_approval,
        ];
    }

    private function formatBooking(FacilityBooking $b, CarbonInterface $now): array
    {
        return [
            'id'             => $b->id,
            'booking_number' => $b->booking_number,
            'facility'       => $b->facility ? ['id' => $b->facility->id, 'name' => $b->facility->name] : null,
            'property'       => $b->flat?->flat_number,
            'booking_date'   => $b->booking_date?->toDateString(),
            // The columns are cast to Carbon, which serialises as a full
            // ISO timestamp (with today's date and a timezone shift) - the
            // app wants the wall-clock "09:00" the resident chose.
            'start_time'     => $b->start_time?->format('H:i'),
            'end_time'       => $b->end_time?->format('H:i'),
            'purpose'        => $b->purpose,
            'expected_guests' => (int) $b->expected_guests,
            'amount'         => (float) ($b->booking_amount ?? 0),
            'payment_status' => $b->payment_status,
            'status'         => $b->status,
            'admin_notes'    => $b->admin_notes,
            'can_cancel'     => in_array($b->status, ['pending', 'approved'], true) && !$this->hasStarted($b, $now),
            'created_at'     => $b->created_at->toIso8601String(),
        ];
    }
}
