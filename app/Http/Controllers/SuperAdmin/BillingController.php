<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\Society;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $query = SubscriptionPayment::with(['society', 'subscriptionPlan']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('society', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })->orWhere('transaction_id', 'like', "%{$search}%");
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->latest()->paginate(15);

        // Statistics
        $stats = [
            'total_revenue' => SubscriptionPayment::where('status', 'completed')->sum('amount'),
            'monthly_revenue' => SubscriptionPayment::where('status', 'completed')
                ->whereMonth('created_at', now()->month)->sum('amount'),
            'pending_payments' => SubscriptionPayment::where('status', 'pending')->count(),
            'failed_payments' => SubscriptionPayment::where('status', 'failed')->count(),
        ];

        return view('super-admin.billing.index', compact('payments', 'stats'));
    }

    public function show(SubscriptionPayment $payment)
    {
        $payment->load(['society', 'subscriptionPlan']);
        return view('super-admin.billing.show', compact('payment'));
    }

    public function markAsPaid(SubscriptionPayment $payment)
    {
        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Update society subscription
        $society = $payment->society;
        if ($payment->billing_cycle === 'monthly') {
            $society->subscription_ends_at = now()->addMonth();
        } elseif ($payment->billing_cycle === 'annual') {
            $society->subscription_ends_at = now()->addYear();
        } elseif ($payment->billing_cycle === 'lifetime') {
            $society->subscription_ends_at = null; // Lifetime
        }
        
        $society->status = 'active';
        $society->save();

        return back()->with('success', 'Payment marked as paid successfully!');
    }

    public function downloadInvoice(SubscriptionPayment $payment)
    {
        // Generate PDF invoice
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('super-admin.billing.invoice', compact('payment'));
        
        return $pdf->download('invoice-' . $payment->id . '.pdf');
    }
}