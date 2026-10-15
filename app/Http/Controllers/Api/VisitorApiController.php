<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiImageHelper;
use App\Http\Controllers\Api\Concerns\ResolvesUserFlats;
use App\Models\Flat;
use App\Models\Visitor;
use App\Models\User;
use App\Services\VisitorApprovalService;
use App\Services\VisitorWorkflowException;
use App\Support\VisitorPropertyScope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Mobile visitor API: the guard logs a visitor and the residents of that unit (or an administrator) decide, all through
 * App\Services\VisitorApprovalService so the rules are the same here and on the web. Authorisation is by property and
 * society (VisitorPropertyScope), never by a client-supplied id alone: a resident sees and decides only visitors to
 * their own unit, gate staff record and check in but never decide, and another society's visitors do not exist.
 */
class VisitorApiController extends Controller
{
    use ResolvesUserFlats;

    private const GATE_ROLES = ['Staff', 'Guard', 'Admin', 'Super Admin'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Visitor::with(['flat.building', 'flat.villaArea', 'decidedBy:id,name', 'requestedBy:id,name'])->latest();

        if ($user->hasRole(self::GATE_ROLES)) {
            // Staff/Guard/Admin sees ALL visitors for the society
            $query->where('society_id', $user->society_id);
        } else {
            // Residents/Owners see visitors for their own unit(s) - the same rule that lets them decide on them
            VisitorPropertyScope::scopeQuery($query, $user);
        }

        $visitors = $query->paginate(100);

        return response()->json([
            'success' => true,
            'data'    => $visitors->map(fn($v) => $this->formatVisitor($v, $user))->values()->toArray(),
            'meta'    => [
                'current_page' => $visitors->currentPage(),
                'last_page'    => $visitors->lastPage(),
                'total'        => $visitors->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'visitor_name'        => 'nullable|string|max:255',
            'visitor_phone'       => 'required|string|max:20',
            'visitor_type'        => 'required|in:guest,delivery,cab,service,maid,driver,cook,electrician,plumber,emergency,other',
            'purpose'             => 'nullable|string|max:500',
            'expected_entry_time' => 'nullable|date',
            'expected_exit_time'  => 'nullable|date',
            'vehicle_number'      => 'nullable|string|max:20',
            'flat_id'             => 'nullable|exists:flats,id',
            // From a scanned ID at the gate. The number is redacted below,
            // not trusted as sent.
            'visitor_id_proof'    => 'nullable|string|in:' . implode(',', \App\Support\IdProof::TYPES),
            'visitor_id_number'   => 'nullable|string|max:40',
            // The gate app's own id for this submission: a retry of a POST it did not hear back from is the same visitor.
            'client_request_id'   => 'nullable|string|max:64',
            'photo'               => 'nullable|file|mimes:jpeg,png,jpg|max:3072',
        ]);

        $user    = $request->user();
        $isStaff = $user->hasRole(self::GATE_ROLES);
        $flatId  = $request->flat_id;

        if ($isStaff) {
            if (!$flatId) {
                return response()->json(['success' => false, 'message' => 'Choose the property being visited.'], 422);
            }
            // Staff-supplied flat must belong to the same society.
            if (!Flat::where('id', $flatId)->where('society_id', $user->society_id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Invalid flat.'], 422);
            }
        } else {
            // Residents/owners can only register visitors for their own units.
            // (exists:flats,id alone allowed attaching a visitor to any flat
            // in any society.)
            $ownFlatIds = $this->userFlatIds($user);

            if ($flatId && !in_array((int) $flatId, $ownFlatIds, true)) {
                return response()->json(['success' => false, 'message' => 'You can only add visitors for your own flat.'], 403);
            }
            if (!$flatId) {
                $flatId = $ownFlatIds[0] ?? null;
            }
            if (!$flatId) {
                return response()->json(['success' => false, 'message' => 'No flat assigned to your account.'], 422);
            }
        }

        $photoPath = null;
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            // same convention as the web uploads: a random name under public/storage, never the client's own filename - and the
            // extension is the one the file's CONTENT says it is (a jpeg or a png), never the name the client gave it
            $file = $request->file('photo');
            $dir  = public_path('storage/visitor-photos');
            @mkdir($dir, 0777, true);
            $ext  = in_array($guessed = strtolower((string) $file->guessExtension()), ['jpg', 'jpeg', 'png'], true) ? ($guessed === 'jpeg' ? 'jpg' : $guessed) : 'jpg';
            $name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $file->move($dir, $name);
            $photoPath = 'visitor-photos/' . $name;
        }

        try {
            $result = VisitorApprovalService::register($user, Flat::findOrFail($flatId), [
                'visitor_name'        => $request->visitor_name,
                'visitor_phone'       => $request->visitor_phone,
                'visitor_type'        => $request->visitor_type,
                'purpose'             => $request->purpose,
                'vehicle_number'      => $request->vehicle_number,
                'expected_entry_time' => $request->expected_entry_time,
                'expected_exit_time'  => $request->expected_exit_time,
                'photo'               => $photoPath,
                'visitor_id_proof'    => $request->visitor_id_proof,
                // An Aadhaar is reduced to its last four digits here, on arrival,
                // however complete the client sent it.
                'visitor_id_number'   => \App\Support\IdProof::redact($request->visitor_id_proof, $request->visitor_id_number),
            ], $request->input('client_request_id'));
        } catch (VisitorWorkflowException $e) {
            return $this->failure($e);
        }

        $visitor = $result['visitor']->load(['flat.building', 'flat.villaArea']);

        $message = match (true) {
            !$result['created']      => 'This visitor was already registered.',
            !$isStaff                => 'Visitor registered successfully',
            $result['linked']        => 'Visitor registered. The resident has been notified and must approve before entry.',
            default                  => 'Visitor registered, but no owner or tenant is linked to this property. The administrators have been asked to decide.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $this->formatVisitor($visitor, $user),
            'meta'    => ['residents_linked' => $result['linked'], 'duplicate' => !$result['created']],
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Has this number been here before, and is there a pass for it?
     *
     * The gate asks this before registering anyone, so a regular visitor
     * becomes a check-in rather than a third duplicate row. Scoped to the
     * caller's society, and restricted to gate staff: letting a resident
     * look up arbitrary numbers would turn this into a "who visits whom"
     * lookup over the whole society.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string|min:6|max:20']);

        $user = $request->user();

        if (!$user->hasAnyRole(['Staff', 'Guard', 'Admin', 'Super Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only gate staff can look up visitors by number.',
            ], 403);
        }

        $digits = \App\Support\IdProof::phoneDigits($request->phone);

        if (strlen($digits) < 6) {
            return response()->json(['success' => true, 'data' => ['found' => false, 'visitor' => null, 'access_pass' => null]]);
        }

        // Stored numbers are inconsistent (some with a country code, some
        // without), so match on the trailing digits as well as exactly.
        $match = function ($query, string $column) use ($digits) {
            $bare = "REPLACE(REPLACE(REPLACE(REPLACE($column, ' ', ''), '-', ''), '(', ''), ')', '')";
            $query->where($column, $digits)
                ->orWhereRaw("$bare LIKE ?", ['%' . $digits]);
        };

        $visitor = Visitor::where('society_id', $user->society_id)
            ->where(fn ($q) => $match($q, 'visitor_phone'))
            ->with('flat.building')
            ->latest('id')
            ->first();

        $pass = \App\Models\AccessPass::where('society_id', $user->society_id)
            ->where(fn ($q) => $match($q, 'person_phone'))
            ->where('status', 'active')
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'found'       => $visitor !== null || $pass !== null,
                'visitor'     => $visitor ? $this->formatVisitor($visitor, $user) : null,
                'access_pass' => $pass ? [
                    'id'            => $pass->id,
                    'person_name'   => $pass->person_name,
                    'category'      => $pass->category,
                    'valid_until'   => optional($pass->valid_until)->toDateString(),
                    'currently_inside' => (bool) $pass->currently_inside,
                    // Never the token: a pass token is a credential.
                ] : null,
            ],
        ]);
    }

    public function checkIn(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $visitor = Visitor::where('society_id', $user->society_id)->findOrFail($id);

        try {
            // Approval is not entry: the guard performs the check-in, and only for a visitor who was allowed.
            VisitorApprovalService::checkIn($visitor, $user, $request->input('notes'));
        } catch (VisitorWorkflowException $e) {
            return $this->failure($e);
        }

        return response()->json(['success' => true, 'message' => 'Visitor checked in successfully', 'data' => $this->formatVisitor($visitor->fresh(['flat.building', 'flat.villaArea']), $user)]);
    }

    public function checkOut(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $visitor = Visitor::where('society_id', $user->society_id)->findOrFail($id);

        try {
            VisitorApprovalService::checkOut($visitor, $user, $request->input('notes'));
        } catch (VisitorWorkflowException $e) {
            return $this->failure($e);
        }

        return response()->json(['success' => true, 'message' => 'Visitor checked out successfully', 'data' => $this->formatVisitor($visitor->fresh(['flat.building', 'flat.villaArea']), $user)]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $societyId = $user->society_id;
        $today = \App\Support\SocietyClock::dayBounds($societyId);

        // gate staff see the whole society; a resident exactly the visitors they may see and decide on
        $base = $user->hasRole(self::GATE_ROLES)
            ? Visitor::where('society_id', $societyId)
            : VisitorPropertyScope::scopeQuery(Visitor::query(), $user);

        return response()->json([
            'success' => true,
            'data' => [
                'today_total' => (clone $base)->whereBetween('expected_entry_time', $today)->count(),
                'pending'     => (clone $base)->where('approval_status', 'pending')->count(),
                'checked_in'  => (clone $base)->where('entry_status', 'entered')->count(),
                'checked_out' => (clone $base)->where('entry_status', 'exited')->whereBetween('actual_exit_time', $today)->count(),
            ],
        ]);
    }

    public function flats(Request $request): JsonResponse
    {
        $flats = \App\Models\Flat::where('society_id', $request->user()->society_id)
            ->with(['building', 'villaArea'])
            ->get()
            ->map(fn($f) => [
                'id'          => $f->id,
                'flat_number' => $f->flat_number,
                'building'    => $f->building?->name ?? $f->villaArea?->name ?? '',
                'display'     => ($f->building?->name ?? $f->villaArea?->name ?? '') . ' - ' . $f->flat_number,
            ]);

        return response()->json(['success' => true, 'data' => $flats]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $visitor = $this->findFor($user, $id);

        try {
            $result = VisitorApprovalService::decide($visitor, $user, 'allowed');
        } catch (VisitorWorkflowException $e) {
            return $this->failure($e);
        }

        return response()->json(['success' => true, 'message' => 'Visitor allowed', 'data' => $this->formatVisitor($result['visitor']->load(['flat.building', 'flat.villaArea']), $user)]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $user = $request->user();
        $visitor = $this->findFor($user, $id);

        try {
            $result = VisitorApprovalService::decide($visitor, $user, 'denied', $request->input('reason'));
        } catch (VisitorWorkflowException $e) {
            return $this->failure($e);
        }

        return response()->json(['success' => true, 'message' => 'Visitor denied', 'data' => $this->formatVisitor($result['visitor']->load(['flat.building', 'flat.villaArea']), $user)]);
    }

    /**
     * Administrator only: change an earlier decision (or decide for residents who have not answered). `decision` is
     * allow | deny; `reason` is mandatory. The residents and the guard are always told - see VisitorApprovalService::override.
     */
    public function override(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|in:allow,deny,allowed,denied',
            'reason'   => 'required|string|min:5|max:500',
        ]);

        $user = $request->user();
        $visitor = $this->findFor($user, $id);

        try {
            $result = VisitorApprovalService::override($visitor, $user, str_starts_with($data['decision'], 'allow') ? 'allowed' : 'denied', $data['reason']);
        } catch (VisitorWorkflowException $e) {
            return $this->failure($e);
        }

        return response()->json(['success' => true, 'message' => 'Decision changed. The residents and the guard have been told.', 'data' => $this->formatVisitor($result['visitor']->load(['flat.building', 'flat.villaArea']), $user)]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $visitor = $this->findFor($user, $id, ['flat.building', 'flat.villaArea', 'decidedBy:id,name', 'requestedBy:id,name']);

        $data = $this->formatVisitor($visitor, $user);

        // The approval trail (who, when, why - and the per-channel notification outcome) is for the office.
        if ($user->hasAnyRole(['Admin', 'Super Admin'])) {
            $data['history'] = $visitor->logs()->with('user:id,name')->orderBy('id')->get()->map(fn ($l) => [
                'action' => $l->action,
                'by'     => $l->user?->name,
                'notes'  => $l->notes,
                'at'     => $l->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /**
     * The visitor if this user may see it, else 404 - another society's visitor, or another resident's, does not exist as
     * far as they are concerned (a 403 would confirm that the id is real).
     */
    private function findFor(User $user, int $id, array $with = []): Visitor
    {
        $visitor = Visitor::with($with)->where('society_id', $user->society_id)->findOrFail($id);

        if (!$user->hasRole(self::GATE_ROLES) && !VisitorPropertyScope::canViewVisitor($user, $visitor)) {
            abort(404);
        }

        return $visitor;
    }

    private function failure(VisitorWorkflowException $e): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $e->getMessage(), 'status' => $e->currentStatus], $e->status);
    }

    private function formatVisitor(Visitor $v, ?User $viewer = null): array
    {
        $pending = $v->approval_status === 'pending' && $v->entry_status === 'pending';
        $isAdmin = $viewer?->hasAnyRole(['Admin', 'Super Admin']) ?? false;

        return [
            'id'                   => $v->id,
            'visitor_name'         => $v->visitor_name,
            'visitor_phone'        => $v->visitor_phone,
            'visitor_type'         => $v->visitor_type,
            'purpose'              => $v->purpose,
            'vehicle_number'       => $v->vehicle_number,
            'approval_status'      => $v->approval_status,
            'entry_status'         => $v->entry_status,
            // pending_approval | approved | rejected | checked_in | checked_out
            'status'               => $v->workflowStatus(),
            'expected_entry_time'  => $v->expected_entry_time?->toIso8601String(),
            'expected_exit_time'   => $v->expected_exit_time?->toIso8601String(),
            'actual_entry_time'    => $v->actual_entry_time?->toIso8601String(),
            'actual_exit_time'     => $v->actual_exit_time?->toIso8601String(),
            'photo'                => ApiImageHelper::storageUrl($v->photo),
            'flat'                 => $v->flat ? [
                'flat_number' => $v->flat->flat_number,
                'building'    => $v->flat->building ? ['name' => $v->flat->building->name] : null,
            ] : null,
            'unit'                 => $v->flat ? VisitorPropertyScope::unitLabel($v->flat) : null,
            'requested_by'         => $v->requestedBy?->name,
            'decided_by'           => $v->decidedBy?->name,
            'decided_at'           => $v->decided_at?->toIso8601String(),
            'decision_role'        => $v->decision_role,
            'rejection_reason'     => $v->rejection_reason,
            'override_reason'      => $v->override_reason,
            'escalated'            => $v->escalated_at !== null,
            // What THIS viewer may do now - the server's answer, so the app never offers a button the server would refuse.
            'can_decide'           => $viewer !== null && $pending && $this->mayDecide($viewer, $v),
            'can_override'         => $isAdmin && $v->entry_status === 'pending',
            'can_check_in'         => $viewer !== null && $viewer->hasAnyRole(['Staff', 'Guard', 'Admin', 'Super Admin']) && $v->approval_status === 'allowed' && $v->entry_status === 'pending',
            'can_check_out'        => $viewer !== null && $viewer->hasAnyRole(['Staff', 'Guard', 'Admin', 'Super Admin']) && $v->entry_status === 'entered',
            'created_at'           => $v->created_at->toIso8601String(),
        ];
    }

    /** The viewer's own units, worked out once per request (a list of 100 visitors must not repeat it 100 times). */
    private ?array $viewerFlatIds = null;

    private function mayDecide(User $viewer, Visitor $v): bool
    {
        if ($viewer->hasAnyRole(['Admin', 'Super Admin'])) {
            return $viewer->hasRole('Super Admin') || (int) $viewer->society_id === (int) $v->society_id;
        }
        if (VisitorPropertyScope::isSocietyWide($viewer) || (int) $v->society_id !== (int) $viewer->society_id) {
            return false;
        }
        $this->viewerFlatIds ??= VisitorPropertyScope::residentFlatIds($viewer);

        return in_array((int) $v->flat_id, $this->viewerFlatIds, true);
    }
}
