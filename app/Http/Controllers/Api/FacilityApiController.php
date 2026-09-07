<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiImageHelper;
use App\Models\Facility;
use App\Models\FacilityBooking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FacilityApiController extends Controller
{
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
            'facility_id' => 'required|exists:facilities,id',
            'booking_date' => 'required|date',
            'start_time'  => 'required|string',
            'end_time'    => 'required|string',
            'purpose'     => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Get user's flat_id from their resident record
        $flatId = $user->resident()->value('flat_id');

        // Check for conflicts (use string comparison for time)
        $conflict = FacilityBooking::where('facility_id', $request->facility_id)
            ->whereDate('booking_date', $request->booking_date)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where(function ($q) use ($request) {
                $q->where(function ($inner) use ($request) {
                    $inner->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                });
            })->exists();

        if ($conflict) {
            return response()->json(['success' => false, 'message' => 'This time slot is already booked.'], 422);
        }

        $booking = FacilityBooking::create([
            'society_id'    => $user->society_id,
            'facility_id'   => $request->facility_id,
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
