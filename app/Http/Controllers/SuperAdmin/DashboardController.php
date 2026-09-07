<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Society;
use App\Models\User;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_societies' => Society::count(),
            'active_societies' => Society::where('status', 'active')->count(),
            'trial_societies' => Society::where('status', 'trial')->count(),
            'expired_societies' => Society::where('status', 'expired')->count(),
            'total_users' => User::count(),
            'monthly_revenue' => SubscriptionPayment::whereMonth('created_at', now()->month)->sum('amount'),
            'yearly_revenue' => SubscriptionPayment::whereYear('created_at', now()->year)->sum('amount'),
        ];

        // Recent societies
        $recentSocieties = Society::with('subscription.subscriptionPlan')
            ->latest()
            ->take(10)
            ->get();

        // Subscription trends (last 7 days)
        $subscriptionTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $subscriptionTrends[] = [
                'date' => $date->format('M d'),
                'count' => Society::whereDate('created_at', $date)->count()
            ];
        }

        // Revenue by plan
        $revenueByPlan = SubscriptionPlan::withSum('subscriptionPayments', 'amount')
            ->get()
            ->map(function ($plan) {
                return [
                    'name' => $plan->name,
                    'revenue' => $plan->subscription_payments_sum_amount ?? 0
                ];
            });

        return view('super-admin.dashboard', compact(
            'stats',
            'recentSocieties',
            'subscriptionTrends',
            'revenueByPlan'
        ));
    }
}