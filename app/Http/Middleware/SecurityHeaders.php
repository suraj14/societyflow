<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser hardening headers on every response.
 *
 * HSTS is only sent on an HTTPS request (sending it over plain HTTP is
 * ignored by browsers and would be wrong on the LAN dev server), and only
 * when APP_ENV is production, so a local HTTPS experiment cannot pin a
 * developer's browser to HTTPS for the host.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($request->isSecure() && app()->environment('production')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
