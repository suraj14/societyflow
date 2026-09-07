<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiImageHelper;
use App\Models\Visitor;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VisitorApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasRole(['Staff', 'Guard', 'Admin', 'Super Admin']);

        $query = Visitor::with(['flat.building'])->latest();

        if ($isStaff) {
            // Staff/Guard/Admin sees ALL visitors for the society
            $query->where('society_id', $user->society_id);
        } else {
            // Residents/Owners see visitors for their own unit(s)
            $flatIds = collect();

            // Via residents table
            $flatIds = $flatIds->merge($user->flats()->pluck('flats.id'));

            // Via owner record (Villa Owner / Apartment Owner)
            if ($user->owner_id) {
                $owner = \App\Models\Owner::find($user->owner_id);
                if ($owner) {
                    if ($owner->villa_area_id && $owner->villa_no) {
                        $flatIds = $flatIds->merge(
                            \App\Models\Flat::where('villa_area_id', $owner->villa_area_id)
                                ->where('flat_number', $owner->villa_no)->pluck('id')
                        );
                    }
                    if ($owner->building_id && $owner->flat_no) {
                        $flatIds = $flatIds->merge(
                            \App\Models\Flat::where('building_id', $owner->building_id)
                                ->where('flat_number', $owner->flat_no)->pluck('id')
                        );
                    }
                }
            }

            $flatIds = $flatIds->unique()->values()->toArray();

            $query->where(function ($q) use ($user, $flatIds) {
                $q->where('host_user_id', $user->id);
                if (!empty($flatIds)) {
                    $q->orWhereIn('flat_id', $flatIds);
                }
            })->where('society_id', $user->society_id);
        }

        $visitors = $query->paginate(100);

        return response()->json([
            'success' => true,
            'data'    => $visitors->map(fn($v) => $this->formatVisitor($v))->values()->toArray(),
            'meta'    => [
                'current_page' => $visitors->currentPage(),
                'last_page'    => $visitors->lastPage(),
                'total'        => $visitors->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'visitor_name'        => 'nullable|string|max:255',
            'visitor_phone'       => 'required|string|max:20',
            'visitor_type'        => 'required|in:guest,delivery,cab,service,maid,driver,cook,electrician,plumber,emergency,other',
            'purpose'             => 'nullable|string|max:500',
            'expected_entry_time' => 'nullable|date',
            'expected_exit_time'  => 'nullable|date',
            'vehicle_number'      => 'nullable|string|max:20',
            'flat_id'             => 'nullable|exists:flats,id',
        ]);

        $user    = $request->user();
        $isStaff = $user->hasRole(['Staff', 'Guard', 'Admin', 'Super Admin']);
        $flatId  = $request->flat_id;

        // For residents, use their own flat
        if (!$isStaff && !$flatId) {
            $resident = $user->resident()->with('flat')->first();
            if (!$resident) {
                return response()->json(['success' => false, 'message' => 'No flat assigned to your account.'], 422);
            }
            $flatId = $resident->flat_id;
        }

        // Find the resident (host) for this flat — notify them
        $hostUserId = $user->id;
        if ($isStaff && $flatId) {
            $flat       = \App\Models\Flat::with('residents.user')->find($flatId);
            $hostResident = $flat?->residents()->where('status', 'active')->first();
            if ($hostResident?->user_id) {
                $hostUserId = $hostResident->user_id;
            }
        }

        // Guard/Staff registers as PENDING — resident must approve
        // Residents/Owners registering their own visitor → auto-allowed
        $approvalStatus = $isStaff ? 'pending' : 'allowed';

        $visitor = Visitor::create([
            'society_id'          => $user->society_id,
            'flat_id'             => $flatId,
            'host_user_id'        => $hostUserId,
            'visitor_name'        => $request->visitor_name ?? 'Visitor',
            'visitor_phone'       => $request->visitor_phone,
            'visitor_type'        => $request->visitor_type,
            'purpose'             => $request->purpose,
            'vehicle_number'      => $request->vehicle_number,
            'expected_entry_time' => $request->expected_entry_time ?? now(),
            'expected_exit_time'  => $request->expected_exit_time,
            'approval_status'     => $approvalStatus,
            'entry_status'        => 'pending',
            'security_notes'      => $isStaff ? 'Registered by guard: ' . $user->name : null,
        ]);

        // Notify the resident if registered by guard
        if ($isStaff && $hostUserId !== $user->id) {
            $this->notifyResident($visitor, $hostUserId);
        }

        $visitor->load('flat.building');

        return response()->json([
            'success' => true,
            'message' => $isStaff
                ? 'Visitor registered. Resident has been notified for approval.'
                : 'Visitor registered successfully',
            'data'    => $this->formatVisitor($visitor),
        ], 201);
    }

    /**
     * Send push notification to resident about pending visitor
     */
    private function notifyResident(Visitor $visitor, int $residentUserId): void
    {
        try {
            $residentUser = User::find($residentUserId);
            if (!$residentUser) return;

            $flatLabel = $visitor->flat
                ? (($visitor->flat->building?->name ?? '') . ' ' . $visitor->flat->flat_number)
                : 'your unit';

            $pushService = app(PushNotificationService::class);
            $pushService->sendToUser(
                $residentUser,
                'visitor_arrived',
                [
                    'visitor_name'  => $visitor->visitor_name,
                    'visitor_phone' => $visitor->visitor_phone,
                    'visitor_type'  => ucfirst($visitor->visitor_type),
                    'flat_number'   => $flatLabel,
                ],
                [
                    'visitor_id' => $visitor->id,
                    'action'     => 'visitor_approval',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Visitor resident notification failed: ' . $e->getMessage());
        }
    }

    public function checkIn(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $visitor = Visitor::where('society_id', $user->society_id)->findOrFail($id);

        if (!$visitor->isAllowed()) {
            return response()->json(['success' => false, 'message' => 'Visitor must be approved before check-in.'], 422);
        }
        if ($visitor->hasEntered()) {
            return response()->json(['success' => false, 'message' => 'Visitor already checked in.'], 422);
        }

        $visitor->markEntry($user, $request->notes);

        return response()->json(['success' => true, 'message' => 'Visitor checked in successfully', 'data' => $this->formatVisitor($visitor->fresh('flat.building'))]);
    }

    public function checkOut(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $visitor = Visitor::where('society_id', $user->society_id)->findOrFail($id);

        if (!$visitor->hasEntered()) {
            return response()->json(['success' => false, 'message' => 'Visitor has not checked in yet.'], 422);
        }
        if ($visitor->hasExited()) {
            return response()->json(['success' => false, 'message' => 'Visitor already checked out.'], 422);
        }

        $visitor->markExit($user, $request->notes);

        return response()->json(['success' => true, 'message' => 'Visitor checked out successfully', 'data' => $this->formatVisitor($visitor->fresh('flat.building'))]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $societyId = $user->society_id;
        $today = now()->toDateString();
        $isStaff = $user->hasRole(['Staff', 'Guard', 'Admin', 'Super Admin']);

        if ($isStaff) {
            $base = Visitor::where('society_id', $societyId);
        } else {
            // Owner/resident — scope to their flat
            $flatIds = collect();
            $flatIds = $flatIds->merge($user->flats()->pluck('flats.id'));
            if ($user->owner_id) {
                $owner = \App\Models\Owner::find($user->owner_id);
                if ($owner) {
                    if ($owner->villa_area_id && $owner->villa_no) {
                        $flatIds = $flatIds->merge(
                            \App\Models\Flat::where('villa_area_id', $owner->villa_area_id)
                                ->where('flat_number', $owner->villa_no)->pluck('id')
                        );
                    }
                    if ($owner->building_id && $owner->flat_no) {
                        $flatIds = $flatIds->merge(
                            \App\Models\Flat::where('building_id', $owner->building_id)
                                ->where('flat_number', $owner->flat_no)->pluck('id')
                        );
                    }
                }
            }
            $flatIds = $flatIds->unique()->values()->toArray();
            $base = Visitor::where('society_id', $societyId)->where(function ($q) use ($user, $flatIds) {
                $q->where('host_user_id', $user->id);
                if (!empty($flatIds)) $q->orWhereIn('flat_id', $flatIds);
            });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'today_total' => (clone $base)->whereDate('expected_entry_time', $today)->count(),
                'pending'     => (clone $base)->where('approval_status', 'pending')->count(),
                'checked_in'  => (clone $base)->where('entry_status', 'entered')->count(),
                'checked_out' => (clone $base)->where('entry_status', 'exited')->whereDate('actual_exit_time', $today)->count(),
            ],
        ]);
    }

    public function flats(Request $request): JsonResponse
    {
        $flats = \App\Models\Flat::where('society_id', $request->user()->society_id)
            ->with(['building', 'villaArea'])
            ->get()
            ->map(fn($f) => [
                'id'          => $f->id,
                'flat_number' => $f->flat_number,
                'building'    => $f->building?->name ?? $f->villaArea?->name ?? '',
                'display'     => ($f->building?->name ?? $f->villaArea?->name ?? '') . ' - ' . $f->flat_number,
            ]);

        return response()->json(['success' => true, 'data' => $flats]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasRole(['Staff', 'Guard', 'Admin', 'Super Admin']);

        $query = Visitor::where('society_id', $user->society_id);
        if (!$isStaff) {
            $query->where('host_user_id', $user->id);
        }

        $visitor = $query->findOrFail($id);
        $visitor->allow($user);

        return response()->json(['success' => true, 'message' => 'Visitor allowed', 'data' => $this->formatVisitor($visitor->fresh('flat.building'))]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $user = $request->user();
        $isStaff = $user->hasRole(['Staff', 'Guard', 'Admin', 'Super Admin']);

        $query = Visitor::where('society_id', $user->society_id);
        if (!$isStaff) {
            $query->where('host_user_id', $user->id);
        }

        $visitor = $query->findOrFail($id);
        $visitor->deny($user, $request->reason ?? 'Denied');

        return response()->json(['success' => true, 'message' => 'Visitor denied', 'data' => $this->formatVisitor($visitor->fresh('flat.building'))]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasRole(['Staff', 'Guard', 'Admin', 'Super Admin']);

        $query = Visitor::with(['flat.building'])->where('society_id', $user->society_id);
        if (!$isStaff) {
            $query->where('host_user_id', $user->id);
        }

        $visitor = $query->findOrFail($id);
        return response()->json(['success' => true, 'data' => $this->formatVisitor($visitor)]);
    }

    private function formatVisitor(Visitor $v): array
    {
        return [
            'id'                   => $v->id,
            'visitor_name'         => $v->visitor_name,
            'visitor_phone'        => $v->visitor_phone,
            'visitor_type'         => $v->visitor_type,
            'purpose'              => $v->purpose,
            'vehicle_number'       => $v->vehicle_number,
            'approval_status'      => $v->approval_status,
            'entry_status'         => $v->entry_status,
            'expected_entry_time'  => $v->expected_entry_time?->toIso8601String(),
            'expected_exit_time'   => $v->expected_exit_time?->toIso8601String(),
            'actual_entry_time'    => $v->actual_entry_time?->toIso8601String(),
            'actual_exit_time'     => $v->actual_exit_time?->toIso8601String(),
            'photo'                => ApiImageHelper::storageUrl($v->photo),
            'flat'                 => $v->flat ? [
                'flat_number' => $v->flat->flat_number,
                'building'    => $v->flat->building ? ['name' => $v->flat->building->name] : null,
            ] : null,
            'created_at'           => $v->created_at->toIso8601String(),
        ];
    }
}
