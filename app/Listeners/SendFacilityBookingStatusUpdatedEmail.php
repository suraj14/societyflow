<?php

namespace App\Listeners;

use App\Events\FacilityBookingStatusUpdated;
use App\Jobs\DispatchEventNotification;
use Illuminate\Support\Facades\Log;

/**
 * `FacilityBookingController::updateStatus()` is Admin-only and had zero
 * notification of any kind before this - a resident's booking could be
 * approved, rejected or cancelled with nothing telling them so beyond
 * checking the booking list themselves.
 */
class SendFacilityBookingStatusUpdatedEmail
{
    public function handle(FacilityBookingStatusUpdated $event): void
    {
        try {
            $booking = $event->booking;
            $user = $booking->user;

            if (!$user || !$user->email) {
                return;
            }

            $booking->loadMissing('facility');

            $variables = [
                'user_name'       => $user->name,
                'facility_name'   => $booking->facility?->name ?? 'Facility',
                'booking_number'  => $booking->booking_number,
                'booking_date'    => optional($booking->booking_date)->format('d M Y') ?? '',
                'booking_status'  => ucfirst($booking->status),
                'admin_notes'     => $booking->admin_notes ?? '',
                'view_url'        => route('facility-bookings.show', $booking),
            ];

            DispatchEventNotification::dispatch(
                'facility_booking_status_updated',
                ['email' => $user->email, 'name' => $user->name, 'role' => $user->getRoleNames()->first() ?? 'user'],
                $variables,
                $booking->society_id,
                "facility_booking_status_updated:{$booking->id}:{$booking->status}:{$user->email}",
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::error('Failed to send facility booking status updated email', [
                'booking_id' => $event->booking->id ?? 'unknown',
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
