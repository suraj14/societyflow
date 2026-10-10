<?php

namespace App\Http\Controllers;

use App\Models\AccessPass;
use App\Models\Flat;
use App\Services\AccessPassEditService;
use App\Support\UserFlatResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Resident/Admin side of gate access passes: recurring staff (cleaner,
 * cook, driver, ...) and temporary service providers (electrician,
 * plumber, ...). The guard side lives in GuardController::scan() and
 * AccessPassService - this controller only creates/lists/blocks/revokes.
 *
 * "Ask only what the system does not already know" applies here too: a
 * resident with one property never picks it, and property_type is never
 * asked - the pass belongs to a flat, and the flat already knows what it is.
 */
class AccessPassController extends BaseController
{
    /** Flats the acting user may create a pass for. */
    private function eligibleFlats(): \Illuminate\Support\Collection
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return Flat::bySociety($this->getSocietyId())->with(['building', 'villaArea'])->get();
        }

        $ids = UserFlatResolver::flatIdsFor($user);

        return Flat::whereIn('id', $ids)->with(['building', 'villaArea'])->get();
    }

    public function index()
    {
        $user = auth()->user();

        $query = AccessPass::with(['flat.building', 'flat.villaArea']);

        // Scope by default, exempt Super Admin explicitly. getSocietyId()
        // returns null for a Super Admin, and bySociety(null) matches
        // `society_id IS NULL` - which no pass ever is - so scoping them
        // the same way as everyone else showed them an empty list rather
        // than every society's.
        if (!$user->hasRole('Super Admin')) {
            $query->bySociety($this->getSocietyId());
        }

        if (!$user->hasAnyRole(['Super Admin', 'Admin'])) {
            $query->whereIn('flat_id', UserFlatResolver::flatIdsFor($user));
        }

        $passes = $query->latest()->paginate(20);

        return view('access-passes.index', compact('passes'));
    }

    public function create()
    {
        $flats = $this->eligibleFlats();
        // Only Admin/Super Admin manage society-wide staff (a common-area
        // cleaner, the society's own electrician) - a resident authorising
        // their own maid has no business granting society-wide access.
        $canCreateSocietyWide = auth()->user()->hasAnyRole(['Super Admin', 'Admin']);

        return view('access-passes.create', compact('flats', 'canCreateSocietyWide'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->hasAnyRole(['Super Admin', 'Admin']);

        $validated = $request->validate([
            'access_scope' => 'nullable|in:property,society',
            // flat_id is validated as required below only for a property-
            // scoped pass - required_if can't itself express "unless
            // society-wide", so the emptiness is checked explicitly.
            'flat_id'      => 'nullable|exists:flats,id',
            'pass_type'    => 'required|in:recurring_staff,temporary_service',
            'person_name'  => 'required|string|max:255',
            'person_phone' => 'nullable|string|max:20',
            'category'     => 'nullable|string|max:100',
            // Recurring only:
            'allowed_days'   => 'required_if:pass_type,recurring_staff|array',
            'allowed_days.*' => 'integer|min:0|max:6',
            'valid_until'    => 'nullable|date|after_or_equal:valid_from',
            // Temporary only:
            'service_date' => 'required_if:pass_type,temporary_service|date',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i|after:start_time',
            'valid_from'   => 'nullable|date',
        ]);

        // Never trust the submitted scope for who is allowed to grant it -
        // a resident's request is always property-scoped regardless of
        // what a forged form field claims.
        $scope = ($isAdmin && ($validated['access_scope'] ?? 'property') === 'society') ? 'society' : 'property';

        $societyId = $this->getSocietyId();
        $flat = null;

        if ($scope === 'property') {
            if (empty($validated['flat_id'])) {
                return back()->withErrors(['flat_id' => 'Select a property for this pass.'])->withInput();
            }

            $flat = Flat::findOrFail($validated['flat_id']);
            $this->authorizeSocietyAccess($flat);

            if (!$isAdmin && !UserFlatResolver::userHasFlat($user, $flat->id)) {
                abort(403, 'You can only create a pass for your own property.');
            }

            $societyId = $flat->society_id;
        }

        if ($validated['pass_type'] === 'recurring_staff') {
            $validFrom = now()->toDateString();
            $validUntil = $validated['valid_until'] ?? null;
        } else {
            $validFrom = $validated['service_date'];
            $validUntil = $validated['service_date'];
        }

        $pass = AccessPass::create([
            'society_id'    => $societyId,
            'flat_id'       => $flat?->id,
            'person_name'   => $validated['person_name'],
            'person_phone'  => $validated['person_phone'] ?? null,
            'category'      => $validated['category'] ?? null,
            'pass_type'     => $validated['pass_type'],
            'access_scope'  => $scope,
            'token'         => AccessPass::generateToken(),
            'allowed_days'  => $validated['pass_type'] === 'recurring_staff' ? ($validated['allowed_days'] ?? []) : null,
            'start_time'    => $validated['start_time'] ?? null,
            'end_time'      => $validated['end_time'] ?? null,
            'valid_from'    => $validFrom,
            'valid_until'   => $validUntil,
            'status'        => 'active',
            'created_by'    => $user->id,
        ]);

        \App\Events\AccessPassCreated::dispatch($pass);

        return redirect()->route('access-passes.show', $pass)
            ->with('success', 'Pass created for ' . $pass->person_name . '.');
    }

    public function show(AccessPass $accessPass)
    {
        $this->authorizeSocietyAccess($accessPass);
        $this->authorizeOwnPass($accessPass);

        return view('access-passes.show', ['pass' => $accessPass->load('flat.building', 'flat.villaArea')]);
    }

    public function edit(AccessPass $accessPass, AccessPassEditService $editor)
    {
        $editor->assertMayManage($accessPass, auth()->user());
        $editor->assertEditable($accessPass);

        return view('access-passes.edit', [
            'pass'                 => $accessPass->load('flat.building', 'flat.villaArea'),
            'flats'                => $this->eligibleFlats(),
            'canCreateSocietyWide' => auth()->user()->hasAnyRole(['Super Admin', 'Admin']),
        ]);
    }

    /**
     * Change a pass in place. The token is deliberately never reissued:
     * a printed or saved QR keeps working, and the gate re-reads every
     * field from this row on each scan (see AccessPassService), so an
     * extended date or a narrowed time window takes effect on the very
     * next scan with no new QR to distribute.
     */
    public function update(Request $request, AccessPass $accessPass, AccessPassEditService $editor)
    {
        $validated = $request->validate(AccessPassEditService::updateRules());

        $editor->update($accessPass, $validated, auth()->user());

        return redirect()->route('access-passes.show', $accessPass)
            ->with('success', $accessPass->person_name . "'s pass has been updated.");
    }

    /**
     * The short form of update() for the common case: the pass lapsed and
     * only its validity needs pushing out. An expired pass is accepted by
     * design - repairing it is the point - but a revoked one is still
     * refused, so "extend" can never quietly restore withdrawn access.
     *
     * Only the schedule keys the form actually submitted are passed on, so
     * pushing a date out never silently erases an existing time window.
     */
    public function extend(Request $request, AccessPass $accessPass, AccessPassEditService $editor)
    {
        $validated = $request->validate(AccessPassEditService::extendRules());

        $pass = $editor->extend(
            $accessPass,
            $validated,
            auth()->user(),
            array_keys($request->only(['allowed_days', 'start_time', 'end_time']))
        );

        return redirect()->route('access-passes.show', $accessPass)
            ->with('success', $pass->person_name . ' is now valid until ' . $pass->valid_until->format('d M Y') . '.');
    }

    /** Lift a same-day block. Revoked passes are not reachable here. */
    public function unblockToday(AccessPass $accessPass, AccessPassEditService $editor)
    {
        $editor->unblockToday($accessPass, auth()->user());

        return back()->with('success', $accessPass->person_name . ' is no longer blocked for today.');
    }

    public function blockToday(AccessPass $accessPass)
    {
        $this->authorizeSocietyAccess($accessPass);
        $this->authorizeOwnPass($accessPass);

        $accessPass->blockToday();

        return back()->with('success', $accessPass->person_name . ' is blocked for today only.');
    }

    public function revoke(AccessPass $accessPass)
    {
        $this->authorizeSocietyAccess($accessPass);
        $this->authorizeOwnPass($accessPass);

        $accessPass->revoke();

        \App\Events\AccessPassRevoked::dispatch($accessPass);

        return redirect()->route('access-passes.index')
            ->with('success', $accessPass->person_name . "'s pass has been revoked.");
    }

    /** Beyond the society check: only Admin or the pass's own flat may manage it. */
    private function authorizeOwnPass(AccessPass $accessPass): void
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return;
        }

        abort_unless(UserFlatResolver::userHasFlat($user, $accessPass->flat_id), 403);
    }
}
