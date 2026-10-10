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

        // Grouped: the bare `where(society) OR whereNull(society) AND status` bound as `society OR (null AND active)`,
        // which also offered this society's INACTIVE categories.
        $categories = ComplaintCategory::where('status', 'active')
            ->where(function ($q) use ($villa) {
                $q->where('society_id', $villa->society_id)->orWhereNull('society_id');
            })
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
            // This society's active categories (or the shared ones): an unscoped `exists` accepted any society's id.
            'category_id' => ['nullable', \Illuminate\Validation\Rule::exists('complaint_categories', 'id')
                ->where(fn ($q) => $q->where('status', 'active')
                    ->where(fn ($s) => $s->where('society_id', $villa->society_id)->orWhereNull('society_id')))],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $complaint = Complaint::create([
            'society_id' => $villa->society_id,
            'flat_id' => $villa->id,
            // The column is complaint_category_id; `category_id` is not fillable, so the chosen category
            // was silently dropped from every request submitted here.
            'complaint_category_id' => $validated['category_id'] ?? null,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        // The office hears about it exactly as for a complaint filed on the main form (this path never told them).
        \App\Support\BusinessNotifications::complaintCreated($complaint);

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
