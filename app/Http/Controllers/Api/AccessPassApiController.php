<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use App\Services\AccessPassEditService;
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

        // Pre-approved guest first, same as the web gate. Both halves run
        // their check and their write under one lock - see the service.
        $guest = $passes->checkInGuest($token, $societyId, $user);
        if ($guest['found']) {
            if (!$guest['ok']) {
                return $this->denied($this->message($guest['reason']), $guest['visitor']?->visitor_name);
            }

            return $this->allowed($guest['visitor']->visitor_name, $guest['visitor']->purpose);
        }

        $result = $passes->verifyAndCheckIn($token, $societyId, $user);

        if (!$result['ok']) {
            return $this->denied($this->message($result['reason']), $result['pass']?->person_name);
        }

        $pass = $result['pass'];

        if ($result['action'] === 'checked_out') {
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

        return $this->allowed($pass->person_name, $pass->category);
    }

    /**
     * The passes the caller may manage - an admin's whole society, a
     * resident's own properties. Never the token: the QR belongs on the
     * pass's own screen, not in a list payload.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = \App\Models\AccessPass::with(['flat.building', 'flat.villaArea']);

        // Scope by default, exempt Super Admin explicitly: their
        // society_id is null, so scoping them like everyone else would
        // match `society_id IS NULL` and return nothing at all.
        if (!$user->hasRole('Super Admin')) {
            $query->where('society_id', $user->society_id);
        }

        if (!$user->hasAnyRole(['Super Admin', 'Admin'])) {
            $query->whereIn('flat_id', \App\Support\UserFlatResolver::flatIdsFor($user));
        }

        $passes = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => collect($passes->items())->map(fn ($p) => $this->formatPass($p))->all(),
            'meta'    => [
                'current_page' => $passes->currentPage(),
                'last_page'    => $passes->lastPage(),
                'total'        => $passes->total(),
            ],
        ]);
    }

    public function show(Request $request, \App\Models\AccessPass $accessPass, AccessPassEditService $editor): JsonResponse
    {
        $editor->assertMayManage($accessPass, $request->user());

        return response()->json([
            'success' => true,
            'data'    => $this->formatPass($accessPass->load('flat.building', 'flat.villaArea')),
        ]);
    }

    /**
     * Full edit and the short "push the date out" form. Both go through
     * AccessPassEditService, the same code the web screen uses, so the
     * rules cannot drift between the two clients.
     */
    public function update(Request $request, \App\Models\AccessPass $accessPass, AccessPassEditService $editor): JsonResponse
    {
        $validated = $request->validate(AccessPassEditService::updateRules());

        $pass = $editor->update($accessPass, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => $pass->person_name . "'s pass has been updated.",
            'data'    => $this->formatPass($pass),
        ]);
    }

    public function extend(Request $request, \App\Models\AccessPass $accessPass, AccessPassEditService $editor): JsonResponse
    {
        $validated = $request->validate(AccessPassEditService::extendRules());

        $pass = $editor->extend(
            $accessPass,
            $validated,
            $request->user(),
            array_keys($request->only(['allowed_days', 'start_time', 'end_time']))
        );

        return response()->json([
            'success' => true,
            'message' => $pass->person_name . ' is now valid until ' . $pass->valid_until->format('d M Y') . '.',
            'data'    => $this->formatPass($pass),
        ]);
    }

    /**
     * The pass as the app reads it. `token` is deliberately absent - it is
     * already $hidden on the model, and the gate is the only thing that
     * needs it.
     */
    private function formatPass(\App\Models\AccessPass $pass): array
    {
        return [
            'id'            => $pass->id,
            'person_name'   => $pass->person_name,
            'person_phone'  => $pass->person_phone,
            'category'      => $pass->category,
            'pass_type'     => $pass->pass_type,
            'access_scope'  => $pass->access_scope,
            'flat_id'       => $pass->flat_id,
            'property'      => $pass->flat?->flat_number,
            'allowed_days'  => $pass->allowed_days ?? [],
            'start_time'    => $pass->start_time,
            'end_time'      => $pass->end_time,
            'valid_from'    => $pass->valid_from?->toDateString(),
            'valid_until'   => $pass->valid_until?->toDateString(),
            'status'        => $pass->status,
            'is_expired'    => $pass->isExpired(),
            'is_revoked'    => $pass->isRevoked(),
            'is_blocked_today' => $pass->isBlockedToday(),
            'can_edit'      => !$pass->isRevoked(),
            'currently_inside' => (bool) $pass->currently_inside,
        ];
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
            AccessPassService::REASON_GUEST_WRONG_SOCIETY => 'QR code not recognised.',
            AccessPassService::REASON_GUEST_NOT_APPROVED => 'This guest has not been approved yet.',
            AccessPassService::REASON_GUEST_ALREADY_IN => 'This guest is already checked in.',
            default => 'Access denied.',
        };
    }
}
