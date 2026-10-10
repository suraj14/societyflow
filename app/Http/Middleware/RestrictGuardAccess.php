<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a gate guard on the gate.
 *
 * A Guard's job is narrow and enumerable - admit visitors, scan passes,
 * check people in and out - and it lives entirely under /guard. But nothing
 * enforced that: 128 web routes carry no role middleware, and
 * RestrictStaffAccess only ever looked at the Staff role. A Guard account
 * could therefore open /payments, /payments/create, /utility-bills,
 * /facilities/create and the resident portal at /my/dashboard, all
 * returning 200.
 *
 * This is an ALLOWLIST, not a denylist, and it is registered globally on
 * the web group rather than per route group. Both choices are deliberate:
 * the existing Staff denylist has to be remembered on every new route and
 * silently admits anything added outside it (see CLAUDE.md §2), whereas a
 * guard can only reach a path somebody deliberately listed here.
 *
 * A user who is a Guard *and* an Admin is not restricted - dual-role
 * accounts exist, and the wider role is the one that should win.
 */
class RestrictGuardAccess
{
    /**
     * Path prefixes a Guard may reach. Matched against the request path
     * with no leading slash, so 'guard' covers 'guard/scan' too.
     */
    private const ALLOWED_PREFIXES = [
        'guard',     // the entire gate surface, already role-gated
        'profile',   // their own account
        'logout',
        'login',
        'dashboard', // redirects a Guard to /guard
        'notifications', // their own header-bell entries only
    ];

    /** Exact route names allowed regardless of path. */
    private const ALLOWED_ROUTES = [
        'logout',
        'login',
        'dashboard',
        'profile.show',
        'profile.edit',
        'profile.update',
        'password.change',
        'password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        // Only a plain Guard is contained. Someone who also administers the
        // society keeps the access that other role grants them.
        if (!$user->hasRole('Guard') || $user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        $path = $request->path(); // no leading slash; '/' becomes '/'

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $next($request);
            }
        }

        // '/' is the landing page and redirects by role - never a module.
        if ($path === '/' || $path === '') {
            return $next($request);
        }

        abort(403, 'Access Denied: gate accounts are limited to the gate workflow.');
    }
}
