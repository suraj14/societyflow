<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\UtilityBill;
use App\Support\Money;
use App\Support\PropertyOccupancy;
use App\Support\ReportPeriod;
use App\Support\SocietyClock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the financial report for one society and one period.
 *
 * This is the single source of the report's numbers: the web page, the PDF, the Payments page's headline totals and the
 * Maintenance Report all read the classification rules below, so they cannot drift apart.
 *
 * What counts as what (cash basis - the application keeps no general ledger, so no opening / closing balance is shown):
 *
 *   SOCIETY COLLECTIONS  a payment with status `success`, dated in the period, that is NOT owner rent and has evidence of
 *                        receipt (its method is not `unrecorded`); plus facility-booking money actually recorded as received
 *                        (net of refunds). A bill that was merely generated, or a booking that was merely approved, is a
 *                        receivable - never a collection.
 *   OWNER RENT           rent (payments typed rent, or settling a rent bill) belongs to the owner, not the society. It is
 *                        shown as a memo "held for owners", by owner and property, and is in no society total.
 *   NO EVIDENCE          a `success` payment whose method is `unrecorded` (money the records say arrived but nothing shows how:
 *                        e.g. the rows the legacy-bill reconciliation wrote so old "paid" bills had a ledger entry). It is
 *                        listed, never counted as cash, and never changed here.
 *   NOT RECEIVED         pending, failed and cancelled payments: listed with their amounts, never counted.
 *   SOCIETY EXPENSES     expenses with scope = society and status approved, by expense_date. A property's own expense is never
 *                        a society expense. (The schema has no "voided" status, no separate paid date: an approved row is the
 *                        recorded expense.)
 *
 * Bills: billed = bills dated in the period; collected / outstanding on them come from the bills' own ledger-derived
 * paid and balance amounts (Payment::saved keeps those equal to the success payments behind them). "Outstanding" is
 * all-time and is what is owed on bills to date; it is not reduced by cash received for a different bill. Cash received in
 * a period is a different question from billing in that period, so the report keeps them apart and says where each rupee of
 * the period's cash was allocated.
 */
class FinancialReportService
{
    /** Income groups, in display order. */
    private const INCOME_MAINTENANCE = 'Maintenance';
    private const INCOME_UTILITY     = 'Utility / Common Charges';
    private const INCOME_OTHER       = 'Other Collections';
    private const INCOME_FACILITY    = 'Facility Bookings';
    private const INCOME_RENT        = 'Rent';

    /**
     * Society-wide report (Admin / Accountant / Super Admin).
     */
    public function build(int $societyId, ReportPeriod $period): array
    {
        [$start, $end] = $this->bounds($period);

        $facility   = $this->facility($societyId, $start, $end);
        $payments   = $this->classifiedPayments($societyId, $start, $end);
        $income     = $this->income($payments, $facility);
        $excluded   = $this->excluded($societyId, $start, $end, $payments);
        $expenses   = $this->societyExpenses($societyId, $start, $end);
        $billing    = $this->maintenanceBilling($societyId, $start, $end, $payments);
        $properties = $this->propertyRows($societyId, $start, $end);
        $rentOwners = $this->rentByOwner($excluded['rent_rows']);

        $outstandingAllTime = $this->outstandingMaintenanceDues($societyId);

        $transactionCount = $income['count'] + $income['rent_count'] + $excluded['unverified_count'] + $expenses['count'] + $billing['bills_count'];

        $net = Money::diff($income['total'], $expenses['total']);

        return [
            'mode'   => 'society',
            'period' => $period->toArray(),

            'overview' => [
                'total_income'           => $income['total'],
                'maintenance_collected'  => $income['maintenance'],
                'other_income'           => Money::diff($income['total'], $income['maintenance']),
                'facility_collected'     => $income['facility'],
                'rent_collected'         => $income['rent'],
                'rent_unverified'        => $excluded['rent_unverified'],
                'total_society_expenses' => $expenses['total'],
                'outstanding_dues'       => $outstandingAllTime,
                'net_position'           => $net,
                'maintenance_billed'     => $billing['billed'],
                'facility_receivable'    => $facility['receivable']['amount'],
            ],

            'income'            => $income,
            'expenses'          => $expenses,
            'billing'           => $billing,
            'properties'        => $properties,
            'facility'          => $facility,
            'excluded'          => $excluded,
            'rent_owners'       => $rentOwners,
            // an annual report also carries its twelve months, which add up to it exactly
            'months'            => $period->isMonthly() ? null : $this->months($societyId, $period->year),
            // today in the society's own calendar, and records already dated after it: a receipt or an expense cannot have happened
            // yet, so they are flagged rather than hidden (they stay in the period of their own date; nothing is moved or rewritten)
            'as_of'             => SocietyClock::now($societyId)->toDateString(),
            'future_dated'      => $this->futureDated($societyId, $start, $end),

            'has_data'          => $transactionCount > 0 || $facility['collected']['count'] > 0 || $facility['refunded']['count'] > 0,
            'transaction_count' => $transactionCount,
        ];
    }

    /**
     * A resident's own view: their properties only, no society-wide
     * income/expense sections at all.
     *
     * @param  array<int,int>  $flatIds
     */
    public function buildForFlats(int $societyId, ReportPeriod $period, array $flatIds): array
    {
        [$start, $end] = $this->bounds($period);

        $flatIds = array_values(array_unique(array_map('intval', $flatIds)));

        $payments = Payment::query()
            ->where('society_id', $societyId)
            ->whereIn('flat_id', $flatIds ?: [0])
            ->where('status', 'success')
            ->whereBetween('payment_date', [$start, $end])
            ->with(['flat.building'])
            ->orderBy('payment_date')
            ->get();

        $bills = MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->whereIn('flat_id', $flatIds ?: [0])
            ->whereBetween('bill_date', [$start, $end])
            ->with(['flat.building'])
            ->orderBy('bill_date')
            ->get();

        $utilityBills = UtilityBill::query()
            ->where('society_id', $societyId)
            ->whereIn('flat_id', $flatIds ?: [0])
            ->whereBetween('bill_date', [$start, $end])
            ->with(['flat.building'])
            ->orderBy('bill_date')
            ->get();

        $outstanding = Money::sum(MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->whereIn('flat_id', $flatIds ?: [0])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->pluck('balance_amount'));

        $paymentRows = $payments->map(fn (Payment $p) => [
            'date'     => $p->payment_date?->format('d M Y') ?? '',
            'property' => $this->flatLabel($p->flat),
            'type'     => $this->incomeGroup($p->bill_type),
            'receipt'  => $p->receipt_number,
            'amount'   => Money::paise($p->amount) / 100,
        ])->values()->all();

        $billRows = $bills->map(fn (MaintenanceBill $b) => [
            'date'     => $b->bill_date?->format('d M Y') ?? '',
            'property' => $this->flatLabel($b->flat),
            'number'   => $b->bill_number,
            'type'     => ucfirst($b->bill_type ?: 'maintenance'),
            'billed'   => Money::paise($b->total_amount) / 100,
            'paid'     => Money::paise($b->paid_amount) / 100,
            'due'      => Money::paise($b->balance_amount) / 100,
        ])->values()->all();

        $utilityRows = $utilityBills->map(fn (UtilityBill $u) => [
            'date'     => $u->bill_date?->format('d M Y') ?? '',
            'property' => $this->flatLabel($u->flat),
            'type'     => $u->bill_type,
            'amount'   => Money::paise($u->bill_amount) / 100,
            'status'   => ucfirst($u->status),
        ])->values()->all();

        $transactionCount = count($paymentRows) + count($billRows) + count($utilityRows);

        return [
            'mode'   => 'property',
            'period' => $period->toArray(),

            'overview' => [
                'total_paid'       => Money::sum($payments->pluck('amount')),
                'total_billed'     => Money::sum($bills->pluck('total_amount')),
                'period_due'       => Money::sum($bills->pluck('balance_amount')),
                'outstanding_dues' => $outstanding,
            ],

            'payments'          => ['rows' => $paymentRows, 'total' => Money::sum($payments->pluck('amount'))],
            'bills'             => ['rows' => $billRows, 'billed' => Money::sum($bills->pluck('total_amount')), 'paid' => Money::sum($bills->pluck('paid_amount')), 'due' => Money::sum($bills->pluck('balance_amount'))],
            'utility_bills'     => ['rows' => $utilityRows, 'total' => Money::sum($utilityBills->pluck('bill_amount'))],

            'has_data'          => $transactionCount > 0,
            'transaction_count' => $transactionCount,
        ];
    }

    // ------------------------------------------------------------------
    // The Payments page's headline figures (same rules, scoped by the caller)
    // ------------------------------------------------------------------

    /**
     * Headline totals for the Payments page. `$scoped` is the payments query the caller already restricted to what the
     * signed-in person may see (society, or their own units).
     *
     * With `$societyView` (Admin / Accountant / Super Admin) "collected" is what the SOCIETY actually received: success,
     * not owner rent, with evidence of receipt. For a resident it is what THEY paid: every success payment of theirs,
     * rent included - their rent is theirs to see - but never a pending, failed or cancelled one.
     *
     * @return array<string, int|float>
     */
    public function paymentSummary(Builder $scoped, bool $societyView): array
    {
        $rentBills = fn ($q) => $q->select('id')->from('maintenance_bills')->where('bill_type', 'rent');
        $isRent = fn ($q) => $q->whereRaw("LOWER(TRIM(COALESCE(bill_type, ''))) = 'rent'")->orWhereIn('maintenance_bill_id', $rentBills);
        $notRent = fn ($q) => $q->whereRaw("LOWER(TRIM(COALESCE(bill_type, ''))) != 'rent'")->where(fn ($z) => $z->whereNull('maintenance_bill_id')->orWhereNotIn('maintenance_bill_id', $rentBills));
        $evidenced = fn ($q) => $q->where(fn ($z) => $z->whereNull('payment_method')->orWhere('payment_method', '!=', 'unrecorded'));

        $success = (clone $scoped)->where('status', 'success');
        $collected = $societyView
            ? self::applyCollectionRules(clone $scoped)
            : (clone $success);

        // payment_date is the society's own calendar date; compare it on that calendar, not the storage clock
        $now = \App\Support\SocietyClock::now();
        $month = [$now->copy()->startOfMonth()->startOfDay()->toDateTimeString(), $now->copy()->endOfMonth()->endOfDay()->toDateTimeString()];
        $today = [$now->copy()->startOfDay()->toDateTimeString(), $now->copy()->endOfDay()->toDateTimeString()];

        $sum = fn (Builder $q) => Money::sum($q->pluck('amount'));

        $summary = [
            'total_amount'      => $sum(clone $collected),
            'total_count'       => (clone $collected)->count(),
            'this_month_amount' => $sum((clone $collected)->whereBetween('payment_date', $month)),
            'today_amount'      => $sum((clone $collected)->whereBetween('payment_date', $today)),
        ];

        // what is NOT in "collected", so the page can say why its total is lower than the sum of its rows
        $pending = (clone $scoped)->where('status', 'pending');
        $notReceived = (clone $scoped)->whereIn('status', ['failed', 'cancelled']);
        $summary += [
            'pending_amount'     => $sum(clone $pending),
            'pending_count'      => (clone $pending)->count(),
            'not_received_amount' => $sum(clone $notReceived),
            'not_received_count' => (clone $notReceived)->count(),
            'rent_amount'        => 0.0,
            'rent_count'         => 0,
            'no_evidence_amount' => 0.0,
            'no_evidence_count'  => 0,
        ];
        if ($societyView) {
            // owner rent that has evidence of receipt is held for the owner; anything recorded as paid with no evidence (rent or not)
            // is its own line - it is never counted, and never hidden
            $rent = (clone $success)->where($isRent)->where($evidenced);
            $noEvidence = (clone $success)->where('payment_method', 'unrecorded');
            $summary['rent_amount'] = $sum(clone $rent);
            $summary['rent_count'] = (clone $rent)->count();
            $summary['no_evidence_amount'] = $sum(clone $noEvidence);
            $summary['no_evidence_count'] = (clone $noEvidence)->count();
        }

        return $summary;
    }

    // ------------------------------------------------------------------
    // "Money the society actually received" - the one definition
    // ------------------------------------------------------------------

    /**
     * Restrict a payments query to money the SOCIETY actually received: status success, not owner rent (the owner's money:
     * a payment typed rent, or one settling a rent bill), and with evidence of receipt (method is not `unrecorded`). A payment
     * linked to no bill is kept - a verified gas or cable-TV collection is still a collection. Every figure that says
     * "collected" - the Financial and Maintenance reports, the Payments page, the dashboards, the mobile API - starts here.
     * Dates are the caller's: use payment_date (the day the money arrived), never created_at (the day the row was typed).
     */
    public static function applyCollectionRules(Builder $q): Builder
    {
        $rentBills = fn ($s) => $s->select('id')->from('maintenance_bills')->where('bill_type', 'rent');

        return $q->where('status', 'success')
            ->whereRaw("LOWER(TRIM(COALESCE(bill_type, ''))) != 'rent'")
            ->where(fn ($z) => $z->whereNull('maintenance_bill_id')->orWhereNotIn('maintenance_bill_id', $rentBills))
            ->where(fn ($z) => $z->whereNull('payment_method')->orWhere('payment_method', '!=', 'unrecorded'));
    }

    /**
     * What the society actually received, optionally within [start, end] (naive society-local datetimes): verified payments
     * plus facility-booking money the office recorded as received, less refunds paid back in the same range. `$societyId`
     * null means every society (a Super Admin's global view).
     *
     * @return array{amount: float, payments: float, facility: float, count: int}
     */
    public static function societyCollections(?int $societyId, ?string $start = null, ?string $end = null): array
    {
        $payments = self::applyCollectionRules(Payment::query()->when($societyId, fn ($q) => $q->where('society_id', $societyId)))
            ->when($start !== null, fn ($q) => $q->whereBetween('payment_date', [$start, $end]));
        $paymentTotal = Money::sum((clone $payments)->pluck('amount'));

        $booking = fn () => FacilityBooking::query()->when($societyId, fn ($q) => $q->where('society_id', $societyId));
        $received = $booking()->whereIn('payment_status', ['paid', 'refunded'])->whereNotNull('paid_at')
            ->when($start !== null, fn ($q) => $q->whereBetween('paid_at', [$start, $end]))->get(['paid_amount', 'booking_amount']);
        $refunded = $booking()->where('payment_status', 'refunded')->whereNotNull('refunded_at')
            ->when($start !== null, fn ($q) => $q->whereBetween('refunded_at', [$start, $end]))->pluck('refunded_amount');
        $facility = Money::diff(Money::sum($received->map(fn ($b) => $b->paid_amount ?? $b->booking_amount)), Money::sum($refunded));

        return [
            'amount'   => Money::rupees(Money::paise($paymentTotal) + Money::paise($facility)),
            'payments' => $paymentTotal,
            'facility' => $facility,
            'count'    => (clone $payments)->count() + $received->count(),
        ];
    }

    /** The current month on a society's own calendar, as inclusive naive datetimes for payment_date / paid_at comparisons. @return array{0:string,1:string} */
    public static function currentMonthBounds(?int $societyId): array
    {
        $now = \App\Support\SocietyClock::now($societyId);

        return [$now->copy()->startOfMonth()->startOfDay()->toDateTimeString(), $now->copy()->endOfMonth()->endOfDay()->toDateTimeString()];
    }

    // ------------------------------------------------------------------
    // Payments, classified once
    // ------------------------------------------------------------------
    /**
     * The period's payments, each tagged: owner rent or not, evidenced or not, and the group it is reported under.
     * `success` rows are dated by payment_date; the other statuses by payment_date, else the day they were created.
     *
     * @return array{success: Collection, other: Collection}
     */
    private function classifiedPayments(int $societyId, string $start, string $end): array
    {
        $base = fn () => Payment::query()
            ->leftJoin('maintenance_bills as mb', 'mb.id', '=', 'payments.maintenance_bill_id')
            ->where('payments.society_id', $societyId)
            ->select('payments.*', 'mb.bill_type as linked_bill_type', 'mb.bill_date as linked_bill_date', 'mb.flat_id as linked_bill_flat_id')
            ->with('flat.building');

        $success = $base()->where('payments.status', 'success')
            ->whereBetween('payments.payment_date', [$start, $end])
            ->orderBy('payments.payment_date')->orderBy('payments.id')->get();

        $other = $base()->whereIn('payments.status', ['pending', 'failed', 'cancelled'])
            ->whereBetween(DB::raw('COALESCE(payments.payment_date, payments.created_at)'), [$start, $end])
            ->orderBy('payments.id')->get();

        $tag = function (Payment $p): Payment {
            $rent = strtolower(trim((string) $p->bill_type)) === 'rent' || $p->linked_bill_type === 'rent';
            $p->setAttribute('is_rent', $rent);
            $p->setAttribute('is_unverified', $p->payment_method === 'unrecorded');
            $p->setAttribute('report_group', $rent ? self::INCOME_RENT : $this->incomeGroup($p->bill_type ?: ($p->linked_bill_type ?? '')));

            return $p;
        };

        return ['success' => $success->map($tag), 'other' => $other->map($tag)];
    }

    // ------------------------------------------------------------------
    // Society sections
    // ------------------------------------------------------------------

    /**
     * What the society actually received in the period: evidenced, non-rent success payments by what they paid for, plus
     * facility money recorded as received (net of refunds). Rent is the owner's and is only tallied here for the memo line.
     */
    private function income(array $payments, array $facility): array
    {
        $buckets = [
            self::INCOME_MAINTENANCE => ['amounts' => [], 'count' => 0],
            self::INCOME_UTILITY     => ['amounts' => [], 'count' => 0],
            self::INCOME_OTHER       => ['amounts' => [], 'count' => 0],
        ];
        $rent = ['amounts' => [], 'count' => 0];
        $transactions = [];

        foreach ($payments['success'] as $p) {
            if ($p->is_unverified) {
                continue;               // no evidence of receipt: see excluded()
            }
            if ($p->is_rent) {
                $rent['amounts'][] = $p->amount;
                $rent['count']++;
                continue;               // the owner's money
            }
            $buckets[$p->report_group]['amounts'][] = $p->amount;
            $buckets[$p->report_group]['count']++;
            $transactions[] = $this->paymentLine($p, $p->report_group);
        }

        $rows = [];
        foreach ($buckets as $source => $b) {
            if ($b['count'] === 0) {
                continue;
            }
            $rows[] = ['source' => $source, 'amount' => Money::sum($b['amounts']), 'count' => $b['count']];
        }

        // facility money recorded as received, less refunds paid back in the same period
        $facilityNet = Money::diff($facility['collected']['amount'], $facility['refunded']['amount']);
        if ($facility['collected']['count'] > 0 || $facility['refunded']['count'] > 0) {
            $rows[] = ['source' => self::INCOME_FACILITY, 'amount' => $facilityNet, 'count' => $facility['collected']['count']];
        }
        foreach ($facility['collected']['rows'] as $r) {
            $transactions[] = ['date' => $r['date'], 'date_sort' => $r['date_sort'], 'property' => $r['property'], 'source' => self::INCOME_FACILITY, 'method' => $r['method'], 'reference' => $r['reference'], 'amount' => $r['amount']];
        }
        foreach ($facility['refunded']['rows'] as $r) {
            $transactions[] = ['date' => $r['date'], 'date_sort' => $r['date_sort'], 'property' => $r['property'], 'source' => 'Facility refund', 'method' => '', 'reference' => $r['reference'], 'amount' => -$r['amount']];
        }
        usort($transactions, fn ($a, $b) => strcmp($a['date_sort'] ?? $a['date'], $b['date_sort'] ?? $b['date']));

        $total = Money::sum(array_column($rows, 'amount'));
        $count = array_sum(array_column($rows, 'count'));
        $maintenance = Money::sum($buckets[self::INCOME_MAINTENANCE]['amounts']);

        return [
            'rows'         => $rows,
            'total'        => $total,
            'count'        => $count,
            'maintenance'  => $maintenance,
            'facility'     => $facilityNet,
            'rent'         => Money::sum($rent['amounts']),
            'rent_count'   => $rent['count'],
            'transactions' => $transactions,
        ];
    }

    /**
     * Money the records mention that is NOT society cash in this period: owner rent (verified and not), success payments with
     * no evidence of receipt, and payments that were never received. Each is listed so an Admin can open it.
     */
    private function excluded(int $societyId, string $start, string $end, array $payments): array
    {
        $success = $payments['success'];

        $rentVerified   = $success->filter(fn ($p) => $p->is_rent && !$p->is_unverified);
        $rentUnverified = $success->filter(fn ($p) => $p->is_rent && $p->is_unverified);
        $otherUnverified = $success->filter(fn ($p) => !$p->is_rent && $p->is_unverified);
        $notReceived    = $payments['other'];

        $byStatus = [];
        foreach (['pending', 'failed', 'cancelled'] as $st) {
            $set = $notReceived->where('status', $st);
            $byStatus[$st] = ['amount' => Money::sum($set->pluck('amount')), 'count' => $set->count()];
        }

        return [
            'rent_verified'       => Money::sum($rentVerified->pluck('amount')),
            'rent_unverified'     => Money::sum($rentUnverified->pluck('amount')),
            'other_unverified'    => Money::sum($otherUnverified->pluck('amount')),
            'unverified_count'    => $rentUnverified->count() + $otherUnverified->count(),
            'not_received'        => $byStatus,
            'rent_rows'           => $success->filter(fn ($p) => $p->is_rent)->values(),
            'transactions'        => collect()
                ->merge($rentVerified->map(fn ($p) => $this->paymentLine($p, 'Owner rent (held for the owner)')))
                ->merge($rentUnverified->map(fn ($p) => $this->paymentLine($p, 'Owner rent - NO evidence of receipt')))
                ->merge($otherUnverified->map(fn ($p) => $this->paymentLine($p, ($p->report_group) . ' - NO evidence of receipt')))
                ->merge($notReceived->map(fn ($p) => $this->paymentLine($p, ucfirst($p->status) . ' - not received')))
                ->values()->all(),
        ];
    }

    /** Owner rent the society's records show, by owner and property. A memo: it is not the society's money. */
    private function rentByOwner(Collection $rentPayments): array
    {
        if ($rentPayments->isEmpty()) {
            return [];
        }
        $flats = $rentPayments->pluck('flat')->filter()->unique('id');
        $owners = PropertyOccupancy::ownersFor($flats->values());

        return $rentPayments->groupBy('flat_id')->map(function (Collection $set, $flatId) use ($flats, $owners) {
            $flat = $flats->firstWhere('id', $flatId);

            return [
                'owner'      => $owners[$flatId]->name ?? 'Owner not recorded',
                'property'   => $this->flatLabel($flat),
                'verified'   => Money::sum($set->reject(fn ($p) => $p->is_unverified)->pluck('amount')),
                'unverified' => Money::sum($set->filter(fn ($p) => $p->is_unverified)->pluck('amount')),
                'count'      => $set->count(),
            ];
        })->sortBy('property', SORT_NATURAL)->values()->all();
    }

    /**
     * Facility-booking money. Cash basis: a booking pays the society when a payment is RECORDED against it (paid_at), and a
     * refund reduces it when the refund is recorded (refunded_at). An approved booking with no payment is a receivable.
     */
    private function facility(int $societyId, string $start, string $end): array
    {
        $line = function (FacilityBooking $b, $date, $amount) {
            return [
                'date'      => $date ? Carbon::parse($date)->format('d M Y') : '',
                'date_sort' => $date ? Carbon::parse($date)->toDateTimeString() : '',
                'property'  => $b->flat ? $this->flatLabel($b->flat) : ($b->facility->name ?? 'Facility'),
                'facility'  => $b->facility->name ?? 'Facility',
                'booking'   => $b->booking_number,
                'method'    => $b->payment_method ? str_replace('_', ' ', $b->payment_method) : '',
                'reference' => $b->payment_reference ?: $b->booking_number,
                'amount'    => Money::paise($amount) / 100,
            ];
        };

        $collected = FacilityBooking::query()->where('society_id', $societyId)
            ->whereIn('payment_status', ['paid', 'refunded'])->whereNotNull('paid_at')->whereBetween('paid_at', [$start, $end])
            ->with(['facility', 'flat.building'])->orderBy('paid_at')->get();
        $refunded = FacilityBooking::query()->where('society_id', $societyId)
            ->where('payment_status', 'refunded')->whereNotNull('refunded_at')->whereBetween('refunded_at', [$start, $end])
            ->with(['facility', 'flat.building'])->orderBy('refunded_at')->get();
        $receivable = FacilityBooking::query()->where('society_id', $societyId)->awaitingPayment()
            ->with(['facility', 'flat.building'])->orderBy('booking_date')->get();
        // marked paid / refunded but with no date of receipt: cannot be placed in any period, so it is listed, not summed
        $undated = FacilityBooking::query()->where('society_id', $societyId)
            ->whereIn('payment_status', ['paid', 'refunded'])->whereNull('paid_at')->count();

        $collectedRows = $collected->map(fn ($b) => $line($b, $b->paid_at, $b->paid_amount ?? $b->booking_amount))->values()->all();
        $refundedRows = $refunded->map(fn ($b) => $line($b, $b->refunded_at, $b->refunded_amount ?? 0))->values()->all();
        $receivableRows = $receivable->map(fn ($b) => $line($b, $b->booking_date, $b->booking_amount) + ['status' => $b->status])->values()->all();

        return [
            'collected'  => ['amount' => Money::sum(array_column($collectedRows, 'amount')), 'count' => count($collectedRows), 'rows' => $collectedRows],
            'refunded'   => ['amount' => Money::sum(array_column($refundedRows, 'amount')), 'count' => count($refundedRows), 'rows' => $refundedRows],
            'receivable' => ['amount' => Money::sum(array_column($receivableRows, 'amount')), 'count' => count($receivableRows), 'rows' => $receivableRows],
            'undated_paid' => $undated,
        ];
    }

    /** Approved society-scoped expenses in the period, by category. */
    private function societyExpenses(int $societyId, string $start, string $end): array
    {
        $rows = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.society_id', $societyId)
            ->where('expenses.scope', Expense::SCOPE_SOCIETY)
            ->where('expenses.status', 'approved')
            ->whereBetween('expenses.expense_date', [$start, $end])
            ->select('expenses.id', 'expenses.title', 'expenses.amount', 'expenses.expense_date', 'expenses.payment_method', 'expenses.paid_by', 'expense_categories.id as category_id', 'expense_categories.name as category')
            ->orderBy('expenses.expense_date')->orderBy('expenses.id')
            ->get();

        $total = Money::sum($rows->pluck('amount'));

        $byCategory = $rows->groupBy('category_id')->map(function (Collection $set) use ($total) {
            $amount = Money::sum($set->pluck('amount'));

            return [
                'category_id' => (int) $set->first()->category_id,
                'category'    => $set->first()->category,
                'amount'      => $amount,
                'count'       => $set->count(),
                'percent'     => $total > 0 ? round(($amount / $total) * 100, 1) : 0.0,
            ];
        })->sortByDesc('amount')->values()->all();

        return [
            'rows'         => $byCategory,
            'total'        => $total,
            'count'        => $rows->count(),
            'transactions' => $rows->map(fn ($e) => [
                'date'     => Carbon::parse($e->expense_date)->format('d M Y'),
                'title'    => $e->title,
                'category' => $e->category,
                'method'   => $e->payment_method ? str_replace('_', ' ', $e->payment_method) : '',
                'amount'   => Money::paise($e->amount) / 100,
            ])->all(),
        ];
    }

    /**
     * Maintenance bills *issued* in the period: billed, collected against them, and still outstanding on them. Rent bills
     * share the table and are excluded - they are not society billing.
     *
     * Also: of the maintenance cash received IN the period, how much settled this period's bills, how much settled earlier
     * bills (arrears) and how much is linked to no bill. A rupee received in October for a September bill is October cash
     * and a September receivable - the two views are kept apart and never netted against each other.
     */
    private function maintenanceBilling(int $societyId, string $start, string $end, array $payments): array
    {
        $bills = MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->where('bill_type', 'maintenance')
            ->whereBetween('bill_date', [$start, $end])
            ->get();

        $sum = fn (string $col) => Money::sum($bills->pluck($col));

        $charges = [
            ['label' => 'Maintenance', 'amount' => $sum('maintenance_amount')],
            ['label' => 'Water charges', 'amount' => $sum('water_charges')],
            ['label' => 'Electricity charges', 'amount' => $sum('electricity_charges')],
            ['label' => 'Parking charges', 'amount' => $sum('parking_charges')],
            ['label' => 'Penalty', 'amount' => $sum('penalty_amount')],
            ['label' => 'Other charges', 'amount' => $sum('other_charges')],
        ];

        // what actually settled these bills, split by whether the payment has evidence of receipt
        $settling = Payment::query()->where('society_id', $societyId)->where('status', 'success')
            ->whereIn('maintenance_bill_id', $bills->pluck('id')->all() ?: [0])->get(['amount', 'payment_method']);
        $withEvidence = Money::sum($settling->reject(fn ($p) => $p->payment_method === 'unrecorded')->pluck('amount'));
        $withoutEvidence = Money::sum($settling->filter(fn ($p) => $p->payment_method === 'unrecorded')->pluck('amount'));

        // maintenance cash received in the period, by which bill it settled
        $startDay = substr($start, 0, 10);
        $endDay = substr($end, 0, 10);
        $cash = $payments['success']->filter(fn ($p) => !$p->is_rent && !$p->is_unverified && $p->report_group === self::INCOME_MAINTENANCE);
        $linked = fn (Payment $p) => $p->maintenance_bill_id && $p->linked_bill_date;
        $billDay = fn (Payment $p) => substr((string) $p->linked_bill_date, 0, 10);
        $against = [
            'this_period' => $cash->filter(fn ($p) => $linked($p) && $billDay($p) >= $startDay && $billDay($p) <= $endDay),
            'earlier'     => $cash->filter(fn ($p) => $linked($p) && $billDay($p) < $startDay),
            'later'       => $cash->filter(fn ($p) => $linked($p) && $billDay($p) > $endDay),
            'unallocated' => $cash->reject($linked),
        ];

        return [
            'bills_count' => $bills->count(),
            'billed'      => $sum('total_amount'),
            'collected'   => $sum('paid_amount'),
            'collected_with_evidence'    => $withEvidence,
            'collected_without_evidence' => $withoutEvidence,
            'outstanding' => $sum('balance_amount'),
            'charges'     => array_values(array_filter($charges, fn ($c) => $c['amount'] > 0)),
            'cash_in_period' => [
                'total'       => Money::sum($cash->pluck('amount')),
                'count'       => $cash->count(),
                'this_period' => Money::sum($against['this_period']->pluck('amount')),
                'earlier'     => Money::sum($against['earlier']->pluck('amount')),
                'later'       => Money::sum($against['later']->pluck('amount')),
                'unallocated' => Money::sum($against['unallocated']->pluck('amount')),
            ],
        ];
    }

    /** Per-property billed / paid / due for maintenance bills issued in the period. */
    private function propertyRows(int $societyId, string $start, string $end): array
    {
        $bills = MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->where('bill_type', 'maintenance')
            ->whereBetween('bill_date', [$start, $end])
            ->get();

        if ($bills->isEmpty()) {
            return [];
        }

        $flats = Flat::with('building')->whereIn('id', $bills->pluck('flat_id')->unique())->get()->keyBy('id');

        return $bills->groupBy('flat_id')
            ->map(fn (Collection $set, $flatId) => [
                'property' => $this->flatLabel($flats->get($flatId)),
                'billed'   => Money::sum($set->pluck('total_amount')),
                'paid'     => Money::sum($set->pluck('paid_amount')),
                'due'      => Money::sum($set->pluck('balance_amount')),
            ])
            ->sortBy('property', SORT_NATURAL)
            ->values()
            ->all();
    }

    /** All-time unpaid maintenance (not limited to the period). */
    private function outstandingMaintenanceDues(int $societyId): float
    {
        return Money::sum(MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->where('bill_type', 'maintenance')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->pluck('balance_amount'));
    }

    /**
     * Counted successful receipts and approved society expenses in [start, end] that are dated AFTER today.
     *
     * @return array{payments: array{count:int,amount:float}, expenses: array{count:int,amount:float}}
     */
    private function futureDated(int $societyId, string $start, string $end): array
    {
        $today = SocietyClock::now($societyId)->toDateString();
        $pay = self::applyCollectionRules(Payment::query()->where('society_id', $societyId))
            ->whereBetween('payment_date', [$start, $end])->whereDate('payment_date', '>', $today)->get(['amount']);
        $exp = Expense::query()->where('society_id', $societyId)->where('scope', Expense::SCOPE_SOCIETY)->where('status', 'approved')
            ->whereBetween('expense_date', [$start, $end])->whereDate('expense_date', '>', $today)->get(['amount']);

        return [
            'payments' => ['count' => $pay->count(), 'amount' => Money::sum($pay->pluck('amount'))],
            'expenses' => ['count' => $exp->count(), 'amount' => Money::sum($exp->pluck('amount'))],
        ];
    }

    /**
     * The twelve months of a year, each built with exactly the same rules as a monthly report, so they add up to the
     * annual figures to the paisa (asserted by tests).
     *
     * @return list<array{month:int,label:string,income:float,expenses:float,net:float,billed:float}>
     */
    private function months(int $societyId, int $year): array
    {
        $rows = [];
        for ($m = 1; $m <= 12; $m++) {
            $p = ReportPeriod::monthly($year, $m);
            [$s, $e] = $this->bounds($p);
            $facility = $this->facility($societyId, $s, $e);
            $payments = $this->classifiedPayments($societyId, $s, $e);
            $income = $this->income($payments, $facility);
            $expenses = $this->societyExpenses($societyId, $s, $e);
            $billing = $this->maintenanceBilling($societyId, $s, $e, $payments);

            $today = SocietyClock::now($societyId)->toDateString();
            $isFuture = $p->start()->toDateString() > $today;
            $rows[] = [
                'month'    => $m,
                'label'    => $p->start()->format('F'),
                // a month that has not begun yet, and whether this one is still running: the annual table must not read as if the whole year has passed
                'future'   => $isFuture,
                'current'  => !$isFuture && $p->end()->toDateString() >= $today,
                'has_data' => $income['total'] != 0 || $expenses['total'] != 0 || $billing['billed'] != 0,
                'income'   => $income['total'],
                'expenses' => $expenses['total'],
                'net'      => Money::diff($income['total'], $expenses['total']),
                'billed'   => $billing['billed'],
            ];
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // The Maintenance Report
    // ------------------------------------------------------------------

    /**
     * Maintenance billing and collections for a period - one question per figure, none of them netted against another:
     *
     *   billed ................ maintenance bills DATED in the period
     *   outstanding ........... still owed on those bills (all payments to date)
     *   collected_on_these_bills  what has been paid against those bills, with evidence, whenever it was paid
     *   total_maintenance ..... maintenance CASH received in the period, with evidence, for any bill
     *   cash_against_*  ....... which bills that cash settled: this period's, earlier ones (arrears), later ones, none
     *   outstanding_all_time .. owed on every maintenance bill to date
     *
     * A rupee received in October for a September bill is October cash and a September receivable: it never reduces what is
     * owed on October's bills. Owner rent is never maintenance. A payment whose method is `unrecorded` has no evidence of
     * receipt: it is listed apart and not counted as collected.
     *
     * @param  array<int,int>|null  $flatIds  null = the whole society; a list = a resident's own units
     */
    public function maintenanceReport(int $societyId, ReportPeriod $period, ?array $flatIds = null): array
    {
        [$start, $end] = $this->bounds($period);
        $startDay = substr($start, 0, 10);
        $endDay = substr($end, 0, 10);
        $mine = fn (Payment $p) => $flatIds === null
            || in_array((int) $p->linked_bill_flat_id, $flatIds, true) || in_array((int) $p->flat_id, $flatIds, true);

        // a maintenance payment settles a maintenance bill, or is an ad-hoc payment whose own bill_type says maintenance
        $cash = $this->classifiedPayments($societyId, $start, $end)['success']
            ->filter(fn (Payment $p) => !$p->is_rent && ($p->linked_bill_type === 'maintenance' || (!$p->maintenance_bill_id && str_contains(strtolower((string) $p->bill_type), 'maint'))))
            ->filter($mine)->values();
        $evidenced = $cash->reject(fn ($p) => $p->is_unverified)->values();
        $noEvidence = $cash->filter(fn ($p) => $p->is_unverified)->values();

        $bills = MaintenanceBill::query()->where('society_id', $societyId)->where('bill_type', 'maintenance')
            ->whereBetween('bill_date', [$start, $end])
            ->when($flatIds !== null, fn ($q) => $q->whereIn('flat_id', $flatIds ?: [0]))->get();
        $settling = Payment::query()->where('society_id', $societyId)->where('status', 'success')
            ->whereIn('maintenance_bill_id', $bills->pluck('id')->all() ?: [0])->get(['amount', 'payment_method']);

        $linked = fn (Payment $p) => $p->maintenance_bill_id && $p->linked_bill_date;
        $billDay = fn (Payment $p) => substr((string) $p->linked_bill_date, 0, 10);
        $allTime = MaintenanceBill::query()->where('society_id', $societyId)->where('bill_type', 'maintenance')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->when($flatIds !== null, fn ($q) => $q->whereIn('flat_id', $flatIds ?: [0]))->pluck('balance_amount');

        return [
            'billed'                      => Money::sum($bills->pluck('total_amount')),
            'outstanding'                 => Money::sum($bills->pluck('balance_amount')),
            'collected_on_these_bills'    => Money::sum($settling->reject(fn ($p) => $p->payment_method === 'unrecorded')->pluck('amount')),
            'no_evidence_on_these_bills'  => Money::sum($settling->filter(fn ($p) => $p->payment_method === 'unrecorded')->pluck('amount')),
            'total_maintenance'           => Money::sum($evidenced->pluck('amount')),
            'count'                       => $evidenced->count(),
            'payments'                    => $evidenced,
            'no_evidence'                 => ['amount' => Money::sum($noEvidence->pluck('amount')), 'count' => $noEvidence->count(), 'rows' => $noEvidence],
            'cash_against_this_period_bills' => Money::sum($evidenced->filter(fn ($p) => $linked($p) && $billDay($p) >= $startDay && $billDay($p) <= $endDay)->pluck('amount')),
            'cash_against_earlier_bills'  => Money::sum($evidenced->filter(fn ($p) => $linked($p) && $billDay($p) < $startDay)->pluck('amount')),
            'cash_against_later_bills'    => Money::sum($evidenced->filter(fn ($p) => $linked($p) && $billDay($p) > $endDay)->pluck('amount')),
            'cash_unallocated'            => Money::sum($evidenced->reject($linked)->pluck('amount')),
            'outstanding_all_time'        => Money::sum($allTime),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Inclusive datetime bounds for whereBetween on a date column.
     *
     * Eloquent's `date` cast writes "2025-06-30 00:00:00" into SQLite's
     * TEXT-typed date columns, so a plain "2025-06-30" upper bound would
     * exclude the last day of the period there; MySQL compares a DATE
     * against a datetime string correctly either way. Full-day bounds are
     * right on both engines.
     *
     * @return array{0:string,1:string}
     */
    public static function bounds(ReportPeriod $period): array
    {
        return [
            $period->start()->startOfDay()->toDateTimeString(),
            $period->end()->endOfDay()->toDateTimeString(),
        ];
    }

    /**
     * payments.bill_type is free text written by several screens
     * ('Maintenance', 'maintenance', 'rent', 'Water Bill', ...). Map it to
     * a small set of report groups.
     */
    public function incomeGroup(?string $billType): string
    {
        $t = strtolower(trim((string) $billType));

        if ($t === 'rent') {
            return self::INCOME_RENT;
        }
        if ($t === '' || str_contains($t, 'maint')) {
            return self::INCOME_MAINTENANCE;
        }
        if (preg_match('/water|electric|utility|gas|common/', $t)) {
            return self::INCOME_UTILITY;
        }

        return self::INCOME_OTHER;
    }

    /** One transaction as the drill-down lists show it. */
    private function paymentLine(Payment $p, string $source): array
    {
        return [
            'date'      => $p->payment_date?->format('d M Y') ?? ($p->created_at?->format('d M Y') ?? ''),
            'date_sort' => $p->payment_date?->toDateTimeString() ?? ($p->created_at?->toDateTimeString() ?? ''),
            'property'  => $this->flatLabel($p->flat),
            'source'    => $source,
            'method'    => $p->payment_method ? str_replace('_', ' ', $p->payment_method) : '',
            'reference' => $p->receipt_number ?: ($p->transaction_id ?: $p->payment_id),
            'amount'    => Money::paise($p->amount) / 100,
        ];
    }

    private function flatLabel(?Flat $flat): string
    {
        if (!$flat) {
            return 'Unknown property';
        }

        if ($flat->property_type === 'villa') {
            return 'Villa ' . $flat->flat_number;
        }

        return ($flat->building->name ?? 'Building') . ' - ' . $flat->flat_number;
    }
}
