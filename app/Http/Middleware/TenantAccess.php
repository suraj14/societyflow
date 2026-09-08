<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Denial used to redirect to route('tenant.rent'), which is itself
        // inside the Tenant-only route group - so a non-tenant who hit any
        // guarded route was redirected straight back into another guarded
        // route. Deny with a 403 instead of bouncing the user around.
        if (!$user->hasRole('Tenant')) {
            abort(403, 'Access denied. Tenant access required.');
        }

        return $next($request);
    }
}