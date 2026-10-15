<?php

namespace App\Services;

use App\Models\AccessPass;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Server-side gate verification for AccessPass QR scans.
 *
 * Every property of a scanned pass is re-resolved here from the database,
 * never trusted from the QR payload itself (the QR carries only the opaque
 * token - see AccessPass's own docblock). A guard's scan can fail for
 * exactly one of the reasons named in ScanResult::$reason, so the gate UI
 * and the tests both speak the same small vocabulary.
 */
class AccessPassService
{
    public const REASON_NOT_FOUND = 'not_found';
    public const REASON_WRONG_SOCIETY = 'wrong_society';
    public const REASON_REVOKED = 'revoked';
    public const REASON_EXPIRED = 'expired';
    public const REASON_NOT_YET_VALID = 'not_yet_valid';
    public const REASON_BLOCKED_TODAY = 'blocked_today';
    public const REASON_WRONG_DAY = 'wrong_day';
    public const REASON_WRONG_TIME = 'wrong_time';
    public const REASON_ALREADY_INSIDE = 'already_inside';

    // Pre-approved-guest refusals (see checkInGuest()).
    public const REASON_GUEST_WRONG_SOCIETY = 'guest_wrong_society';
    public const REASON_GUEST_NOT_APPROVED = 'guest_not_approved';
    public const REASON_GUEST_ALREADY_IN = 'guest_already_in';

    /**
     * Resolve a scanned token to a verdict, scoped to the scanning guard's
     * own society. Never returns a pass belonging to another society - a
     * cross-society scan is indistinguishable from an unknown token.
     *
     * @return array{ok:bool, reason:?string, pass:?AccessPass}
     */
    public function verify(string $token, int $guardSocietyId, ?Carbon $at = null): array
    {
        $at = $at ?? now();

        $pass = AccessPass::where('token', $token)->first();

        if (!$pass) {
            return ['ok' => false, 'reason' => self::REASON_NOT_FOUND, 'pass' => null];
        }

        if ((int) $pass->society_id !== $guardSocietyId) {
            // Deliberately the same reason as "not found" from the caller's
            // point of view (see scan() below) - a cross-society token must
            // not confirm its own existence to the scanning guard.
            return ['ok' => false, 'reason' => self::REASON_WRONG_SOCIETY, 'pass' => null];
        }

        if ($pass->isRevoked()) {
            return ['ok' => false, 'reason' => self::REASON_REVOKED, 'pass' => $pass];
        }

        // Validity dates, blocked days, allowed weekdays and time windows
        // are all written on the society's own calendar, so the scan moment
        // has to be read on that clock too - at 00:30 IST the storage clock
        // (UTC) still says yesterday 19:00.
        $at = \App\Support\SocietyClock::local($at, $pass->society_id);

        if (!$pass->isWithinValidity($at)) {
            $reason = $at->toDateString() < $pass->valid_from->toDateString()
                ? self::REASON_NOT_YET_VALID
                : self::REASON_EXPIRED;

            return ['ok' => false, 'reason' => $reason, 'pass' => $pass];
        }

        if ($pass->isBlockedOn($at)) {
            return ['ok' => false, 'reason' => self::REASON_BLOCKED_TODAY, 'pass' => $pass];
        }

        if (!$pass->isWithinSchedule($at)) {
            $allowedDays = $pass->allowed_days ?? [];
            $reason = ($pass->pass_type === 'recurring_staff' && !empty($allowedDays)
                    && !in_array((int) $at->dayOfWeek, array_map('intval', $allowedDays), true))
                ? self::REASON_WRONG_DAY
                : self::REASON_WRONG_TIME;

            return ['ok' => false, 'reason' => $reason, 'pass' => $pass];
        }

        // A property-scoped pass already checked in (entered, not yet
        // exited) must be checked out through "Visitors Inside" before
        // scanning again - otherwise one person could accumulate any number
        // of open visits. Society-wide passes have no visitors row to check
        // (see checkIn()'s docblock) - scan() toggles them in/out instead,
        // so "already inside" never applies here.
        if (!$pass->isSocietyWide()) {
            $openVisit = Visitor::where('access_pass_id', $pass->id)
                ->where('entry_status', 'entered')
                ->exists();

            if ($openVisit) {
                return ['ok' => false, 'reason' => self::REASON_ALREADY_INSIDE, 'pass' => $pass];
            }
        }

        return ['ok' => true, 'reason' => null, 'pass' => $pass];
    }

    /**
     * Verify a token and, if it passes, record the entry - as one atomic
     * step. This is what a gate should call; verify() on its own is only a
     * predicate.
     *
     * verify() and checkIn() used to be two separate, unsynchronised
     * statements in both gates (GuardController::scan and
     * AccessPassApiController::scan). Between the "is anyone already
     * inside?" read and the insert, a second scan of the same QR - a guard
     * double-tapping, the app retrying a request, or two guards on two
     * phones - could run the same read, also see nothing open, and insert a
     * second 'entered' row. The duplicate needed no unusual timing: the
     * check and the write were simply not the same operation.
     *
     * Taking the pass row with lockForUpdate() inside a transaction makes
     * the second scan wait for the first to commit, so it re-reads the
     * now-open visit and is refused with ALREADY_INSIDE. The read is
     * repeated *inside* the lock - re-reading is the entire point, so the
     * verdict reflects committed state rather than what was true before
     * the wait.
     *
     * @return array{ok:bool, reason:?string, pass:?AccessPass, visitor:?Visitor, action:?string}
     */
    public function verifyAndCheckIn(string $token, int $guardSocietyId, User $guard): array
    {
        return DB::transaction(function () use ($token, $guardSocietyId, $guard) {
            // Serialise every concurrent scan of this pass behind one row.
            // On MySQL this is a real row lock; on SQLite the surrounding
            // transaction serialises writers, which gives the same
            // guarantee for the dev database.
            $locked = AccessPass::where('token', $token)->lockForUpdate()->first();

            if (!$locked) {
                return ['ok' => false, 'reason' => self::REASON_NOT_FOUND, 'pass' => null, 'visitor' => null, 'action' => null];
            }

            $result = $this->verify($token, $guardSocietyId);

            if (!$result['ok']) {
                return $result + ['visitor' => null, 'action' => null];
            }

            $pass = $result['pass'];

            // A society-wide pass toggles out on the scan that finds it in -
            // see checkIn(). Read under the same lock so two scans cannot
            // both decide it is currently inside.
            if ($pass->isSocietyWide() && $locked->currently_inside) {
                $this->checkOutSocietyWide($pass);

                return ['ok' => true, 'reason' => null, 'pass' => $pass, 'visitor' => null, 'action' => 'checked_out'];
            }

            $visitor = $this->checkIn($pass, $guard);

            return ['ok' => true, 'reason' => null, 'pass' => $pass, 'visitor' => $visitor, 'action' => 'entered'];
        });
    }

    /**
     * The pre-approved-guest half of a gate scan, with the same atomicity
     * as verifyAndCheckIn(): the "is this guest still pending?" read and
     * the update that marks them entered happen under one lock, so a
     * double scan cannot mark the same guest in twice.
     *
     * @return array{found:bool, ok:bool, reason:?string, visitor:?Visitor}
     */
    public function checkInGuest(string $token, int $guardSocietyId, User $guard): array
    {
        return DB::transaction(function () use ($token, $guardSocietyId, $guard) {
            $guest = Visitor::where('qr_token', $token)->lockForUpdate()->first();

            if (!$guest) {
                return ['found' => false, 'ok' => false, 'reason' => null, 'visitor' => null];
            }

            $deny = fn (string $reason) => [
                'found' => true, 'ok' => false, 'reason' => $reason, 'visitor' => $guest,
            ];

            if ((int) $guest->society_id !== $guardSocietyId) {
                return $deny(self::REASON_GUEST_WRONG_SOCIETY);
            }
            if ($guest->approval_status !== 'allowed') {
                return $deny(self::REASON_GUEST_NOT_APPROVED);
            }
            if ($guest->entry_status !== 'pending') {
                return $deny(self::REASON_GUEST_ALREADY_IN);
            }

            $guest->update(['entry_status' => 'entered', 'actual_entry_time' => now(), 'checked_in_by' => $guard->id]);
            $guest->addLog($guard, 'entry', 'Guest QR scanned in');

            // A real entry: the people of the unit hear it, as for any check-in. The guest was approved by the resident who
            // invited them, so nothing is asked of anyone - this is only the notice that they are now inside.
            \App\Events\VisitorWorkflowEvent::dispatch($guest, 'checked_in', $guard->id);

            return ['found' => true, 'ok' => true, 'reason' => null, 'visitor' => $guest];
        });
    }

    /**
     * Record the entry. A property-scoped pass becomes an ordinary visitor
     * row - check-in, "Visitors Inside", check-out and history all already
     * work on this table, so a valid scan needs no parallel bookkeeping of
     * its own. A society-wide pass has no single flat to attach a visitor
     * row to (visitors.flat_id is NOT NULL and read unguarded by several
     * existing views/emails/API formatters - widening it was judged too
     * large and risky a change for this pass, see the access_scope
     * migration's own docblock), so it is tracked directly on the pass and
     * this same scan toggles it back out again (see scan() in
     * GuardController).
     */
    public function checkIn(AccessPass $pass, User $guard): ?Visitor
    {
        if ($pass->isSocietyWide()) {
            $pass->update([
                'currently_inside' => true,
                'last_entry_at'    => now(),
                'last_used_at'     => now(),
            ]);

            return null;
        }

        $visitor = Visitor::create([
            'society_id'          => $pass->society_id,
            'flat_id'              => $pass->flat_id,
            'host_user_id'         => $pass->created_by,
            'access_pass_id'       => $pass->id,
            'visitor_name'         => $pass->person_name,
            // visitors.visitor_phone is NOT NULL, but a pass's own phone is
            // optional (the person is already identified by the QR itself)
            // - '' satisfies the column without making the field mandatory
            // on the pass. Found by a live scan of a phone-less pass, which
            // every existing test fixture had happened to avoid.
            'visitor_phone'        => $pass->person_phone ?? '',
            'visitor_type'         => $pass->pass_type === 'recurring_staff' ? 'service' : 'service',
            'purpose'              => $pass->category,
            'expected_count'       => 1,
            'expected_entry_time'  => now(),
            'approval_status'      => 'allowed',
            'approved_by'          => $guard->id,
            'entry_status'         => 'entered',
            'actual_entry_time'    => now(),
            'security_notes'       => 'Auto entry via access pass scan',
        ]);

        $visitor->addLog($guard, 'entry', 'Access pass "' . $pass->person_name . '" (' . $pass->pass_type . ') scanned in');

        $pass->update(['last_used_at' => now()]);

        return $visitor;
    }

    /** The second scan of a society-wide pass while inside - see checkIn(). */
    public function checkOutSocietyWide(AccessPass $pass): void
    {
        $pass->update([
            'currently_inside' => false,
            'last_exit_at'     => now(),
            'last_used_at'     => now(),
        ]);
    }
}
