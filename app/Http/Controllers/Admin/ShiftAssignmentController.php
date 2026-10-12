<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Gate;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\GuardShiftService;
use App\Exceptions\ShiftAssignmentException;
use App\Support\SocietyClock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        $query = ShiftAssignment::bySociety($societyId)->with(['shift', 'gate', 'guardUser', 'replacedBy']);

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
        // only guards whose account is active can be rostered
        $guards = User::where('society_id', $societyId)->where('status', 'active')->whereHas('roles', fn ($q) => $q->where('name', 'Guard'))->orderBy('name')->get();

        return view('admin.shift-assignments.create', compact('gates', 'shifts', 'guards'));
    }

    public function store(Request $request, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $societyId = $this->getSocietyId();
        // "Today" by the society's own clock. Past dates are refused (a shift that has already ended cannot be
        // scheduled; the history of earlier assignments is untouched), and so is a date a year away, which is a typo.
        $today = SocietyClock::today($societyId);

        // Every id must belong to THIS society: an id from another society is "invalid", exactly like one that does not
        // exist, and says nothing about whether it exists there.
        $validated = $request->validate([
            'guard_user_id' => ['required', Rule::exists('users', 'id')->where('society_id', $societyId)],
            'shift_id'      => ['required', Rule::exists('shifts', 'id')->where('society_id', $societyId)],
            'gate_id'       => ['required', Rule::exists('gates', 'id')->where('society_id', $societyId)],
            'date'          => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $today->toDateString(), 'before_or_equal:' . $today->copy()->addDays(366)->toDateString()],
        ], [
            'guard_user_id.required' => 'Choose a guard.',
            'guard_user_id.exists'   => 'Choose a guard from this society.',
            'shift_id.required'      => 'Choose a shift.',
            'shift_id.exists'        => 'Choose a shift from this society.',
            'gate_id.required'       => 'Choose a gate.',
            'gate_id.exists'         => 'Choose a gate from this society.',
            'date.required'          => 'Choose the date of the shift.',
            'date.date_format'       => 'Enter the date as day, month and year.',
            'date.after_or_equal'    => 'Choose today or a later date - a shift that has already ended cannot be scheduled.',
            'date.before_or_equal'   => 'Choose a date within the next year.',
        ]);

        $validated['society_id'] = $societyId;
        $validated['created_by'] = auth()->id();

        try {
            $service->assign($validated);
        } catch (ShiftAssignmentException $e) {
            // a roster rule (overlap, duplicate, inactive gate ...) belongs next to its field, not on a bare 422 page
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return redirect()->route('admin.shift-assignments.index', ['date' => $validated['date']])
            ->with('success', 'Guard assigned.');
    }

    public function destroy(ShiftAssignment $shiftAssignment, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shiftAssignment);

        try {
            $service->remove($shiftAssignment, auth()->user());
        } catch (ShiftAssignmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Assignment removed from the roster.');
    }

    public function markAbsent(ShiftAssignment $shiftAssignment, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shiftAssignment);

        try {
            $service->markAbsent($shiftAssignment, auth()->user());
        } catch (ShiftAssignmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Guard marked absent for this shift.');
    }

    public function replace(Request $request, ShiftAssignment $shiftAssignment, GuardShiftService $service)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shiftAssignment);

        $validated = $request->validate([
            'replacement_guard_id' => ['required', Rule::exists('users', 'id')->where('society_id', $shiftAssignment->society_id)],
            'reason'               => 'required|string|max:255',
        ], [
            'replacement_guard_id.required' => 'Choose the guard who takes over.',
            'replacement_guard_id.exists'   => 'Choose a guard from this society.',
            'reason.required'               => 'Say why the guard is being replaced.',
        ]);

        $replacement = User::findOrFail($validated['replacement_guard_id']);

        try {
            $service->replace($shiftAssignment, $replacement, auth()->user(), $validated['reason']);
        } catch (ShiftAssignmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Guard replaced for this shift.');
    }
}
