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

        // Find or match category
        $categoryId = null;
        if ($request->category) {
            $cat = ComplaintCategory::where('society_id', $user->society_id)
                ->where('name', 'like', '%' . $request->category . '%')
                ->first();
            $categoryId = $cat?->id;
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('complaints', 'public');
        }

        $complaint = Complaint::create([
            'society_id'             => $user->society_id,
            'flat_id'                => $resident?->flat_id,
            'created_by'             => $user->id,
            'complaint_category_id'  => $categoryId,
            'title'                  => $request->title,
            'description'            => $request->description,
            'status'                 => 'open',
            'priority'               => 'medium',
            'attachments'            => $imagePath ? [$imagePath] : [],
        ]);

        $complaint->load(['category', 'updates.user']);

        return response()->json([
            'success' => true,
            'message' => 'Complaint submitted successfully',
            'data'    => $this->formatComplaint($complaint),
        ], 201);
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
