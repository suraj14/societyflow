<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a gate guard on the gate - in the mobile API as well as the web.
 *
 * RestrictGuardAccess does this for the web, but the API is a separate
 * surface: every route in the authenticated group answered any signed-in
 * role, so a Guard's token could read the society's payments and bills, file
 * or list complaints, book facilities and list access passes. The app hid
 * those screens; nothing refused the requests.
 *
 * Like the web version this is an ALLOWLIST (a new route is closed to guards
 * until someone lists it here) and a user who is a Guard *and* an Admin is
 * not restricted - the wider role wins.
 */
class RestrictGuardApiAccess
{
    /**
     * Paths a Guard may call, relative to /api/v1. A prefix matches itself
     * and everything under it ('visitors' covers 'visitors/12/check-in').
     */
    private const ALLOWED = [
        'auth',               // /me, /logout
        'visitors',           // the whole gate workflow
        'access-passes/scan', // QR scan - not the resident's pass management
        'guard',              // own shift, roster
        'notices',
        'events',
        'society/contacts',   // emergency + office numbers
        'society/info',       // Society Info: details + committee (read-only)
        'society-documents',  // gate rules (the controller limits guards to guard-visible ones)
        'notifications',      // own bell entries
        'services',           // the provider directory, read-only
        'service-providers',
        'profile',
        'change-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('Guard') || $user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $next($request);
        }

        $path = trim(preg_replace('#^api/v1/?#', '', $request->path()), '/');

        foreach (self::ALLOWED as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'This is not available for your role.',
        ], 403);
    }
}
