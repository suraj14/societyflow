<?php

namespace App\Policies;

use App\Models\ServiceProvider;
use App\Models\User;

class ServiceProviderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view service providers list
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceProvider $serviceProvider): bool
    {
        // Super Admin can view all
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can view only their society's providers
        if ($user->hasRole('Admin')) {
            // If admin doesn't have society_id, allow access (for Super Admin acting as Admin)
            if (!$user->society_id) {
                return true;
            }
            return $user->society_id === $serviceProvider->society_id;
        }

        // Allow other roles to view service providers (Villa Owner, Apartment Owner, Tenant, Staff)
        if ($user->hasAnyRole(['Villa Owner', 'Apartment Owner', 'Tenant', 'Staff'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceProvider $serviceProvider): bool
    {
        // Super Admin can update all
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can update only their society's providers
        if ($user->hasRole('Admin')) {
            // If admin doesn't have society_id, allow access (for Super Admin acting as Admin)
            if (!$user->society_id) {
                return true;
            }
            return $user->society_id === $serviceProvider->society_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceProvider $serviceProvider): bool
    {
        // Super Admin can delete all
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can delete only their society's providers
        if ($user->hasRole('Admin')) {
            // If admin doesn't have society_id, allow access (for Super Admin acting as Admin)
            if (!$user->society_id) {
                return true;
            }
            return $user->society_id === $serviceProvider->society_id;
        }

        return false;
    }
}