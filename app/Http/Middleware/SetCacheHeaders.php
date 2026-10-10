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
     *
     * Headers are set through `$response->headers`, not `->header()`: the
     * latter only exists on Illuminate's own Response, so a file or
     * streamed response (BinaryFileResponse / StreamedResponse, e.g. a PDF
     * served from private storage) used to 500 here on every web route.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Disable ALL browser caching for dynamic content
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        
        // Prevent caching at all levels
        $response->headers->set('Vary', 'Accept-Encoding, Accept, Authorization');
        
        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}
