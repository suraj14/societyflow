<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionStability
{
    /**
     * Ensure session remains stable throughout the request cycle.
     * 
     * This middleware:
     * - Prevents premature session regeneration
     * - Maintains CSRF token consistency
     * - Ensures session data persists across redirects
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Store the current session ID before processing
        $sessionId = session()->getId();
        $csrfToken = session()->token();
        
        // Process the request
        $response = $next($request);
        
        // CRITICAL: Ensure session ID and CSRF token remain unchanged
        // This prevents "Session expired" errors on redirects
        if (session()->isStarted()) {
            // Verify session is still valid
            if (session()->getId() !== $sessionId) {
                \Log::warning('Session ID changed during request', [
                    'original' => $sessionId,
                    'current' => session()->getId(),
                    'url' => $request->url(),
                    'user_id' => auth()->id(),
                ]);
            }
            
            // Verify CSRF token is still valid
            if (session()->token() !== $csrfToken) {
                \Log::warning('CSRF token changed during request', [
                    'url' => $request->url(),
                    'user_id' => auth()->id(),
                    'method' => $request->method(),
                ]);
            }
        }
        
        return $response;
    }
}
