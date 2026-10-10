<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PreventBackHistory
{
    /**
     * PERMANENT CACHE PREVENTION
     * Disables all browser caching globally
     * Prevents back button from showing cached pages
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Prevent all caching
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Vary', 'Accept-Encoding, Accept, Authorization');
        
        // Prevent back button caching
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
        $response->headers->set('ETag', '"' . md5(microtime()) . '"');

        return $response;
    }
}
