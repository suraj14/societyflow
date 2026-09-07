<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCacheHeaders
{
    /**
     * PRODUCTION-STABLE CACHE CONTROL
     * 
     * Simple, reliable cache prevention that:
     * - Disables browser caching
     * - Maintains CSRF token validity
     * - Prevents session expiration
     * - Works across all browsers
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Disable ALL browser caching for dynamic content
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        
        // Prevent caching at all levels
        $response->header('Vary', 'Accept-Encoding, Accept, Authorization');
        
        // Security headers
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
