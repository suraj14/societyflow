<?php

namespace App\Http\Controllers\VillaOwner;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        $requests = Complaint::where('flat_id', $villa->id)
            ->with('category')
            ->latest()
            ->paginate(10);

        return view('villa-owner.requests.index', compact('requests', 'villa'));
    }

    public function create()
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        $categories = ComplaintCategory::where('society_id', $villa->society_id)
            ->orWhereNull('society_id')
            ->where('status', 'active')
            ->get();

        return view('villa-owner.requests.create', compact('villa', 'categories'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa) {
            return redirect()->route('villa-owner.dashboard');
        }

        $validated = $request->validate([
            'category_id' => 'nullable|exists:complaint_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        Complaint::create([
            'society_id' => $villa->society_id,
            'flat_id' => $villa->id,
            'category_id' => $validated['category_id'],
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        return redirect()->route('villa-owner.requests')
            ->with('success', 'Request submitted successfully. We will get back to you soon.');
    }

    public function show(Complaint $request)
    {
        $user = auth()->user();
        $villa = $user->ownedVilla;

        if (!$villa || $request->flat_id !== $villa->id) {
            abort(403);
        }

        $request->load(['category', 'updates']);

        return view('villa-owner.requests.show', compact('request', 'villa'));
    }
}
