<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends BaseController
{
    public function index()
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $shifts = Shift::bySociety($this->getSocietyId())->orderBy('start_time')->get();

        return view('admin.shifts.index', compact('shifts'));
    }

    public function create()
    {
        $this->authorizeRole('Super Admin', 'Admin');

        return view('admin.shifts.create');
    }

    public function store(Request $request)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $validated = $request->validate([
            'name'                  => 'required|string|max:100',
            'start_time'            => 'required|date_format:H:i',
            'end_time'              => 'required|date_format:H:i',
            'grace_period_minutes'  => 'required|integer|min:0|max:120',
        ]);

        Shift::create($validated + ['society_id' => $this->requireSocietyForWrite(), 'is_active' => true]);

        return redirect()->route('admin.shifts.index')->with('success', 'Shift created.');
    }

    public function edit(Shift $shift)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shift);

        return view('admin.shifts.edit', compact('shift'));
    }

    public function update(Request $request, Shift $shift)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shift);

        $validated = $request->validate([
            'name'                 => 'required|string|max:100',
            'start_time'           => 'required|date_format:H:i',
            'end_time'             => 'required|date_format:H:i',
            'grace_period_minutes' => 'required|integer|min:0|max:120',
            'is_active'            => 'nullable|boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $shift->update($validated);

        return redirect()->route('admin.shifts.index')->with('success', 'Shift updated.');
    }

    public function destroy(Shift $shift)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($shift);

        if ($shift->assignments()->exists()) {
            return back()->with('error', 'This shift has assignments and cannot be deleted. Deactivate it instead.');
        }

        $shift->delete();

        return redirect()->route('admin.shifts.index')->with('success', 'Shift removed.');
    }
}
