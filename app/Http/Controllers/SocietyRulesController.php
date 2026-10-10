<?php

namespace App\Http\Controllers;

use App\Models\SocietyDocument;
use App\Services\SocietyDocumentService;
use App\Support\SocietyInfo;
use Illuminate\Http\Request;

/**
 * Read side of Society Rules & Documents for residents (/society-rules)
 * and guards (/guard/rules). Every lookup goes through
 * SocietyDocument::visibleTo(), so a draft, an archived version, another
 * society's document or (for a guard) a resident-only document is a 404 -
 * indistinguishable from a document that does not exist.
 *
 * Admins manage documents at /admin/society-documents; if one opens these
 * pages they get the reader view of their own society's published set.
 */
class SocietyRulesController extends Controller
{
    public function __construct(private SocietyDocumentService $documents)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensureReader($user);

        $documents = $this->readable($user)
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->where('title', 'like', '%' . $request->q . '%')
                  ->orWhere('description', 'like', '%' . $request->q . '%');
            }))
            ->orderBy('title')
            ->get(['id', 'society_id', 'title', 'category', 'description', 'version', 'effective_from',
                   'published_at', 'requires_acknowledgement', 'file_size', 'visible_to_guards']);

        $acknowledged = $user->id
            ? \App\Models\SocietyDocumentAcknowledgement::where('user_id', $user->id)
                ->whereIn('society_document_id', $documents->pluck('id'))
                ->pluck('society_document_id')->all()
            : [];

        $grouped = collect(SocietyDocument::CATEGORIES)
            ->map(fn ($label, $key) => ['label' => $label, 'documents' => $documents->where('category', $key)->values()])
            ->filter(fn ($g) => $g['documents']->isNotEmpty());

        // Society Info: the same page now also carries the society's details
        // and its committee. Built by the one shared App\Support\SocietyInfo
        // so the app's /society/info can never show something different.
        $society = $user->society;

        return view($this->viewPrefix($request) . 'index', [
            'grouped'      => $grouped,
            'total'        => $documents->count(),
            'acknowledged' => $acknowledged,
            'routePrefix'  => $this->routePrefix($request),
            'details'      => $society ? SocietyInfo::details($society) : null,
            'committee'    => $society ? SocietyInfo::committee($society) : [],
            'tab'          => in_array($request->query('tab'), ['details', 'committee', 'documents'], true)
                                ? $request->query('tab')
                                // A search in the Documents list stays on Documents.
                                : ($request->filled('q') ? 'documents' : 'details'),
            'isManager'    => SocietyDocument::isManager($user),
        ]);
    }

    public function show(Request $request, int $document)
    {
        $user = $request->user();
        $this->ensureReader($user);
        $doc = $this->readable($user)->findOrFail($document);

        return view($this->viewPrefix($request) . 'show', [
            'document'     => $doc,
            'acknowledged' => $doc->acknowledgedBy($user),
            'routePrefix'  => $this->routePrefix($request),
        ]);
    }

    public function file(Request $request, int $document)
    {
        $user = $request->user();
        $this->ensureReader($user);

        return $this->documents->fileResponse(
            $this->readable($user)->findOrFail($document),
            $request->boolean('download')
        );
    }

    public function acknowledge(Request $request, int $document)
    {
        $user = $request->user();
        $this->ensureReader($user);
        $doc = $this->readable($user)->findOrFail($document);

        if (!$doc->requires_acknowledgement) {
            return back()->with('error', 'This document does not ask for an acknowledgement.');
        }

        $doc->acknowledgements()->firstOrCreate(
            ['user_id' => $user->id],
            ['version' => $doc->version, 'acknowledged_at' => now()]
        );

        return back()->with('success', 'Thank you - your acknowledgement has been recorded.');
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

    private function routePrefix(Request $request): string
    {
        return $request->routeIs('guard.*') ? 'guard.rules' : 'society-rules';
    }

    private function viewPrefix(Request $request): string
    {
        return $request->routeIs('guard.*') ? 'guard.rules.' : 'society-rules.';
    }
}
