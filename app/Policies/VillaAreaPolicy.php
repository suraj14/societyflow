<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VillaArea;

class VillaAreaPolicy
{
    /**
     * Determine whether the user can view any villa areas.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can view the villa area.
     */
    public function view(User $user, VillaArea $villaArea): bool
    {
        // Super Admin can view any villa area
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can only view villa areas from their society
        if ($user->hasRole('Admin')) {
            return $villaArea->society_id === $user->society_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create villa areas.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can update the villa area.
     */
    public function update(User $user, VillaArea $villaArea): bool
    {
        // Super Admin can update any villa area
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can only update villa areas from their society
        if ($user->hasRole('Admin')) {
            return $villaArea->society_id === $user->society_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the villa area.
     */
    public function delete(User $user, VillaArea $villaArea): bool
    {
        // Super Admin can delete any villa area
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can only delete villa areas from their society
        if ($user->hasRole('Admin')) {
            return $villaArea->society_id === $user->society_id;
        }

        return false;
    }
}
