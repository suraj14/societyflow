<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile drawer (partials/mobile-menu) has its own, older rules and had never linked several destinations a role
 * may open: Admin - Property, Access Passes, Guard Shifts, Society Expenses; Owner - Access Passes; Tenant - Visitors,
 * Access Passes, Rent; Accountant - Utility Bills, Society Expenses. They are appended now (App\Support\SidebarMenu::
 * drawerExtras), only when the drawer does not already link them. Module switches the drawer honours stay honoured.
 *
 * Authorization is unchanged and still enforced by each page: a visible link is not access.
 */
class DrawerParityTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['Admin', 'Apartment Owner', 'Tenant', 'Staff', 'Accountant'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function paths(string $html, string $from, string $to): array
    {
        $a = strpos($html, $from);
        $this->assertNotFalse($a, "marker '$from' missing");
        $b = strpos($html, $to, $a + 5);
        preg_match_all('/<a\s[^>]*href="([^"]+)"/', substr($html, $a, ($b === false ? strlen($html) : $b) - $a), $m);

        return collect($m[1])->map(fn ($h) => parse_url($h, PHP_URL_PATH) ?: '/')->reject(fn ($p) => in_array($p, ['/dashboard', '/profile', '/profile/edit', '/logout']))->values()->all();
    }

    private function both(string $role, array $flags = []): array
    {
        $society = $this->makeSociety('S' . uniqid());
        SystemSetting::set('features', 'enable_events', true, 'boolean', $society->id);
        foreach ($flags as $k => $v) {
            SystemSetting::set('features', $k, $v, 'boolean', $society->id);
        }
        $user = $this->makeUserWithRole($society, $role);
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        return [
            'desktop' => collect($this->paths($html, '<!-- Navigation', '<!-- User Profile -->'))->unique()->sort()->values()->all(),
            'mobile' => $this->paths($html, 'Mobile Sidebar - SAME AS DESKTOP', '</aside>'),
            'user' => $user,
        ];
    }

    public function test_every_destination_a_role_sees_on_desktop_is_reachable_from_its_drawer_when_all_modules_are_on(): void
    {
        foreach (self::ROLES as $role) {
            $x = $this->both($role);
            $missing = array_values(array_diff($x['desktop'], array_unique($x['mobile'])));
            $this->assertSame([], $missing, "$role: on desktop but not in the phone drawer");
        }
    }

    public function test_the_drawer_never_lists_a_destination_twice(): void
    {
        foreach (self::ROLES as $role) {
            $mobile = $this->both($role)['mobile'];
            $dups = array_keys(array_filter(array_count_values($mobile), fn ($n) => $n > 1));
            $this->assertSame([], $dups, "$role: duplicated in the drawer");
        }
    }

    public function test_the_previously_missing_destinations_now_appear_for_the_roles_that_may_open_them(): void
    {
        $expect = [
            'Admin' => ['/admin/property-occupancy', '/access-passes', '/admin/shift-assignments', '/expenses'],
            'Apartment Owner' => ['/access-passes'],
            'Tenant' => ['/visitors', '/access-passes', '/tenant/rent'],
            'Accountant' => ['/utility-bills', '/expenses'],
        ];
        foreach ($expect as $role => $paths) {
            $x = $this->both($role);
            foreach ($paths as $p) {
                $this->assertContains($p, $x['mobile'], "$role drawer lacks $p");
                // and the page really opens for that role - hiding/showing is navigation, not access
                $this->assertContains($this->actingAs($x['user'])->get($p)->getStatusCode(), [200, 302], "$role -> $p");
            }
        }
    }

    public function test_the_drawer_adds_nothing_a_role_may_not_see_on_desktop(): void
    {
        foreach (self::ROLES as $role) {
            $x = $this->both($role);
            $extra = array_values(array_diff(array_unique($x['mobile']), $x['desktop']));
            $this->assertSame([], $extra, "$role: the drawer links something desktop does not offer");
        }
    }

    public function test_staff_get_no_extra_rows_and_still_cannot_open_the_booking_page(): void
    {
        $x = $this->both('Staff');

        $this->assertNotContains('/facility-bookings', $x['mobile']);
        $this->assertNotContains('/expenses', $x['mobile']);
        $this->actingAs($x['user'])->get('/facility-bookings')->assertForbidden();
    }

    public function test_the_visitors_switch_still_hides_visitors_in_the_drawer(): void
    {
        $x = $this->both('Tenant', ['enable_visitors' => false]);

        $this->assertNotContains('/visitors', $x['mobile'], 'the drawer honours the module switch (the page itself still answers 200 - menu switch only)');
    }

    public function test_the_facilities_switch_is_not_overridden_by_the_extra_rows(): void
    {
        foreach (['Staff', 'Tenant', 'Admin'] as $role) {
            $x = $this->both($role, ['enable_facilities' => false]);
            $this->assertNotContains('/facilities', $x['mobile'], "$role: a switched-off module must stay out of the drawer");
        }
        $this->assertContains('/facilities', $this->both('Staff')['mobile'], 'Staff read-only Facilities is now in the drawer');
    }

    public function test_events_show_in_the_drawer_and_on_desktop_for_an_accountant_only_when_the_module_is_on(): void
    {
        $on = $this->both('Accountant');
        $this->assertContains('/events', $on['desktop']);
        $this->assertContains('/events', $on['mobile']);

        $society = $this->makeSociety('Off');
        SystemSetting::set('features', 'enable_events', false, 'boolean', $society->id);
        $acct = $this->makeUserWithRole($society, 'Accountant');
        $html = $this->actingAs($acct)->get('/dashboard')->assertOk()->getContent();
        $this->assertNotContains('/events', $this->paths($html, '<!-- Navigation', '<!-- User Profile -->'));
        $this->assertNotContains('/events', $this->paths($html, 'Mobile Sidebar - SAME AS DESKTOP', '</aside>'));
        $this->actingAs($acct)->get('/events')->assertForbidden();
    }
}
