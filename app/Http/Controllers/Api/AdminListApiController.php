<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Services\GuardShiftService;
use App\Support\PropertyOccupancy;
use App\Support\SocietyClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The lists behind the office's Quick overview cards on the phone. Each list is built from the SAME rule the card's
 * number is (App\Support\SocietyOverview), so the figure on the card and `meta.total` of the list it opens cannot
 * disagree - AdminListParityTest pins every pair:
 *
 *   Properties / Occupied / Vacant -> units      (PropertyOccupancy, the canonical occupancy rule)
 *   Residents                      -> residents  (active owners + tenants, one person once, matched on email)
 *   Visitors today                 -> visitors   (expected entry inside the society's own calendar day)
 *   Pending dues                   -> bills      (pending / partial / overdue; meta.summary.amount = the card's sum)
 *   Collected this month           -> collections(successful payments only, society-local month; a pending claim
 *                                                is never "collected")
 *
 * Admin only, society-scoped from the signed-in account - no parameter can name another society.
 */
class AdminListApiController extends Controller
{
    private const PER_PAGE = 30;

    private function societyId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasRole('Admin') && $user->society_id ? (int) $user->society_id : null;
    }

    private function denied(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'This is for society admins.'], 403);
    }

    private function page(Request $request): int
    {
        return max(1, (int) $request->input('page', 1));
    }

    /** A paginated JSON answer. `$summary` carries figures over the WHOLE filtered set, not just this page. */
    private function respond(Collection $rows, LengthAwarePaginator|int $total, int $page, array $summary = [], ?int $lastPage = null): JsonResponse
    {
        $count = $total instanceof LengthAwarePaginator ? $total->total() : $total;

        return response()->json([
            'success' => true,
            'data'    => $rows->values(),
            'meta'    => [
                'current_page' => $page,
                'last_page'    => $lastPage ?? (int) max(1, ceil($count / self::PER_PAGE)),
                'total'        => $count,
                'summary'      => (object) $summary,
            ],
        ]);
    }

    private function unitLabel(?Flat $f): string
    {
        if (!$f) {
            return 'Unit not linked';
        }
        if ($f->isVilla()) {
            return trim('Villa ' . ($f->villa_name ?: $f->flat_number) . ($f->villaArea ? ', ' . $f->villaArea->name : ''));
        }

        return trim('Flat ' . $f->flat_number . ($f->building ? ', ' . $f->building->name : ''));
    }

    // ── Properties / Occupied / Vacant ──────────────────────────────────────

    public function units(Request $request): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $request->validate(['state' => 'nullable|in:all,occupied,vacant']);
        $state = $request->input('state', 'all');
        $page = $this->page($request);

        $flats = Flat::where('society_id', $sid)->with(['building:id,name', 'villaArea:id,name'])
            ->orderBy('property_type')->orderBy('flat_number')->get();
        $states = PropertyOccupancy::statesFor($flats);

        $rows = $flats->filter(function (Flat $f) use ($states, $state) {
            $s = $states[$f->id]['state'];

            return match ($state) {
                'occupied' => $s !== PropertyOccupancy::VACANT,
                'vacant'   => $s === PropertyOccupancy::VACANT,
                default    => true,
            };
        });

        $total = $rows->count();
        $slice = $rows->forPage($page, self::PER_PAGE)->map(function (Flat $f) use ($states) {
            $row = $states[$f->id];
            $occupant = $row['tenant']?->name ?? $row['owner']?->name;

            return [
                'id'            => $f->id,
                'title'         => $this->unitLabel($f),
                'property_type' => $f->property_type,
                'state'         => $row['state'],
                'state_label'   => PropertyOccupancy::label($row['state']),
                'occupant'      => $occupant,
            ];
        });

        return $this->respond($slice, $total, $page, ['state' => $state]);
    }

    // ── Residents ───────────────────────────────────────────────────────────

    public function residents(Request $request): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $page = $this->page($request);

        $owners = Owner::where('society_id', $sid)->where('status', 'active')->orderBy('name')->get();
        $tenants = Tenant::where('society_id', $sid)->where('status', 'active')->with(['flat.building:id,name', 'flat.villaArea:id,name'])->orderBy('name')->get();
        $ownedUnits = Flat::where('society_id', $sid)->whereIn('owner_id', $owners->pluck('id'))->with(['building:id,name', 'villaArea:id,name'])->get()->groupBy('owner_id');

        // Same identity rule as SocietyOverview::residentCounts: one person once (matched on email); a blank email stands alone.
        $people = [];
        $add = function (string $name, ?string $email, ?string $phone, string $role, string $unit) use (&$people) {
            $key = strtolower(trim((string) $email));
            $key = $key === '' ? 'blank:' . count($people) : $key;
            if (!isset($people[$key])) {
                $people[$key] = ['title' => $name, 'phone' => $phone, 'roles' => [], 'units' => []];
            }
            $people[$key]['roles'][$role] = true;
            $people[$key]['units'][] = $unit;
        };

        foreach ($owners as $o) {
            $units = ($ownedUnits[$o->id] ?? collect())->map(fn (Flat $f) => $this->unitLabel($f))->implode(', ');
            $add($o->name, $o->email, $o->phone, 'Owner', $units !== '' ? $units : 'Unit not linked');
        }
        foreach ($tenants as $t) {
            $add($t->name, $t->email, $t->phone, 'Tenant', $this->unitLabel($t->flat));
        }

        $list = collect($people)->sortBy(fn ($p) => strtolower($p['title']))->values();
        $total = $list->count();
        $slice = $list->forPage($page, self::PER_PAGE)->map(fn ($p) => [
            'title'    => $p['title'],
            'phone'    => $p['phone'],
            'role'     => implode(' & ', array_keys($p['roles'])),
            'subtitle' => collect($p['units'])->unique()->implode(' | '),
        ]);

        return $this->respond($slice, $total, $page, ['owners' => $owners->count(), 'tenants' => $tenants->count()]);
    }

    // ── Visitors ────────────────────────────────────────────────────────────

    public function visitors(Request $request): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $request->validate(['scope' => 'nullable|in:today,pending']);
        $scope = $request->input('scope', 'today');

        $page = Visitor::where('society_id', $sid)
            ->when($scope === 'today', fn ($q) => $q->whereBetween('expected_entry_time', SocietyClock::dayBounds($sid)))
            ->when($scope === 'pending', fn ($q) => $q->where('approval_status', 'pending'))
            ->with(['flat.building:id,name', 'flat.villaArea:id,name'])
            ->orderBy('expected_entry_time', $scope === 'pending' ? 'asc' : 'desc')
            ->paginate(self::PER_PAGE);

        $rows = $page->getCollection()->map(fn (Visitor $v) => [
            'id'              => $v->id,
            'title'           => $v->visitor_name,
            'subtitle'        => trim($this->unitLabel($v->flat) . ($v->purpose ? ' | ' . $v->purpose : '')),
            'when'            => $v->expected_entry_time ? SocietyClock::local($v->expected_entry_time, $sid)->format('d M, h:i A') : null,
            'approval_status' => $v->approval_status,
            'entry_status'    => $v->entry_status,
        ]);

        return $this->respond($rows, $page, $page->currentPage(), ['scope' => $scope], $page->lastPage());
    }

    // ── Pending dues ────────────────────────────────────────────────────────

    public function bills(Request $request): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $request->validate(['scope' => 'nullable|in:outstanding,overdue']);
        $scope = $request->input('scope', 'outstanding');

        $base = fn () => MaintenanceBill::where('society_id', $sid)
            ->when($scope === 'overdue', fn ($q) => $q->overdue(), fn ($q) => $q->whereIn('status', ['pending', 'partial', 'overdue']));

        $sum = (float) $base()->sum('balance_amount');
        $page = $base()->with(['flat.building:id,name', 'flat.villaArea:id,name'])->orderBy('due_date')->orderBy('id')->paginate(self::PER_PAGE);

        $rows = $page->getCollection()->map(fn (MaintenanceBill $b) => [
            'id'       => $b->id,
            'title'    => $this->unitLabel($b->flat),
            'subtitle' => trim(ucfirst((string) ($b->bill_type ?: 'maintenance')) . ' | ' . $b->month . ' ' . $b->year),
            'due_date' => $b->due_date?->format('d M Y'),
            'status'   => $b->status,
            'amount'   => (float) $b->balance_amount,
            'total'    => (float) $b->maintenance_amount,
        ]);

        return $this->respond($rows, $page, $page->currentPage(), ['scope' => $scope, 'amount' => $sum], $page->lastPage());
    }

    // ── Collected this month ────────────────────────────────────────────────

    public function collections(Request $request): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $now = SocietyClock::now($sid);

        // Verified money only: Payment::success() is the one status that counts (a resident's claim is `pending`).
        $base = fn () => Payment::where('society_id', $sid)->success()
            ->whereMonth('payment_date', $now->month)->whereYear('payment_date', $now->year);

        $sum = (float) $base()->sum('amount');
        $page = $base()->with(['flat.building:id,name', 'flat.villaArea:id,name', 'user:id,name'])->orderByDesc('payment_date')->orderByDesc('id')->paginate(self::PER_PAGE);

        $rows = $page->getCollection()->map(fn (Payment $p) => [
            'id'       => $p->id,
            'title'    => $this->unitLabel($p->flat),
            'subtitle' => trim(ucfirst((string) ($p->bill_type ?: 'payment')) . ' | ' . ucfirst((string) $p->payment_method)),
            'date'     => $p->payment_date?->format('d M Y'),
            'payer'    => $p->user?->name,
            'amount'   => (float) $p->amount,
        ]);

        return $this->respond($rows, $page, $page->currentPage(), ['amount' => $sum, 'month' => $now->format('F Y')], $page->lastPage());
    }

    // ── Guards: who is on duty, and where ───────────────────────────────────

    public function guardShifts(Request $request, GuardShiftService $shifts): JsonResponse
    {
        if (!($sid = $this->societyId($request))) {
            return $this->denied();
        }
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);

        $board = $shifts->dutyBoard($sid, null, $request->input('date'));

        return response()->json([
            'success' => true,
            'data'    => $board['items']->values(),
            'meta'    => ['total' => $board['items']->count(), 'date' => $board['date'], 'summary' => (object) $board['counts']],
        ]);
    }
}
