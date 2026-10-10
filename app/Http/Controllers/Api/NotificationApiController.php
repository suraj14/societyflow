<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The header bell for the mobile app: the caller's own notifications only. */
class NotificationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $page->getCollection()->map(fn ($n) => NotificationController::format($n))->values(),
            'meta'    => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'total'        => $page->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['success' => true, 'data' => ['unread_count' => $request->user()->unreadNotifications()->count()]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true, 'data' => ['unread_count' => 0]]);
    }
}
