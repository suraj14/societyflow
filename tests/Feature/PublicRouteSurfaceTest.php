<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * The application's unauthenticated surface, derived from the REAL route table
 * (not a hand-picked list) so a route added later is covered the moment it
 * exists - the same approach as GuardApiAllowlistTest.
 *
 *  1. The set of routes with no authentication middleware must equal the
 *     reviewed allowlist below. A new unprotected route fails this test until
 *     someone consciously adds it here.
 *  2. Every other route must refuse an anonymous caller - never answer 200 and
 *     never crash (500). Anonymous API calls must get a JSON 401.
 */
class PublicRouteSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /** Reviewed. METHOD uri. Login, registration, password reset, OTP login, health, landing. */
    private const PUBLIC_ROUTES = [
        'GET /',
        'GET api/v1/health',
        'GET api/v1/password-policy',   // rules only (min length, required character kinds, well-known passwords); the register / reset screens need them before sign-in. No tenant data.
        'GET forgot-password',
        'GET login',
        'GET logout',            // redirect-only stub for stale bookmarks; does NOT log out (LogoutTest)
        'GET register',
        'GET reset-password/{token}',
        'POST api/v1/auth/check-account',
        'POST api/v1/auth/forgot-password',
        'POST api/v1/auth/login',
        'POST api/v1/auth/register',
        'POST api/v1/auth/reset-password',
        'POST api/v1/auth/select-account',
        'POST api/v1/auth/send-otp',
        'POST api/v1/auth/verify-otp',
        'POST forgot-password',
        'POST login',
        'POST register',
        'POST reset-password',
        'POST {locale}/livewire/message/{name}',
    ];

    /** Framework-provided routes that are public by design. */
    private function isFramework(string $uri): bool
    {
        return (bool) preg_match('#^(_ignition|_debugbar|sanctum/|storage/|up$|livewire)#', $uri);
    }

    private function hasAuthentication(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && preg_match('#^(auth|role:|permission:|restrict\.)#i', $middleware)) {
                return true;
            }
            if (is_string($middleware) && preg_match('#\\\\(Authenticate|CheckRole|CheckPermission|RestrictStaffAccess|RestrictGuardAccess|RestrictGuardApiAccess)(:|$)#', $middleware)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{0:string,1:Route}> [ "METHOD uri", route ] for every method of every route */
    private function endpoints(): array
    {
        $out = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');
            if ($this->isFramework($uri)) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $out[] = [$method . ' ' . ($uri === '' ? '/' : $uri), $route, $method, $uri];
            }
        }

        return $out;
    }

    public function test_the_unauthenticated_surface_is_exactly_the_reviewed_allowlist(): void
    {
        $unprotected = [];
        foreach ($this->endpoints() as [$label, $route]) {
            if (!$this->hasAuthentication($route)) {
                $unprotected[] = $label;
            }
        }
        sort($unprotected);
        $expected = self::PUBLIC_ROUTES;
        sort($expected);

        $this->assertSame(
            $expected,
            $unprotected,
            "A route is reachable without logging in that is not on the reviewed allowlist (or an allowlisted one gained protection - remove it).\n"
        );
    }

    public function test_every_other_route_refuses_an_anonymous_caller_without_crashing(): void
    {
        $checked = 0;
        $failures = [];

        foreach ($this->endpoints() as [$label, $route, $method, $uri]) {
            if (in_array($label, self::PUBLIC_ROUTES, true) || str_starts_with($uri, 'storage')) {
                continue;
            }

            $concrete = '/' . preg_replace(['/\{[^}?]+\?\}/', '/\{[^}]+\}/'], ['', '1'], $uri);
            $isApi    = str_starts_with($uri, 'api/');

            $response = $isApi
                ? $this->json($method, $concrete)
                : $this->call($method, $concrete);

            $status = $response->getStatusCode();
            $checked++;

            // Redirect to login, 401/403, 404 (a concealed resource), 405 (wrong verb), 419 (CSRF), 422 are all refusals.
            $refused = in_array($status, [301, 302, 303, 307, 401, 403, 404, 405, 419, 422], true);
            if (!$refused || ($isApi && $status !== 401 && $status !== 404 && $status !== 405 && $status !== 422)) {
                $failures[] = "{$label} -> {$status}";
            }
        }

        $this->assertGreaterThan(400, $checked, 'the route table was not enumerated');
        $this->assertSame([], $failures, "anonymous callers got a non-refusal (or a crash) from:\n  " . implode("\n  ", $failures));
    }
}
