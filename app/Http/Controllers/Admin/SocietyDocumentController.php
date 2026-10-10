<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Society;
use App\Models\SocietyDocument;
use App\Models\User;
use App\Services\SocietyDocumentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings -> Rules & Documents. Admin manages its own society's documents;
 * Super Admin manages any society's (choosing the society on upload).
 * Residents and guards read through App\Http\Controllers\SocietyRulesController.
 */
class SocietyDocumentController extends BaseController
{
    public function __construct(private SocietyDocumentService $documents)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $user = auth()->user();

        // One row per document: its newest version. Older versions are on
        // the document's own page.
        $latestIds = SocietyDocument::query()
            ->selectRaw('MAX(id)')
            ->when(!$user->hasRole('Super Admin'), fn ($q) => $q->where('society_id', $user->society_id))
            ->groupBy('series_key');

        $documents = SocietyDocument::query()
            ->whereIn('id', $latestIds)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%' . $request->q . '%'))
            ->with(['society:id,name', 'uploader:id,name'])
            ->withCount('acknowledgements')
            ->orderBy('category')
            ->orderBy('title')
            ->paginate(25)
            ->withQueryString();

        // Which series currently have a published version (it may not be
        // the newest row - a v3 draft can sit on top of a published v2).
        $publishedSeries = SocietyDocument::whereIn('series_key', $documents->pluck('series_key'))
            ->where('status', 'published')
            ->pluck('version', 'series_key');

        return view('admin.society-documents.index', [
            'documents'       => $documents,
            'publishedSeries' => $publishedSeries,
            'categories'      => SocietyDocument::CATEGORIES,
        ]);
    }

    public function create()
    {
        $this->authorizeRole('Super Admin', 'Admin');

        return view('admin.society-documents.create', [
            'categories' => SocietyDocument::CATEGORIES,
            'societies'  => $this->societyOptions(),
            'previous'   => null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $user = auth()->user();

        $validated = $request->validate($this->metadataRules() + [
            'society_id' => [$user->hasRole('Super Admin') ? 'required' : 'nullable', 'integer', 'exists:societies,id'],
            'file'       => SocietyDocumentService::fileRules(),
        ]);
        $validated['society_id'] = $user->hasRole('Super Admin') ? (int) $validated['society_id'] : $user->society_id;
        $validated['visible_to_guards'] = $request->boolean('visible_to_guards');
        $validated['requires_acknowledgement'] = $request->boolean('requires_acknowledgement');

        $document = $this->documents->create($validated, $request->file('file'), $user);

        return redirect()->route('admin.society-documents.show', $document)
            ->with('success', 'Document uploaded as a draft. Publish it when ready.');
    }

    public function show(SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        $societyDocument->load(['society:id,name', 'uploader:id,name']);
        $versions = $societyDocument->versions()->with('uploader:id,name')->withCount('acknowledgements')->get();

        $acknowledgements = $societyDocument->acknowledgements()->with('user:id,name,email')->latest('acknowledged_at')->get();
        $audienceCount = $societyDocument->requires_acknowledgement
            ? User::where('society_id', $societyDocument->society_id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', SocietyDocument::RESIDENT_ROLES))
                ->count()
            : 0;

        return view('admin.society-documents.show', [
            'document'         => $societyDocument,
            'versions'         => $versions,
            'acknowledgements' => $acknowledgements,
            'audienceCount'    => $audienceCount,
            'channels'         => SocietyDocumentService::ANNOUNCE_CHANNELS,
        ]);
    }

    public function edit(SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        return view('admin.society-documents.edit', [
            'document'   => $societyDocument,
            'categories' => SocietyDocument::CATEGORIES,
        ]);
    }

    public function update(Request $request, SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        $validated = $request->validate($this->metadataRules());
        $validated['visible_to_guards'] = $request->boolean('visible_to_guards');
        $validated['requires_acknowledgement'] = $request->boolean('requires_acknowledgement');

        $societyDocument->update($validated);

        return redirect()->route('admin.society-documents.show', $societyDocument)->with('success', 'Details updated.');
    }

    /** Upload form for the next version of an existing document. */
    public function createVersion(SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        return view('admin.society-documents.create', [
            'categories' => SocietyDocument::CATEGORIES,
            'societies'  => collect(),
            'previous'   => $societyDocument,
        ]);
    }

    public function storeVersion(Request $request, SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        $validated = $request->validate($this->metadataRules() + ['file' => SocietyDocumentService::fileRules()]);
        $validated['visible_to_guards'] = $request->boolean('visible_to_guards');
        $validated['requires_acknowledgement'] = $request->boolean('requires_acknowledgement');

        $version = $this->documents->create($validated, $request->file('file'), auth()->user(), $societyDocument);

        return redirect()->route('admin.society-documents.show', $version)
            ->with('success', "Version {$version->version} uploaded as a draft. The current version stays live until you publish this one.");
    }

    public function publish(Request $request, SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        if ($societyDocument->status === 'published') {
            return back()->with('error', 'This version is already published.');
        }

        $request->validate([
            'announce'   => 'nullable|boolean',
            'channels'   => 'nullable|array',
            'channels.*' => ['string', Rule::in(SocietyDocumentService::ANNOUNCE_CHANNELS)],
        ]);

        $channels = $request->boolean('announce') ? (array) $request->input('channels', []) : [];
        $this->documents->publish($societyDocument, $channels);

        $message = 'Published. Residents now see this as the current version.';
        if ($channels !== [] && $societyDocument->fresh()->announced_at) {
            $message .= ' Announcement sent via ' . implode(', ', array_map('ucfirst', $channels)) . '.';
        }

        return back()->with('success', $message);
    }

    public function unpublish(SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        if ($societyDocument->status !== 'published') {
            return back()->with('error', 'Only a published version can be unpublished.');
        }

        $societyDocument->update(['status' => 'unpublished']);

        return back()->with('success', 'Unpublished. Residents and guards can no longer see it.');
    }

    public function archive(SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        $societyDocument->update(['status' => 'archived']);

        return back()->with('success', 'Archived. It stays in the version history for admins.');
    }

    /**
     * Only a draft that was never published can be deleted - anything a
     * resident may have read is history and is archived instead.
     */
    public function destroy(SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        if ($societyDocument->status !== 'draft' || $societyDocument->published_at !== null) {
            return back()->with('error', 'Only an unpublished draft can be deleted. Archive this version instead.');
        }

        $sibling = SocietyDocument::where('series_key', $societyDocument->series_key)
            ->where('id', '!=', $societyDocument->id)
            ->orderByDesc('version_number')
            ->first();

        \Illuminate\Support\Facades\Storage::disk(SocietyDocumentService::DISK)->delete($societyDocument->file_path);
        $societyDocument->delete();

        return $sibling
            ? redirect()->route('admin.society-documents.show', $sibling)->with('success', 'Draft deleted.')
            : redirect()->route('admin.society-documents.index')->with('success', 'Draft deleted.');
    }

    public function file(SocietyDocument $societyDocument)
    {
        $this->authorizeRole('Super Admin', 'Admin');
        $this->authorizeSocietyAccess($societyDocument);

        return $this->documents->fileResponse($societyDocument, request()->boolean('download'));
    }

    private function metadataRules(): array
    {
        return [
            'title'                    => 'required|string|max:150',
            'category'                 => ['required', Rule::in(array_keys(SocietyDocument::CATEGORIES))],
            'version'                  => 'required|string|max:30',
            'description'              => 'nullable|string|max:2000',
            'effective_from'           => 'nullable|date',
            'visible_to_guards'        => 'nullable|boolean',
            'requires_acknowledgement' => 'nullable|boolean',
        ];
    }

    private function societyOptions()
    {
        return auth()->user()->hasRole('Super Admin')
            ? Society::orderBy('name')->pluck('name', 'id')
            : collect();
    }
}
