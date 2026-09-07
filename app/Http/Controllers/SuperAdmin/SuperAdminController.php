<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Society;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class SuperAdminController extends Controller
{
    public function dashboard(): View
    {
        $stats = [
            'total_societies' => Society::count(),
            'active_societies' => Society::active()->count(),
            'total_users' => User::count(),
            'total_revenue' => Payment::success()->sum('amount'),
            'monthly_revenue' => Payment::success()
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'active_subscriptions' => Subscription::where('status', 'active')
                ->where('end_date', '>=', now())
                ->count(),
        ];

        // Recent societies
        $recentSocieties = Society::with('subscription')
            ->latest()
            ->take(5)
            ->get();

        // Revenue chart data (last 12 months)
        $revenueData = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = Payment::success()
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('amount');
            
            $revenueData[] = [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        }

        // Subscription distribution
        $subscriptionStats = Subscription::selectRaw('subscription_plan_id, COUNT(*) as count')
            ->where('status', 'active')
            ->groupBy('subscription_plan_id')
            ->with('subscriptionPlan')
            ->get();

        return view('super-admin.dashboard', compact(
            'stats',
            'recentSocieties',
            'revenueData',
            'subscriptionStats'
        ));
    }

    public function revenueReport(Request $request): View
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        $payments = Payment::success()
            ->with(['society', 'user'])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('payment_date', 'desc')
            ->paginate(50);

        $totalRevenue = Payment::success()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        $revenueByMonth = Payment::success()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('YEAR(payment_date) as year, MONTH(payment_date) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return view('super-admin.reports.revenue', compact(
            'payments',
            'totalRevenue',
            'revenueByMonth',
            'startDate',
            'endDate'
        ));
    }

    public function societiesReport(): View
    {
        $societies = Society::with(['subscription.subscriptionPlan'])
            ->withCount(['users', 'flats', 'maintenanceBills'])
            ->paginate(20);

        $stats = [
            'total_societies' => Society::count(),
            'active_societies' => Society::active()->count(),
            'suspended_societies' => Society::where('status', 'suspended')->count(),
            'trial_societies' => Society::whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '>', now())
                ->count(),
        ];

        return view('super-admin.reports.societies', compact('societies', 'stats'));
    }

    public function settings(): View
    {
        $settings = [
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'mail_from_name' => config('mail.from.name'),
            'mail_from_address' => config('mail.from.address'),
            'demo_mode' => env('SOCIETYFLOW_DEMO_MODE', false),
        ];

        return view('super-admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'app_url' => 'required|url',
            'mail_from_name' => 'required|string|max:255',
            'mail_from_address' => 'required|email',
            'demo_mode' => 'boolean',
        ]);

        // Update .env file (in production, use a proper configuration management system)
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        $updates = [
            'APP_NAME' => '"' . $request->app_name . '"',
            'APP_URL' => $request->app_url,
            'MAIL_FROM_NAME' => '"' . $request->mail_from_name . '"',
            'MAIL_FROM_ADDRESS' => $request->mail_from_address,
            'SOCIETYFLOW_DEMO_MODE' => $request->demo_mode ? 'true' : 'false',
        ];

        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envFile, $envContent);

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}