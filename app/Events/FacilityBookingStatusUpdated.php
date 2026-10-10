<?php

namespace App\Events;

use App\Models\FacilityBooking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FacilityBookingStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FacilityBooking $booking,
        public string $previousStatus,
    ) {
    }
}
