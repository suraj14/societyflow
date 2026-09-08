<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle the request, catching token mismatches gracefully.
     */
    public function handle($request, \Closure $next)
    {
        // Skip CSRF check for GET, HEAD, OPTIONS requests
        if ($this->isReading($request)) {
            return $next($request);
        }

        // Try to verify CSRF token
        try {
            $response = parent::handle($request, $next);
            
            // CRITICAL: Do NOT regenerate token after successful verification
            // This prevents token mismatch on redirect
            // The token should remain the same for the entire request cycle
            
            return $response;
        } catch (TokenMismatchException $e) {
            \Illuminate\Support\Facades\Log::warning('CSRF token mismatch', [
                'url' => $request->url(),
                'method' => $request->method(),
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
            ]);

            // If it's an AJAX request, return JSON error
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'CSRF token expired. Please refresh the page.',
                    'error' => 'token_mismatch'
                ], 419);
            }

            // For form submissions, redirect back with error
            return redirect()
                ->back()
                ->with('error', 'Session expired. Please try again.')
                ->withInput();
        }
    }

    /*
     * tokensMatch() is deliberately NOT overridden.
     *
     * The previous override accepted $request->bearerToken() as a CSRF
     * token and dropped the parent's handling of the encrypted
     * X-XSRF-TOKEN header. Two problems:
     *
     *   - Treating an Authorization bearer token as proof of CSRF intent
     *     conflates authentication with request forgery protection. They
     *     are different controls and the bearer token is not a per-session
     *     secret the way the CSRF token is.
     *   - Dropping X-XSRF-TOKEN broke the standard cookie-based flow that
     *     axios and every Laravel-aware JS client use by default, which is
     *     likely why the "token mismatch" problems this class complains
     *     about in its comments appeared in the first place.
     *
     * Laravel's own implementation handles _token, X-CSRF-TOKEN and the
     * encrypted X-XSRF-TOKEN cookie correctly. The handle() override above
     * is kept: it only changes how a mismatch is REPORTED (a friendly
     * redirect or JSON 419 instead of an exception page) and never lets a
     * request through.
     */
}