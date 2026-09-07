<?php

namespace App\Http\Controllers\VillaOwner;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        $visitors = Visitor::where('flat_id', $villa->id)
            ->latest()
            ->paginate(10);

        return view('villa-owner.visitors.index', compact('visitors', 'villa'));
    }

    public function create()
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        return view('villa-owner.visitors.create', compact('villa'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        $validated = $request->validate([
            'visitor_name' => 'required|string|max:255',
            'visitor_phone' => 'required|string|max:20',
            'visitor_email' => 'nullable|email|max:255',
            'purpose' => 'required|string|max:255',
            'visit_date' => 'required|date',
            'expected_time' => 'nullable|string',
            'vehicle_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $validated['society_id'] = $villa->society_id;
        $validated['flat_id'] = $villa->id;
        $validated['host_user_id'] = $user->id;
        $validated['status'] = 'expected';

        Visitor::create($validated);

        return redirect()->route('villa-owner.visitors')
            ->with('success', 'Visitor added successfully. Security will be notified.');
    }

    public function show(Visitor $visitor)
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa || $visitor->flat_id !== $villa->id) {
            abort(403);
        }

        return view('villa-owner.visitors.show', compact('visitor', 'villa'));
    }
}
