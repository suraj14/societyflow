<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiImageHelper;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = Event::where('society_id', $request->user()->society_id)
            ->latest('event_date')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $events->map(fn($e) => $this->formatEvent($e)),
            'meta'    => ['current_page' => $events->currentPage(), 'last_page' => $events->lastPage(), 'total' => $events->total()],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $event = Event::where('society_id', $request->user()->society_id)->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->formatEvent($event)]);
    }

    private function formatEvent(Event $e): array
    {
        return [
            'id'          => $e->id,
            'title'       => $e->title,
            'description' => $e->description,
            'event_date'  => $e->event_date?->toDateString(),
            'start_time'  => $e->start_time,
            'end_time'    => $e->end_time,
            'venue'       => $e->venue,
            'status'      => $e->status,
            'image'       => ApiImageHelper::storageUrl($e->image ?? null),
            'created_at'  => $e->created_at->toIso8601String(),
        ];
    }
}
