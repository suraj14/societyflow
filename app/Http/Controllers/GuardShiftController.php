<?php

namespace App\Http\Controllers;

use App\Models\ShiftHandover;
use App\Services\GuardShiftService;
use Illuminate\Http\Request;

/**
 * The Guard's own side of shift management: what shift am I on right now,
 * start it, end it, hand it over. Deliberately separate from
 * Admin\ShiftAssignmentController - a guard never manages the roster,
 * only their own attendance on the assignment the roster already gave them.
 */
class GuardShiftController extends BaseController
{
    public function current(GuardShiftService $service)
    {
        $assignment = $service->currentAssignmentFor(auth()->user());

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $assignment ? $this->formatAssignment($assignment) : null,
            ]);
        }

        return view('guard.shift', ['assignment' => $assignment]);
    }

    public function start(GuardShiftService $service)
    {
        $assignment = $service->currentAssignmentFor(auth()->user());
        abort_if(!$assignment, 404, 'You have no scheduled shift right now.');

        $assignment = $service->startShift($assignment);

        return back()->with('success', 'Shift started. Status: ' . ucfirst($assignment->status) . '.');
    }

    public function end(GuardShiftService $service)
    {
        $assignment = $service->currentAssignmentFor(auth()->user());
        abort_if(!$assignment, 404, 'You have no active shift right now.');

        $service->endShift($assignment);

        return back()->with('success', 'Shift ended.');
    }

    public function startHandover(Request $request, GuardShiftService $service)
    {
        $assignment = $service->currentAssignmentFor(auth()->user());
        abort_if(!$assignment, 404, 'You have no active shift to hand over.');

        $validated = $request->validate([
            'notes'                 => 'nullable|string|max:2000',
            'pending_visitors_note' => 'nullable|string|max:1000',
            'active_passes_note'    => 'nullable|string|max:1000',
        ]);

        $service->startHandover($assignment, $validated);

        return back()->with('success', 'Handover notes saved for the incoming guard.');
    }

    public function acceptHandover(ShiftHandover $shiftHandover, GuardShiftService $service)
    {
        $this->authorizeSocietyAccess($shiftHandover->shiftAssignment);

        $service->acceptHandover($shiftHandover, auth()->user());

        return back()->with('success', 'Handover accepted.');
    }

    private function formatAssignment($assignment): array
    {
        $assignment->loadMissing('shift', 'gate', 'handover');

        return [
            'id'             => $assignment->id,
            'shift_name'     => $assignment->shift->name,
            'gate_name'      => $assignment->gate->name,
            'date'           => $assignment->date->toDateString(),
            'start_time'     => $assignment->shift->start_time,
            'end_time'       => $assignment->shift->end_time,
            'status'         => $assignment->status,
            'checked_in_at'  => $assignment->checked_in_at?->toIso8601String(),
            'checked_out_at' => $assignment->checked_out_at?->toIso8601String(),
            'handover'       => $assignment->handover ? [
                'status' => $assignment->handover->status,
                'notes'  => $assignment->handover->notes,
            ] : null,
        ];
    }
}
