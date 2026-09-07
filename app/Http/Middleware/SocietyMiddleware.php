<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SocietyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super Admin can access any society
        if ($user && $user->hasRole('Super Admin')) {
            return $next($request);
        }

        // Check if user belongs to a society
        if (!$user || !$user->society_id) {
            abort(403, 'You do not belong to any society.');
        }

        // Check if society is active
        if (!$user->society || !$user->society->isActive()) {
            abort(403, 'Your society is currently inactive.');
        }

        // Check if society has active subscription (except for trial)
        if (!$user->society->hasActiveSubscription() && !$user->society->isOnTrial()) {
            return redirect()->route('subscription.expired');
        }

        // Set current society in session
        session(['current_society_id' => $user->society_id]);

        return $next($request);
    }
}