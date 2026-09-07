<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiImageHelper;
use App\Http\Controllers\Api\Concerns\ResolvesUserFlats;
use App\Models\Facility;
use App\Models\FacilityBooking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FacilityApiController extends Controller
{
    use ResolvesUserFlats;

    public function index(Request $request): JsonResponse
    {
        $facilities = Facility::where('society_id', $request->user()->society_id)
            ->where('status', 'active')
            ->get()
            ->map(fn($f) => $this->formatFacility($f));

        return response()->json(['success' => true, 'data' => $facilities]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = FacilityBooking::where('user_id', $request->user()->id)
            ->with('facility')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $bookings->map(fn($b) => $this->formatBooking($b)),
            'meta'    => ['current_page' => $bookings->currentPage(), 'last_page' => $bookings->lastPage(), 'total' => $bookings->total()],
        ]);
    }

    public function book(Request $request): JsonResponse
    {
        $request->validate([
            'facility_id'  => 'required|integer',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time'   => 'required|date_format:H:i,H:i:s',
            'end_time'     => 'required|date_format:H:i,H:i:s|after:start_time',
            'purpose'      => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Attach the user's unit (residency or ownership) to the booking
        $flatId = $this->userFlatIds($user)[0] ?? null;

        $booking = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $user, $flatId) {
            // Lock the facility row: serializes concurrent booking attempts on
            // the same facility so the conflict check below cannot race.
            // Also enforces society ownership — a facility_id from another
            // society is a 404, never bookable.
            $facility = Facility::where('id', $request->facility_id)
                ->where('society_id', $user->society_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$facility) {
                return null;
            }

            $conflict = FacilityBooking::where('facility_id', $facility->id)
                ->whereDate('booking_date', $request->booking_date)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->where('start_time', '<', $request->end_time)
                ->where('end_time', '>', $request->start_time)
                ->exists();

            if ($conflict) {
                return false;
            }

            return FacilityBooking::create([
                'society_id'    => $user->society_id,
                'facility_id'   => $facility->id,
                'user_id'       => $user->id,
                'flat_id'       => $flatId,
                'booking_date'  => $request->booking_date,
                'start_time'    => $request->start_time,
                'end_time'      => $request->end_time,
                'purpose'       => $request->purpose,
                'status'        => 'pending',
                'booking_amount'=> 0,
                'security_deposit' => 0,
                'payment_status'=> 'pending',
            ]);
        });

        if ($booking === null) {
            return response()->json(['success' => false, 'message' => 'Facility not found.'], 404);
        }

        if ($booking === false) {
            return response()->json(['success' => false, 'message' => 'This time slot is already booked.'], 422);
        }

        $booking->load('facility');

        return response()->json([
            'success' => true,
            'message' => 'Facility booked successfully! Awaiting approval.',
            'data'    => $this->formatBooking($booking),
        ], 201);
    }

    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $booking = FacilityBooking::where('user_id', $request->user()->id)->findOrFail($id);

        if (!in_array($booking->status, ['pending', 'approved'])) {
            return response()->json(['success' => false, 'message' => 'Cannot cancel this booking.'], 422);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Booking cancelled.']);
    }

    private function formatFacility(Facility $f): array
    {
        return [
            'id'          => $f->id,
            'name'        => $f->name,
            'description' => $f->description,
            'capacity'    => $f->capacity,
            'charges'     => (float) ($f->charges ?? 0),
            'image'       => ApiImageHelper::storageUrl($f->image ?? null),
            'status'      => $f->status,
            'open_time'   => $f->open_time,
            'close_time'  => $f->close_time,
        ];
    }

    private function formatBooking(FacilityBooking $b): array
    {
        return [
            'id'           => $b->id,
            'facility'     => $b->facility ? ['id' => $b->facility->id, 'name' => $b->facility->name] : null,
            'booking_date' => $b->booking_date?->toDateString(),
            'start_time'   => $b->start_time,
            'end_time'     => $b->end_time,
            'purpose'      => $b->purpose,
            'status'       => $b->status,
            'created_at'   => $b->created_at->toIso8601String(),
        ];
    }
}
