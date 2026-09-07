<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\UtilityBill;
use App\Models\Payment;
use App\Models\Rent;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use PDF;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show Maintenance Report page
     */
    public function maintenanceReport(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // Check access
        if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Owner', 'Tenant'])) {
            abort(403, 'Unauthorized access to reports.');
        }

        $reportType = $request->get('report_type', 'monthly');
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        return view('reports.maintenance', compact('reportType', 'month', 'year'));
    }

    /**
     * Download Maintenance Report as PDF
     */
    public function downloadMaintenanceReport(Request $request)
    {
        try {
            $user = auth()->user();
            $societyId = $user->society_id;

            // Check access
            if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Owner', 'Tenant'])) {
                abort(403, 'Unauthorized access to reports.');
            }

            $reportType = $request->get('report_type', 'monthly');
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            // Get society name
            $society = $user->society;

            // Prepare data based on report type
            if ($reportType === 'monthly') {
                $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                $periodLabel = $startDate->format('F Y');
            } else {
                $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $endDate = $startDate->copy()->endOfYear();
                $periodLabel = $year;
            }

            // Get maintenance data (from payments/bills)
            $maintenanceData = $this->getMaintenanceData($societyId, $startDate, $endDate, $user);

            $pdf = PDF::loadView('reports.maintenance-pdf', [
                'society' => $society,
                'reportType' => $reportType,
                'periodLabel' => $periodLabel,
                'data' => $maintenanceData,
            ]);

            $filename = "Maintenance-Report-{$periodLabel}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Show Financial Report page
     */
    public function financialReport(Request $request)
    {
        $user = auth()->user();
        $societyId = $user->society_id;

        // Check access
        if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Owner', 'Tenant'])) {
            abort(403, 'Unauthorized access to reports.');
        }

        $reportType = $request->get('report_type', 'monthly');
        $month = (int)$request->get('month', now()->month);
        $year = (int)$request->get('year', now()->year);

        // Get financial data
        $financialData = $this->getFinancialData($societyId, $reportType, $month, $year, $user);

        return view('reports.financial', compact('reportType', 'month', 'year', 'financialData'));
    }

    /**
     * Download Financial Report as PDF
     */
    public function downloadFinancialReport(Request $request)
    {
        try {
            $user = auth()->user();
            $societyId = $user->society_id;

            // Check access
            if (!$user->hasAnyRole(['Super Admin', 'Admin', 'Owner', 'Tenant'])) {
                abort(403, 'Unauthorized access to reports.');
            }

            $reportType = $request->get('report_type', 'monthly');
            $month = $request->get('month', now()->month);
            $year = $request->get('year', now()->year);

            // Get society name
            $society = $user->society;

            // Prepare period label
            if ($reportType === 'monthly') {
                $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $periodLabel = $startDate->format('F Y');
            } else {
                $periodLabel = $year;
            }

            // Get financial data
            $financialData = $this->getFinancialData($societyId, $reportType, $month, $year, $user);

            $pdf = PDF::loadView('reports.financial-pdf', [
                'society' => $society,
                'reportType' => $reportType,
                'periodLabel' => $periodLabel,
                'data' => $financialData,
            ]);

            $filename = "Financial-Report-{$periodLabel}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Get maintenance data for report
     */
    private function getMaintenanceData($societyId, $startDate, $endDate, $user)
    {
        // Get maintenance charges from payments through maintenance bills
        $query = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
            ->whereBetween('payments.payment_date', [$startDate, $endDate])
            ->where('payments.society_id', $societyId);

        // If owner, only their properties
        if ($user->hasRole('Owner')) {
            $flatIds = Flat::where('society_id', $societyId)
                ->whereHas('residents', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->pluck('id');

            $query->whereIn('maintenance_bills.flat_id', $flatIds);
        }

        $payments = $query->select('payments.*')->get();

        return [
            'total_maintenance' => $payments->sum('amount'),
            'count' => $payments->count(),
            'payments' => $payments,
        ];
    }

    /**
     * Get financial data for report
     */
    private function getFinancialData($societyId, $reportType, $month, $year, $user)
    {
        // Get apartments/villas
        $flats = Flat::where('society_id', $societyId)->where('property_type', '!=', 'villa')->get();
        $villas = Flat::where('society_id', $societyId)->villas()->get();

        $data = [];
        
        // Check if utility_bills table exists
        $utilityBillsTableExists = \Schema::hasTable('utility_bills');

        // Process apartments
        foreach ($flats as $flat) {
            // If owner, only show their own
            if ($user->hasRole('Owner')) {
                $isUserFlat = $flat->residents()->where('user_id', $user->id)->exists();
                if (!$isUserFlat) {
                    continue;
                }
            }

            // If tenant, only show their own
            if ($user->hasRole('Tenant')) {
                $isUserFlat = $flat->residents()->where('user_id', $user->id)->exists();
                if (!$isUserFlat) {
                    continue;
                }
            }

            // Get payments through maintenance bills filtered by month/year
            if ($reportType === 'monthly') {
                $maintenanceBilled = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
                    ->where('maintenance_bills.flat_id', $flat->id)
                    ->where('maintenance_bills.month', $month)
                    ->where('maintenance_bills.year', $year)
                    ->sum('payments.amount');

                $maintenancePaid = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
                    ->where('maintenance_bills.flat_id', $flat->id)
                    ->where('maintenance_bills.month', $month)
                    ->where('maintenance_bills.year', $year)
                    ->where('payments.status', 'paid')
                    ->sum('payments.amount');
            } else {
                // For annual, get all months in that year
                $maintenanceBilled = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
                    ->where('maintenance_bills.flat_id', $flat->id)
                    ->where('maintenance_bills.year', $year)
                    ->sum('payments.amount');

                $maintenancePaid = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
                    ->where('maintenance_bills.flat_id', $flat->id)
                    ->where('maintenance_bills.year', $year)
                    ->where('payments.status', 'paid')
                    ->sum('payments.amount');
            }

            $utilityBilled = 0;
            $utilityPaid = 0;
            
            if ($utilityBillsTableExists) {
                if ($reportType === 'monthly') {
                    $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();
                } else {
                    $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                    $endDate = $startDate->copy()->endOfYear();
                }

                $utilityBilled = UtilityBill::where('flat_id', $flat->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');

                $utilityPaid = UtilityBill::where('flat_id', $flat->id)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');
            }

            $totalPaid = $maintenancePaid + $utilityPaid;
            $totalDues = ($maintenanceBilled - $maintenancePaid) + ($utilityBilled - $utilityPaid);

            // Only include if there's data for this period
            if ($maintenanceBilled > 0 || $utilityBilled > 0) {
                $data[] = [
                    'unit' => $flat->flat_number ?? $flat->name,
                    'type' => 'Apartment',
                    'maintenance_billed' => $maintenanceBilled,
                    'maintenance_paid' => $maintenancePaid,
                    'utility_billed' => $utilityBilled,
                    'utility_paid' => $utilityPaid,
                    'total_paid' => $totalPaid,
                    'total_dues' => $totalDues,
                ];
            }
        }

        // Process villas
        foreach ($villas as $villa) {
            // If owner, only show their own
            if ($user->hasRole('Owner')) {
                $isUserVilla = $villa->residents()->where('user_id', $user->id)->exists();
                if (!$isUserVilla) {
                    continue;
                }
            }

            // If tenant, only show their own
            if ($user->hasRole('Tenant')) {
                $isUserVilla = $villa->residents()->where('user_id', $user->id)->exists();
                if (!$isUserVilla) {
                    continue;
                }
            }

            // Get payments through maintenance bills filtered by month/year
            if ($reportType === 'monthly') {
                $maintenanceBilled = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
                    ->where('maintenance_bills.flat_id', $villa->id)
                    ->where('maintenance_bills.month', $month)
                    ->where('maintenance_bills.year', $year)
                    ->sum('payments.amount');

                $maintenancePaid = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
                    ->where('maintenance_bills.flat_id', $villa->id)
                    ->where('maintenance_bills.month', $month)
                    ->where('maintenance_bills.year', $year)
                    ->where('payments.status', 'paid')
                    ->sum('payments.amount');
            } else {
                // For annual, get all months in that year
                $maintenanceBilled = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
                    ->where('maintenance_bills.flat_id', $villa->id)
                    ->where('maintenance_bills.year', $year)
                    ->sum('payments.amount');

                $maintenancePaid = Payment::join('maintenance_bills', 'payments.maintenance_bill_id', '=', 'maintenance_bills.id')
                    ->where('maintenance_bills.flat_id', $villa->id)
                    ->where('maintenance_bills.year', $year)
                    ->where('payments.status', 'paid')
                    ->sum('payments.amount');
            }

            $utilityBilled = 0;
            $utilityPaid = 0;
            
            if ($utilityBillsTableExists) {
                if ($reportType === 'monthly') {
                    $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();
                } else {
                    $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
                    $endDate = $startDate->copy()->endOfYear();
                }

                $utilityBilled = UtilityBill::where('flat_id', $villa->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');

                $utilityPaid = UtilityBill::where('flat_id', $villa->id)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('amount');
            }

            $totalPaid = $maintenancePaid + $utilityPaid;
            $totalDues = ($maintenanceBilled - $maintenancePaid) + ($utilityBilled - $utilityPaid);

            // Only include if there's data for this period
            if ($maintenanceBilled > 0 || $utilityBilled > 0) {
                $data[] = [
                    'unit' => $villa->villa_name ?? $villa->flat_number,
                    'type' => 'Villa',
                    'maintenance_billed' => $maintenanceBilled,
                    'maintenance_paid' => $maintenancePaid,
                    'utility_billed' => $utilityBilled,
                    'utility_paid' => $utilityPaid,
                    'total_paid' => $totalPaid,
                    'total_dues' => $totalDues,
                ];
            }
        }

        return $data;
    }
}
