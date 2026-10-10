<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftHandover;
use App\Models\User;
use App\Support\SocietyClock;
// The base Carbon\Carbon, not Illuminate\Support\Carbon - SocietyClock
// itself returns the base class, and a strict Illuminate\Support\Carbon
// type hint rejects it (the inheritance only runs the other way).
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * All the "what time is it, really, for this shift" logic lives here, not
 * scattered across controllers - the one thing this module cannot afford
 * to get wrong twice in two different places. Every moment comparison
 * uses the society's own clock (SocietyClock), the same convention
 * `AccessPass` was fixed to follow this session after a real UTC/IST
 * day-boundary bug.
 */
class GuardShiftService
{
    /**
     * Which of this guard's assignments (if any) covers right now.
     * Checks today's date and yesterday's, because an overnight shift
     * that *started* yesterday can still be the active one after midnight.
     */
    public function currentAssignmentFor(User $guard, ?Carbon $at = null): ?ShiftAssignment
    {
        $at = $at ?? SocietyClock::now($guard->society_id);

        // `date` is declared DATE in the migration, but SQLite has no
        // native date type and stores whatever was written - a plain
        // string match against the column (even via whereIn) silently
        // matched nothing, because the stored value carries a
        // "00:00:00" time component. whereDate() truncates on both
        // sides of the comparison and is portable across drivers.
        $candidates = ShiftAssignment::where('guard_user_id', $guard->id)
            ->where('society_id', $guard->society_id)
            ->where(function ($q) use ($at) {
                $q->whereDate('date', $at->copy()->subDay()->toDateString())
                  ->orWhereDate('date', $at->toDateString());
            })
            ->whereIn('status', [ShiftAssignment::STATUS_SCHEDULED, ShiftAssignment::STATUS_PRESENT, ShiftAssignment::STATUS_LATE])
            ->with('shift', 'gate')
            ->get();

        foreach ($candidates as $assignment) {
            if ($assignment->shift->coversMoment($assignment->date, $at)) {
                return $assignment;
            }
        }

        return null;
    }

    /**
     * What a guard's phone shows: today's shift(s), the one that is on now,
     * and what is coming. currentAssignmentFor() alone answers "which shift
     * covers this exact minute", so a guard looking at 07:00 for a 09:00
     * shift - or at 19:00 after it - saw nothing at all and could not tell
     * "no shift today" from "shift not started yet".
     *
     * Only this guard's own assignments, in their own society.
     *
     * @return array{now:Carbon,current:?ShiftAssignment,today:\Illuminate\Support\Collection,upcoming:\Illuminate\Support\Collection}
     */
    public function overviewFor(User $guard, int $upcomingDays = 7, ?Carbon $at = null): array
    {
        $now   = $at ?? SocietyClock::now($guard->society_id);
        $today = $now->copy()->startOfDay();

        $mine = fn () => ShiftAssignment::where('guard_user_id', $guard->id)
            ->where('society_id', $guard->society_id)
            ->where('status', '!=', ShiftAssignment::STATUS_REPLACED)
            ->with('shift', 'gate', 'handover');

        // Today's own date, plus yesterday's if that overnight shift is still
        // running now (22:00 -> 06:00 at 02:00).
        $todays = $mine()
            ->where(function ($q) use ($now) {
                $q->whereDate('date', $now->toDateString())
                  ->orWhereDate('date', $now->copy()->subDay()->toDateString());
            })
            ->get()
            ->filter(fn (ShiftAssignment $a) => $a->date->toDateString() === $now->toDateString()
                || $a->shift->windowStartingOn($a->date)[1]->gt($today))
            ->sortBy(fn (ShiftAssignment $a) => $a->shift->windowStartingOn($a->date)[0]->getTimestamp())
            ->values();

        $upcoming = $mine()
            ->whereIn('status', [ShiftAssignment::STATUS_SCHEDULED])
            ->whereDate('date', '>', $now->toDateString())
            ->whereDate('date', '<=', $now->copy()->addDays($upcomingDays)->toDateString())
            ->orderBy('date')
            ->get()
            ->sortBy(fn (ShiftAssignment $a) => $a->shift->windowStartingOn($a->date)[0]->getTimestamp())
            ->values();

        return [
            'now'      => $now,
            'current'  => $this->currentAssignmentFor($guard, $now),
            'today'    => $todays,
            'upcoming' => $upcoming,
        ];
    }

    /**
     * The guard's started-but-not-ended assignment, whether or not its window
     * is still open: a guard who forgot to end last night's shift must still
     * be able to close it (currentAssignmentFor() stops answering once the
     * window has passed).
     */
    public function openAttendanceFor(User $guard): ?ShiftAssignment
    {
        return ShiftAssignment::where('guard_user_id', $guard->id)
            ->where('society_id', $guard->society_id)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->whereIn('status', [ShiftAssignment::STATUS_PRESENT, ShiftAssignment::STATUS_LATE])
            ->with('shift', 'gate')
            ->orderByDesc('date')
            ->first();
    }

    /**
     * Create an assignment, rejecting anything that would double-book this
     * guard or this gate's own exclusivity rule for the same real time
     * window - not just a same-shift-same-date match (the unique index
     * already covers that), but two *different* shifts whose windows
     * actually overlap on the same guard.
     */
    public function assign(array $data): ShiftAssignment
    {
        $shift = Shift::findOrFail($data['shift_id']);
        $gate = Gate::findOrFail($data['gate_id']);
        $guard = User::findOrFail($data['guard_user_id']);

        abort_if(!$shift->is_active, 422, 'This shift is not active.');
        abort_if(!$gate->is_active, 422, 'This gate is not active.');
        abort_if(!$guard->hasRole('Guard'), 422, 'Only a Guard account can be assigned a shift.');
        abort_if(
            (int) $shift->society_id !== (int) $data['society_id']
                || (int) $gate->society_id !== (int) $data['society_id']
                || (int) $guard->society_id !== (int) $data['society_id'],
            403,
            'Shift, gate and guard must all belong to the same society.'
        );

        [$newStart, $newEnd] = $shift->windowStartingOn($data['date']);

        // Same whereDate() reasoning as currentAssignmentFor() above - a
        // plain string whereIn('date', ...) never matched the stored
        // "YYYY-MM-DD 00:00:00" value, so this check silently let every
        // overlapping assignment through until it was caught by a test
        // actually asserting on the result, not just on the code existing.
        $overlaps = ShiftAssignment::where('guard_user_id', $guard->id)
            ->where(function ($q) use ($data) {
                $q->whereDate('date', Carbon::parse($data['date'])->subDay()->toDateString())
                  ->orWhereDate('date', $data['date'])
                  ->orWhereDate('date', Carbon::parse($data['date'])->addDay()->toDateString());
            })
            ->whereIn('status', [ShiftAssignment::STATUS_SCHEDULED, ShiftAssignment::STATUS_PRESENT, ShiftAssignment::STATUS_LATE])
            ->with('shift')
            ->get()
            ->first(function (ShiftAssignment $existing) use ($newStart, $newEnd) {
                [$existingStart, $existingEnd] = $existing->shift->windowStartingOn($existing->date);

                return $newStart->lt($existingEnd) && $existingStart->lt($newEnd);
            });

        abort_if($overlaps !== null, 422, 'This guard already has an overlapping shift assignment.');

        return ShiftAssignment::create([
            'society_id'    => $data['society_id'],
            'shift_id'      => $shift->id,
            'gate_id'       => $gate->id,
            'guard_user_id' => $guard->id,
            'date'          => $data['date'],
            'status'        => ShiftAssignment::STATUS_SCHEDULED,
            'created_by'    => $data['created_by'] ?? null,
        ]);
    }

    public function startShift(ShiftAssignment $assignment, ?Carbon $at = null): ShiftAssignment
    {
        abort_if($assignment->hasStarted(), 422, 'This shift has already been started.');

        $at = $at ?? SocietyClock::now($assignment->society_id);
        $assignment->loadMissing('shift');
        $late = $at->gt($assignment->shift->graceDeadlineFor($assignment->date));

        $assignment->update([
            // Eloquent writes a Carbon's wall-clock digits WITHOUT converting zones,
            // so an IST instant was stored as IST digits and read back as UTC
            // (5h30m late). Store the instant in the storage timezone.
            'checked_in_at' => SocietyClock::toStorage($at),
            'status'        => $late ? ShiftAssignment::STATUS_LATE : ShiftAssignment::STATUS_PRESENT,
        ]);

        return $assignment->refresh();
    }

    public function endShift(ShiftAssignment $assignment, ?Carbon $at = null): ShiftAssignment
    {
        abort_if(!$assignment->hasStarted(), 422, 'This shift was never started.');
        abort_if($assignment->hasEnded(), 422, 'This shift has already ended.');

        $assignment->update([
            'checked_out_at' => SocietyClock::toStorage($at ?? SocietyClock::now($assignment->society_id)),
            'status'         => ShiftAssignment::STATUS_CHECKED_OUT,
        ]);

        return $assignment->refresh();
    }

    /**
     * The outgoing guard replaces themselves - not reassigned by an admin.
     * A brand-new assignment row for the replacement, the original marked
     * 'replaced' rather than mutated, so its own attendance timestamps
     * (if any) stay exactly what actually happened.
     */
    public function replace(ShiftAssignment $assignment, User $replacement, User $admin, string $reason): ShiftAssignment
    {
        abort_if(!$assignment->isOpen(), 422, 'Only an open assignment can be replaced.');
        abort_if(!$replacement->hasRole('Guard'), 422, 'Only a Guard account can replace another guard.');
        abort_if((int) $replacement->society_id !== (int) $assignment->society_id, 403, 'Replacement guard must belong to the same society.');

        return DB::transaction(function () use ($assignment, $replacement, $admin, $reason) {
            $old = $assignment->only(['status', 'replaced_by_user_id', 'replacement_reason']);

            $assignment->update([
                'status'               => ShiftAssignment::STATUS_REPLACED,
                'replaced_by_user_id'  => $replacement->id,
                'replacement_reason'   => $reason,
                'replaced_by_admin_id' => $admin->id,
                'replaced_at'          => SocietyClock::toStorage(SocietyClock::now($assignment->society_id)),
            ]);

            ActivityLog::recordFor($assignment, 'guard_shift_replaced', [
                'replaced_by_user_id' => $replacement->id,
                'reason'              => $reason,
            ], $old);

            return ShiftAssignment::create([
                'society_id'    => $assignment->society_id,
                'shift_id'      => $assignment->shift_id,
                'gate_id'       => $assignment->gate_id,
                'guard_user_id' => $replacement->id,
                'date'          => $assignment->date,
                'status'        => ShiftAssignment::STATUS_SCHEDULED,
                'created_by'    => $admin->id,
            ]);
        });
    }

    /**
     * Take an assignment off the roster - a shift given to the wrong guard or
     * date. Only one that is still just a plan: once the guard has started it,
     * or it has been marked missed/absent/replaced, it is attendance history
     * and stays. Audited like a replacement.
     */
    public function remove(ShiftAssignment $assignment, User $admin): void
    {
        abort_if(
            $assignment->status !== ShiftAssignment::STATUS_SCHEDULED || $assignment->hasStarted(),
            422,
            'Only a scheduled shift that has not started can be removed.'
        );

        ActivityLog::recordFor($assignment, 'guard_shift_removed', [
            'guard_user_id' => $assignment->guard_user_id,
            'shift_id'      => $assignment->shift_id,
            'gate_id'       => $assignment->gate_id,
            'date'          => $assignment->date->toDateString(),
            'removed_by'    => $admin->id,
        ], $assignment->only(['status']));

        $assignment->delete();
    }

    /**
     * The guard did not come. Only for a shift that has begun (the guard had
     * the chance to start it) and was never started.
     */
    public function markAbsent(ShiftAssignment $assignment, User $admin): ShiftAssignment
    {
        abort_if(
            $assignment->status !== ShiftAssignment::STATUS_SCHEDULED || $assignment->hasStarted(),
            422,
            'Only a scheduled shift that was never started can be marked absent.'
        );
        $assignment->loadMissing('shift');
        abort_if(
            SocietyClock::now($assignment->society_id)->lt($assignment->shift->windowStartingOn($assignment->date)[0]),
            422,
            'This shift has not begun yet.'
        );

        $old = $assignment->only(['status']);
        $assignment->update(['status' => ShiftAssignment::STATUS_ABSENT]);

        ActivityLog::recordFor($assignment, 'guard_shift_absent', ['marked_by' => $admin->id], $old);

        return $assignment->refresh();
    }
    public function startHandover(ShiftAssignment $assignment, array $notes): ShiftHandover
    {
        abort_if($assignment->handover()->exists(), 422, 'A handover already exists for this shift.');

        return ShiftHandover::create([
            'shift_assignment_id'    => $assignment->id,
            'outgoing_guard_id'      => $assignment->guard_user_id,
            'notes'                  => $notes['notes'] ?? null,
            'pending_visitors_note'  => $notes['pending_visitors_note'] ?? null,
            'active_passes_note'     => $notes['active_passes_note'] ?? null,
            'status'                 => ShiftHandover::STATUS_PENDING,
        ]);
    }

    public function acceptHandover(ShiftHandover $handover, User $incomingGuard): ShiftHandover
    {
        abort_if(!$handover->isPending(), 422, 'This handover was already accepted.');
        abort_if(!$incomingGuard->hasRole('Guard'), 403, 'Only a Guard account can accept a handover.');
        abort_if(
            (int) $handover->shiftAssignment->society_id !== (int) $incomingGuard->society_id,
            403,
            'This handover belongs to a different society.'
        );

        $handover->update([
            'incoming_guard_id' => $incomingGuard->id,
            'status'            => ShiftHandover::STATUS_ACCEPTED,
            'accepted_at'       => SocietyClock::toStorage(SocietyClock::now($incomingGuard->society_id)),
        ]);

        return $handover->refresh();
    }

    /**
     * Scheduled-command friendly: any assignment whose shift start + grace
     * period has passed with no check-in is marked missed. Society-local,
     * so this must be driven per society, not a single global "now".
     */
    public function markMissedAssignments(int $societyId): int
    {
        $now = SocietyClock::now($societyId);
        $count = 0;

        ShiftAssignment::where('society_id', $societyId)
            ->where('status', ShiftAssignment::STATUS_SCHEDULED)
            ->whereNull('checked_in_at')
            ->where(function ($q) use ($now) {
                $q->whereDate('date', $now->copy()->subDay()->toDateString())
                  ->orWhereDate('date', $now->toDateString());
            })
            ->with('shift')
            ->get()
            ->each(function (ShiftAssignment $assignment) use ($now, &$count) {
                if ($now->gt($assignment->shift->graceDeadlineFor($assignment->date))) {
                    $assignment->update(['status' => ShiftAssignment::STATUS_MISSED]);
                    $count++;
                }
            });

        return $count;
    }
}
