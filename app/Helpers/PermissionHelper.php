<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class PermissionHelper
{
    /**
     * Check if user can access admin features
     */
    public static function canAccessAdmin(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Check if user can access super admin features
     */
    public static function canAccessSuperAdmin(): bool
    {
        return Auth::check() && Auth::user()->hasRole('Super Admin');
    }

    /**
     * Check if a module is enabled for the current user's role
     */
    public static function canAccessModule(string $module): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        $societyId = $user->society_id;
        
        // Get module permissions from settings
        $modulePermissions = \App\Models\SystemSetting::get('permissions', 'module_permissions', [], $societyId);
        
        // If no permissions are set, use default behavior (allow based on role permissions)
        if (empty($modulePermissions)) {
            return self::getDefaultModuleAccess($module, $user);
        }
        
        // Check if module is enabled for any of user's roles
        $userRoles = $user->getRoleNames();
        
        foreach ($userRoles as $role) {
            if (isset($modulePermissions[$module][$role]) && $modulePermissions[$module][$role]) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get default module access based on role (fallback when no settings configured)
     */
    private static function getDefaultModuleAccess(string $module, $user): bool
    {
        // In Free Forever plan, all modules are available to all roles by default
        // But we still respect role-based permissions for what they can do within modules
        $defaults = [
            'facilities' => ['Admin', 'Villa Owner', 'Apartment Owner', 'Tenant'],
            'visitors' => ['Admin', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Staff'],
            'complaints' => ['Admin', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Staff'],
            'bills' => ['Admin', 'Villa Owner', 'Apartment Owner', 'Tenant'],
            'services' => ['Admin', 'Villa Owner', 'Apartment Owner', 'Staff'],
            'notices' => ['Admin', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Staff'],
            'reports' => ['Admin'], // Only Admin can access reports by default
            'advanced_settings' => ['Admin'], // Only Admin can access advanced settings
        ];

        $allowedRoles = $defaults[$module] ?? [];
        return $user->hasAnyRole($allowedRoles);
    }

    /**
     * Check if user can access settings
     */
    public static function canAccessSettings(): bool
    {
        return Auth::check() && Auth::user()->can('access_settings');
    }

    /**
     * Check if user can manage buildings
     */
    public static function canManageBuildings(): bool
    {
        return Auth::check() && Auth::user()->can('view_buildings');
    }

    /**
     * Check if user can manage units
     */
    public static function canManageUnits(): bool
    {
        return Auth::check() && Auth::user()->can('view_units');
    }

    /**
     * Check if user can manage villas
     */
    public static function canManageVillas(): bool
    {
        return Auth::check() && Auth::user()->can('view_villas');
    }

    /**
     * Check if user can manage residents
     */
    public static function canManageResidents(): bool
    {
        return Auth::check() && Auth::user()->can('view_residents');
    }

    /**
     * Check if user can view all bills (admin)
     */
    public static function canViewAllBills(): bool
    {
        return Auth::check() && Auth::user()->can('view_all_bills');
    }

    /**
     * Check if user can view reports
     */
    public static function canViewReports(): bool
    {
        return Auth::check() && Auth::user()->can('view_reports');
    }

    /**
     * Check if user is owner type (Villa Owner or Apartment Owner)
     */
    public static function isOwnerType(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['Villa Owner', 'Apartment Owner']);
    }

    /**
     * Check if user is tenant
     */
    public static function isTenant(): bool
    {
        return Auth::check() && Auth::user()->hasRole('Tenant');
    }

    /**
     * Check if user is staff
     */
    public static function isStaff(): bool
    {
        return Auth::check() && Auth::user()->hasRole('Staff');
    }

    /**
     * Get user's dashboard route based on role
     */
    public static function getDashboardRoute(): string
    {
        if (!Auth::check()) {
            return 'login';
        }

        $user = Auth::user();

        if ($user->hasRole('Super Admin')) {
            return 'super-admin.dashboard';
        }

        if ($user->hasRole('Admin')) {
            return 'admin.dashboard';
        }

        if ($user->hasRole('Villa Owner')) {
            return 'villa-owner.dashboard';
        }

        if ($user->hasAnyRole(['Apartment Owner', 'Tenant'])) {
            return 'owner.dashboard';
        }

        if ($user->hasRole('Staff')) {
            return 'staff.dashboard';
        }

        return 'dashboard';
    }

    /**
     * Get sidebar menu items based on user permissions
     */
    public static function getSidebarMenu(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $user = Auth::user();
        $menu = [];

        // Dashboard - All users
        $menu[] = [
            'name' => 'Dashboard',
            'icon' => 'fa-home',
            'route' => self::getDashboardRoute(),
            'active' => 'dashboard',
        ];

        // Admin-only menus
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            if ($user->can('view_buildings') && self::canAccessModule('buildings')) {
                $menu[] = [
                    'name' => 'Buildings',
                    'icon' => 'fa-city',
                    'route' => 'buildings.index',
                    'active' => 'buildings.*',
                ];
            }

            if ($user->can('view_units') && self::canAccessModule('apartments')) {
                $menu[] = [
                    'name' => 'Apartments',
                    'icon' => 'fa-door-open',
                    'route' => 'flats.index',
                    'active' => 'flats.*',
                ];
            }

            if ($user->can('view_villa_areas') && self::canAccessModule('villas')) {
                $menu[] = [
                    'name' => 'Villa Areas',
                    'icon' => 'fa-map-marked-alt',
                    'route' => 'villa-areas.index',
                    'active' => 'villa-areas.*',
                ];
            }

            if ($user->can('view_villas') && self::canAccessModule('villas')) {
                $menu[] = [
                    'name' => 'Villas',
                    'icon' => 'fa-house-user',
                    'route' => 'villas.index',
                    'active' => 'villas.*',
                ];
            }

            if ($user->can('view_residents') && self::canAccessModule('residents')) {
                $menu[] = [
                    'name' => 'Residents',
                    'icon' => 'fa-users',
                    'route' => 'residents.index',
                    'active' => 'residents.*',
                ];
            }
        }

        // Bills - Admin sees all, owners see own
        if ($user->can('view_all_bills') && self::canAccessModule('bills')) {
            $menu[] = [
                'name' => 'Bills',
                'icon' => 'fa-file-invoice-dollar',
                'route' => 'payments.index',
                'active' => 'payments.*',
            ];
        }

        // Tickets - All users can create/view
        if (self::canAccessModule('complaints')) {
            $menu[] = [
                'name' => 'Tickets',
                'icon' => 'fa-ticket-alt',
                'route' => 'complaints.index',
                'active' => 'complaints.*',
            ];
        }

        // Facilities - All users
        if ($user->can('view_facilities') && self::canAccessModule('facilities')) {
            $menu[] = [
                'name' => 'Facilities',
                'icon' => 'fa-swimming-pool',
                'route' => 'facilities.index',
                'active' => 'facilities.*',
            ];
        }

        // Visitors - Admin, Owners, Staff
        if (($user->can('view_all_visitors') || $user->can('view_own_visitors')) && self::canAccessModule('visitors')) {
            $menu[] = [
                'name' => 'Visitors',
                'icon' => 'fa-user-friends',
                'route' => 'visitors.index',
                'active' => 'visitors.*',
            ];
        }

        // Notices - All users
        if ($user->can('view_notices') && self::canAccessModule('notices')) {
            $menu[] = [
                'name' => 'Notice Board',
                'icon' => 'fa-bullhorn',
                'route' => 'notices.index',
                'active' => 'notices.*',
            ];
        }

        // Services - Admin, Staff
        if ($user->can('view_services') && self::canAccessModule('services')) {
            $menu[] = [
                'name' => 'Services',
                'icon' => 'fa-tools',
                'route' => 'services.index',
                'active' => 'services.*',
            ];
        }

        // Reports - Admin only
        if ($user->can('view_reports') && self::canAccessModule('reports')) {
            $menu[] = [
                'name' => 'Reports',
                'icon' => 'fa-chart-bar',
                'route' => 'reports.index',
                'active' => 'reports.*',
            ];
        }

        // Super Admin link - Admin only
        if ($user->hasRole('Super Admin')) {
            $menu[] = [
                'name' => 'Super Admin',
                'icon' => 'fa-user-shield',
                'route' => 'super-admin.dashboard',
                'active' => 'super-admin.*',
                'divider' => true,
            ];
        }

        // Settings - Admin only
        if ($user->can('access_settings')) {
            $menu[] = [
                'name' => 'Settings',
                'icon' => 'fa-cog',
                'route' => 'settings.index',
                'active' => 'settings.*',
            ];
        }

        return $menu;
    }
}
