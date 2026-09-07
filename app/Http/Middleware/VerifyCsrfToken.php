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

    /**
     * Determine if the request has a valid CSRF token.
     * Enhanced to handle token regeneration more gracefully.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        $token = $request->input('_token') ?: $request->bearerToken();

        if (!$token) {
            $token = $request->header('X-CSRF-TOKEN');
        }

        return hash_equals(
            (string) $request->session()->token(),
            (string) $token
        );
    }
}