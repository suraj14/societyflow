<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Gate;
use Illuminate\Http\Request;

/**
 * Gate CRUD - additive, new module. Does not touch any existing guard/
 * visitor/access-pass authorization path; see the creating migration's
 * own docblock for why that integration is deliberately out of scope here.
 */
class GateController extends BaseController
{
    public function index()
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $gates = Gate::bySociety($this->getSocietyId())->orderBy('name')->get();

        return view('admin.gates.index', compact('gates'));
    }

    public function create()
    {
        $this->authorizeRole('Super Admin', 'Admin');

        return view('admin.gates.create');
    }

    public function store(Request $request)
    {
        $this->authorizeRole('Super Admin', 'Admin');

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:' . implode(',', Gate::TYPES),
            'description' => 'nullable|string|max:500',
        ]);

        Gate::create($validated + ['society_id' => $this->requireSocietyForWrite(), 'is_active' => true]);

        return redirect()->route('admin.gates.index')->with('success', 'Gate added.');
    }

    public function edit(Gate $gate)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($gate);

        return view('admin.gates.edit', compact('gate'));
    }

    public function update(Request $request, Gate $gate)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($gate);

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => 'required|in:' . implode(',', Gate::TYPES),
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        $gate->update($validated);

        return redirect()->route('admin.gates.index')->with('success', 'Gate updated.');
    }

    public function destroy(Gate $gate)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($gate);

        if ($gate->shiftAssignments()->exists()) {
            return back()->with('error', 'This gate has shift assignments and cannot be deleted. Deactivate it instead.');
        }

        $gate->delete();

        return redirect()->route('admin.gates.index')->with('success', 'Gate removed.');
    }
}
