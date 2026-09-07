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
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        $response->header('Vary', 'Accept-Encoding, Accept, Authorization');
        
        // Prevent back button caching
        $response->header('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');
        $response->header('ETag', '"' . md5(microtime()) . '"');

        return $response;
    }
}
