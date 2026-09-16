<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The gate, as a guard sees it.
 *
 * A guard is not a junior administrator. They stand at a gate holding a
 * cheap phone, often one-handed, and need four things: who is waiting, who
 * is expected, let someone in, let someone out. Everything else on the admin
 * dashboard - revenue, dues, occupancy - is noise they cannot act on and
 * should not see (brief §8, §18).
 *
 * This controller deliberately owns no new visitor logic. Approval, entry
 * and exit semantics remain the visitor module's; a guard just gets a
 * surface shaped for a gate instead of a desk.
 */
class GuardController extends BaseController
{
    /**
     * Every query here is pinned to the guard's own society. A guard has no
     * legitimate reason to read another society's gate, so unlike the admin
     * controllers there is no Super Admin widening.
     */
    private function societyId(): int
    {
        $societyId = auth()->user()->society_id;

        abort_if($societyId === null, 403, 'No society assigned to this account.');

        return (int) $societyId;
    }

    /**
     * Today's four gate counters, one grouped query rather than four counts.
     * Shared by the dashboard render and the check-in/check-out JSON
     * response, so a fetch()-based update and a full page load can never
     * disagree about the numbers.
     */
    private function todaysStats(int $societyId): array
    {
        $counts = Visitor::where('society_id', $societyId)
            ->whereDate('expected_entry_time', today())
            ->selectRaw('approval_status, entry_status, COUNT(*) as total')
            ->groupBy('approval_status', 'entry_status')
            ->get();

        $stats = ['waiting' => 0, 'expected' => 0, 'inside' => 0, 'departed' => 0];

        foreach ($counts as $row) {
            if ($row->entry_status === 'entered') {
                $stats['inside'] += $row->total;
            } elseif ($row->entry_status === 'exited') {
                $stats['departed'] += $row->total;
            } elseif ($row->approval_status === 'allowed') {
                $stats['expected'] += $row->total;
            } else {
                $stats['waiting'] += $row->total;
            }
        }

        return $stats;
    }

    /** Today's gate. */
    public function dashboard()
    {
        $societyId = $this->societyId();
        $society   = auth()->user()->society;
        $stats     = $this->todaysStats($societyId);

        $relations = ['flat.building', 'flat.villaArea'];

        // Waiting on a resident's decision - the guard's primary queue.
        $waiting = Visitor::where('society_id', $societyId)
            ->where('approval_status', 'pending')
            ->where('entry_status', 'pending')
            ->with($relations)
            ->latest('expected_entry_time')
            ->limit(25)
            ->get();

        // Approved and not yet arrived.
        $expected = Visitor::where('society_id', $societyId)
            ->where('approval_status', 'allowed')
            ->where('entry_status', 'pending')
            ->whereDate('expected_entry_time', today())
            ->with($relations)
            ->orderBy('expected_entry_time')
            ->limit(25)
            ->get();

        // On the premises - the list that matters during an emergency.
        $inside = Visitor::where('society_id', $societyId)
            ->where('entry_status', 'entered')
            ->with($relations)
            ->latest('actual_entry_time')
            ->limit(50)
            ->get();

        return view('guard.dashboard', compact('stats', 'waiting', 'expected', 'inside', 'society'));
    }

    /** Quick visitor registration - the short form, not the admin one. */
    public function createVisitor()
    {
        $society = auth()->user()->society;

        return view('guard.visitor-create', compact('society'));
    }

    public function storeVisitor(Request $request)
    {
        $societyId = $this->societyId();

        $validated = $request->validate([
            'flat_id'        => 'required|exists:flats,id',
            'visitor_name'   => 'required|string|max:255',
            'visitor_phone'  => 'required|string|max:15',
            'visitor_type'   => 'required|in:guest,delivery,cab,service,other',
            'purpose'        => 'nullable|string|max:500',
            'vehicle_number' => 'nullable|string|max:20',
            'expected_count' => 'nullable|integer|min:1|max:50',
        ]);

        // Never trust the posted flat: it must live in this guard's society.
        $flat = Flat::where('id', $validated['flat_id'])
            ->where('society_id', $societyId)
            ->first();

        abort_if($flat === null, 403, 'That property is not in your society.');

        $visitor = Visitor::create([
            'society_id'          => $societyId,
            'flat_id'             => $flat->id,
            'host_user_id'        => auth()->id(),
            'visitor_name'        => $validated['visitor_name'],
            'visitor_phone'       => $validated['visitor_phone'],
            'visitor_type'        => $validated['visitor_type'],
            'purpose'             => $validated['purpose'] ?? null,
            'vehicle_number'      => $validated['vehicle_number'] ?? null,
            'expected_count'      => $validated['expected_count'] ?? 1,
            'expected_entry_time' => now(),
            'approval_status'     => 'pending',
            'entry_status'        => 'pending',
        ]);

        return redirect()->route('guard.dashboard')
            ->with('success', $visitor->visitor_name . ' registered. Awaiting resident approval.');
    }

    /**
     * Resident / property lookup for the registration form.
     *
     * Typing a flat number, a villa number, a block name or a resident's
     * name all reach the same place, because a guard should not have to know
     * which kind of property they are standing in front of (brief §9).
     */
    public function searchProperty(Request $request): JsonResponse
    {
        $societyId = $this->societyId();
        $term      = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $like = '%' . $term . '%';

        $flats = Flat::where('society_id', $societyId)
            ->with(['building:id,name', 'villaArea:id,name'])
            // Grouped: an ungrouped OR would escape the society scope above.
            ->where(function ($q) use ($like) {
                $q->where('flat_number', 'like', $like)
                    ->orWhereHas('building', function ($b) use ($like) {
                        $b->where('name', 'like', $like);
                    })
                    ->orWhereHas('villaArea', function ($v) use ($like) {
                        $v->where('name', 'like', $like);
                    })
                    ->orWhereHas('residents', function ($r) use ($like) {
                        $r->where('status', 'active')
                            ->where(function ($rr) use ($like) {
                                $rr->where('name', 'like', $like)
                                    ->orWhere('phone', 'like', $like);
                            });
                    });
            })
            ->select('id', 'society_id', 'building_id', 'villa_area_id', 'flat_number', 'property_type')
            ->limit(15)
            ->get();

        // Loaded separately so the select() above stays narrow.
        $flats->load(['activeResident:id,flat_id,name,phone']);

        return response()->json([
            'success' => true,
            'data'    => $flats->map(function (Flat $flat) {
                return [
                    'id'       => $flat->id,
                    'label'    => $this->propertyLabel($flat),
                    'resident' => $flat->activeResident?->name,
                    'phone'    => $flat->activeResident?->phone,
                ];
            })->values(),
        ]);
    }

    /**
     * "Villa V-102" or "Tower A / Flat A-102" - whichever this property is.
     * The guard never picks a property type; the record already knows.
     */
    private function propertyLabel(Flat $flat): string
    {
        if ($flat->property_type === 'villa') {
            $area = $flat->villaArea?->name;

            return trim('Villa ' . $flat->flat_number . ($area ? ' - ' . $area : ''));
        }

        $building = $flat->building?->name;

        return trim(($building ? $building . ' / ' : '') . 'Flat ' . $flat->flat_number);
    }

    /**
     * Visitor arrives.
     *
     * Responds with JSON when the caller asks for it (the gate dashboard's
     * fetch() call does), so the card can be removed and the counters
     * updated in place - a guard checks visitors in and out rapidly, and a
     * full page reload after every tap is exactly the friction brief §18
     * asks to remove. A plain form POST still gets the original
     * redirect-with-flash behaviour, so nothing that already worked
     * changes for a client with JavaScript disabled.
     */
    public function checkIn(Request $request, Visitor $visitor)
    {
        $this->authorizeGateAccess($visitor);

        if ($visitor->approval_status !== 'allowed') {
            return $this->gateActionResponse($request, false, 'This visitor has not been approved yet.');
        }

        if ($visitor->entry_status !== 'pending') {
            return $this->gateActionResponse($request, false, 'This visitor is already checked in.');
        }

        $visitor->update([
            'entry_status'      => 'entered',
            'actual_entry_time' => now(),
        ]);

        return $this->gateActionResponse($request, true, $visitor->visitor_name . ' checked in.', $visitor);
    }

    /** Visitor leaves. See checkIn() for why this responds with JSON. */
    public function checkOut(Request $request, Visitor $visitor)
    {
        $this->authorizeGateAccess($visitor);

        if ($visitor->entry_status !== 'entered') {
            return $this->gateActionResponse($request, false, 'This visitor is not currently inside.');
        }

        $visitor->update([
            'entry_status'     => 'exited',
            'actual_exit_time' => now(),
        ]);

        return $this->gateActionResponse($request, true, $visitor->visitor_name . ' checked out.', $visitor);
    }

    /**
     * One response shape for both actions, on both paths (JSON and
     * redirect), so success and failure look the same to every caller.
     */
    private function gateActionResponse(Request $request, bool $success, string $message, ?Visitor $visitor = null)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success'    => $success,
                'message'    => $message,
                'visitor_id' => $visitor?->id,
                'status'     => $visitor?->entry_status,
                'stats'      => $this->todaysStats($this->societyId()),
            ], $success ? 200 : 422);
        }

        return back()->with($success ? 'success' : 'error', $message);
    }

    /** Recent gate activity. */
    public function history()
    {
        $societyId = $this->societyId();

        $visitors = Visitor::where('society_id', $societyId)
            ->with(['flat.building', 'flat.villaArea'])
            ->latest('expected_entry_time')
            ->paginate(20);

        return view('guard.history', compact('visitors'));
    }

    /**
     * A route-model-bound visitor is an id supplied by the client. Confirm
     * it belongs to this guard's society before touching it, or the gate
     * becomes an IDOR.
     */
    private function authorizeGateAccess(Visitor $visitor): void
    {
        abort_if((int) $visitor->society_id !== $this->societyId(), 403, 'Access denied.');
    }
}
