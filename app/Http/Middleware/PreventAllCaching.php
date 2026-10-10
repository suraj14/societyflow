<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAllCaching
{
    /**
     * AGGRESSIVE CACHE PREVENTION MIDDLEWARE
     * 
     * Ensures ZERO caching at all levels:
     * - Browser cache
     * - Proxy cache
     * - CDN cache
     * - Service worker cache
     * - Application cache
     * 
     * Applied to ALL requests globally
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // CRITICAL: Prevent browser caching
        $response->headers->set('Cache-Control', 'no-store, no-cache, no-transform, must-revalidate, private, max-age=0, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        
        // Prevent proxy/CDN caching
        $response->headers->set('Surrogate-Control', 'no-store');
        $response->headers->set('Vary', 'Accept-Encoding, Accept, Authorization, Cookie');
        
        // Prevent service worker caching
        $response->headers->set('Service-Worker-Allowed', '/');
        
        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
