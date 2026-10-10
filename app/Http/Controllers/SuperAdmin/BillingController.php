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
        $query = SubscriptionPayment::with(['society', 'subscriptionPlan', 'subscription']);

        // Search functionality.
        //
        // The orWhere used to sit outside a group, so it escaped the search
        // clause entirely: OR binds across the whole WHERE, which meant any
        // row with a matching transaction_id came back regardless of the
        // status and date filters applied below.
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('society', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                })->orWhere('transaction_id', 'like', "%{$search}%");
            });
        }

        // Status filter (the table allows pending | success | failed | refunded)
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

        $payments = $query->latest()->paginate(15)->withQueryString();

        // Statistics. These counted status 'completed', which the table's CHECK
        // constraint does not allow, so revenue could only ever read zero.
        $stats = [
            'total_revenue' => SubscriptionPayment::where('status', 'success')->sum('amount'),
            'monthly_revenue' => SubscriptionPayment::where('status', 'success')
                ->whereYear('payment_date', now()->year)->whereMonth('payment_date', now()->month)->sum('amount'),
            'pending_payments' => SubscriptionPayment::where('status', 'pending')->count(),
            'failed_payments' => SubscriptionPayment::where('status', 'failed')->count(),
        ];

        return view('super-admin.billing.index', compact('payments', 'stats'));
    }

    public function show(SubscriptionPayment $payment)
    {
        $payment->load(['subscription', 'society', 'subscriptionPlan']);

        return view('super-admin.billing.show', compact('payment'));
    }

    /**
     * Record that a pending/failed subscription payment was received (offline,
     * bank transfer...) and extend the subscription by one cycle. Idempotent: a
     * payment already marked paid is not extended a second time.
     *
     * The extension starts from the later of "now" and the current end date, so
     * paying late does not leave the plan already expired.
     */
    public function markAsPaid(SubscriptionPayment $payment)
    {
        if ($payment->status === 'success') {
            return back()->with('success', 'This payment was already marked as paid.');
        }
        if ($payment->status === 'refunded') {
            return back()->with('error', 'A refunded payment cannot be marked as paid.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'success', 'payment_date' => now()->toDateString()]);

            $subscription = $payment->subscription;
            $society = $subscription?->society;
            if (!$subscription || !$society) {
                return;
            }

            $from = $subscription->end_date && $subscription->end_date->isFuture() ? $subscription->end_date->copy() : now();
            $end = $subscription->billing_cycle === 'yearly' ? $from->copy()->addYear() : $from->copy()->addMonth();

            $subscription->update([
                'status' => 'active',
                'end_date' => $end->toDateString(),
                'next_billing_date' => $end->toDateString(),
            ]);

            $society->subscription_ends_at = $end;
            $society->status = 'active';
            $society->save();
        });

        return back()->with('success', 'Payment marked as paid and the subscription extended.');
    }
    public function downloadInvoice(SubscriptionPayment $payment)
    {
        // Generate PDF invoice
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('super-admin.billing.invoice', compact('payment'));
        
        return $pdf->download('invoice-' . $payment->id . '.pdf');
    }
}