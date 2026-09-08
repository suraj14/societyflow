<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Society activity and subscription gate.
 *
 * NOT CURRENTLY REGISTERED. It has no alias in Http\Kernel and no route
 * uses it, so no society is presently gated on plan or expiry - the core
 * SaaS constraint is not enforced anywhere in the application.
 *
 * It also redirected to route('subscription.expired'), which does not
 * exist, so the moment it was wired up an expired society would have hit a
 * RouteNotFoundException instead of a billing page. That is corrected
 * below; building the billing screen and registering the alias is the
 * remaining work.
 */
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

        // Check if society has active subscription (except for trial).
        // Aborts rather than redirecting to route('subscription.expired'),
        // which is not defined anywhere - that redirect would have thrown
        // RouteNotFoundException on the exact request it was meant to
        // handle. Swap this for a redirect once a billing page exists.
        if (!$user->society->hasActiveSubscription() && !$user->society->isOnTrial()) {
            abort(402, 'Your society\'s subscription has expired. Please contact your administrator.');
        }

        // Set current society in session
        session(['current_society_id' => $user->society_id]);

        return $next($request);
    }
}