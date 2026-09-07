<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Events\EventCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Traits\HandlesFormSubmissions;

class EventController extends BaseController
{
    use HandlesFormSubmissions;

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display events in table view
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // Check if events module is enabled
        $eventsEnabled = \App\Models\SystemSetting::get('features', 'enable_events', false, $societyId);
        if (!$eventsEnabled && !$user->hasRole('Super Admin')) {
            abort(403, 'Events module is not enabled.');
        }

        // Check access - All roles can view events
        if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Manager', 'Owner', 'Tenant', 'Guard', 'Staff', 'Accountant', 'Villa Owner', 'Apartment Owner'])) {
            abort(403, 'Unauthorized access to events.');
        }

        // Auto-update expired events before displaying
        Event::updateExpiredEvents();

        $query = Event::where('society_id', $societyId);

        // Apply visibility filter for non-admin users
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            $query->visibleToUser($user);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('event_name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $events = $query->orderBy('start_date', 'asc')->paginate(15);

        return view('events.index', compact('events'));
    }

    /**
     * Display calendar view
     */
    public function calendar(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // Check if events module is enabled
        $eventsEnabled = \App\Models\SystemSetting::get('features', 'enable_events', false, $societyId);
        if (!$eventsEnabled && !$user->hasRole('Super Admin')) {
            abort(403, 'Events module is not enabled.');
        }

        // Auto-update expired events before displaying
        Event::updateExpiredEvents();

        $query = Event::where('society_id', $societyId);

        // Apply visibility filter for non-admin users
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            $query->visibleToUser($user);
        }

        $events = $query->get();

        return view('events.calendar', compact('events'));
    }

    /**
     * Show create event form
     */
    public function create()
    {
        $user = auth()->user();

        // Only Admin and Manager can create events
        if (!$user->hasRole(['Admin', 'Manager'])) {
            abort(403, 'You do not have permission to create events.');
        }

        $roles = \Spatie\Permission\Models\Role::all();
        $users = \App\Models\User::where('society_id', $user->society_id)->get();

        return view('events.create', compact('roles', 'users'));
    }

    /**
     * Store event
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        // Only Admin and Manager can create events
        if (!$user->hasRole(['Admin', 'Manager'])) {
            abort(403, 'You do not have permission to create events.');
        }

        return $this->handleFormSubmission(function() use ($request, $user) {
            $validated = $request->validate([
                'event_name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'description' => 'required|string',
                'start_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_date' => 'required|date|after_or_equal:start_date',
                'end_time' => 'required|date_format:H:i',
                'status' => 'required|in:pending,completed,cancelled',
                'is_role_based' => 'required|boolean',
                'visible_roles' => 'nullable|array',
                'visible_users' => 'nullable|array',
            ]);

            // Combine date and time
            $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $validated['start_date'] . ' ' . $validated['start_time']);
            $endDateTime = Carbon::createFromFormat('Y-m-d H:i', $validated['end_date'] . ' ' . $validated['end_time']);

            $event = Event::create([
                'society_id' => $user->society_id,
                'created_by' => $user->id,
                'event_name' => $validated['event_name'],
                'location' => $validated['location'],
                'description' => $validated['description'],
                'start_date' => $startDateTime,
                'end_date' => $endDateTime,
                'status' => $validated['status'],
                'is_role_based' => $validated['is_role_based'],
                'visible_roles' => $validated['is_role_based'] ? $validated['visible_roles'] : null,
                'visible_users' => !$validated['is_role_based'] ? $validated['visible_users'] : null,
            ]);

            Log::info('Event created', ['event_id' => $event->id, 'user_id' => $user->id]);

            // Dispatch event for email notification
            EventCreated::dispatch($event);

            return $event;
        }, 'Event created successfully.', 'events.index');
    }

    /**
     * Show event details
     */
    public function show(Event $event)
    {
        $user = auth()->user();

        // Check society access
        if ($event->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }

        // Check visibility
        if (!$event->isVisibleToUser($user) && !$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'You do not have permission to view this event.');
        }

        // Auto-update this event's status if needed
        $event->autoUpdateStatus();
        $event->refresh(); // Refresh to get updated status

        return view('events.show', compact('event'));
    }

    /**
     * Show edit form
     */
    public function edit(Event $event)
    {
        $user = auth()->user();

        // Check society access
        if ($event->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }

        // Only Admin can edit, Manager can edit own events
        if ($user->hasRole('Manager') && $event->created_by !== $user->id) {
            abort(403, 'You can only edit your own events.');
        }

        if (!$user->hasRole(['Admin', 'Manager'])) {
            abort(403, 'You do not have permission to edit events.');
        }

        $roles = \Spatie\Permission\Models\Role::all();
        $users = \App\Models\User::where('society_id', $user->society_id)->get();

        return view('events.edit', compact('event', 'roles', 'users'));
    }

    /**
     * Update event
     */
    public function update(Request $request, Event $event)
    {
        $user = auth()->user();

        // Check society access
        if ($event->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }

        // Only Admin can update, Manager can update own events
        if ($user->hasRole('Manager') && $event->created_by !== $user->id) {
            abort(403, 'You can only edit your own events.');
        }

        if (!$user->hasRole(['Admin', 'Manager'])) {
            abort(403, 'You do not have permission to update events.');
        }

        return $this->handleFormSubmission(function() use ($request, $event, $user) {
            $validated = $request->validate([
                'event_name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'description' => 'required|string',
                'start_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_date' => 'required|date|after_or_equal:start_date',
                'end_time' => 'required|date_format:H:i',
                'status' => 'required|in:pending,completed,cancelled',
                'is_role_based' => 'required|boolean',
                'visible_roles' => 'nullable|array',
                'visible_users' => 'nullable|array',
            ]);

            // Combine date and time
            $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $validated['start_date'] . ' ' . $validated['start_time']);
            $endDateTime = Carbon::createFromFormat('Y-m-d H:i', $validated['end_date'] . ' ' . $validated['end_time']);

            // Validate status based on event date
            $now = Carbon::now();
            if ($validated['status'] === 'completed' && $endDateTime > $now) {
                throw new \Exception('Event cannot be marked as completed until after the event end date (' . $endDateTime->format('M d, Y H:i') . ').');
            }

            $event->update([
                'event_name' => $validated['event_name'],
                'location' => $validated['location'],
                'description' => $validated['description'],
                'start_date' => $startDateTime,
                'end_date' => $endDateTime,
                'status' => $validated['status'],
                'is_role_based' => $validated['is_role_based'],
                'visible_roles' => $validated['is_role_based'] ? $validated['visible_roles'] : null,
                'visible_users' => !$validated['is_role_based'] ? $validated['visible_users'] : null,
            ]);

            Log::info('Event updated', ['event_id' => $event->id, 'user_id' => $user->id]);

            // Send email notification
            \App\Services\EmailNotificationService::sendEventNotification($event, 'updated');

            return $event;
        }, 'Event updated successfully.', 'events.index');
    }

    /**
     * Delete event
     */
    public function destroy(Event $event)
    {
        $user = auth()->user();

        // Check society access
        if ($event->society_id !== $user->society_id) {
            abort(403, 'Unauthorized access.');
        }

        // Only Admin can delete
        if (!$user->hasRole('Admin')) {
            abort(403, 'You do not have permission to delete events.');
        }

        Log::info('Event deleted', ['event_id' => $event->id, 'user_id' => $user->id]);

        $event->delete();

        return redirect()->route('events.index')->with('success', 'Event deleted successfully.');
    }

    /**
     * Get events for calendar API
     */
    public function getCalendarEvents(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // Auto-update expired events before returning data
        Event::updateExpiredEvents();

        $query = Event::where('society_id', $societyId);

        // Apply visibility filter for non-admin users
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            $query->visibleToUser($user);
        }

        $events = $query->get();

        return response()->json($events->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->event_name,
                'start' => $event->start_date->toIso8601String(),
                'end' => $event->end_date->toIso8601String(),
                'location' => $event->location,
                'status' => $event->status,
            ];
        }));
    }
}
