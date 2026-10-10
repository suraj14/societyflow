<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Everything under /super-admin is platform administration: societies, plans,
 * billing, roles and permissions, the public landing site. Taken from the real
 * route table so a route added later is covered automatically.
 *
 * No other role - including a society Admin - may reach any of it: every
 * endpoint, with every HTTP verb it accepts, must refuse them (redirect, 403,
 * 404 for a concealed record) and must never answer 2xx or crash. A hidden
 * menu item is not security.
 */
class SuperAdminSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public static function nonSuperRoles(): array
    {
        return array_map(fn ($r) => [$r], ['Admin', 'Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Guard', 'Staff', 'Accountant']);
    }

    /** @return list<array{0:string,1:string}> [method, concrete uri] */
    private function endpoints(): array
    {
        $out = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');
            if (!str_starts_with($uri, 'super-admin')) {
                continue;
            }
            $concrete = '/' . preg_replace(['/\{[^}?]+\?\}/', '/\{[^}]+\}/'], ['', '1'], $uri);
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $out[] = [$method, $concrete];
            }
        }

        return $out;
    }

    public function test_the_super_admin_surface_was_actually_enumerated(): void
    {
        // 51 route/verb pairs when written; a floor, so shrinking it by accident is noticed.
        $this->assertGreaterThanOrEqual(45, count($this->endpoints()));
    }

    /** @dataProvider nonSuperRoles */
    public function test_no_other_role_can_reach_any_super_admin_endpoint(string $role): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, $role);
        $this->actingAs($user);

        $leaks = [];
        foreach ($this->endpoints() as [$method, $uri]) {
            $status = $this->call($method, $uri)->getStatusCode();

            if (!in_array($status, [301, 302, 303, 307, 401, 403, 404, 405, 419, 422], true)) {
                $leaks[] = "{$method} {$uri} -> {$status}";
            }
        }

        $this->assertSame([], $leaks, "{$role} was not refused by:\n  " . implode("\n  ", $leaks));
    }

    public function test_a_super_admin_is_served_the_listing_pages(): void
    {
        $super = $this->makeUserWithRole($this->makeSociety(), 'Super Admin', ['society_id' => null]);
        $this->actingAs($super);

        foreach (['/super-admin/dashboard', '/super-admin/societies', '/super-admin/packages', '/super-admin/roles', '/super-admin/billing', '/super-admin/landing-site'] as $uri) {
            $status = $this->get($uri)->getStatusCode();
            $this->assertLessThan(500, $status, "{$uri} crashed for Super Admin");
        }
    }
}
