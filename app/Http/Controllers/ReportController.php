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
     * Maintenance billing and cash for the period, from the one calculation layer (FinancialReportService::maintenanceReport):
     * what was billed, what is owed on those bills, the cash that arrived (with evidence) and which bills it settled.
     * Residents (owner or tenant) see only their own units, resolved server-side.
     */
    private function getMaintenanceData(?int $societyId, ReportPeriod $period, $user): array
    {
        if (!$societyId) {
            return [
                'billed' => 0.0, 'outstanding' => 0.0, 'collected_on_these_bills' => 0.0, 'no_evidence_on_these_bills' => 0.0, 'total_maintenance' => 0.0, 'count' => 0,
                'payments' => collect(), 'no_evidence' => ['amount' => 0.0, 'count' => 0, 'rows' => collect()], 'cash_against_this_period_bills' => 0.0,
                'cash_against_earlier_bills' => 0.0, 'cash_against_later_bills' => 0.0, 'cash_unallocated' => 0.0, 'outstanding_all_time' => 0.0,
            ];
        }

        $flatIds = $user->hasAnyRole(self::SOCIETY_WIDE_ROLES) ? null : (UserFlatResolver::flatIdsFor($user) ?: [0]);

        return app(FinancialReportService::class)->maintenanceReport($societyId, $period, $flatIds);
    }
}
