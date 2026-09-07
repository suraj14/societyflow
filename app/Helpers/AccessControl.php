<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class AccessControl
{
    /**
     * Check if user can manage facilities
     */
    public static function canManageFacilities(): bool
    {
        return Auth::user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Check if user can book facilities
     */
    public static function canBookFacilities(): bool
    {
        return Auth::user()?->can('book_facility') ?? false;
    }

    /**
     * Check if user can manage visitors
     */
    public static function canManageVisitors(): bool
    {
        return Auth::user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Check if user can view own visitors
     */
    public static function canViewOwnVisitors(): bool
    {
        return Auth::user()?->can('view_own_visitors') ?? false;
    }

    /**
     * Check if user can check in/out visitors
     */
    public static function canCheckInVisitors(): bool
    {
        return Auth::user()?->can('checkin_visitor') ?? false;
    }

    /**
     * Check if user can manage bills
     */
    public static function canManageBills(): bool
    {
        return Auth::user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Check if user can view own bills
     */
    public static function canViewOwnBills(): bool
    {
        return Auth::user()?->can('view_own_bills') ?? false;
    }

    /**
     * Check if user can pay bills
     */
    public static function canPayBills(): bool
    {
        return Auth::user()?->can('pay_bill') ?? false;
    }

    /**
     * Check if user can manage tickets
     */
    public static function canManageTickets(): bool
    {
        return Auth::user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Check if user can create tickets
     */
    public static function canCreateTickets(): bool
    {
        return Auth::user()?->can('create_ticket') ?? false;
    }

    /**
     * Check if user can view own tickets
     */
    public static function canViewOwnTickets(): bool
    {
        return Auth::user()?->can('view_own_ticket') ?? false;
    }

    /**
     * Check if user can resolve tickets
     */
    public static function canResolveTickets(): bool
    {
        return Auth::user()?->can('resolve_ticket') ?? false;
    }

    /**
     * Check if user can manage services
     */
    public static function canManageServices(): bool
    {
        return Auth::user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Check if user can clock in/out services
     */
    public static function canClockServices(): bool
    {
        return Auth::user()?->can('clock_service') ?? false;
    }

    /**
     * Check if user can manage notices
     */
    public static function canManageNotices(): bool
    {
        return Auth::user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Check if user can view notices
     */
    public static function canViewNotices(): bool
    {
        return Auth::user()?->can('view_notices') ?? false;
    }

    /**
     * Check if user is owner or tenant
     */
    public static function isOwnerOrTenant(): bool
    {
        return Auth::user()?->hasAnyRole(['Villa Owner', 'Apartment Owner', 'Tenant']) ?? false;
    }

    /**
     * Check if user is staff
     */
    public static function isStaff(): bool
    {
        return Auth::user()?->hasRole('Staff') ?? false;
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin(): bool
    {
        return Auth::user()?->hasAnyRole(['Super Admin', 'Admin']) ?? false;
    }

    /**
     * Get user's accessible modules
     */
    public static function getAccessibleModules(): array
    {
        $user = Auth::user();
        if (!$user) {
            return [];
        }

        $modules = [];

        // Dashboard always accessible
        $modules[] = 'dashboard';

        // Admin modules
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            $modules = array_merge($modules, [
                'societies',
                'buildings',
                'apartments',
                'villas',
                'villa_areas',
                'residents',
                'bills_management',
                'facilities_management',
                'service_providers',
                'reports',
                'settings',
            ]);
        }

        // User modules
        if ($user->can('view_own_tickets') || $user->can('view_all_tickets')) {
            $modules[] = 'tickets';
        }

        if ($user->can('book_facility')) {
            $modules[] = 'amenities';
        }

        if ($user->can('view_own_visitors') || $user->can('view_all_visitors')) {
            $modules[] = 'visitors';
        }

        if ($user->can('view_services')) {
            $modules[] = 'services';
        }

        if ($user->can('view_notices')) {
            $modules[] = 'notices';
        }

        if ($user->can('view_own_bills')) {
            $modules[] = 'bills';
        }

        return $modules;
    }
}
