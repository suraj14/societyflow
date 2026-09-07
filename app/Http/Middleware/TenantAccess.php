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
        
        // Check if user is authenticated and has Tenant role
        if (!$user || !$user->hasRole('Tenant')) {
            return redirect()->route('tenant.rent')->with('error', 'Access denied. Tenant access required.');
        }
        
        return $next($request);
    }
}