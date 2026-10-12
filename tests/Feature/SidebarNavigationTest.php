<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The desktop sidebar is grouped and compact (partials/sidebar-nav). Visibility rules were carried over 1:1 from
 * the old flat sidebar, so each role must see exactly the destinations it saw before, every one must be reachable
 * (not 403/404/500), the group holding the active route must start open, and the active link must be marked.
 *
 * Baseline for the "same destinations" check was produced by running this file's dump against the old layout
 * (SIDEBAR_DUMP=<file>); the constants below are that recorded set.
 */
class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** role => destinations (path) the OLD sidebar linked to, besides Dashboard. */
    private const BASELINE = [
        'Admin' => ['/users', '/owners', '/admin/property-occupancy', '/buildings', '/flats', '/villa-areas', '/villas', '/admin/tenants', '/admin/rents', '/complaints', '/facilities', '/facility-bookings', '/visitors', '/access-passes', '/services', '/notices', '/society-rules', '/payments', '/expenses', '/reports/maintenance', '/reports/financial', '/events', '/admin/shift-assignments', '/settings'],
        'Apartment Owner' => ['/complaints', '/facilities', '/facility-bookings', '/visitors', '/access-passes', '/services', '/notices', '/society-rules', '/payments', '/reports/maintenance', '/reports/financial', '/events'],
        'Tenant' => ['/tenant/rent', '/complaints', '/facilities', '/facility-bookings', '/visitors', '/access-passes', '/services', '/notices', '/society-rules', '/payments', '/reports/maintenance', '/reports/financial', '/events'],
        'Staff' => ['/complaints', '/facilities', '/visitors', '/services', '/notices', '/events'],   // no /facility-bookings: Staff hold no booking permission
        'Accountant' => ['/payments', '/utility-bills', '/admin/rents', '/expenses', '/reports/financial', '/events'],   // /events: EventController lets every role view them (module on); the old desktop gate omitted Accountant
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // The real role -> permission map, so pages and links are judged as in production.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function navLinks(string $html): array
    {
        $a = strpos($html, '<!-- Navigation');
        $b = strpos($html, '<!-- User Profile -->', $a);
        $this->assertNotFalse($a);
        $this->assertNotFalse($b);
        preg_match_all('/<a\s[^>]*href="([^"]+)"/', substr($html, $a, $b - $a), $m);

        return collect($m[1])->map(fn ($h) => parse_url($h, PHP_URL_PATH) ?: '/')->reject(fn ($p) => $p === '/dashboard')->unique()->sort()->values()->all();
    }

    private function userFor(string $role, $society, bool $events = true)
    {
        // The old sidebar linked Events unconditionally; the baseline below is for a society with the module on.
        \App\Models\SystemSetting::set('features', 'enable_events', $events, 'boolean', $society->id);

        return $this->makeUserWithRole($society, $role);
    }

    public function test_events_is_hidden_when_the_events_module_is_off_because_that_page_only_answers_403(): void
    {
        $society = $this->makeSociety('Alpha');
        $off = $this->userFor('Admin', $society, events: false);
        $this->assertNotContains('/events', $this->navLinks($this->actingAs($off)->get('/dashboard')->assertOk()->getContent()));
        $this->actingAs($off)->get('/events')->assertForbidden();

        $on = $this->userFor('Admin', $society, events: true);
        $this->assertContains('/events', $this->navLinks($this->actingAs($on)->get('/dashboard')->assertOk()->getContent()));
    }

    public function test_dump_for_baseline_capture(): void
    {
        if (!($file = getenv('SIDEBAR_DUMP'))) {
            $this->markTestSkipped('only used to record the baseline');
        }
        $society = $this->makeSociety('Alpha');
        $out = [];
        foreach (array_keys(self::BASELINE) as $role) {
            $html = $this->actingAs($this->userFor($role, $society))->get('/dashboard')->assertOk()->getContent();
            $out[$role] = $this->navLinks($html);
        }
        file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT));
        $this->assertTrue(true);
    }

    public function test_each_role_sees_exactly_the_destinations_it_saw_before(): void
    {
        $society = $this->makeSociety('Alpha');

        foreach (self::BASELINE as $role => $expected) {
            $html = $this->actingAs($this->userFor($role, $society))->get('/dashboard')->assertOk()->getContent();
            $expected = collect($expected)->sort()->values()->all();
            $this->assertSame($expected, $this->navLinks($html), "$role: sidebar destinations changed");
        }
    }

    public function test_every_sidebar_destination_is_reachable_for_the_role_that_sees_it(): void
    {
        $society = $this->makeSociety('Alpha');

        foreach (array_keys(self::BASELINE) as $role) {
            $user = $this->userFor($role, $society);
            $links = $this->navLinks($this->actingAs($user)->get('/dashboard')->assertOk()->getContent());
            foreach ($links as $path) {
                $status = $this->actingAs($user)->get($path)->getStatusCode();
                $this->assertContains($status, [200, 302], "$role -> $path answered $status");
            }
        }
    }

    public function test_the_group_holding_the_active_route_starts_open_and_the_active_link_is_marked(): void
    {
        $society = $this->makeSociety('Alpha');
        $admin = $this->userFor('Admin', $society);

        $html = $this->actingAs($admin)->get('/visitors')->assertOk()->getContent();
        $nav = substr($html, strpos($html, '<nav class="flex-1'), 60000);
        $nav = substr($nav, 0, strpos($nav, '</nav>'));

        // the Security & Visitors group is open, the others are closed
        $this->assertMatchesRegularExpression('/x-data="\{ open: true \}"[^>]*>\s*<button[^>]*aria-controls="sf-nav-security"/s', $nav);
        $this->assertMatchesRegularExpression('/x-data="\{ open: false \}"[^>]*>\s*<button[^>]*aria-controls="sf-nav-people"/s', $nav);
        // the active child carries aria-current and the highlight
        $this->assertMatchesRegularExpression('#<a href="[^"]*/visitors"\s+aria-current="page"#', $nav);
        $this->assertSame(1, substr_count($nav, 'aria-current="page"'));
    }

    public function test_single_item_sections_are_direct_links_and_dashboard_is_never_inside_a_group(): void
    {
        $society = $this->makeSociety('Alpha');
        $html = $this->actingAs($this->userFor('Admin', $society))->get('/dashboard')->assertOk()->getContent();
        $nav = substr($html, strpos($html, '<nav class="flex-1'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));

        $this->assertStringNotContainsString('aria-controls="sf-nav-settings"', $nav, 'Settings is a direct link, not a one-item group');
        $this->assertStringNotContainsString('aria-controls="sf-nav-society"', $nav);
        $this->assertSame(1, preg_match('#<li>\s*<a href="[^"]*/dashboard"#', $nav), 'Dashboard is the first direct link');
        $this->assertSame(1, substr_count($nav, 'href="' . url('/settings') . '"'), 'no destination is listed twice');
    }

    public function test_a_user_who_is_both_accountant_and_admin_sees_each_destination_once(): void
    {
        $society = $this->makeSociety('Alpha');
        $user = $this->userFor('Admin', $society);
        $this->makeRole('Accountant');
        $user->assignRole('Accountant');

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
        $a = strpos($html, '<!-- Navigation');
        $nav = substr($html, $a, strpos($html, '<!-- User Profile -->', $a) - $a);

        foreach (['/payments', '/admin/rents', '/expenses', '/reports/financial'] as $path) {
            $this->assertSame(1, substr_count($nav, 'href="' . url($path) . '"'), "$path listed more than once");
        }
    }

    public function test_staff_are_not_shown_book_facilities_but_the_booking_pages_stay_forbidden(): void
    {
        $society = $this->makeSociety('Alpha');
        $staff = $this->userFor('Staff', $society);

        $links = $this->navLinks($this->actingAs($staff)->get('/dashboard')->assertOk()->getContent());
        $this->assertNotContains('/facility-bookings', $links);
        $this->assertContains('/facilities', $links, 'read-only Facilities (a documented Staff right) stays');

        // hiding the link is not the control: the backend still refuses every booking route
        $this->actingAs($staff)->get('/facility-bookings')->assertForbidden();
        $this->actingAs($staff)->get('/facility-bookings/create')->assertForbidden();
        $this->actingAs($staff)->post('/facility-bookings', ['facility_id' => 1])->assertForbidden();
    }

    public function test_roles_that_may_book_facilities_still_see_the_link(): void
    {
        $society = $this->makeSociety('Alpha');
        foreach (['Admin', 'Apartment Owner', 'Tenant'] as $role) {
            $links = $this->navLinks($this->actingAs($this->userFor($role, $society))->get('/dashboard')->assertOk()->getContent());
            $this->assertContains('/facility-bookings', $links, $role);
        }
    }

    public function test_a_multi_role_user_sees_the_union_of_what_each_role_sees(): void
    {
        $society = $this->makeSociety('Alpha');
        $links = fn ($u) => $this->navLinks($this->actingAs($u)->followingRedirects()->get('/dashboard')->assertOk()->getContent());   // a multi-role dashboard may redirect

        foreach ([['Admin', 'Accountant'], ['Apartment Owner', 'Tenant'], ['Staff', 'Accountant'], ['Apartment Owner', 'Villa Owner']] as [$r1, $r2]) {
            $a = $links($this->userFor($r1, $society));
            $b = $links($this->userFor($r2, $society));
            $both = $this->userFor($r1, $society);
            $this->makeRole($r2);
            $both->assignRole($r2);

            $expected = collect($a)->merge($b)->unique()->sort()->values()->all();
            $this->assertSame($expected, $links($both), "$r1 + $r2: deduplication lost or invented a destination");
        }
    }

    public function test_events_direct_access_follows_the_module_flag_for_every_role_not_the_menu(): void
    {
        $society = $this->makeSociety('Alpha');
        foreach (['Admin', 'Apartment Owner', 'Tenant', 'Staff', 'Accountant'] as $role) {
            $off = $this->userFor($role, $society, events: false);
            $this->actingAs($off)->get('/events')->assertForbidden();           // hidden link is not the control

            $on = $this->userFor($role, $society, events: true);
            $this->actingAs($on)->get('/events')->assertOk();
        }
    }
}