<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiImageHelper;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NoticeApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notices = Notice::where('society_id', $request->user()->society_id)
            ->published()
            ->with('createdBy')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $notices->map(fn($n) => $this->formatNotice($n)),
            'meta'    => [
                'current_page' => $notices->currentPage(),
                'last_page'    => $notices->lastPage(),
                'total'        => $notices->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $notice = Notice::where('society_id', $request->user()->society_id)
            ->published()
            ->with('createdBy')
            ->findOrFail($id);

        $notice->markAsRead($request->user());

        return response()->json(['success' => true, 'data' => $this->formatNotice($notice)]);
    }

    private function formatNotice(Notice $n): array
    {
        return [
            'id'           => $n->id,
            'title'        => $n->title,
            'content'      => $n->content,
            'status'       => $n->status,
            'type'         => $n->type ?? 'general',
            'is_important' => $n->priority === 'high',
            'image'        => ApiImageHelper::storageUrl($n->image),
            'attachments'  => collect($n->attachments ?? [])->map(fn($a) => ApiImageHelper::storageUrl($a))->filter()->values()->toArray(),
            'posted_by'    => $n->createdBy?->name ?? 'Admin',
            'created_at'   => $n->created_at->toIso8601String(),
        ];
    }
}
