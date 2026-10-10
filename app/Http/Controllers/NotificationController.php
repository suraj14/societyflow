<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The header bell. Every query goes through $request->user()->notifications(),
 * so a user can only ever see or mark their own entries - another user's
 * notification id is simply not found.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items'        => $user->notifications()->latest()->limit(8)->get()->map(fn ($n) => self::format($n))->values(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'unread_count' => $request->user()->unreadNotifications()->count()]);
        }

        return redirect($this->safeUrl($notification->data['url'] ?? null) ?? route('notifications.index'));
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'unread_count' => 0])
            : back()->with('success', 'All notifications marked as read.');
    }

    public static function format(DatabaseNotification $n): array
    {
        return [
            'id'         => $n->id,
            'category'   => $n->data['category'] ?? 'general',
            'title'      => $n->data['title'] ?? 'Notification',
            'body'       => $n->data['body'] ?? '',
            'url'        => $n->data['url'] ?? null,
            'icon'       => $n->data['icon'] ?? 'fa-bell',
            'read'       => $n->read_at !== null,
            'time_ago'   => $n->created_at?->diffForHumans(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }

    /** Only follow links back into this app. */
    private function safeUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return $host === null || $host === request()->getHost() ? $url : null;
    }
}
