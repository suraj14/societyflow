<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use App\Services\AccessPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile guard scanning - the app equivalent of GuardController::scan().
 *
 * Deliberately thin: both the web gate and this endpoint call the same
 * AccessPassService, so there is exactly one place the actual verification
 * rules live (see that service's own docblock). This controller only
 * translates the result into the `{success, message, data}` shape every
 * other /api/v1 endpoint already uses.
 */
class AccessPassApiController extends Controller
{
    /**
     * The QR payload is an opaque token - see AccessPass's own docblock.
     * The app sends nothing else; every property of the pass (society,
     * property, schedule, status) is re-resolved here from the database,
     * never trusted from the request.
     */
    public function scan(Request $request, AccessPassService $passes): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['Guard', 'Admin', 'Super Admin'])) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $request->validate(['token' => 'required|string|max:64']);
        $token = trim($request->token);
        $societyId = (int) $user->society_id;

        // Pre-approved guest first, same as the web gate.
        $guest = Visitor::where('qr_token', $token)->first();
        if ($guest) {
            if ((int) $guest->society_id !== $societyId) {
                return $this->denied('QR code not recognised.');
            }
            if ($guest->approval_status !== 'allowed') {
                return $this->denied('This guest has not been approved yet.');
            }
            if ($guest->entry_status !== 'pending') {
                return $this->denied('This guest is already checked in.');
            }

            $guest->update(['entry_status' => 'entered', 'actual_entry_time' => now()]);
            $guest->addLog($user, 'entry', 'Guest QR scanned in (mobile)');

            return $this->allowed($guest->visitor_name, $guest->purpose);
        }

        $result = $passes->verify($token, $societyId);

        if (!$result['ok']) {
            return $this->denied($this->message($result['reason']), $result['pass']?->person_name);
        }

        $pass = $result['pass'];

        if ($pass->isSocietyWide() && $pass->currently_inside) {
            $passes->checkOutSocietyWide($pass);

            return response()->json([
                'success' => true,
                'message' => $pass->person_name . ' - checked out.',
                'data' => [
                    'person' => $pass->person_name,
                    'category' => $pass->category,
                    'action' => 'checked_out',
                ],
            ]);
        }

        $passes->checkIn($pass, $user);

        return $this->allowed($pass->person_name, $pass->category);
    }

    private function allowed(string $person, ?string $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $person . ' - entry allowed.',
            'data' => [
                'person' => $person,
                'category' => $category,
                'action' => 'entered',
            ],
        ]);
    }

    private function denied(string $message, ?string $person = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => ['person' => $person],
        ], 422);
    }

    private function message(?string $reason): string
    {
        return match ($reason) {
            AccessPassService::REASON_NOT_FOUND, AccessPassService::REASON_WRONG_SOCIETY => 'QR code not recognised.',
            AccessPassService::REASON_REVOKED => 'Access revoked.',
            AccessPassService::REASON_EXPIRED => 'This pass has expired.',
            AccessPassService::REASON_NOT_YET_VALID => 'This pass is not valid yet.',
            AccessPassService::REASON_BLOCKED_TODAY => 'Access blocked for today.',
            AccessPassService::REASON_WRONG_DAY => 'Not an authorised day for this pass.',
            AccessPassService::REASON_WRONG_TIME => 'Outside the authorised time window.',
            AccessPassService::REASON_ALREADY_INSIDE => 'Already checked in - check out first.',
            default => 'Access denied.',
        };
    }
}
