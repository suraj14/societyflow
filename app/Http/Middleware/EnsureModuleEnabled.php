<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings -> Features switches a module off for ONE society. Until now the switch only hid the menu entry (web drawer, mobile capabilities):
 * the pages and the API stayed reachable by address. This makes the switch real: a request into a switched-off module is refused for every
 * role of that society (Super Admin is global and exempt). An unset switch means ON, exactly as the menus already treat it.
 *
 * Events keeps its own check in EventController, which also redirects differently.
 */
class EnsureModuleEnabled
{
    /** first path segment (after api/v1/ and an optional admin/) => the Features key that governs it */
    public const MODULES = [
        'complaints'        => 'enable_complaints',
        'visitors'          => 'enable_visitors',
        'facilities'        => 'enable_facilities',
        'facility-bookings' => 'enable_facilities',
        'services'          => 'enable_services',
        'service-requests'  => 'enable_services',
        'service-providers' => 'enable_services',
        'notices'           => 'enable_notices',
        'notices-pending'   => 'enable_notices',
        'notices-history'   => 'enable_notices',
    ];

    public const LABELS = [
        'enable_complaints' => 'Complaints', 'enable_visitors' => 'Visitors', 'enable_facilities' => 'Facilities',
        'enable_services' => 'Services', 'enable_notices' => 'Notices',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $key = self::switchFor($request->path());
        if ($key === null) {
            return $next($request);
        }

        $user = $request->user() ?? auth('sanctum')->user();
        if (!$user || !$user->society_id || $user->hasRole('Super Admin')) {
            return $next($request);   // not signed in yet (the auth middleware decides) or a global account
        }

        if (SystemSetting::get('features', $key, true, $user->society_id)) {
            return $next($request);
        }

        $message = (self::LABELS[$key] ?? 'This module') . ' is turned off for your society.';

        return $request->expectsJson() || $request->is('api/*')
            ? response()->json(['success' => false, 'message' => $message, 'module_disabled' => true], 403)
            : abort(403, $message);
    }

    public static function switchFor(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        if (($segments[0] ?? '') === 'api') {
            $segments = array_slice($segments, 2);           // api/v1/...
        }
        if (($segments[0] ?? '') === 'admin') {
            $segments = array_slice($segments, 1);           // api/v1/admin/complaints, admin/... pages
        }

        return self::MODULES[$segments[0] ?? ''] ?? null;
    }
}
