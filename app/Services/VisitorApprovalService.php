<?php

namespace App\Services;

use App\Events\VisitorApproved;
use App\Events\VisitorWorkflowEvent;
use App\Models\Flat;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Visitor;
use App\Support\VisitorPropertyScope;
use App\Support\VisitorRecipients;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The gate approval workflow, in one place: a guard logs a visitor, the residents of that unit (or an administrator)
 * allow or turn them away, the guard checks them in and out, and an administrator can override with a reason.
 *
 * Every state change is a compare-and-swap UPDATE (`WHERE approval_status = ? AND entry_status = ?`): when an owner and an
 * administrator tap at the same moment exactly one UPDATE matches a row, the other gets a 409 that names the winner. That
 * holds on SQLite and MySQL alike, without relying on row locks.
 *
 * Approval is not entry: allowing a visitor only makes check-in possible. A denied visitor can never be checked in, and a
 * decision is never changed silently - only override() changes one, for an administrator, with a mandatory reason, and it
 * always notifies the residents and the guard.
 *
 * Notifications are an event (VisitorWorkflowEvent) fired after the change is stored; a failure in any channel cannot undo
 * or block the business action.
 */
class VisitorApprovalService
{
    private const GATE_ROLES = ['Guard', 'Staff', 'Admin', 'Super Admin'];

    // ── logging a visitor ───────────────────────────────────────────────────

    /**
     * @param  array<string,mixed> $attributes visitor_name, visitor_phone, visitor_type, purpose, vehicle_number,
     *                                         expected_entry_time, expected_exit_time, expected_count, photo,
     *                                         visitor_id_proof, visitor_id_number, visitor_email
     * @return array{visitor: Visitor, created: bool, linked: bool, pre_approved: bool}
     */
    public static function register(User $creator, Flat $flat, array $attributes, ?string $clientRequestId = null): array
    {
        if (!$creator->hasRole('Super Admin') && (int) $flat->society_id !== (int) $creator->society_id) {
            throw new VisitorWorkflowException('That property is not in your society.', 403);
        }

        // A tenant or owner inviting their own guest is pre-approved (nothing to decide). Anything gate staff or an
        // administrator logs waits for the residents.
        $preApproved = !VisitorPropertyScope::isSocietyWide($creator);
        if ($preApproved && !VisitorPropertyScope::canManageFlat($creator, $flat)) {
            throw new VisitorWorkflowException('You can only add visitors for your own property.', 403);
        }

        $clientRequestId = $clientRequestId !== null && trim($clientRequestId) !== '' ? mb_substr(trim($clientRequestId), 0, 64) : null;

        // The gate app retries a POST it did not hear back from: the same client_request_id is the same visitor.
        if ($clientRequestId !== null && ($existing = self::existingRequest($flat->society_id, $clientRequestId, $creator))) {
            return ['visitor' => $existing, 'created' => false, 'linked' => true, 'pre_approved' => $existing->approval_status === 'allowed' && $existing->decision_role === 'resident'];
        }

        $residents = $preApproved ? collect([$creator]) : VisitorRecipients::residents($flat);
        $now = now();

        $data = Arr::only($attributes, [
            'visitor_name', 'visitor_phone', 'visitor_type', 'purpose', 'vehicle_number', 'expected_entry_time', 'expected_exit_time',
            'expected_count', 'photo', 'visitor_id_proof', 'visitor_id_number', 'visitor_email', 'host_user_id',
        ]) + [
            'society_id'          => $flat->society_id,
            'flat_id'             => $flat->id,
            // kept for the existing screens that read the host (a caller may name one); decisions never depend on it
            'host_user_id'        => $residents->first()?->id ?? $creator->id,
            'requested_by'        => $creator->id,
            'client_request_id'   => $clientRequestId,
            'approval_status'     => $preApproved ? 'allowed' : 'pending',
            'entry_status'        => 'pending',
        ];
        $data['visitor_name'] = trim((string) ($data['visitor_name'] ?? '')) !== '' ? $data['visitor_name'] : 'Visitor';
        $data['expected_entry_time'] ??= $now;
        if ($preApproved) {
            $data += ['approved_by' => $creator->id, 'decided_by' => $creator->id, 'decided_at' => $now, 'decision_role' => 'resident'];
        } else {
            $data['security_notes'] = 'Registered by ' . ($creator->hasAnyRole(['Guard', 'Staff']) ? 'gate staff' : 'an administrator') . ': ' . $creator->name;
        }

        try {
            $visitor = DB::transaction(function () use ($data, $creator, $preApproved) {
                $visitor = Visitor::create($data);
                $visitor->addLog($creator, 'created', $preApproved
                    ? 'Pre-approved by ' . $creator->name
                    : 'Logged at the gate by ' . $creator->name . ' - waiting for the residents to decide');

                return $visitor;
            });
        } catch (QueryException $e) {
            // two submissions of the same client_request_id raced; the unique index let one in
            if ($clientRequestId !== null && ($existing = self::existingRequest($flat->society_id, $clientRequestId, $creator))) {
                return ['visitor' => $existing, 'created' => false, 'linked' => true, 'pre_approved' => $preApproved];
            }
            throw $e;
        }

        if (!$preApproved) {
            VisitorWorkflowEvent::dispatch($visitor, 'requested', $creator->id, ['linked' => $residents->isNotEmpty()]);
        }

        return ['visitor' => $visitor, 'created' => true, 'linked' => $residents->isNotEmpty(), 'pre_approved' => $preApproved];
    }

    private static function existingRequest(int $societyId, string $clientRequestId, User $creator): ?Visitor
    {
        $existing = Visitor::query()->where('society_id', $societyId)->where('client_request_id', $clientRequestId)->first();
        if ($existing && (int) $existing->requested_by !== (int) $creator->id) {
            throw new VisitorWorkflowException('That request id was already used for another visitor.', 409);
        }

        return $existing;
    }

    // ── the residents' (or administrator's) decision ─────────────────────────

    /**
     * Allow or deny a visitor who is still waiting.
     *
     * @param  'allowed'|'denied' $decision
     * @return array{visitor: Visitor, changed: bool}
     */
    public static function decide(Visitor $visitor, User $actor, string $decision, ?string $reason = null): array
    {
        if (!in_array($decision, ['allowed', 'denied'], true)) {
            throw new VisitorWorkflowException('Choose allow or deny.', 422);
        }
        $role = self::deciderRole($visitor, $actor);

        $reason = trim((string) $reason);
        if ($decision === 'denied' && $role === 'admin' && mb_strlen($reason) < 3) {
            throw new VisitorWorkflowException('Enter the reason for turning this visitor away.', 422);
        }
        $reason = $reason !== '' ? mb_substr($reason, 0, 500) : 'Denied by resident';
        $now = now();

        $matched = Visitor::query()->whereKey($visitor->id)
            ->where('approval_status', 'pending')->where('entry_status', 'pending')
            ->update([
                'approval_status'  => $decision,
                'approved_by'      => $decision === 'allowed' ? $actor->id : $visitor->approved_by,
                'decided_by'       => $actor->id,
                'decided_at'       => $now,
                'decision_role'    => $role,
                'rejection_reason' => $decision === 'denied' ? $reason : null,
            ]);

        $fresh = Visitor::findOrFail($visitor->id);

        if ($matched === 0) {
            // The same person tapping twice (a double tap, a retried request) is the same decision, not a conflict.
            if ($fresh->approval_status === $decision && (int) $fresh->decided_by === (int) $actor->id) {
                return ['visitor' => $fresh, 'changed' => false];
            }
            throw self::conflict($fresh);
        }

        if ($decision === 'allowed') {
            $fresh->save();   // the model's saving hook issues the gate QR token for an allowed visitor
        }
        $fresh->addLog($actor, $decision, $decision === 'allowed'
            ? 'Allowed by ' . $actor->name . ($role === 'admin' ? ' (administrator)' : '')
            : $reason . ' - by ' . $actor->name . ($role === 'admin' ? ' (administrator)' : ''));

        VisitorWorkflowEvent::dispatch($fresh, $decision === 'allowed' ? 'approved' : 'rejected', $actor->id, ['reason' => $decision === 'denied' ? $reason : null, 'role' => $role]);
        if ($decision === 'allowed') {
            // the existing "your visitor was approved" email / WhatsApp / bell entry for the other people of the unit
            VisitorApproved::dispatch($fresh);
        }

        return ['visitor' => $fresh, 'changed' => true];
    }

    /**
     * Change an earlier decision (or decide for residents who have not answered). Administrators only, a written reason is
     * mandatory, and the residents and the guard are always told - a rejection is never reversed silently.
     *
     * @param  'allowed'|'denied' $decision
     * @return array{visitor: Visitor, changed: bool}
     */
    public static function override(Visitor $visitor, User $actor, string $decision, ?string $reason): array
    {
        if (!$actor->hasAnyRole(['Admin', 'Super Admin'])) {
            throw new VisitorWorkflowException('Only an administrator can override a gate decision.', 403);
        }
        self::assertVisible($visitor, $actor);

        if (!in_array($decision, ['allowed', 'denied'], true)) {
            throw new VisitorWorkflowException('Choose allow or deny.', 422);
        }
        $reason = trim((string) $reason);
        if (mb_strlen($reason) < 5) {
            throw new VisitorWorkflowException('A reason (at least 5 characters) is required to override a decision.', 422);
        }
        $reason = mb_substr($reason, 0, 500);

        $before = Visitor::findOrFail($visitor->id);
        if ($before->entry_status !== 'pending') {
            throw new VisitorWorkflowException('This visitor has already ' . ($before->entry_status === 'entered' ? 'entered' : 'left') . '; the decision can no longer be changed.', 409, $before->workflowStatus());
        }
        if ($before->approval_status === $decision) {
            throw new VisitorWorkflowException('This visitor is already ' . ($decision === 'allowed' ? 'allowed' : 'denied') . '.', 409, $before->workflowStatus());
        }

        $now = now();
        $matched = Visitor::query()->whereKey($before->id)
            ->where('approval_status', $before->approval_status)->where('entry_status', 'pending')
            ->update([
                'approval_status'  => $decision,
                'approved_by'      => $decision === 'allowed' ? $actor->id : $before->approved_by,
                'decided_by'       => $actor->id,
                'decided_at'       => $now,
                'decision_role'    => 'override',
                'override_by'      => $actor->id,
                'override_at'      => $now,
                'override_reason'  => $reason,
                'rejection_reason' => $decision === 'denied' ? $reason : null,
            ]);

        $fresh = Visitor::findOrFail($before->id);
        if ($matched === 0) {
            throw self::conflict($fresh);
        }

        if ($decision === 'allowed') {
            $fresh->save();
        }
        $fresh->addLog($actor, 'override', sprintf(
            'Administrator %s changed the decision from %s to %s. Reason: %s',
            $actor->name, $before->approval_status, $decision, $reason
        ));

        VisitorWorkflowEvent::dispatch($fresh, 'overridden', $actor->id, [
            'reason' => $reason, 'previous' => $before->approval_status, 'previous_by' => $before->decided_by,
        ]);

        return ['visitor' => $fresh, 'changed' => true];
    }

    // ── the guard lets the visitor in and out ────────────────────────────────

    /** @return array{visitor: Visitor, changed: bool} */
    public static function checkIn(Visitor $visitor, User $actor, ?string $notes = null): array
    {
        self::assertVisible($visitor, $actor);
        self::assertMayRecordGate($visitor, $actor);

        $now = now();
        $matched = Visitor::query()->whereKey($visitor->id)
            ->where('approval_status', 'allowed')->where('entry_status', 'pending')
            ->update([
                'entry_status'      => 'entered',
                'actual_entry_time' => $now,
                'checked_in_by'     => $actor->id,
                'security_notes'    => $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 500) : $visitor->security_notes,
            ]);

        $fresh = Visitor::findOrFail($visitor->id);

        if ($matched === 0) {
            throw match (true) {
                $fresh->approval_status === 'pending' => new VisitorWorkflowException('Visitor must be approved before check-in.', 422, $fresh->workflowStatus()),
                $fresh->approval_status === 'denied'  => new VisitorWorkflowException('Entry was denied for this visitor, so they cannot be checked in.', 422, $fresh->workflowStatus()),
                $fresh->entry_status === 'entered'    => new VisitorWorkflowException('Visitor already checked in.', 422, $fresh->workflowStatus()),
                default                               => new VisitorWorkflowException('Visitor already checked out.', 422, $fresh->workflowStatus()),
            };
        }

        $fresh->addLog($actor, 'entry', trim($notes ?? '') !== '' ? trim($notes) : 'Checked in by ' . $actor->name);
        VisitorWorkflowEvent::dispatch($fresh, 'checked_in', $actor->id);

        return ['visitor' => $fresh, 'changed' => true];
    }

    /** @return array{visitor: Visitor, changed: bool} */
    public static function checkOut(Visitor $visitor, User $actor, ?string $notes = null): array
    {
        self::assertVisible($visitor, $actor);
        self::assertMayRecordGate($visitor, $actor);

        $matched = Visitor::query()->whereKey($visitor->id)
            ->where('entry_status', 'entered')
            ->update(['entry_status' => 'exited', 'actual_exit_time' => now(), 'checked_out_by' => $actor->id]);

        $fresh = Visitor::findOrFail($visitor->id);

        if ($matched === 0) {
            throw $fresh->entry_status === 'exited'
                ? new VisitorWorkflowException('Visitor already checked out.', 422, $fresh->workflowStatus())
                : new VisitorWorkflowException('Visitor has not checked in yet.', 422, $fresh->workflowStatus());
        }

        $fresh->addLog($actor, 'exit', trim($notes ?? '') !== '' ? trim($notes) : 'Checked out by ' . $actor->name);
        VisitorWorkflowEvent::dispatch($fresh, 'checked_out', $actor->id);

        return ['visitor' => $fresh, 'changed' => true];
    }

    // ── nobody answered ─────────────────────────────────────────────────────

    /**
     * Visitors still waiting after the society's no-response time (Settings -> Security "Visitor no-response time", SystemSetting security / visitor_no_response_minutes, default 10,
     * 0 switches it off): tell the administrators and the guard once. The visitor is NEVER admitted by the passing of time -
     * an administrator must decide (override) or the guard turns them away.
     */
    public static function escalateUnanswered(): int
    {
        $count = 0;

        $societies = Visitor::query()->where('approval_status', 'pending')->where('entry_status', 'pending')->whereNull('escalated_at')
            ->distinct()->pluck('society_id');

        foreach ($societies as $societyId) {
            $minutes = (int) SystemSetting::get('security', 'visitor_no_response_minutes', 10, (int) $societyId);
            if ($minutes <= 0) {
                continue;
            }

            Visitor::query()->where('society_id', $societyId)
                ->where('approval_status', 'pending')->where('entry_status', 'pending')->whereNull('escalated_at')
                ->where('created_at', '<=', now()->subMinutes($minutes))
                ->orderBy('id')->each(function (Visitor $visitor) use (&$count, $minutes) {
                    // claim it: only one runner escalates a given visitor
                    $claimed = Visitor::query()->whereKey($visitor->id)->whereNull('escalated_at')
                        ->where('approval_status', 'pending')->where('entry_status', 'pending')
                        ->update(['escalated_at' => now()]);
                    if ($claimed !== 1) {
                        return;
                    }

                    $fresh = Visitor::findOrFail($visitor->id);
                    $who = User::find($fresh->requested_by ?? $fresh->host_user_id);
                    if ($who) {
                        $fresh->addLog($who, 'escalated', "No answer after {$minutes} minutes - the administrators and the guard were told. The visitor was not admitted.");
                    }
                    VisitorWorkflowEvent::dispatch($fresh, 'escalated', null, ['minutes' => $minutes]);
                    $count++;
                });
        }

        return $count;
    }

    // ── authorisation ───────────────────────────────────────────────────────

    /**
     * Who may decide: an administrator of the visitor's society (or Super Admin), or a tenant / owner of that exact unit.
     * Gate staff record the visitor and let them in; they never decide, least of all on their own request.
     *
     * @return 'admin'|'resident'
     */
    private static function deciderRole(Visitor $visitor, User $actor): string
    {
        self::assertVisible($visitor, $actor);

        if ($actor->hasAnyRole(['Admin', 'Super Admin'])) {
            return 'admin';
        }
        if (VisitorPropertyScope::isSocietyWide($actor)) {
            throw new VisitorWorkflowException('Gate staff record visitors; the resident or an administrator decides.', 403);
        }
        if (!VisitorPropertyScope::canManageVisitor($actor, $visitor)) {
            throw new VisitorWorkflowException('You can only decide on visitors to your own property.', 403);
        }

        return 'resident';
    }

    /**
     * Entry and exit are recorded by gate staff or an administrator. The one exception is the existing pre-approved flow:
     * a resident who invited their own guest (they registered it, so it was allowed by them at creation) may still mark
     * that guest in and out. A visitor the guard logged is never checked in by a resident's tap.
     */
    private static function assertMayRecordGate(Visitor $visitor, User $actor): void
    {
        if ($actor->hasAnyRole(self::GATE_ROLES)) {
            return;
        }

        $ownInvitation = (int) $visitor->requested_by === (int) $actor->id
            && (int) $visitor->decided_by === (int) $actor->id
            && $visitor->decision_role === 'resident';
        if (!$ownInvitation) {
            throw new VisitorWorkflowException('Only gate staff or an administrator can record entry and exit.', 403);
        }
    }

    /** A visitor from another society does not exist as far as this user is concerned. */
    private static function assertVisible(Visitor $visitor, User $actor): void
    {
        if (!$actor->hasRole('Super Admin') && (int) $visitor->society_id !== (int) $actor->society_id) {
            throw new VisitorWorkflowException('Visitor not found.', 404);
        }
    }

    private static function conflict(Visitor $current): VisitorWorkflowException
    {
        if ($current->entry_status !== 'pending') {
            return new VisitorWorkflowException('This visitor has already ' . ($current->entry_status === 'entered' ? 'entered' : 'left') . '; the decision can no longer be changed.', 409, $current->workflowStatus());
        }

        $by = $current->decided_by ? User::find($current->decided_by)?->name : null;
        $when = $current->decided_at?->format('d M, h:i A');

        return new VisitorWorkflowException(
            'This visitor was already ' . ($current->approval_status === 'allowed' ? 'allowed' : 'denied')
            . ($by ? " by {$by}" : '') . ($when ? " at {$when}" : '') . '.',
            409,
            $current->workflowStatus()
        );
    }
}
