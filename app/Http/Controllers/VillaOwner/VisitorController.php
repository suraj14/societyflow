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
            'expected_time' => 'nullable|date_format:H:i',
            'vehicle_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        // The form's field names do not match the visitors table, so they are
        // mapped here rather than mass-assigned. Previously $validated went
        // straight into Visitor::create(), which meant writing visit_date,
        // expected_time, notes and status - none of which are columns - while
        // never setting expected_entry_time, which is NOT NULL with no
        // default. The insert could not succeed, so this form never worked.
        $expectedEntry = $validated['visit_date'] . ' ' . ($validated['expected_time'] ?? '00:00');

        Visitor::create([
            'society_id'          => $villa->society_id,
            'flat_id'             => $villa->id,
            'host_user_id'        => $user->id,
            'visitor_name'        => $validated['visitor_name'],
            'visitor_phone'       => $validated['visitor_phone'],
            'visitor_email'       => $validated['visitor_email'] ?? null,
            'visitor_type'        => 'guest',
            'purpose'             => $validated['purpose'],
            'vehicle_number'      => $validated['vehicle_number'] ?? null,
            'expected_entry_time' => \Carbon\Carbon::parse($expectedEntry),
            // An owner registering their own guest is pre-approved, matching
            // what VisitorApiController does for the same situation.
            'approval_status'     => 'allowed',
            'entry_status'        => 'pending',
            'approved_by'         => $user->id,
            // who invited and decided, for the approval trail (same as VisitorApprovalService::register for a pre-approved visit)
            'requested_by'        => $user->id,
            'decided_by'          => $user->id,
            'decided_at'          => now(),
            'decision_role'       => 'resident',
            'security_notes'      => $validated['notes'] ?? null,
        ]);

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
