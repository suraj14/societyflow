<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ReleaseFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Two tiny endpoints that used to be route closures (which `php artisan route:cache` cannot serialise). Behaviour unchanged. */
class HealthController extends Controller
{
    /**
     * The app confirms a LAN host really is SocietyFlow by this exact answer (ServerDiscovery) - keep the keys, add nothing to the body.
     * Which release is running travels in a response header instead (opaque; only on a site deployed from a package).
     */
    public function health(): JsonResponse
    {
        $response = response()->json([
            'application' => 'societyflow',
            'api'         => 'v1',
            'status'      => 'ok',
        ]);

        if ($tag = ReleaseFingerprint::current()) {
            $response->header(ReleaseFingerprint::HEADER, $tag);
        }

        return $response;
    }

    public function user(Request $request)
    {
        return $request->user();
    }
}
