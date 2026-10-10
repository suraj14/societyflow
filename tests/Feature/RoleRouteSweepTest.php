<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Owner;
use App\Models\Society;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * No page may crash for any role. Requests every parameterless GET route of
 * the real route table as every role, against a small but populated society
 * (a page that renders on an empty database can still fatal once a row
 * exists - WebSmokeTest's lesson), and fails on any 5xx.
 *
 * This is a crash detector, not an authorization test: 200, 403, 302 and 404
 * are all acceptable answers here; only "the server fell over" is not. (What
 * each role may and may not do is covered by the per-module suites.) It is
 * the net that would have caught the Villa Assignments screen returning 500
 * whenever the legacy 'Villa Owner' role was absent from the roles table.
 */
class RoleRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    /** GET routes that must not be hit blindly: they end the session or are not pages. */
    private const SKIP = '#^(logout|impersonat|stop-impersonat|sanctum|_ignition|_debugbar|storage|up$|livewire|api/)#i';

    public static function roles(): array
    {
        return array_map(fn ($r) => [$r], ['Super Admin', 'Admin', 'Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Guard', 'Staff', 'Accountant']);
    }

    private function populated(): Society
    {
        $society = $this->makeSociety('Sweep Society');
        $building = $this->makeBuilding($society);
        $flat = $this->makeFlat($society, $building, '101');

        $owner = Owner::create(['society_id' => $society->id, 'name' => 'Sweep Owner', 'email' => 'sweep.owner@test.local', 'phone' => '9000000010', 'status' => 'active', 'property_type' => 'apartment', 'building_id' => $building->id, 'flat_no' => '101']);
        $flat->update(['owner_id' => $owner->id]);
        Tenant::create(['society_id' => $society->id, 'flat_id' => $flat->id, 'name' => 'Sweep Tenant', 'email' => 'sweep.tenant@test.local', 'phone' => '9000000011', 'move_in_date' => '2026-01-01', 'status' => 'active']);
        Facility::create(['society_id' => $society->id, 'name' => 'Sweep Gym', 'type' => 'gym', 'status' => 'active']);

        return $society;
    }

    /** @dataProvider roles */
    public function test_no_parameterless_page_crashes_for_this_role(string $role): void
    {
        $society = $this->populated();
        $user = $this->makeUserWithRole($society, $role, $role === 'Super Admin' ? ['society_id' => null] : []);
        $this->actingAs($user);

        $crashes = [];
        $visited = 0;

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $uri = ltrim($route->uri(), '/');
            if (!in_array('GET', $route->methods(), true) || str_contains($uri, '{') || preg_match(self::SKIP, $uri)) {
                continue;
            }

            $status = $this->get('/' . $uri)->getStatusCode();
            $visited++;

            if ($status >= 500) {
                $crashes[] = "GET /{$uri} -> {$status}";
            }
        }

        $this->assertGreaterThan(100, $visited, 'the route table was not enumerated');
        $this->assertSame([], $crashes, "{$role}: pages that crashed\n  " . implode("\n  ", $crashes));
    }
}
