<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Services\FinancialReportService;
use App\Support\ReportPeriod;
use App\Support\UserFlatResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PDF;

/**
 * Two reports, kept deliberately separate:
 *
 *  - Maintenance Report: "how much maintenance was billed and collected?"
 *  - Financial Report:   "how is the society doing financially, and where
 *                         is its money going?" - income, society expenses
 *                         by category, collections, dues, net position.
 *
 * Both resolve their period through ReportPeriod (so the queried period,
 * the on-screen label and the PDF can never disagree) and the financial
 * report's numbers come from FinancialReportService for the page and the
 * PDF alike - there is no second calculation path.
 */
class ReportController extends Controller
{
    private const MAINTENANCE_ROLES = ['Super Admin', 'Admin', 'Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'];
    private const FINANCIAL_ROLES   = ['Super Admin', 'Admin', 'Owner', 'Villa Owner', 'Apartment Owner', 'Tenant', 'Accountant'];

    /** Roles that see the society-wide report rather than their own units. */
    private const SOCIETY_WIDE_ROLES = ['Super Admin', 'Admin', 'Accountant'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    // ------------------------------------------------------------------
    // Maintenance report
    // ------------------------------------------------------------------

    public function maintenanceReport(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasAnyRole(self::MAINTENANCE_ROLES)) {
            abort(403, 'Unauthorized access to reports.');
        }

        try {
            $period = ReportPeriod::fromRequest($request);
        } catch (ValidationException $e) {
            return redirect()->route('reports.maintenance')->withErrors($e->errors());
        }

        $data = $this->getMaintenanceData($user->society_id, $period, $user);

        return view('reports.maintenance', [
            'period'     => $period,
            'reportType' => $period->type,
            'month'      => $period->month ?? now()->month,
            'year'       => $period->year,
            'data'       => $data,
        ]);
    }

    public function downloadMaintenanceReport(Request $request)
    {
        $user = auth()->user();

        if (!$user->hasAnyRole(self::MAINTENANCE_ROLES)) {
            abort(403, 'Unauthorized access to reports.');
        }

        try {
            $period = ReportPeriod::fromRequest($request);
        } catch (ValidationException $e) {
            return redirect()->route('reports.maintenance')->withErrors($e->errors());
        }

        try {
            $data = $this->getMaintenanceData($user->society_id, $period, $user);

            $pdf = PDF::loadView('reports.maintenance-pdf', [
                'society'     => $user->society,
                'reportType'  => $period->type,
                'periodLabel' => $period->label(),
                'data'        => $data,
            ]);

            return $pdf->download("Maintenance-Report-{$period->slug()}.pdf");
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Financial report
    // ------------------------------------------------------------------

    public function financialReport(Request $request, FinancialReportService $service)
    {
        $user = auth()->user();

        if (!$user->hasAnyRole(self::FINANCIAL_ROLES)) {
            abort(403, 'Unauthorized access to reports.');
        }

        try {
            $period = ReportPeriod::fromRequest($request);
        } catch (ValidationException $e) {
            return redirect()->route('reports.financial')->withErrors($e->errors());
        }

        $report = $this->financialReportFor($user, $period, $service);

        return view('reports.financial', [
            'period'     => $period,
            'reportType' => $period->type,
            'month'      => $period->month ?? now()->month,
            'year'       => $period->year,
            'report'     => $report,
            'society'    => $user->society,
        ]);
    }

    public function downloadFinancialReport(Request $request, FinancialReportService $service)
    {
        $user = auth()->user();

        if (!$user->hasAnyRole(self::FINANCIAL_ROLES)) {
            abort(403, 'Unauthorized access to reports.');
        }

        try {
            $period = ReportPeriod::fromRequest($request);
        } catch (ValidationException $e) {
            return redirect()->route('reports.financial')->withErrors($e->errors());
        }

        try {
            // Exactly the same array the page rendered. Screen = PDF.
            $report = $this->financialReportFor($user, $period, $service);

            $pdf = PDF::loadView('reports.financial-pdf', [
                'society'     => $user->society,
                'period'      => $period,
                'periodLabel' => $period->label(),
                'report'      => $report,
                'generatedBy' => $user->name,
            ]);

            return $pdf->download("Financial-Report-{$period->slug()}.pdf");
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Society-wide for finance roles; a resident sees only their own units
     * (bills, payments, property charges) and never the society's internal
     * expense management.
     *
     * Returns null when there is no society to report on (a Super Admin
     * who has not switched into a society).
     */
    private function financialReportFor($user, ReportPeriod $period, FinancialReportService $service): ?array
    {
        $societyId = $user->society_id;

        if (!$societyId) {
            return null;
        }

        if ($user->hasAnyRole(self::SOCIETY_WIDE_ROLES)) {
            return $service->build((int) $societyId, $period);
        }

        return $service->buildForFlats((int) $societyId, $period, UserFlatResolver::flatIdsFor($user));
    }

    // ------------------------------------------------------------------
    // Maintenance data
    // ------------------------------------------------------------------

    /**
     * Successful maintenance payments in the period. Residents (owner or
     * tenant) see only their own units, resolved server-side.
     */
    private function getMaintenanceData(?int $societyId, ReportPeriod $period, $user): array
    {
        if (!$societyId) {
            return ['total_maintenance' => 0.0, 'count' => 0, 'payments' => collect(), 'billed' => 0.0, 'outstanding' => 0.0];
        }

        [$start, $end] = FinancialReportService::bounds($period);

        // A maintenance payment is one recorded against a maintenance bill
        // OR an ad-hoc payment whose own bill_type says maintenance (the
        // payment form allows both). Rent shares the bills table and is
        // never maintenance; the old report counted it - and counted
        // pending payments - which is where "₹36,800 / 3 transactions" for a
        // year with no maintenance collected at all came from.
        $query = Payment::query()
            ->leftJoin('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
            ->where('payments.society_id', $societyId)
            ->where('payments.status', 'success')
            ->whereBetween('payments.payment_date', [$start, $end])
            ->where(function ($q) {
                $q->where('maintenance_bills.bill_type', 'maintenance')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('payments.maintenance_bill_id')
                         ->whereRaw('LOWER(payments.bill_type) LIKE ?', ['%maint%']);
                  });
            });

        $bills = MaintenanceBill::query()
            ->where('society_id', $societyId)
            ->where('bill_type', 'maintenance')
            ->whereBetween('bill_date', [$start, $end]);

        if (!$user->hasAnyRole(self::SOCIETY_WIDE_ROLES)) {
            $flatIds = UserFlatResolver::flatIdsFor($user) ?: [0];
            $query->where(function ($q) use ($flatIds) {
                $q->whereIn('maintenance_bills.flat_id', $flatIds)
                  ->orWhereIn('payments.flat_id', $flatIds);
            });
            $bills->whereIn('flat_id', $flatIds);
        }

        $payments = $query->select('payments.*')->with('flat')->orderBy('payments.payment_date')->get();

        return [
            'total_maintenance' => (float) $payments->sum('amount'),
            'count'             => $payments->count(),
            'payments'          => $payments,
            'billed'            => (float) $bills->sum('total_amount'),
            'outstanding'       => (float) (clone $bills)->sum('balance_amount'),
        ];
    }
}
