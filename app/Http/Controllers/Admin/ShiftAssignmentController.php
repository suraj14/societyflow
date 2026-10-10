<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\GuardShiftService;
use Illuminate\Http\Request;

/**
 * "Do not create a complicated enterprise scheduling UI" - a filterable
 * list + a simple create form, one assignment at a time. No recurrence
 * engine: an admin assigning a guard to the same shift/gate every weekday
 * submits the form once per date, which is deliberately the ceiling of
 * complexity this module takes on.
 */
class ShiftAssignmentController extends BaseController
{
    public function index(Request $request, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $societyId = $this->getSocietyId();

        $query = ShiftAssignment::bySociety($societyId)->with(['shift', 'gate', 'guardUser']);

        if ($request->filled('date')) {
            // whereDate(), not where() - the column stores a full
            // "YYYY-MM-DD 00:00:00" value on SQLite despite being declared
            // DATE, so a plain string match silently returns nothing.
            $query->whereDate('date', $request->date);
        } else {
            // "Today" by the society's own clock, not the server's.
            $query->whereDate('date', \App\Support\SocietyClock::today($societyId)->toDateString());
        }
        if ($request->filled('gate_id')) {
            $query->where('gate_id', $request->gate_id);
        }
        if ($request->filled('guard_user_id')) {
            $query->where('guard_user_id', $request->guard_user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $assignments = $query->orderBy('date')->paginate(20)->withQueryString();

        $gates = Gate::bySociety($societyId)->active()->orderBy('name')->get();
        $guards = User::where('society_id', $societyId)->whereHas('roles', fn ($q) => $q->where('name', 'Guard'))->orderBy('name')->get();
        $shifts = Shift::bySociety($societyId)->active()->orderBy('start_time')->get();

        return view('admin.shift-assignments.index', compact('assignments', 'gates', 'guards', 'shifts'));
    }

    public function create()
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $societyId = $this->getSocietyId();
        $gates = Gate::bySociety($societyId)->active()->orderBy('name')->get();
        $shifts = Shift::bySociety($societyId)->active()->orderBy('start_time')->get();
        $guards = User::where('society_id', $societyId)->whereHas('roles', fn ($q) => $q->where('name', 'Guard'))->orderBy('name')->get();

        return view('admin.shift-assignments.create', compact('gates', 'shifts', 'guards'));
    }

    public function store(Request $request, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $validated = $request->validate([
            'shift_id'      => 'required|exists:shifts,id',
            'gate_id'       => 'required|exists:gates,id',
            'guard_user_id' => 'required|exists:users,id',
            'date'          => 'required|date',
        ]);

        $validated['society_id'] = $this->getSocietyId();
        $validated['created_by'] = auth()->id();

        $service->assign($validated);

        return redirect()->route('admin.shift-assignments.index', ['date' => $validated['date']])
            ->with('success', 'Guard assigned.');
    }

    public function destroy(ShiftAssignment $shiftAssignment, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shiftAssignment);

        $service->remove($shiftAssignment, auth()->user());

        return back()->with('success', 'Assignment removed from the roster.');
    }

    public function markAbsent(ShiftAssignment $shiftAssignment, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shiftAssignment);

        $service->markAbsent($shiftAssignment, auth()->user());

        return back()->with('success', 'Guard marked absent for this shift.');
    }
    public function replace(Request $request, ShiftAssignment $shiftAssignment, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shiftAssignment);

        $validated = $request->validate([
            'replacement_guard_id' => 'required|exists:users,id',
            'reason'               => 'required|string|max:255',
        ]);

        $replacement = User::findOrFail($validated['replacement_guard_id']);

        $service->replace($shiftAssignment, $replacement, auth()->user(), $validated['reason']);

        return back()->with('success', 'Guard replaced for this shift.');
    }
}
