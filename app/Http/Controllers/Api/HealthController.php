<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Two tiny endpoints that used to be route closures (which `php artisan route:cache` cannot serialise). Behaviour unchanged. */
class HealthController extends Controller
{
    /** The app confirms a LAN host really is SocietyFlow by this exact answer (ServerDiscovery) - keep the keys. */
    public function health(): JsonResponse
    {
        return response()->json([
            'application' => 'societyflow',
            'api'         => 'v1',
            'status'      => 'ok',
        ]);
    }

    public function user(Request $request)
    {
        return $request->user();
    }
}
