<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\UtilityBill;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the financial report for one society and one period.
 *
 * This is the single source of the report's numbers: the web page and the
 * PDF both render the array returned here, so they cannot drift apart.
 *
 * The non-negotiable rule: only expenses with scope = society are society
 * expenses. A property's own expense (Villa 160's electricity) is never part
 * of "Total Society Expenses" or the category breakdown, and the report does
 * not display property expenses at all - an individual property's bills and
 * payments belong to the owner/tenant billing workflow, not to society
 * finances. Individual bills collected from residents are collections
 * (income), never society expense.
 *
 * Accounting basis: cash. Income is payments with status `success` whose
 * payment_date falls in the period; expenses are approved expense rows whose
 * expense_date falls in the period. No opening/closing balance is shown
 * because the application does not keep a ledger from which one could be
 * calculated honestly.
 */
class FinancialReportService
{
    /** Income groups, in display order. */
    private const INCOME_MAINTENANCE = 'Maintenance';
    private const INCOME_UTILITY     = 'Utility / Common Charges';
    private const INCOME_OTHER       = 'Other Collections';
    private const INCOME_RENT        = 'Rent';

    /**
     * Society-wide report (Admin / Accountant / Super Admin).
     */
    public function build(int $societyId, ReportPeriod $period): array
    {
        [$start, $end] = $this->bounds($period);

        $income     = $this->income($societyId, $start, $end);
        $expenses   = $this->societyExpenses($societyId, $start, $end);
        $billing   = $this->maintenanceBilling($societyId, $start, $end);
        $properties = $this->propertyRows($societyId, $start, $end);

        $outstandingAllTime = $this->outstandingMaintenanceDues($societyId);

        $transactionCount = $income['count'] + $income['rent_count'] + $expenses['count'] + $billing['bills_count'];

        return [
            'mode'   => 'society',
            'period' => $period->toArray(),

            'overview' => [
                'total_income'           => $income['total'],
                'maintenance_collected'  => $income['maintenance'],
                'other_income'           => $income['total'] - $income['maintenance'],
                'rent_collected'         => $income['rent'],
                'total_society_expenses' => $expenses['total'],
                'outstanding_dues'       => $outstandingAllTime,
                'net_position'           => $income['total'] - $expenses['total'],
            ],

            'income'            => $income,
            'expenses'          => $expenses,
            'billing'           => $billing,
            'properties'        => $properties,

            'has_data'          => $transactionCount > 0,
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

        $outstanding = (float) MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->whereIn('flat_id', $flatIds ?: [0])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('balance_amount');

        $paymentRows = $payments->map(fn (Payment $p) => [
            'date'     => $p->payment_date?->format('d M Y') ?? '',
            'property' => $this->flatLabel($p->flat),
            'type'     => $this->incomeGroup($p->bill_type),
            'receipt'  => $p->receipt_number,
            'amount'   => (float) $p->amount,
        ])->values()->all();

        $billRows = $bills->map(fn (MaintenanceBill $b) => [
            'date'     => $b->bill_date?->format('d M Y') ?? '',
            'property' => $this->flatLabel($b->flat),
            'number'   => $b->bill_number,
            'type'     => ucfirst($b->bill_type ?: 'maintenance'),
            'billed'   => (float) $b->total_amount,
            'paid'     => (float) $b->paid_amount,
            'due'      => (float) $b->balance_amount,
        ])->values()->all();

        $utilityRows = $utilityBills->map(fn (UtilityBill $u) => [
            'date'     => $u->bill_date?->format('d M Y') ?? '',
            'property' => $this->flatLabel($u->flat),
            'type'     => $u->bill_type,
            'amount'   => (float) $u->bill_amount,
            'status'   => ucfirst($u->status),
        ])->values()->all();

        $transactionCount = count($paymentRows) + count($billRows) + count($utilityRows);

        return [
            'mode'   => 'property',
            'period' => $period->toArray(),

            'overview' => [
                'total_paid'       => (float) $payments->sum('amount'),
                'total_billed'     => (float) $bills->sum('total_amount'),
                'period_due'       => (float) $bills->sum('balance_amount'),
                'outstanding_dues' => $outstanding,
            ],

            'payments'          => ['rows' => $paymentRows, 'total' => (float) $payments->sum('amount')],
            'bills'             => ['rows' => $billRows, 'billed' => (float) $bills->sum('total_amount'), 'paid' => (float) $bills->sum('paid_amount'), 'due' => (float) $bills->sum('balance_amount')],
            'utility_bills'     => ['rows' => $utilityRows, 'total' => (float) $utilityBills->sum('bill_amount')],

            'has_data'          => $transactionCount > 0,
            'transaction_count' => $transactionCount,
        ];
    }

    // ------------------------------------------------------------------
    // Society sections
    // ------------------------------------------------------------------

    /**
     * Successful payments in the period, grouped by what they paid for.
     *
     * Rent is tracked by the society on behalf of owners but is not the
     * society's own income, so it is reported as a memo line and excluded
     * from total_income. Every other collection counts.
     */
    private function income(int $societyId, string $start, string $end): array
    {
        $grouped = Payment::query()
            ->where('society_id', $societyId)
            ->where('status', 'success')
            ->whereBetween('payment_date', [$start, $end])
            ->groupBy('bill_type')
            ->select('bill_type', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->get();

        $buckets = [
            self::INCOME_MAINTENANCE => ['amount' => 0.0, 'count' => 0],
            self::INCOME_UTILITY     => ['amount' => 0.0, 'count' => 0],
            self::INCOME_OTHER       => ['amount' => 0.0, 'count' => 0],
        ];
        $rent = ['amount' => 0.0, 'count' => 0];

        foreach ($grouped as $row) {
            $group = $this->incomeGroup($row->bill_type);

            if ($group === self::INCOME_RENT) {
                $rent['amount'] += (float) $row->total;
                $rent['count']  += (int) $row->cnt;
                continue;
            }

            $buckets[$group]['amount'] += (float) $row->total;
            $buckets[$group]['count']  += (int) $row->cnt;
        }

        $rows = [];
        foreach ($buckets as $source => $b) {
            if ($b['count'] === 0) {
                continue;
            }
            $rows[] = ['source' => $source, 'amount' => $b['amount'], 'count' => $b['count']];
        }

        $total = array_sum(array_column($rows, 'amount'));
        $count = array_sum(array_column($rows, 'count'));

        return [
            'rows'        => $rows,
            'total'       => $total,
            'count'       => $count,
            'maintenance' => $buckets[self::INCOME_MAINTENANCE]['amount'],
            'rent'        => $rent['amount'],
            'rent_count'  => $rent['count'],
        ];
    }

    /** Approved society-scoped expenses in the period, by category. */
    private function societyExpenses(int $societyId, string $start, string $end): array
    {
        $grouped = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.society_id', $societyId)
            ->where('expenses.scope', Expense::SCOPE_SOCIETY)
            ->where('expenses.status', 'approved')
            ->whereBetween('expenses.expense_date', [$start, $end])
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->select(
                'expense_categories.id as category_id',
                'expense_categories.name as category',
                DB::raw('SUM(expenses.amount) as total'),
                DB::raw('COUNT(*) as cnt')
            )
            ->orderByDesc('total')
            ->get();

        $total = (float) $grouped->sum('total');

        $rows = $grouped->map(fn ($row) => [
            'category_id' => (int) $row->category_id,
            'category'    => $row->category,
            'amount'      => (float) $row->total,
            'count'       => (int) $row->cnt,
            'percent'     => $total > 0 ? round(((float) $row->total / $total) * 100, 1) : 0.0,
        ])->values()->all();

        return [
            'rows'  => $rows,
            'total' => $total,
            'count' => (int) $grouped->sum('cnt'),
        ];
    }

    /**
     * Maintenance bills *issued* in the period: billed, collected against
     * them, and still outstanding on them. Rent bills share the table and
     * are excluded - they are not society billing.
     */
    private function maintenanceBilling(int $societyId, string $start, string $end): array
    {
        $agg = MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->where('bill_type', 'maintenance')
            ->whereBetween('bill_date', [$start, $end])
            ->select(
                DB::raw('COUNT(*) as bills_count'),
                DB::raw('COALESCE(SUM(total_amount), 0) as billed'),
                DB::raw('COALESCE(SUM(paid_amount), 0) as collected'),
                DB::raw('COALESCE(SUM(balance_amount), 0) as outstanding'),
                DB::raw('COALESCE(SUM(maintenance_amount), 0) as maintenance_amount'),
                DB::raw('COALESCE(SUM(water_charges), 0) as water_charges'),
                DB::raw('COALESCE(SUM(electricity_charges), 0) as electricity_charges'),
                DB::raw('COALESCE(SUM(parking_charges), 0) as parking_charges'),
                DB::raw('COALESCE(SUM(penalty_amount), 0) as penalty_amount'),
                DB::raw('COALESCE(SUM(other_charges), 0) as other_charges')
            )
            ->first();

        $charges = [
            ['label' => 'Maintenance', 'amount' => (float) $agg->maintenance_amount],
            ['label' => 'Water charges', 'amount' => (float) $agg->water_charges],
            ['label' => 'Electricity charges', 'amount' => (float) $agg->electricity_charges],
            ['label' => 'Parking charges', 'amount' => (float) $agg->parking_charges],
            ['label' => 'Penalty', 'amount' => (float) $agg->penalty_amount],
            ['label' => 'Other charges', 'amount' => (float) $agg->other_charges],
        ];

        return [
            'bills_count' => (int) $agg->bills_count,
            'billed'      => (float) $agg->billed,
            'collected'   => (float) $agg->collected,
            'outstanding' => (float) $agg->outstanding,
            'charges'     => array_values(array_filter($charges, fn ($c) => $c['amount'] > 0)),
        ];
    }

    /** Per-property billed / paid / due for maintenance bills issued in the period. */
    private function propertyRows(int $societyId, string $start, string $end): array
    {
        $grouped = MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->where('bill_type', 'maintenance')
            ->whereBetween('bill_date', [$start, $end])
            ->groupBy('flat_id')
            ->select(
                'flat_id',
                DB::raw('SUM(total_amount) as billed'),
                DB::raw('SUM(paid_amount) as paid'),
                DB::raw('SUM(balance_amount) as due')
            )
            ->get();

        if ($grouped->isEmpty()) {
            return [];
        }

        $flats = Flat::with('building')
            ->whereIn('id', $grouped->pluck('flat_id'))
            ->get()
            ->keyBy('id');

        return $grouped
            ->map(fn ($row) => [
                'property' => $this->flatLabel($flats->get($row->flat_id)),
                'billed'   => (float) $row->billed,
                'paid'     => (float) $row->paid,
                'due'      => (float) $row->due,
            ])
            ->sortBy('property', SORT_NATURAL)
            ->values()
            ->all();
    }

    /** All-time unpaid maintenance (not limited to the period). */
    private function outstandingMaintenanceDues(int $societyId): float
    {
        return (float) MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->where('bill_type', 'maintenance')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sum('balance_amount');
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
