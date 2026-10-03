<?php

namespace App\Services;

use App\Models\AccessPass;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Carbon;

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
