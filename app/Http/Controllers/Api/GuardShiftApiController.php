<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftAssignment;
use App\Services\GuardShiftService;
use App\Support\SocietyClock;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Guard app's "Today's Shift": which gate, which shift, when, and whether
 * it is on yet - from the same roster the office manages on the web
 * (Admin\ShiftAssignmentController) through the same GuardShiftService. A guard
 * only ever sees and acts on their own assignments; the roster itself is
 * managed on the web.
 */
class GuardShiftApiController extends Controller
{
    private function guardOnly(Request $request): ?JsonResponse
    {
        return $request->user()->hasRole('Guard')
            ? null
            : response()->json(['success' => false, 'message' => 'Shifts are for guards.'], 403);
    }

    public function show(Request $request, GuardShiftService $service): JsonResponse
    {
        if ($denied = $this->guardOnly($request)) {
            return $denied;
        }

        $overview = $service->overviewFor($request->user());
        $now = $overview['now'];

        $today    = $overview['today']->map(fn (ShiftAssignment $a) => $this->format($a, $now))->values();
        $upcoming = $overview['upcoming']->map(fn (ShiftAssignment $a) => $this->format($a, $now))->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'now'      => $now->toIso8601String(),
                'timezone' => $now->getTimezone()->getName(),
                'current'  => $overview['current'] ? $this->format($overview['current'], $now) : null,
                'today'    => $today,
                'next'     => $today->firstWhere('phase', 'upcoming') ?? $upcoming->first(),
                'upcoming' => $upcoming,
            ],
        ]);
    }

    public function start(Request $request, GuardShiftService $service): JsonResponse
    {
        if ($denied = $this->guardOnly($request)) {
            return $denied;
        }

        $assignment = $service->currentAssignmentFor($request->user());
        if (!$assignment) {
            return response()->json(['success' => false, 'message' => 'You have no shift to start right now.'], 404);
        }
        if ($assignment->hasStarted()) {
            return response()->json(['success' => false, 'message' => 'This shift has already been started.'], 422);
        }

        $assignment = $service->startShift($assignment);

        return response()->json([
            'success' => true,
            'message' => $assignment->status === ShiftAssignment::STATUS_LATE ? 'Shift started (marked late).' : 'Shift started.',
            'data'    => $this->format($assignment->load('shift', 'gate', 'handover'), SocietyClock::now($assignment->society_id)),
        ]);
    }

    public function end(Request $request, GuardShiftService $service): JsonResponse
    {
        if ($denied = $this->guardOnly($request)) {
            return $denied;
        }

        $assignment = $service->openAttendanceFor($request->user());
        if (!$assignment) {
            return response()->json(['success' => false, 'message' => 'You have no active shift to end.'], 404);
        }

        $assignment = $service->endShift($assignment);

        return response()->json([
            'success' => true,
            'message' => 'Shift ended.',
            'data'    => $this->format($assignment->load('shift', 'gate', 'handover'), SocietyClock::now($assignment->society_id)),
        ]);
    }

    /** Notes for the guard coming on after you. */
    public function handover(Request $request, GuardShiftService $service): JsonResponse
    {
        if ($denied = $this->guardOnly($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'notes'                 => 'nullable|string|max:2000',
            'pending_visitors_note' => 'nullable|string|max:1000',
            'active_passes_note'    => 'nullable|string|max:1000',
        ]);

        $assignment = $service->openAttendanceFor($request->user());
        if (!$assignment) {
            return response()->json(['success' => false, 'message' => 'You have no active shift to hand over.'], 404);
        }
        if ($assignment->handover()->exists()) {
            return response()->json(['success' => false, 'message' => 'A handover already exists for this shift.'], 422);
        }

        $service->startHandover($assignment, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Handover notes saved for the incoming guard.',
            'data'    => $this->format($assignment->load('shift', 'gate', 'handover'), SocietyClock::now($assignment->society_id)),
        ], 201);
    }

    /**
     * phase: upcoming | active | ended | missed - what the shift is, on the
     * society's clock, in words the app can show without doing time maths.
     *
     * @return array{0:string,1:string}
     */
    private function phase(ShiftAssignment $a, Carbon $now): array
    {
        [$start, $end] = $a->shift->windowStartingOn($a->date);

        if ($a->status === ShiftAssignment::STATUS_CHECKED_OUT) {
            return ['ended', 'Completed'];
        }
        if (in_array($a->status, [ShiftAssignment::STATUS_ABSENT, ShiftAssignment::STATUS_MISSED], true)) {
            return ['missed', 'Missed'];
        }
        if ($now->lt($start)) {
            return ['upcoming', 'Upcoming'];
        }
        if ($now->lt($end)) {
            return ['active', 'Active'];
        }

        return $a->hasStarted() ? ['ended', 'Ended'] : ['missed', 'Missed'];
    }

    private function format(ShiftAssignment $a, Carbon $now): array
    {
        $a->loadMissing('shift', 'gate', 'handover');
        [$phase, $label] = $this->phase($a, $now);
        [$start, $end]   = $a->shift->windowStartingOn($a->date);

        return [
            'id'             => $a->id,
            'shift_name'     => $a->shift->name,
            'gate_name'      => $a->gate->name,
            'date'           => $a->date->toDateString(),
            'start_time'     => substr((string) $a->shift->start_time, 0, 5),
            'end_time'       => substr((string) $a->shift->end_time, 0, 5),
            'overnight'      => $a->shift->isOvernight(),
            'starts_at'      => $start->toIso8601String(),
            'ends_at'        => $end->toIso8601String(),
            'status'         => $a->status,
            'phase'          => $phase,
            'phase_label'    => $label,
            'late'           => $a->status === ShiftAssignment::STATUS_LATE,
            // On the society's clock (+05:30), the same wall time the shift is in.
            'checked_in_at'  => $a->checked_in_at ? SocietyClock::local($a->checked_in_at, $a->society_id)->toIso8601String() : null,
            'checked_out_at' => $a->checked_out_at ? SocietyClock::local($a->checked_out_at, $a->society_id)->toIso8601String() : null,
            'can_start'      => $phase === 'active' && !$a->hasStarted() && $a->isOpen(),
            'can_end'        => $a->hasStarted() && !$a->hasEnded(),
            'handover'       => $a->handover ? [
                'status' => $a->handover->status,
                'notes'  => $a->handover->notes,
            ] : null,
        ];
    }
}
