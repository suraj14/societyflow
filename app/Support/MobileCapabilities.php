<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;

/**
 * What the signed-in account may DO in the mobile app - the one place the app learns it.
 *
 * The app used to guess from a single `role` string (the first role Spatie returned), which is wrong for
 * multi-role accounts (Admin + Accountant, Owner + Tenant ...) and meant every screen carried its own hard-coded
 * role list. These booleans are computed from the SAME rules the API controllers enforce, so a button is shown
 * only when its endpoint will accept the call. They are a convenience for the UI, never authorization: every
 * endpoint still checks for itself.
 *
 * Mirrored gates (keep in step when a controller's rule changes - MobileCapabilitiesTest pins each against the
 * real endpoint):
 *   book_facility    FacilityApiController::canBook      can('book_facility') || resident role
 *   request_service  ServiceApiController::requestService office || resident role || has a unit
 *   gate_scan        AccessPassApiController::scan       Guard | Admin | Super Admin
 *   guard_shift      GuardShiftApiController             Guard
 *   visitor_manage   VisitorApiController                Staff | Guard | Admin | Super Admin
 *   admin_console    AdminApiController::societyId       Admin with a society
 *   guard-only accounts reach only RestrictGuardApiAccess::ALLOWED (a Guard who is also Admin is not restricted)
 */
final class MobileCapabilities
{
    private const RESIDENT_ROLES = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'];

    /** @return array{roles: list<string>, capabilities: array<string,bool>, modules: array<string,bool>} */
    public static function for(User $user): array
    {
        $roles = $user->getRoleNames()->values()->all();

        $isSuper = $user->hasRole('Super Admin');
        $isAdmin = $user->hasRole('Admin');
        $isGuard = $user->hasRole('Guard');
        $guardOnly = $isGuard && !$isSuper && !$isAdmin;      // RestrictGuardApiAccess
        $isResidentRole = $user->hasAnyRole(self::RESIDENT_ROLES);
        $hasUnit = UserFlatResolver::flatIdsFor($user) !== [];
        $hasSociety = (bool) $user->society_id;
        $sid = $user->society_id;

        $flag = fn (string $key, bool $default) => (bool) SystemSetting::get('features', $key, $default, $sid);
        // Settings -> Features. Only Events is enforced by the backend; the rest are menu switches that the
        // mobile drawer already honours, so the app does too. Defaults match the Settings page.
        $modules = [
            'facilities' => $flag('enable_facilities', true),
            'visitors'   => $flag('enable_visitors', true),
            'complaints' => $flag('enable_complaints', true),
            'services'   => $flag('enable_services', true),
            'notices'    => $flag('enable_notices', true),
            'events'     => $flag('enable_events', false),
        ];

        $office = $isAdmin && $hasSociety;                       // AdminApiController::societyId
        $notGuardOnly = !$guardOnly;
        $residentLike = $isResidentRole || $hasUnit;

        $caps = [
            // office console (Admin)
            'admin_console'        => $office,
            'review_bookings'      => $office && $modules['facilities'],
            'manage_complaints'    => $office && $modules['complaints'],
            'assign_service_requests' => $office && $modules['services'],

            // visitors: everyone sees their own; gate roles see and act on the society's
            'visitors'             => $modules['visitors'],
            'visitor_manage'       => $user->hasAnyRole(['Staff', 'Guard', 'Admin', 'Super Admin']) && $modules['visitors'],
            'visitor_register'     => $user->hasAnyRole(['Staff', 'Guard', 'Admin', 'Super Admin']) && $modules['visitors'],

            // gate / security
            'gate_scan'            => $user->hasAnyRole(['Guard', 'Admin', 'Super Admin']),
            'guard_shift'          => $isGuard,

            // residents' own things (not reachable by a guard-only token)
            'book_facility'        => $notGuardOnly && $modules['facilities'] && ($user->can('book_facility') || $isResidentRole),
            'view_facilities'      => $notGuardOnly && $modules['facilities'],
            'request_service'      => $notGuardOnly && $modules['services'] && ($office || $residentLike),
            'file_complaint'       => $notGuardOnly && $modules['complaints'],
            'bills'                => $notGuardOnly && $hasUnit,
            'access_passes'        => $notGuardOnly && ($office || $residentLike),

            // read-only society surfaces (a guard may read these)
            'services_directory'   => $modules['services'],
            'notices'              => $modules['notices'],
            'events'               => $modules['events'],
            'society_info'         => true,
        ];

        return ['roles' => $roles, 'capabilities' => $caps, 'modules' => $modules];
    }
}
