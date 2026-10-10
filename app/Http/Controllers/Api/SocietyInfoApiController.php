<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocietyDocument;
use App\Support\SocietyInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Society Info for the mobile app: the "Society Details" and "Committee"
 * sections. The third section, Documents, already has its own endpoints
 * (/society-documents, unchanged) and is deliberately not repeated here.
 *
 * Readable by exactly who can read Society Rules & Documents (residents,
 * Admin, Guard) and always the caller's OWN society - there is no society id
 * parameter to tamper with.
 */
class SocietyInfoApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!SocietyDocument::isReader($user)) {
            return response()->json(['success' => false, 'message' => 'Society info is available to residents, guards and the society office.'], 403);
        }

        $society = $user->society;

        if (!$society) {
            return response()->json(['success' => false, 'message' => 'Your account is not linked to a society.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'details'   => SocietyInfo::details($society),
                // photo_web is a browser URL and has no use in the app payload.
                'committee' => array_map(fn (array $m) => array_diff_key($m, ['photo_web' => true]), SocietyInfo::committee($society)),
            ],
        ]);
    }
}
