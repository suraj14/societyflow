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

        // Scope to the user's own society for everyone except Super Admin.
        //
        // This used to read `if ($user->hasRole('Admin'))`, so only Admin was
        // scoped. Every other role - Tenant, Apartment Owner, Villa Owner,
        // Staff, Guard, Accountant - fell through with no filter and saw
        // every notice in the database, including other societies' drafts and
        // rejected submissions. Super Admin is cross-society by design.
        if (!$user->hasRole('Super Admin')) {
            $query->where('society_id', $user->society_id);
        }

        // Only the office works with drafts, pending and rejected notices; everyone else reads published ones.
        $manages = $user->can('manage_notices');
        if (!$manages) {
            $query->where('status', 'published');
        }

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
        // Scoped like the list (the old non-Admin branch counted every society's notices, drafts included).
        $count = function (?string $status = null) use ($user, $manages) {
            $q = Notice::query();
            if (!$user->hasRole('Super Admin')) {
                $q->where('society_id', $user->society_id);
            }
            if (!$manages) {
                $q->where('status', 'published');
            }

            return $status ? $q->where('status', $status)->count() : $q->count();
        };
        $stats = [
            'total' => $count(),
            'published' => $count('published'),
            'pending' => $count('pending'),
            'draft' => $count('draft'),
        ];

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
            $validated['image'] = $this->handleFileUpload($request, 'image', 'notices', ['jpeg', 'png', 'jpg', 'gif']);
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
        // Was `if ($user->hasRole('Admin') && $notice->society_id !== ...)`.
        // Unlike edit/update/destroy/approve/reject, show() has no
        // authorize('manage_notices') gate, so every authenticated role
        // reached it and the && skipped the society check for all of them -
        // a Tenant, Owner, Guard or Accountant could read any notice in any
        // society by id, including other societies' drafts.
        $this->authorizeSocietyAccess($notice);

        // Unpublished notices are office-only (a resident guessing the id must not read a draft).
        abort_unless($notice->status === 'published' || auth()->user()->can('manage_notices'), 404);

        $notice->load(['society', 'createdBy', 'approvedBy', 'reads.user']);
        return view('notices.show', compact('notice'));
    }

    public function edit(Notice $notice)
    {
        // Only admin and super admin can edit notices
        $this->authorize('manage_notices');
        
        $user = auth()->user();
        
        // Verify notice belongs to user's society (for Admin only)
        // Defence in depth. These five are reached only through
        // authorize('manage_notices'), which today admits Admin and Super
        // Admin alone, so the && was not exploitable here as it was in
        // show(). But CLAUDE.md §2 notes the 36 permissions are only partly
        // wired: the day manage_notices reaches another role, this check
        // would silently stop applying. authorizeSocietyAccess() is
        // equivalent for both current roles (it exempts Super Admin) and
        // does not depend on the permission map staying as it is.
        $this->authorizeSocietyAccess($notice);
        
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
        // Defence in depth. These five are reached only through
        // authorize('manage_notices'), which today admits Admin and Super
        // Admin alone, so the && was not exploitable here as it was in
        // show(). But CLAUDE.md §2 notes the 36 permissions are only partly
        // wired: the day manage_notices reaches another role, this check
        // would silently stop applying. authorizeSocietyAccess() is
        // equivalent for both current roles (it exempts Super Admin) and
        // does not depend on the permission map staying as it is.
        $this->authorizeSocietyAccess($notice);
        
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
            $oldImage = $notice->image;
            $validated['image'] = $this->handleFileUpload($request, 'image', 'notices', ['jpeg', 'png', 'jpg', 'gif']);
            if ($oldImage) {
                $this->deleteUploadedFile($oldImage);
            }
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
        // Defence in depth. These five are reached only through
        // authorize('manage_notices'), which today admits Admin and Super
        // Admin alone, so the && was not exploitable here as it was in
        // show(). But CLAUDE.md §2 notes the 36 permissions are only partly
        // wired: the day manage_notices reaches another role, this check
        // would silently stop applying. authorizeSocietyAccess() is
        // equivalent for both current roles (it exempts Super Admin) and
        // does not depend on the permission map staying as it is.
        $this->authorizeSocietyAccess($notice);
        
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
        // Defence in depth. These five are reached only through
        // authorize('manage_notices'), which today admits Admin and Super
        // Admin alone, so the && was not exploitable here as it was in
        // show(). But CLAUDE.md §2 notes the 36 permissions are only partly
        // wired: the day manage_notices reaches another role, this check
        // would silently stop applying. authorizeSocietyAccess() is
        // equivalent for both current roles (it exempts Super Admin) and
        // does not depend on the permission map staying as it is.
        $this->authorizeSocietyAccess($notice);
        
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
        // Defence in depth. These five are reached only through
        // authorize('manage_notices'), which today admits Admin and Super
        // Admin alone, so the && was not exploitable here as it was in
        // show(). But CLAUDE.md §2 notes the 36 permissions are only partly
        // wired: the day manage_notices reaches another role, this check
        // would silently stop applying. authorizeSocietyAccess() is
        // equivalent for both current roles (it exempts Super Admin) and
        // does not depend on the permission map staying as it is.
        $this->authorizeSocietyAccess($notice);
        
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

        // The approval queue is the office's; everyone else sees only their own submissions in it
        // (it used to list every pending notice of the society to any signed-in resident).
        if (!$user->can('manage_notices')) {
            $query->where('created_by', $user->id);
        }

        // Everyone except Super Admin is scoped to their own society.
        if (!$user->hasRole('Super Admin')) {
            $query->where('society_id', $user->society_id);
        }

        $notices = $query->latest()->paginate(10);

        return view('notices.pending', compact('notices'));
    }

    public function history(Request $request)
    {
        $user = auth()->user();
        $query = Notice::with(['society', 'createdBy', 'approvedBy']);

        // Everyone except Super Admin is scoped to their own society.
        if (!$user->hasRole('Super Admin')) {
            $query->where('society_id', $user->society_id);
        }

        if (!$user->can('manage_notices')) {
            $query->where('status', 'published');
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
