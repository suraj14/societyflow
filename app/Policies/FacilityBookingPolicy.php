<?php

namespace App\Policies;

use App\Models\FacilityBooking;
use App\Models\User;

class FacilityBookingPolicy
{
    /**
     * Determine whether the user can view any facility bookings.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_own_bookings') || $user->can('manage_facilities');
    }

    /**
     * Determine whether the user can view the facility booking.
     */
    public function view(User $user, FacilityBooking $facilityBooking): bool
    {
        // Admin can view all bookings
        if ($user->can('manage_facilities')) {
            return true;
        }

        // Users can view their own bookings
        return $user->id === $facilityBooking->user_id;
    }

    /**
     * Determine whether the user can create facility bookings.
     */
    public function create(User $user): bool
    {
        return $user->can('book_facility');
    }

    /**
     * Determine whether the user can update the facility booking.
     */
    public function update(User $user, FacilityBooking $facilityBooking): bool
    {
        // Only admin can update booking status
        return $user->can('manage_facilities');
    }

    /**
     * Determine whether the user can delete the facility booking.
     */
    public function delete(User $user, FacilityBooking $facilityBooking): bool
    {
        // Only admin can delete bookings
        return $user->can('manage_facilities');
    }

    /**
     * Determine whether the user can approve the facility booking.
     */
    public function approve(User $user, FacilityBooking $facilityBooking): bool
    {
        return $user->can('manage_facilities');
    }

    /**
     * Determine whether the user can reject the facility booking.
     */
    public function reject(User $user, FacilityBooking $facilityBooking): bool
    {
        return $user->can('manage_facilities');
    }
}
