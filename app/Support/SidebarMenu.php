<?php

namespace App\Support;

use App\Models\SocietyDocument;
use App\Models\User;

/**
 * Who sees which sidebar destination - the rules behind partials/sidebar-nav (desktop) and the extra rows of the
 * mobile drawer. Visibility is navigation only: every destination enforces its own authorization (a hidden link
 * is never a control, and a visible one can still 403).
 *
 * Rules were carried over 1:1 from the old flat sidebar. Differences from it, each pinned by SidebarNavigationTest:
 *  - Events is listed only when the society switched the Events module on (the page 403s otherwise);
 *  - "Book Facilities" mirrors FacilityBookingController::index()'s own gate (Staff hold no booking permission);
 *  - a destination two role blocks would both show is listed once.
 *
 * @phpstan-type Item array{label: string, route: string, icon: string, fa: string, active: list<string>}
 */
final class SidebarMenu
{
    /**
     * Destinations the mobile drawer (partials/mobile-menu) has never rendered for roles that may open them.
     * partials/mobile-menu appends exactly these - and only when the drawer's own markup does not already link
     * to them - so a phone user can reach what a desktop user can.
     */
    public const DRAWER_GAPS = [
        'admin.property-occupancy.index',
        'access-passes.index',
        'admin.shift-assignments.index',
        'expenses.index',
        'utility-bills.index',
        'tenant.rent',
        'visitors.index',
        'facilities.index',   // Staff have read-only Facilities (RestrictStaffAccess) but the drawer only linked it for others
    ];

    /** Module switches (Settings -> Features) the drawer honours; an extra row for such a module respects the same switch. */
    private const MODULE_SWITCH = ['visitors.index' => 'enable_visitors', 'facilities.index' => 'enable_facilities'];

    /** @return array<string, array{label: string, icon: string, items: list<array>}> */
    public static function groups(User $user): array
    {
        $isAdmin = $user->hasAnyRole(['Admin', 'Society Admin']);
        $isOwner = $user->hasAnyRole(['Villa Owner', 'Apartment Owner', 'Owner']);
        $isTenant = $user->hasRole('Tenant');
        $isStaff = $user->hasRole('Staff');
        $isAccountant = $user->hasRole('Accountant');
        $superOrAdmin = $user->hasAnyRole(['Super Admin', 'Admin']);
        $broad = $isAdmin || $isOwner || $isTenant || $isStaff;   // the old "everything else" block

        // Navigation follows the society's configured property type. Super Admin has no society and keeps both.
        $society = $user->society;
        $showApartments = $society === null || $society->supportsApartments();
        $showVillas = $society === null || $society->supportsVillas();

        $groups = [];
        $seen = [];
        $add = function (string $key, string $groupLabel, string $groupIcon, string $label, string $route, string $icon, string $fa, array $active) use (&$groups, &$seen) {
            if (isset($seen[$route])) {
                return;
            }
            $seen[$route] = true;
            $groups[$key] ??= ['label' => $groupLabel, 'icon' => $groupIcon, 'items' => []];
            $groups[$key]['items'][] = compact('label', 'route', 'icon', 'fa', 'active');
        };

        // People & Properties
        $P = ['people', 'People & Properties', 'users'];
        if ($isAdmin) {
            $add(...[...$P, 'User', 'users.index', 'user', 'fa-users', ['users.*']]);
            $add(...[...$P, 'Owners', 'owners.index', 'user-check', 'fa-user-tie', ['owners.*']]);
            if ($superOrAdmin) {
                $add(...[...$P, 'Property', 'admin.property-occupancy.index', 'building-2', 'fa-building', ['admin.property-occupancy.*']]);
            }
            if ($showApartments) {
                $add(...[...$P, 'Buildings', 'buildings.index', 'building', 'fa-city', ['buildings.*']]);
                $add(...[...$P, 'Apartments', 'flats.index', 'door-open', 'fa-door-open', ['flats.*']]);
            }
            if ($showVillas) {
                $add(...[...$P, 'Villa Areas', 'villa-areas.index', 'map', 'fa-map', ['villa-areas.*']]);
                $add(...[...$P, 'Villas', 'villas.index', 'home', 'fa-house-user', ['villas.*']]);
            }
            $add(...[...$P, 'Tenants', 'admin.tenants.index', 'key-round', 'fa-key', ['admin.tenants.*']]);
        }

        // Security & Visitors
        $S = ['security', 'Security & Visitors', 'shield-check'];
        if ($broad) {
            $add(...[...$S, 'Visitors', 'visitors.index', 'footprints', 'fa-user-friends', ['visitors.*']]);
            if ($user->hasAnyRole(['Super Admin', 'Admin', 'Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'])) {
                $add(...[...$S, 'Access Passes', 'access-passes.index', 'contact', 'fa-id-card', ['access-passes.*']]);
            }
        }
        if ($isAdmin) {
            $add(...[...$S, 'Guard Shifts', 'admin.shift-assignments.index', 'user-cog', 'fa-user-shield', ['admin.shift-assignments.*', 'admin.gates.*', 'admin.shifts.*']]);
        }

        // Facilities & Services
        $F = ['services', 'Facilities & Services', 'concierge-bell'];
        if ($broad) {
            $add(...[...$F, 'Tickets', 'complaints.index', 'ticket', 'fa-ticket-alt', ['complaints.*']]);
            $add(...[...$F, 'Services', 'services.index', 'concierge-bell', 'fa-concierge-bell', ['services.*', 'service-providers.*']]);
            $add(...[...$F, 'Facilities', 'facilities.index', 'dumbbell', 'fa-swimming-pool', ['facilities.*']]);
            // Mirrors FacilityBookingController::index(): Staff hold no booking permission and get a 403 there.
            if ($user->can('view_own_bookings') || $user->can('manage_facilities')
                || $user->hasAnyRole(['Owner', 'Villa Owner', 'Apartment Owner', 'Resident', 'Tenant'])) {
                $add(...[...$F, 'Book Facilities', 'facility-bookings.index', 'calendar-check', 'fa-calendar-check', ['facility-bookings.*']]);
            }
        }

        // Notices & Events
        $N = ['notices', 'Notices & Events', 'megaphone'];
        if ($broad) {
            $add(...[...$N, 'Notices', 'notices.index', 'megaphone', 'fa-bullhorn', ['notices.*']]);
        }
        // Events: EventController lets every role view them ("All roles can view events", Accountant included), but it is a
        // feature-flagged module that answers 403 when off. The old desktop gate left Accountant out (the drawer had
        // them), so Accountant gets the link too.
        if ($broad || $isAccountant) {
            if (\App\Models\SystemSetting::get('features', 'enable_events', false, $user->society_id)) {
                $add(...[...$N, 'Events', 'events.index', 'calendar-days', 'fa-calendar-alt', ['events.*']]);
            }
        }

        // Finance & Reports
        $M = ['finance', 'Finance & Reports', 'wallet'];
        if ($isAccountant) {
            $add(...[...$M, 'Bills & Payments', 'payments.index', 'file-text', 'fa-file-invoice-dollar', ['payments.*']]);
            $add(...[...$M, 'Utility Bills', 'utility-bills.index', 'receipt', 'fa-receipt', ['utility-bills.*']]);
            $add(...[...$M, 'Rent', 'admin.rents.index', 'banknote', 'fa-key', ['admin.rents.*']]);
            $add(...[...$M, 'Society Expenses', 'expenses.index', 'wallet', 'fa-wallet', ['expenses.*']]);
            $add(...[...$M, 'Finance Reports', 'reports.financial', 'trending-up', 'fa-chart-line', ['reports.financial']]);
        }
        if ($isTenant) {
            $add(...[...$M, 'Rent', 'tenant.rent', 'banknote', 'fa-money-bill-wave', ['tenant.rent']]);
        }
        if ($broad && !$isStaff) {
            $add(...[...$M, 'Bills', 'payments.index', 'file-text', 'fa-file-invoice-dollar', ['payments.*']]);
        }
        if ($isAdmin) {
            $add(...[...$M, 'Rent', 'admin.rents.index', 'banknote', 'fa-key', ['admin.rents.*']]);
            $add(...[...$M, 'Society Expenses', 'expenses.index', 'wallet', 'fa-wallet', ['expenses.*']]);
        }
        if ($broad && !$isStaff) {
            $add(...[...$M, 'Maintenance Report', 'reports.maintenance', 'wrench', 'fa-wrench', ['reports.maintenance']]);
            $add(...[...$M, 'Financial Report', 'reports.financial', 'bar-chart-3', 'fa-chart-line', ['reports.financial']]);
        }

        // Society Info (details, committee, documents live on its own tabs)
        if ($broad && ($superOrAdmin || $user->hasAnyRole(SocietyDocument::RESIDENT_ROLES))) {
            $add('society', 'Society Info', 'info', 'Society Info', 'society-rules.index', 'info', 'fa-circle-info',
                $superOrAdmin ? ['society-rules.*', 'admin.society-documents.*'] : ['society-rules.*']);
        }

        // Settings
        if ($isAdmin) {
            $add('settings', 'Settings', 'settings', 'Settings', 'settings.index', 'settings', 'fa-cog', ['settings.*']);
        }

        return $groups;
    }

    /**
     * Rows to append to the mobile drawer: a destination this account may see on desktop that the drawer has never
     * linked. [$drawerHtml] is what the drawer already rendered for this user - anything it already links to is
     * skipped, so adding a row here can never duplicate one, whatever the drawer's own rules are. Module switches
     * the drawer honours (Visitors) are honoured here too.
     *
     * @return list<array>
     */
    public static function drawerExtras(User $user, string $drawerHtml): array
    {
        $extras = [];
        foreach (self::groups($user) as $group) {
            foreach ($group['items'] as $item) {
                if (!in_array($item['route'], self::DRAWER_GAPS, true)) {
                    continue;
                }
                $switch = self::MODULE_SWITCH[$item['route']] ?? null;
                if ($switch && !\App\Models\SystemSetting::get('features', $switch, true, $user->society_id)) {
                    continue;
                }
                $path = parse_url(route($item['route']), PHP_URL_PATH) ?: '';
                if ($path !== '' && preg_match('#href="[^"]*' . preg_quote($path, '#') . '"#', $drawerHtml)) {
                    continue;
                }
                $extras[] = $item;
            }
        }

        return $extras;
    }
}
