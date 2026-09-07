<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Role-based filtering
        if ($user->hasRole('Admin')) {
            // Admin can see all complaints in their society
            // No additional filtering needed
        } else {
            // Other roles can only see their own complaints
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

        // Statistics based on user role
        if ($user->hasRole('Admin')) {
            // Admin sees all society statistics
            $stats = [
                'total_complaints' => Complaint::where('society_id', $societyId)->count(),
                'open_complaints' => Complaint::where('society_id', $societyId)->where('status', 'open')->count(),
                'in_progress_complaints' => Complaint::where('society_id', $societyId)->where('status', 'in_progress')->count(),
                'resolved_complaints' => Complaint::where('society_id', $societyId)->where('status', 'resolved')->count(),
                'high_priority' => Complaint::where('society_id', $societyId)->where('priority', 'high')->where('status', '!=', 'resolved')->count(),
            ];
        } else {
            // Other roles see only their own statistics
            $stats = [
                'total_complaints' => Complaint::where('society_id', $societyId)->where('created_by', $user->id)->count(),
                'open_complaints' => Complaint::where('society_id', $societyId)->where('created_by', $user->id)->where('status', 'open')->count(),
                'in_progress_complaints' => Complaint::where('society_id', $societyId)->where('created_by', $user->id)->where('status', 'in_progress')->count(),
                'resolved_complaints' => Complaint::where('society_id', $societyId)->where('created_by', $user->id)->where('status', 'resolved')->count(),
                'high_priority' => Complaint::where('society_id', $societyId)->where('created_by', $user->id)->where('priority', 'high')->where('status', '!=', 'resolved')->count(),
            ];
        }

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
        if ($user->hasRole('Villa Owner') || $user->hasRole('Apartment Owner')) {
            // Get owned flats
            $ownedFlats = \App\Models\Flat::where('owner_id', $user->id)
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
            
            if ($user->hasRole('Tenant')) {
                // Tenant: get their assigned flat
                $tenant = \App\Models\Tenant::where('user_id', $user->id)->first();
                $flatId = $tenant ? $tenant->flat_id : null;
            } elseif ($user->hasRole('Villa Owner') || $user->hasRole('Apartment Owner')) {
                // Owner: use provided flat_id or get their first property
                if ($request->flat_id) {
                    $flatId = $request->flat_id;
                } else {
                    // Get first owned flat
                    $ownedFlat = \App\Models\Flat::where('owner_id', $user->id)
                                                 ->where('society_id', $societyId)
                                                 ->first();
                    $flatId = $ownedFlat ? $ownedFlat->id : null;
                }
            } else {
                // Admin/other: use provided flat_id if any
                $flatId = $request->flat_id;
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
        return $this->handleFormSubmission(function() use ($request, $complaint) {
            $request->validate([
                'complaint_category_id' => 'required|exists:complaint_categories,id',
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'priority' => 'required|in:low,medium,high,urgent',
                'status' => 'required|in:open,in_progress,resolved,closed',
            ]);

            $oldStatus = $complaint->status;
            $complaint->update($request->all());

            // Dispatch event if status changed
            if ($oldStatus !== $complaint->status) {
                \App\Events\TicketUpdated::dispatch($complaint);
            }

            return $complaint;
        }, 'Complaint updated successfully!', 'complaints.index');
    }

    public function destroy(Complaint $complaint)
    {
        $complaint->delete();

        return redirect()->route('complaints.index')
            ->with('success', 'Complaint deleted successfully!');
    }

    public function addUpdate(Request $request, Complaint $complaint)
    {
        $request->validate([
            'message' => 'required|string',
            'type' => 'required|in:comment,status_change,assignment',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $attachments = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('complaint-updates', 'public');
                $attachments[] = $path;
            }
        }

        $complaint->addUpdate(Auth::user(), $request->message, $request->type, $attachments);

        // Update complaint status if provided
        if ($request->filled('status')) {
            $complaint->update(['status' => $request->status]);
        }

        return back()->with('success', 'Update added successfully!');
    }

    public function assign(Request $request, Complaint $complaint)
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'message' => 'nullable|string',
        ]);

        $user = User::find($request->assigned_to);
        $complaint->assignTo($user, $request->message);

        return back()->with('success', 'Complaint assigned successfully!');
    }
}