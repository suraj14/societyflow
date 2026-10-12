<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventApiController extends Controller
{
    /**
     * Same rule as the web EventController: Events is a module an admin switches on in Settings -> Features
     * (default off). The API served events regardless, so the phone showed a module the web answers 403 for.
     */
    private function moduleOff(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if ($user->hasRole('Super Admin') || \App\Models\SystemSetting::get('features', 'enable_events', false, $user->society_id)) {
            return null;
        }

        return response()->json(['success' => false, 'message' => 'Events module is not enabled.'], 403);
    }
    public function index(Request $request): JsonResponse
    {
        if ($off = $this->moduleOff($request)) {
            return $off;
        }

        $user = $request->user();

        // Cancelled events were served to residents alongside live ones,
        // because the listing had no status filter at all.
        $events = Event::where('society_id', $user->society_id)
            ->where('status', '!=', 'cancelled')
            ->latest('start_date')
            ->paginate(20);

        // Events carry per-role and per-user visibility (is_role_based,
        // visible_roles, visible_users) which the web controller honours via
        // Event::isVisibleToUser but the API ignored entirely - so an event
        // restricted to committee members was listed for every resident.
        // Filtering after pagination keeps the page size honest for the
        // caller; the alternative is duplicating the rule in SQL across two
        // JSON columns.
        $visible = $events->getCollection()
            ->filter(fn ($e) => $e->isVisibleToUser($user))
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $visible->map(fn($e) => $this->formatEvent($e)),
            'meta'    => ['current_page' => $events->currentPage(), 'last_page' => $events->lastPage(), 'total' => $events->total()],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($off = $this->moduleOff($request)) {
            return $off;
        }

        $user = $request->user();

        $event = Event::where('society_id', $user->society_id)->findOrFail($id);

        if (!$event->isVisibleToUser($user)) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatEvent($event)]);
    }

    /**
     * Map an event onto the JSON shape the mobile app parses.
     *
     * Every field here previously read an attribute that does not exist on
     * the events table - $e->title, ->event_date, ->start_time, ->end_time,
     * ->venue - so the app received a null title, date and venue for every
     * event and the Events screen rendered empty rows. The real columns are
     * event_name, location, start_date and end_date.
     *
     * The JSON keys are deliberately unchanged: they are the contract the
     * installed app parses, and renaming them would need a coordinated
     * release. The mapping is corrected on this side instead.
     */
    private function formatEvent(Event $e): array
    {
        return [
            'id'          => $e->id,
            'title'       => $e->event_name,
            'description' => $e->description,
            'event_date'  => $e->start_date?->toDateString(),
            'start_time'  => $e->start_date?->format('H:i'),
            'end_time'    => $e->end_date?->format('H:i'),
            'venue'       => $e->location,
            'status'      => $this->displayStatus($e),
            // events has no image column; the key is kept so the app's
            // parser keeps working.
            'image'       => null,
            'created_at'  => $e->created_at->toIso8601String(),
        ];
    }

    /**
     * Translate the stored status into the vocabulary the app understands.
     *
     * The column stores pending/completed/cancelled, but the app renders
     * labels for upcoming/ongoing/completed and falls through to the raw
     * string otherwise - so residents saw "pending" on every future event.
     * Derived from the dates, which is what the label is meant to convey.
     */
    private function displayStatus(Event $e): string
    {
        if ($e->status === 'cancelled') {
            return 'cancelled';
        }

        if ($e->status === 'completed' || ($e->end_date && $e->end_date->isPast())) {
            return 'completed';
        }

        if ($e->start_date && $e->start_date->isPast()) {
            return 'ongoing';
        }

        return 'upcoming';
    }
}
