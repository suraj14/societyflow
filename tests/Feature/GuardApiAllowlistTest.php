<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The mobile API had no role gating at all: every route in the authenticated
 * group answered any signed-in role, so a Guard's token could read the
 * society's payments and bills, file or list complaints, book facilities and
 * list access passes. The app only hid those screens.
 *
 * Like GuardAllowlistExhaustiveTest for the web, this walks the router itself
 * (not a hand-picked list) so a route added later is covered the moment it
 * exists, and it states the allowlist independently of the middleware so the
 * two cannot silently drift together.
 */
class GuardApiAllowlistTest extends TestCase
{
    use RefreshDatabase;

    /** Paths (under /api/v1) a Guard legitimately uses. */
    private const ALLOWED = [
        'auth', 'visitors', 'access-passes/scan', 'guard', 'notices', 'events',
        'society/contacts', 'society-documents', 'notifications', 'services',
        'service-providers', 'profile', 'change-password',
        // Society Info (read-only, own society): guards see the same three
        // sections on the web (/guard/rules) and now in the app. Added
        // deliberately alongside RestrictGuardApiAccess::ALLOWED.
        'society/info',
    ];

    private function allowed(string $path): bool
    {
        foreach (self::ALLOWED as $a) {
            if ($path === $a || str_starts_with($path, $a . '/')) {
                return true;
            }
        }

        return false;
    }

    private function relative(Route $route): string
    {
        return trim(preg_replace('#^api/v1/?#', '', $route->uri()), '/');
    }

    private function concrete(Route $route): string
    {
        return '/' . preg_replace('/\{[^}?]+\??\}/', '1', $route->uri());
    }

    /** @return list<Route> routes behind the Guard containment */
    private function guardedRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $r) => str_starts_with($r->uri(), 'api/v1/') && in_array('restrict.guard.api', $r->gatherMiddleware(), true)
        ));
    }

    public function test_a_guard_gets_a_403_from_every_api_route_outside_the_allowlist(): void
    {
        $guard = $this->makeUserWithRole($this->makeSociety(), 'Guard');
        Sanctum::actingAs($guard);

        $walked = [];
        $leaks = [];
        foreach ($this->guardedRoutes() as $route) {
            if ($this->allowed($this->relative($route))) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $walked[] = $this->relative($route);
                $status = $this->json($method, $this->concrete($route))->getStatusCode();
                if ($status !== 403) {
                    $leaks[] = "{$method} {$this->concrete($route)} -> {$status}";
                }
            }
        }

        // The walk must really reach the modules a guard must never touch.
        foreach (['payments', 'bills', 'complaints', 'facilities', 'facility-bookings', 'service-requests', 'access-passes', 'dashboard'] as $module) {
            $this->assertNotEmpty(
                array_filter($walked, fn ($p) => $p === $module || str_starts_with($p, $module . '/')),
                "the walk never reached /{$module}"
            );
        }
        $this->assertSame([], $leaks, 'a Guard token reached API routes outside the allowlist');
    }

    public function test_the_allowlisted_routes_are_not_blocked_by_the_containment(): void
    {
        $guard = $this->makeUserWithRole($this->makeSociety(), 'Guard');
        Sanctum::actingAs($guard);

        $blocked = [];
        $checked = 0;
        foreach ($this->guardedRoutes() as $route) {
            if (!$this->allowed($this->relative($route))) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $checked++;
                $response = $this->json($method, $this->concrete($route));
                // The controller may answer 404/422/403 for its own reasons;
                // only the containment's own message means the gate was shut.
                if ($response->getStatusCode() === 403 && $response->json('message') === 'This is not available for your role.') {
                    $blocked[] = "{$method} {$this->concrete($route)}";
                }
            }
        }

        $this->assertGreaterThan(10, $checked);
        $this->assertSame([], $blocked, 'the containment must not block what a guard does');
    }

    public function test_the_gate_workflow_still_answers_for_a_guard(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUserWithRole($society, 'Guard');
        Sanctum::actingAs($guard);

        foreach (['/api/v1/visitors', '/api/v1/visitors/dashboard', '/api/v1/notices', '/api/v1/events', '/api/v1/society/contacts', '/api/v1/notifications', '/api/v1/profile', '/api/v1/services', '/api/v1/service-providers', '/api/v1/auth/me'] as $url) {
            $this->getJson($url)->assertOk();
        }
    }

    public function test_a_guard_who_is_also_an_admin_keeps_the_admin_access(): void
    {
        $society = $this->makeSociety();
        $both = $this->makeUserWithRole($society, 'Guard');
        $this->makeRole('Admin');
        $both->assignRole('Admin');
        Sanctum::actingAs($both);

        $this->getJson('/api/v1/facility-bookings')->assertOk();
    }

    public function test_other_roles_are_not_contained(): void
    {
        $society = $this->makeSociety();
        foreach (['Tenant', 'Admin', 'Accountant', 'Staff'] as $role) {
            Sanctum::actingAs($this->makeUserWithRole($society, $role));
            $this->getJson('/api/v1/facilities')->assertOk();
        }
    }
}
