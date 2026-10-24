<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiImageHelper;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ComplaintApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $complaints = Complaint::where('created_by', $user->id)
            ->with(['category', 'updates.user'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $complaints->map(fn($c) => $this->formatComplaint($c)),
            'meta'    => [
                'current_page' => $complaints->currentPage(),
                'last_page'    => $complaints->lastPage(),
                'total'        => $complaints->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'category'    => 'nullable|string|max:100',
            'image'       => 'nullable|image|max:5120',
        ]);

        $user     = $request->user();
        $resident = $user->resident()->with('flat')->first();

        // Match the free-text category hint to one of the society's categories.
        //
        // This was a LIKE '%hint%' search, so a one-character hint matched an
        // arbitrary category and the complaint was filed under it. Exact match
        // first (case-insensitively), then a prefix match, and otherwise no
        // category - which the column now permits.
        $categoryId = null;
        if (filled($request->category)) {
            $hint = trim($request->category);

            $categoryId = ComplaintCategory::where('society_id', $user->society_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($hint)])
                ->value('id');

            if (!$categoryId && mb_strlen($hint) >= 3) {
                $categoryId = ComplaintCategory::where('society_id', $user->society_id)
                    ->where('name', 'like', $hint . '%')
                    ->value('id');
            }
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('complaints', 'public');
        }

        $complaint = Complaint::create([
            'society_id'             => $user->society_id,
            'flat_id'                => $this->unitFor($user, $resident),
            'created_by'             => $user->id,
            'complaint_category_id'  => $categoryId,
            'title'                  => $request->title,
            'description'            => $request->description,
            'status'                 => 'open',
            'priority'               => 'medium',
            'attachments'            => $imagePath ? [$imagePath] : [],
        ]);

        // The same notifications the web form sends (ComplaintController::store): the office is told a resident raised a complaint. The mobile
        // app used to skip this entirely, so a complaint filed from the phone reached nobody. A notification problem never fails the request.
        try {
            \App\Services\EmailNotificationService::sendComplaintNotification($complaint, 'created');
            \App\Support\BusinessNotifications::complaintCreated($complaint);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Complaint notifications failed after a mobile submission', ['complaint_id' => $complaint->id, 'error' => $e->getMessage()]);
        }

        $complaint->load(['category', 'updates.user']);

        return response()->json([
            'success' => true,
            'message' => 'Complaint submitted successfully',
            'data'    => $this->formatComplaint($complaint),
        ], 201);
    }

    /**
     * The unit a new complaint is filed against: the active tenancy first, then
     * what the user owns, and only then the legacy residency row. That row is
     * never closed when a tenancy moves, so on its own it filed complaints
     * against the unit the tenant had LEFT - and gave a tenant who has no
     * residency row (every tenant created from Admin -> Tenants) no unit at all.
     */
    private function unitFor(\App\Models\User $user, $resident): ?int
    {
        $id = \App\Support\UserFlatResolver::tenantFlatIds($user)[0]
            ?? \App\Support\UserFlatResolver::ownedFlatIds($user)->first()
            ?? $resident?->flat_id;

        return $id ? (int) $id : null;
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::where('id', $id)
            ->where('created_by', $user->id)
            ->with(['category', 'updates.user'])
            ->first();

        if (!$complaint) {
            return response()->json(['success' => false, 'message' => 'Complaint not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatComplaint($complaint)]);
    }

    public function categories(Request $request): JsonResponse
    {
        ComplaintCategory::ensureDefaults((int) $request->user()->society_id);
        $categories = ComplaintCategory::where('society_id', $request->user()->society_id)
            ->orderBy('name')
            ->pluck('name');

        return response()->json(['success' => true, 'data' => $categories]);
    }

    private function formatComplaint(Complaint $c): array
    {
        return [
            'id'          => $c->id,
            'title'       => $c->title,
            'description' => $c->description,
            'status'      => $c->status,
            'priority'    => $c->priority,
            'image_path'  => $c->attachments ? ApiImageHelper::storageUrl($c->attachments[0] ?? null) : null,
            'assigned_to' => $c->assignedTo?->name,
            'category'    => $c->category ? ['name' => $c->category->name] : null,
            'created_at'  => $c->created_at->toIso8601String(),
            'updated_at'  => $c->updated_at->toIso8601String(),
            'updates'     => $c->updates->map(fn($u) => [
                'id'         => $u->id,
                'message'    => $u->message,
                'updated_by' => $u->user?->name,
                'created_at' => $u->created_at->toIso8601String(),
            ])->toArray(),
        ];
    }
}
