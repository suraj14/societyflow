<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictStaffAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // Skip middleware for unauthenticated users
        if (!$user) {
            return $next($request);
        }

        // Only apply restrictions to Staff users
        if (!$user->hasRole('Staff')) {
            return $next($request);
        }

        $currentRoute = $request->route()->getName();
        $currentPath = $request->path();
        
        // Allow authentication and basic routes for Staff
        $allowedRoutes = [
            'login',
            'logout', 
            'dashboard',
            'profile.show',
            'profile.edit',
            'profile.update',
            'staff.dashboard',
        ];
        
        if (in_array($currentRoute, $allowedRoutes)) {
            return $next($request);
        }

        // STAFF RESTRICTIONS - Block access to financial and management modules
        $restrictedPaths = [
            // Financial & Sensitive Modules
            'payments',
            'bills', 
            // 'utility-bills', // Temporarily allow utility-bills for testing
            'reports',
            'maintenance',
            
            // Management Modules  
            'owners',
            'tenants',
            'residents', 
            'buildings',
            'flats',
            'villas',
            'villa-areas',
            'users',
            'societies',
            
            // Settings (ALL)
            'settings',
            'super-admin',
            
            // Service Provider Management (Staff can view services but not manage providers)
            'service-providers',
        ];

        // Check if current path starts with any restricted path
        foreach ($restrictedPaths as $restrictedPath) {
            if (str_starts_with($currentPath, $restrictedPath)) {
                abort(403, 'Access Denied: Staff members do not have permission to access this module.');
            }
        }

        // STAFF ALLOWED MODULES WITH RESTRICTIONS
        
        // 1. Visitors - Full access except delete/export
        if (str_starts_with($currentPath, 'visitors')) {
            if (in_array($currentRoute, ['visitors.destroy', 'visitors.export'])) {
                abort(403, 'Access Denied: Staff members cannot delete or export visitor records.');
            }
            return $next($request);
        }
        
        // 2. Complaints/Tickets - View and update status only
        if (str_starts_with($currentPath, 'complaints')) {
            $method = $request->method();
            // Allow GET (view) and specific POST routes for status updates
            if ($method === 'GET' || 
                in_array($currentRoute, ['complaints.add-update', 'complaints.assign'])) {
                return $next($request);
            }
            // Block create, edit, delete
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']) && 
                !in_array($currentRoute, ['complaints.add-update', 'complaints.assign'])) {
                abort(403, 'Access Denied: Staff members can only view tickets and update status.');
            }
            return $next($request);
        }
        
        // 3. Services - Read-only access (can view, clock in/out)
        if (str_starts_with($currentPath, 'services')) {
            $method = $request->method();
            // Allow GET requests and clock in/out
            if ($method === 'GET' || 
                in_array($currentRoute, ['services.clock-in', 'services.clock-out'])) {
                return $next($request);
            }
            // Block other modifications
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']) && 
                !in_array($currentRoute, ['services.clock-in', 'services.clock-out'])) {
                abort(403, 'Access Denied: Staff members have read-only access to services.');
            }
            return $next($request);
        }
        
        // 4. Events - Read-only access
        if (str_starts_with($currentPath, 'events')) {
            $method = $request->method();
            if ($method !== 'GET') {
                abort(403, 'Access Denied: Staff members have read-only access to events.');
            }
            return $next($request);
        }
        
        // 5. Notices - Read-only access
        if (str_starts_with($currentPath, 'notices')) {
            $method = $request->method();
            if ($method !== 'GET') {
                abort(403, 'Access Denied: Staff members have read-only access to notices.');
            }
            return $next($request);
        }

        // 6. Facilities - Read-only access (can view but not manage)
        if (str_starts_with($currentPath, 'facilities')) {
            $method = $request->method();
            if ($method !== 'GET') {
                abort(403, 'Access Denied: Staff members have read-only access to facilities.');
            }
            return $next($request);
        }

        return $next($request);
    }
}