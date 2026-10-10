<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocietyDocument;
use App\Services\SocietyDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Society Rules & Documents for the mobile app. Read-only plus
 * acknowledgement; management stays on the web. Same visibility rule as
 * the web reader (SocietyDocument::visibleTo), and the PDF is streamed
 * through `/file` with the bearer token - never a public storage URL.
 */
class SocietyDocumentApiController extends Controller
{
    public function __construct(private SocietyDocumentService $documents)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureReader($user);

        $docs = $this->readable($user)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->orderBy('category')
            ->orderBy('title')
            ->paginate(50);

        $acknowledged = $user->hasAnyRole(SocietyDocument::RESIDENT_ROLES) || $user->hasRole('Guard')
            ? \App\Models\SocietyDocumentAcknowledgement::where('user_id', $user->id)
                ->whereIn('society_document_id', $docs->pluck('id'))
                ->pluck('society_document_id')->all()
            : [];

        return response()->json([
            'success' => true,
            'data'    => $docs->getCollection()->map(fn ($d) => $this->format($d, in_array($d->id, $acknowledged, true)))->values(),
            'meta'    => [
                'current_page' => $docs->currentPage(),
                'last_page'    => $docs->lastPage(),
                'total'        => $docs->total(),
                'categories'   => SocietyDocument::CATEGORIES,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureReader($user);
        $doc = $this->readable($user)->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->format($doc, $doc->acknowledgedBy($user))]);
    }

    public function file(Request $request, int $id)
    {
        $user = $request->user();
        $this->ensureReader($user);

        return $this->documents->fileResponse($this->readable($user)->findOrFail($id), $request->boolean('download'));
    }

    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureReader($user);
        $doc = $this->readable($user)->findOrFail($id);

        if (!$doc->requires_acknowledgement) {
            return response()->json(['success' => false, 'message' => 'This document does not ask for an acknowledgement.'], 422);
        }

        $doc->acknowledgements()->firstOrCreate(
            ['user_id' => $user->id],
            ['version' => $doc->version, 'acknowledged_at' => now()]
        );

        return response()->json(['success' => true, 'data' => $this->format($doc, true), 'message' => 'Acknowledgement recorded.']);
    }

    private function readable($user)
    {
        return SocietyDocument::readableBy($user);
    }

    private function ensureReader($user): void
    {
        if (!SocietyDocument::isReader($user)) {
            abort(403, 'Society documents are available to residents and guards.');
        }
    }

    private function format(SocietyDocument $d, bool $acknowledged): array
    {
        return [
            'id'                       => $d->id,
            'title'                    => $d->title,
            'category'                 => $d->category,
            'category_label'           => $d->category_label,
            'description'              => $d->description ?? '',
            'version'                  => $d->version,
            'effective_from'           => $d->effective_from?->toDateString(),
            'published_at'             => $d->published_at?->toIso8601String(),
            'file_size'                => $d->file_size,
            'requires_acknowledgement' => $d->requires_acknowledgement,
            'acknowledged'             => $acknowledged,
            'file_url'                 => "/society-documents/{$d->id}/file",
        ];
    }
}
