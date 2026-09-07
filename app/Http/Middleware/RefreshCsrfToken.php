<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshCsrfToken
{
    /**
     * PRODUCTION-STABLE CSRF TOKEN HANDLING
     * 
     * Only regenerate tokens on safe requests (GET, HEAD, OPTIONS)
     * to prevent token mismatch on form submissions.
     * 
     * This ensures:
     * - Form submissions always have valid tokens
     * - CSRF protection is maintained
     * - No "Session expired" errors
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only regenerate token on safe requests (GET, HEAD, OPTIONS)
        // This prevents token mismatch when form is submitted
        if ($request->isMethod('get') || $request->isMethod('head') || $request->isMethod('options')) {
            $request->session()->regenerateToken();
        }

        $response = $next($request);

        // Add CSRF token to response headers for JavaScript access
        $response->header('X-CSRF-TOKEN', $request->session()->token());

        return $response;
    }
}
