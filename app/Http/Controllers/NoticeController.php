<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\Society;
use App\Events\NoticePublished;
use App\Traits\HandlesFormSubmissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NoticeController extends BaseController
{
    use HandlesFormSubmissions;
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Notice::with(['society', 'createdBy', 'approvedBy']);

        // Filter by society for Admin users
        if ($user->hasRole('Admin')) {
            $query->where('society_id', $user->society_id);
        }
        // Super Admin sees all notices

        // Search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Priority filter
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $notices = $query->latest()->paginate(10);

        // Stats should also be filtered by society for Admin users
        if ($user->hasRole('Admin')) {
            $stats = [
                'total' => Notice::where('society_id', $user->society_id)->count(),
                'published' => Notice::where('society_id', $user->society_id)->where('status', 'published')->count(),
                'pending' => Notice::where('society_id', $user->society_id)->where('status', 'pending')->count(),
                'draft' => Notice::where('society_id', $user->society_id)->where('status', 'draft')->count(),
            ];
        } else {
            $stats = [
                'total' => Notice::count(),
                'published' => Notice::where('status', 'published')->count(),
                'pending' => Notice::where('status', 'pending')->count(),
                'draft' => Notice::where('status', 'draft')->count(),
            ];
        }

        return view('notices.index', compact('notices', 'stats'));
    }

    public function create()
    {
        // Only admin and super admin can create notices
        $this->authorize('manage_notices');
        
        $user = auth()->user();
        
        // If user is Super Admin, show all societies; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
        } else {
            // For Society Admin, only show their own society
            $societies = collect([$user->society]);
        }
        
        return view('notices.create', compact('societies'));
    }

    public function store(Request $request)
    {
        // Only admin and super admin can create notices
        $this->authorize('manage_notices');

        $user = auth()->user();
        
        // Validation rules
        $rules = [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:general,urgent,maintenance,event,meeting',
            'priority' => 'required|in:low,medium,high',
            'publish_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:publish_date',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'send_email' => 'boolean',
            'send_sms' => 'boolean',
            'status' => 'required|in:draft,pending,published',
        ];
        
        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        $validated = $request->validate($rules);

        // Set society_id for non-Super Admin users
        if (!$user->hasRole('Super Admin')) {
            $validated['society_id'] = $user->society_id;
        }

        if ($request->hasFile('image')) {
            // Store image filename only, don't use Storage::disk
            $file = $request->file('image');
            $uploadPath = public_path('storage/notices');
            @mkdir($uploadPath, 0777, true);
            $filename = time() . '_' . $file->getClientOriginalName();
            try {
                $file->move($uploadPath, $filename);
            } catch (\Exception $e) {
                @chmod($uploadPath, 0777);
                $file->move($uploadPath, $filename);
            }
            $validated['image'] = 'notices/' . $filename;
        }

        $validated['created_by'] = auth()->id() ?? 1;
        $validated['send_email'] = $request->has('send_email');
        $validated['send_sms'] = $request->has('send_sms');
        $validated['publish_date'] = $validated['publish_date'] ?? now();

        // If admin creates, auto-publish; if resident creates, set to pending
        if ($validated['status'] === 'published') {
            $validated['approved_by'] = auth()->id() ?? 1;
            $validated['approved_at'] = now();
        }

        $notice = Notice::create($validated);

        // Dispatch event if notice is published
        if ($validated['status'] === 'published') {
            NoticePublished::dispatch($notice);
        }

        return redirect()->route('notices.index')
            ->with('success', 'Notice created successfully.');
    }

    public function show(Notice $notice)
    {
        $user = auth()->user();
        
        // Verify notice belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $notice->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        $notice->load(['society', 'createdBy', 'approvedBy', 'reads.user']);
        return view('notices.show', compact('notice'));
    }

    public function edit(Notice $notice)
    {
        // Only admin and super admin can edit notices
        $this->authorize('manage_notices');
        
        $user = auth()->user();
        
        // Verify notice belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $notice->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        // If user is Super Admin, show all societies; otherwise, only their society
        if ($user->hasRole('Super Admin')) {
            $societies = Society::where('status', 'active')->get();
        } else {
            // For Society Admin, only show their own society
            $societies = collect([$user->society]);
        }
        
        return view('notices.edit', compact('notice', 'societies'));
    }

    public function update(Request $request, Notice $notice)
    {
        // Only admin and super admin can update notices
        $this->authorize('manage_notices');

        $user = auth()->user();
        
        // Verify notice belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $notice->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        // Validation rules
        $rules = [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:general,urgent,maintenance,event,meeting',
            'priority' => 'required|in:low,medium,high',
            'publish_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:publish_date',
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'send_email' => 'boolean',
            'send_sms' => 'boolean',
            'status' => 'required|in:draft,pending,published,expired',
        ];
        
        // Only Super Admin can select society, others use their own society
        if ($user->hasRole('Super Admin')) {
            $rules['society_id'] = 'required|exists:societies,id';
        }

        $validated = $request->validate($rules);

        // Set society_id for non-Super Admin users
        if (!$user->hasRole('Super Admin')) {
            $validated['society_id'] = $user->society_id;
        }

        if ($request->hasFile('image')) {
            if ($notice->image) {
                // Don't try to delete - just skip it
            }
            // Store image filename only, don't use Storage::disk
            $file = $request->file('image');
            $uploadPath = public_path('storage/notices');
            @mkdir($uploadPath, 0777, true);
            $filename = time() . '_' . $file->getClientOriginalName();
            try {
                $file->move($uploadPath, $filename);
            } catch (\Exception $e) {
                @chmod($uploadPath, 0777);
                $file->move($uploadPath, $filename);
            }
            $validated['image'] = 'notices/' . $filename;
        }

        $validated['send_email'] = $request->has('send_email');
        $validated['send_sms'] = $request->has('send_sms');

        $oldStatus = $notice->status;
        $notice->update($validated);

        // Dispatch event if notice status changed to published
        if ($oldStatus !== 'published' && $validated['status'] === 'published') {
            NoticePublished::dispatch($notice);
        }

        return redirect()->route('notices.index')
            ->with('success', 'Notice updated successfully.');
    }

    public function destroy(Notice $notice)
    {
        // Only admin and super admin can delete notices
        $this->authorize('manage_notices');
        
        $user = auth()->user();
        
        // Verify notice belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $notice->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        if ($notice->image) {
            // Don't try to delete - just skip it
        }

        $notice->delete();

        return redirect()->route('notices.index')
            ->with('success', 'Notice deleted successfully.');
    }

    public function approve(Notice $notice)
    {
        // Only admin and super admin can approve notices
        $this->authorize('manage_notices');
        
        $user = auth()->user();
        
        // Verify notice belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $notice->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        // Additional check to ensure only Admin or Super Admin roles can approve
        if (!auth()->user()->hasRole(['Admin', 'Super Admin'])) {
            abort(403, 'Only administrators can approve notices.');
        }

        $notice->approve(auth()->user());

        return redirect()->route('notices.index')
            ->with('success', 'Notice approved and published successfully.');
    }

    public function reject(Request $request, Notice $notice)
    {
        // Only admin and super admin can reject notices
        $this->authorize('manage_notices');
        
        $user = auth()->user();
        
        // Verify notice belongs to user's society (for Admin only)
        if ($user->hasRole('Admin') && $notice->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }
        
        // Additional check to ensure only Admin or Super Admin roles can reject
        if (!auth()->user()->hasRole(['Admin', 'Super Admin'])) {
            abort(403, 'Only administrators can reject notices.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $notice->reject(auth()->user(), $validated['rejection_reason']);

        return redirect()->route('notices.index')
            ->with('success', 'Notice rejected.');
    }

    public function pending()
    {
        $user = auth()->user();
        $query = Notice::with(['society', 'createdBy'])
            ->where('status', 'pending');

        // Filter by society for Admin users
        if ($user->hasRole('Admin')) {
            $query->where('society_id', $user->society_id);
        }

        $notices = $query->latest()->paginate(10);

        return view('notices.pending', compact('notices'));
    }

    public function history(Request $request)
    {
        $user = auth()->user();
        $query = Notice::with(['society', 'createdBy', 'approvedBy']);

        // Filter by society for Admin users
        if ($user->hasRole('Admin')) {
            $query->where('society_id', $user->society_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $notices = $query->latest()->paginate(15);

        return view('notices.history', compact('notices'));
    }
}
