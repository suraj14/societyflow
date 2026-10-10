<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Walks every registered web route and asserts a Guard cannot reach any
 * path outside the gate allowlist, for every HTTP verb.
 *
 * A hand-picked list of URLs proves only the URLs someone thought of. This
 * test is generated from the router itself, so a route added next month is
 * covered the moment it exists.
 *
 * The check is on the status code, not the body: a 403 from the allowlist,
 * or a 302/redirect that does not reach a module, both count as refused.
 * What must never happen is a 200 (or a 201/204) from a module path.
 */
class GuardAllowlistExhaustiveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Path prefixes the allowlist deliberately admits. Keep in step with
     * RestrictGuardAccess. `notifications` is the header bell in the guard
     * layout: it only ever lists/marks the requesting user's own entries
     * (InAppNotificationTest::test_guard_bell_is_reachable_and_scoped and
     * the own-notifications-only tests beside it).
     */
    private const ALLOWED_PREFIXES = ['guard', 'profile', 'logout', 'login', 'dashboard', 'notifications'];

    /**
     * Vendor routes that are static public files, not modules: served the
     * same to anyone, signed in or not. Livewire's JS assets answer with a
     * file response that the cache-header middleware used to crash on (500),
     * which hid them from this walk until that crash was fixed.
     */
    private const PUBLIC_ASSET_PATHS = ['livewire/livewire.js', 'livewire/livewire.js.map'];

    private function isAllowedPath(string $uri): bool
    {
        $path = ltrim($uri, '/');

        // The landing page redirects by role and is not a module.
        if ($path === '') {
            return true;
        }

        if (in_array($path, self::PUBLIC_ASSET_PATHS, true)) {
            return true;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** Route params are unknown here, so substitute a harmless placeholder. */
    private function concreteUri(Route $route): string
    {
        return '/' . preg_replace('/\{[^}]+\}/', '1', $route->uri());
    }

    public function test_a_guard_gets_no_success_response_from_any_module_route(): void
    {
        $society = $this->makeSociety();
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $leaks = [];
        $checked = 0;

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            // Only web routes: the API is authenticated with Sanctum and
            // covered by its own suite.
            if (str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $uri = $this->concreteUri($route);
            if ($this->isAllowedPath($uri)) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $checked++;
                $status = $this->actingAs($guard)
                    ->call($method, $uri, ['_token' => 'x'])
                    ->getStatusCode();

                // Refused means 403, or a redirect/404/419/422 that never
                // reached the module. Only a 2xx is a leak.
                if ($status >= 200 && $status < 300) {
                    $leaks[] = "{$method} {$uri} -> {$status}";
                }
            }
        }

        $this->assertGreaterThan(100, $checked, 'the walk must actually cover the router');
        $this->assertSame([], $leaks, 'a Guard reached module routes outside the allowlist');
    }

    /**
     * The gate's own routes must still answer - the containment must not
     * have broken the workflow it protects.
     */
    public function test_the_gate_routes_still_respond_for_a_guard(): void
    {
        $society = $this->makeSociety();
        $guard   = $this->makeUserWithRole($society, 'Guard');

        $this->actingAs($guard)->get('/guard')->assertOk();
        $this->actingAs($guard)->get('/guard/scan')->assertOk();
    }
}
