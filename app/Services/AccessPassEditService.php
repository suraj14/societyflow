<?php

namespace App\Services;

use App\Models\AccessPass;
use App\Models\ActivityLog;
use App\Models\Flat;
use App\Models\User;
use App\Support\UserFlatResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editing and extending an existing pass - the one place those rules live.
 *
 * The web screen (AccessPassController) and the mobile API
 * (Api\AccessPassApiController) both call this, so a rule written once -
 * a revoked pass is terminal, a resident may not move a pass onto someone
 * else's property, the token is never reissued - cannot be true on one
 * client and false on the other.
 *
 * Deliberately *not* folded into AccessPassService: that class answers
 * "may this person through the gate right now", which the guard calls on
 * every scan. This one answers "may this person change this pass", which
 * residents and admins call. Keeping them apart keeps the hot scan path
 * free of edit concerns.
 */
class AccessPassEditService
{
    /** Validation rules for a full edit, shared by both clients. */
    public static function updateRules(): array
    {
        return [
            'person_name'    => 'required|string|max:255',
            'person_phone'   => 'nullable|string|max:20',
            'category'       => 'nullable|string|max:100',
            'flat_id'        => 'nullable|exists:flats,id',
            'access_scope'   => 'nullable|in:property,society',
            'allowed_days'   => 'nullable|array',
            'allowed_days.*' => 'integer|min:0|max:6',
            'start_time'     => 'nullable|date_format:H:i',
            'end_time'       => 'nullable|date_format:H:i|after:start_time',
            'valid_from'     => 'required|date',
            'valid_until'    => 'nullable|date|after_or_equal:valid_from',
        ];
    }

    /** Validation rules for the short "push the date out" form. */
    public static function extendRules(): array
    {
        return [
            'valid_until'    => 'required|date|after_or_equal:today',
            'allowed_days'   => 'nullable|array',
            'allowed_days.*' => 'integer|min:0|max:6',
            'start_time'     => 'nullable|date_format:H:i',
            'end_time'       => 'nullable|date_format:H:i|after:start_time',
        ];
    }

    /**
     * A revoked pass is terminal. If editing a date could reactivate it,
     * "revoke" would be a reversible toggle rather than a withdrawal, so
     * every write path refuses it and the UI offers a new pass instead.
     * An *expired* pass is explicitly still editable - repairing it is the
     * entire point of extend().
     */
    public function assertEditable(AccessPass $pass): void
    {
        abort_if(
            $pass->isRevoked(),
            403,
            'This pass was revoked and cannot be edited. Create a new pass instead.'
        );
    }

    /** Society + ownership. Admins manage their society; residents only their own property. */
    public function assertMayManage(AccessPass $pass, User $actor): void
    {
        abort_if((int) $pass->society_id !== (int) $actor->society_id && !$actor->hasRole('Super Admin'), 403);

        if ($actor->hasAnyRole(['Super Admin', 'Admin'])) {
            return;
        }

        abort_unless(UserFlatResolver::userHasFlat($actor, $pass->flat_id), 403);
    }

    /**
     * Apply a full edit. The token is never reissued: a printed or saved
     * QR keeps working, and the gate re-reads every field from this row on
     * each scan (see AccessPassService), so a changed window takes effect
     * on the very next scan with no new QR to hand out.
     */
    public function update(AccessPass $pass, array $data, User $actor): AccessPass
    {
        $this->assertMayManage($pass, $actor);
        $this->assertEditable($pass);

        $isAdmin = $actor->hasAnyRole(['Super Admin', 'Admin']);

        return DB::transaction(function () use ($pass, $data, $actor, $isAdmin) {
            $old = $this->auditPayload($pass);

            // The scope a resident may grant is always 'property',
            // whatever the submitted field claims - same rule as create.
            $scope = ($isAdmin && ($data['access_scope'] ?? $pass->access_scope) === 'society')
                ? 'society'
                : 'property';

            $flatId = $scope === 'society'
                ? null
                : $this->resolveFlatId($pass, $data, $actor, $isAdmin);

            $pass->update([
                'person_name'  => $data['person_name'],
                'person_phone' => $data['person_phone'] ?? null,
                'category'     => $data['category'] ?? null,
                'access_scope' => $scope,
                'flat_id'      => $flatId,
                'allowed_days' => $pass->pass_type === 'recurring_staff'
                    ? array_values($data['allowed_days'] ?? [])
                    : null,
                'start_time'   => $data['start_time'] ?? null,
                'end_time'     => $data['end_time'] ?? null,
                'valid_from'   => $data['valid_from'],
                'valid_until'  => $data['valid_until'] ?? null,
            ]);

            $this->record($pass, 'access_pass_updated', $old);

            return $pass->fresh();
        });
    }

    /**
     * Push the validity out, optionally adjusting the schedule. Only the
     * keys actually present are written, so extending a date never
     * silently erases a time window the caller did not mention.
     */
    public function extend(AccessPass $pass, array $data, User $actor, array $present = []): AccessPass
    {
        $this->assertMayManage($pass, $actor);
        $this->assertEditable($pass);

        return DB::transaction(function () use ($pass, $data, $present) {
            $old = $this->auditPayload($pass);

            $changes = ['valid_until' => $data['valid_until']];

            if (in_array('allowed_days', $present, true) && $pass->pass_type === 'recurring_staff') {
                $changes['allowed_days'] = array_values($data['allowed_days'] ?? []);
            }
            if (in_array('start_time', $present, true)) {
                $changes['start_time'] = $data['start_time'] ?? null;
            }
            if (in_array('end_time', $present, true)) {
                $changes['end_time'] = $data['end_time'] ?? null;
            }

            $pass->update($changes);

            $this->record($pass, 'access_pass_extended', $old);

            return $pass->fresh();
        });
    }

    public function unblockToday(AccessPass $pass, User $actor): AccessPass
    {
        $this->assertMayManage($pass, $actor);
        $this->assertEditable($pass);

        $old = $this->auditPayload($pass);
        $pass->unblockToday();
        $this->record($pass, 'access_pass_unblocked', $old);

        return $pass->fresh();
    }

    /**
     * A missing flat_id means "leave it where it is", not "detach the pass
     * from its property". Moving a pass onto a property the actor does not
     * hold would otherwise be a way to grant themselves standing access to
     * somebody else's home, so the target is re-checked rather than taken
     * on trust from the request.
     */
    private function resolveFlatId(AccessPass $pass, array $data, User $actor, bool $isAdmin): int
    {
        $requested = $data['flat_id'] ?? $pass->flat_id;

        if (empty($requested)) {
            throw ValidationException::withMessages([
                'flat_id' => ['Select a property for this pass.'],
            ]);
        }

        if ((int) $requested === (int) $pass->flat_id) {
            return (int) $pass->flat_id;
        }

        $flat = Flat::findOrFail($requested);

        abort_if((int) $flat->society_id !== (int) $pass->society_id, 403);

        if (!$isAdmin && !UserFlatResolver::userHasFlat($actor, $flat->id)) {
            abort(403, 'You can only move a pass to your own property.');
        }

        return (int) $flat->id;
    }

    /** The fields worth auditing. The token is never among them. */
    private function auditPayload(AccessPass $pass): array
    {
        return [
            'person_name'   => $pass->person_name,
            'person_phone'  => $pass->person_phone,
            'category'      => $pass->category,
            'access_scope'  => $pass->access_scope,
            'flat_id'       => $pass->flat_id,
            'allowed_days'  => $pass->allowed_days,
            'start_time'    => $pass->start_time,
            'end_time'      => $pass->end_time,
            'valid_from'    => $pass->valid_from?->toDateString(),
            'valid_until'   => $pass->valid_until?->toDateString(),
            'status'        => $pass->status,
            'blocked_dates' => $pass->blocked_dates,
        ];
    }

    /** Record only what actually changed, with who and when. */
    private function record(AccessPass $pass, string $action, array $old): void
    {
        $new = $this->auditPayload($pass->fresh());

        $changed = array_filter(
            $new,
            fn ($value, $key) => $value !== ($old[$key] ?? null),
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($changed)) {
            return;
        }

        ActivityLog::recordFor($pass, $action, $changed, array_intersect_key($old, $changed));
    }
}
