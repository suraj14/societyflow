<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Traits\HandlesFormSubmissions;

class ComplaintController extends BaseController
{
    use HandlesFormSubmissions;

    public function index(Request $request)
    {
        $user = Auth::user();
        $societyId = $user->society_id;
        
        $query = Complaint::with(['society', 'createdBy', 'category', 'flat.building'])
                          ->where('society_id', $societyId);

        // Society staff (Admin, Staff) see every complaint in their society -
        // the same set show()/edit() already let them open. Residents see
        // only what they raised.
        $isStaff = $user->hasAnyRole(['Super Admin', 'Admin', 'Staff']);
        if (!$isStaff) {
            $query->where('created_by', $user->id);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('createdBy', function ($subQ) use ($search) {
                      $subQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Priority filter
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('complaint_category_id', $request->category_id);
        }

        $complaints = $query->latest()->paginate(15);
        
        // Get categories for the current user's society
        $categories = ComplaintCategory::where('society_id', $societyId)
                                      ->where('status', 'active')
                                      ->orderBy('name')
                                      ->get();

        // Statistics over the same set the list shows, from one grouped
        // query. "High priority" = high or urgent and still unresolved
        // (open / in progress) - the same rule as Complaint::scopeOpenTickets.
        $base = Complaint::where('society_id', $societyId)
            ->when(!$isStaff, fn ($q) => $q->where('created_by', $user->id));

        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $stats = [
            'total_complaints' => (int) $byStatus->sum(),
            'open_complaints' => (int) ($byStatus['open'] ?? 0),
            'in_progress_complaints' => (int) ($byStatus['in_progress'] ?? 0),
            'resolved_complaints' => (int) ($byStatus['resolved'] ?? 0),
            'high_priority' => (clone $base)->openTickets()->whereIn('priority', ['high', 'urgent'])->count(),
        ];

        return view('complaints.index', compact('complaints', 'categories', 'stats'));
    }

    public function create()
    {
        $user = Auth::user();
        $societyId = $user->society_id;
        
        // Get categories for the current user's society
        $categories = ComplaintCategory::where('society_id', $societyId)
                                      ->where('status', 'active')
                                      ->orderBy('name')
                                      ->get();
        
        // Get properties for owners
        $properties = collect();
        if ($user->hasAnyRole(['Owner', 'Villa Owner', 'Apartment Owner'])) {
            // Get owned flats
            $ownedFlats = \App\Models\Flat::where('owner_id', $user->owner_id)
                                          ->where('society_id', $societyId)
                                          ->with(['building', 'villaArea'])
                                          ->get();
            
            // Also get flats where user is owner through Resident model
            $residentFlats = \App\Models\Flat::whereHas('residents', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('type', 'owner')
                  ->where('status', 'active');
            })->with(['building', 'villaArea'])->get();
            
            $allFlats = $ownedFlats->merge($residentFlats)->unique('id');
            
            foreach ($allFlats as $flat) {
                if ($flat->property_type === 'villa') {
                    $villaName = !empty($flat->villa_name) ? $flat->villa_name : $flat->flat_number;
                    $areaName = $flat->villaArea ? $flat->villaArea->name : '';
                    $properties->push([
                        'id' => $flat->id,
                        'display_name' => "Villa {$villaName}" . ($areaName ? " - {$areaName}" : '')
                    ]);
                } else {
                    $buildingName = $flat->building ? $flat->building->name : 'Unknown';
                    $properties->push([
                        'id' => $flat->id,
                        'display_name' => "Apartment {$flat->flat_number} - {$buildingName}"
                    ]);
                }
            }
        }
        
        return view('complaints.create', compact('categories', 'properties'));
    }

    public function store(Request $request)
    {
        return $this->handleFormSubmission(function() use ($request) {
            $request->validate([
                'category_id' => 'required|exists:complaint_categories,id',
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'priority' => 'required|in:low,medium,high,urgent',
                'flat_id' => 'nullable|exists:flats,id',
                'images' => 'nullable|array|max:5',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Get logged-in user's society_id and flat_id
            $user = Auth::user();
            $societyId = $user->society_id;
            
            // Determine flat_id based on user role
            $flatId = null;
            
            // Residents (owner or tenant) file against a property they
            // actually occupy or own - resolved by the one resolver the rest
            // of the app uses, never by trusting the submitted flat_id. A
            // single property is auto-resolved; with several, the submitted
            // one must be among theirs.
            if ($user->hasAnyRole(['Super Admin', 'Admin', 'Staff'])) {
                $flatId = $request->flat_id;
            } else {
                $own = \App\Support\UserFlatResolver::flatIdsFor($user);

                if ($request->filled('flat_id')) {
                    if (!in_array((int) $request->flat_id, $own, true)) {
                        abort(403, 'You can only raise a complaint for your own property.');
                    }
                    $flatId = (int) $request->flat_id;
                } else {
                    $flatId = count($own) === 1 ? $own[0] : null;
                }
            }

            // Validate category belongs to user's society
            $category = ComplaintCategory::where('id', $request->category_id)
                                         ->where('society_id', $societyId)
                                         ->first();
            
            if (!$category) {
                throw new \Exception('Invalid complaint category selected.');
            }

            // If flat_id is provided, validate it belongs to user's society
            if ($flatId) {
                $flat = \App\Models\Flat::where('id', $flatId)
                                        ->where('society_id', $societyId)
                                        ->first();
                
                if (!$flat) {
                    throw new \Exception('Invalid property selected.');
                }
            }

            $complaint = Complaint::create([
                'society_id' => $societyId,
                'flat_id' => $flatId,
                'complaint_category_id' => $request->category_id,
                'created_by' => $user->id,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'status' => 'open',
            ]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                $attachments = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('complaints', 'public');
                    $attachments[] = $path;
                }
                $complaint->update(['attachments' => $attachments]);
            }

            // Send email notification
            \App\Services\EmailNotificationService::sendComplaintNotification($complaint, 'created');

            return $complaint;
        }, 'Complaint registered successfully!', 'complaints.index');
    }

    public function show(Complaint $complaint)
    {
        $user = auth()->user();
        
        // Check if user can view this complaint
        if (!$user->hasRole(['Admin', 'Super Admin', 'Staff'])) {
            // Non-admin users can only view their own complaints
            if ($complaint->created_by !== $user->id) {
                abort(403, 'You are not authorized to view this complaint.');
            }
        }
        
        // Check society access
        if ($complaint->society_id !== $user->society_id && !$user->hasRole('Super Admin')) {
            abort(403, 'You are not authorized to view this complaint.');
        }
        
        // Load relationships
        $complaint->load([
            'createdBy', 
            'assignedTo', 
            'category', 
            'flat.building', 
            'updates.user'
        ]);
        
        return view('complaints.show', compact('complaint'));
    }

    public function edit(Complaint $complaint)
    {
        $this->authorizeComplaintManagement($complaint);

        $user = Auth::user();
        $societyId = $user->society_id;

        // Get categories for the current user's society
        $categories = ComplaintCategory::where('society_id', $societyId)
                                      ->where('status', 'active')
                                      ->orderBy('name')
                                      ->get();
        
        return view('complaints.edit', compact('complaint', 'categories'));
    }

    public function update(Request $request, Complaint $complaint)
    {
        $this->authorizeComplaintManagement($complaint);

        return $this->handleFormSubmission(function() use ($request, $complaint) {
            $validated = $request->validate([
                'complaint_category_id' => [
                    'required',
                    // Category must belong to the complaint's own society,
                    // otherwise a valid-looking id from another society could
                    // be attached to it.
                    Rule::exists('complaint_categories', 'id')
                        ->where('society_id', $complaint->society_id),
                ],
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'priority' => 'required|in:low,medium,high,urgent',
                'status' => ['required', Rule::in(Complaint::STATUSES)],
            ]);

            // A resident may edit the text of their own complaint, but the
            // status is the society's to change (open -> in progress ->
            // resolved -> closed), not the reporter's.
            if (!Auth::user()->hasAnyRole(['Super Admin', 'Admin', 'Staff'])) {
                $validated['status'] = $complaint->status;
            }

            $oldStatus = $complaint->status;

            // Only the validated fields are written. This was
            // $complaint->update($request->all()), which let a request rewrite
            // society_id, created_by, assigned_to and flat_id - moving a
            // complaint into another society or reassigning its author.
            $complaint->update($validated);
            $complaint->syncResolutionTimestamp($oldStatus);

            // Dispatch event if status changed
            if ($oldStatus !== $complaint->status) {
                \App\Events\TicketUpdated::dispatch($complaint);
            }

            return $complaint;
        }, 'Complaint updated successfully!', 'complaints.index');
    }

    public function destroy(Complaint $complaint)
    {
        $this->authorizeComplaintManagement($complaint);

        $complaint->delete();

        return redirect()->route('complaints.index')
            ->with('success', 'Complaint deleted successfully!');
    }

    /**
     * Abort unless the current user may modify this complaint.
     *
     * edit/update/destroy previously had no authorization at all, and the
     * complaints resource route is only guarded by `auth` - so any signed-in
     * user could rewrite or delete any complaint in any society by id.
     *
     * Society staff manage every complaint in their own society; a resident
     * manages only the ones they raised.
     */
    private function authorizeComplaintManagement(Complaint $complaint): void
    {
        $this->authorizeSocietyAccess($complaint);

        $user = Auth::user();

        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Staff'])) {
            return;
        }

        if ((int) $complaint->created_by !== (int) $user->id) {
            abort(403, 'You are not authorized to modify this complaint.');
        }
    }

    /**
     * Post an update (and optionally a status change). The reporter may
     * comment on their own complaint; only society staff may change status.
     *
     * Was unauthenticated beyond `auth` - any signed-in user could post to,
     * or change the status of, any complaint in any society by id.
     */
    public function addUpdate(Request $request, Complaint $complaint)
    {
        $this->authorizeComplaintManagement($complaint);

        $isStaff = Auth::user()->hasAnyRole(['Super Admin', 'Admin', 'Staff']);

        $request->validate([
            'message' => 'required|string',
            'type' => 'required|in:comment,status_change,assignment',
            'status' => ['nullable', Rule::in(Complaint::STATUSES)],
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->filled('status') && !$isStaff) {
            abort(403, 'Only society staff can change a complaint\'s status.');
        }

        $attachments = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('complaint-updates', 'public');
                $attachments[] = $path;
            }
        }

        $complaint->addUpdate(Auth::user(), $request->message, $request->type, $attachments);

        if ($request->filled('status') && $request->status !== $complaint->status) {
            $oldStatus = $complaint->status;
            $complaint->update(['status' => $request->status]);
            $complaint->syncResolutionTimestamp($oldStatus);
            \App\Events\TicketUpdated::dispatch($complaint);
        }

        return back()->with('success', 'Update added successfully!');
    }

    /**
     * Assign to a staff member of the same society. Society staff only; the
     * assignee must belong to the complaint's society (was `exists:users,id`,
     * accepting any user from any society).
     */
    public function assign(Request $request, Complaint $complaint)
    {
        $this->authorizeSocietyAccess($complaint);

        if (!Auth::user()->hasAnyRole(['Super Admin', 'Admin', 'Staff'])) {
            abort(403, 'Only society staff can assign complaints.');
        }

        $request->validate([
            'assigned_to' => [
                'required',
                Rule::exists('users', 'id')->where('society_id', $complaint->society_id),
            ],
            'message' => 'nullable|string',
        ]);

        $assignee = User::findOrFail($request->assigned_to);
        $complaint->assignTo($assignee, $request->message, Auth::user());

        return back()->with('success', 'Complaint assigned successfully!');
    }
}