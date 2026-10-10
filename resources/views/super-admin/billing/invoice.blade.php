<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $payment->payment_id }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 13px; color: #111827; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f3f4f6; font-size: 12px; text-transform: uppercase; }
        .right { text-align: right; }
        .total td { font-weight: bold; border-top: 2px solid #111827; }
    </style>
</head>
<body>
    <h1>Subscription invoice</h1>
    <div class="muted">Reference {{ $payment->payment_id }} &middot; {{ $payment->payment_date ? $payment->payment_date->format('d M Y') : '' }}</div>

    <p style="margin-top:20px">
        <strong>Billed to:</strong> {{ $payment->society->name ?? 'N/A' }}<br>
        <strong>Status:</strong> {{ ucfirst($payment->status) }}
    </p>

    <table>
        <thead><tr><th>Description</th><th>Billing cycle</th><th class="right">Amount</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $payment->subscriptionPlan->name ?? 'Subscription' }} plan</td>
                <td>{{ ucfirst($payment->billing_cycle ?? '-') }}</td>
                <td class="right">&#8377;{{ number_format($payment->amount ?? 0, 2) }}</td>
            </tr>
            <tr class="total"><td colspan="2">Total</td><td class="right">&#8377;{{ number_format($payment->amount ?? 0, 2) }}</td></tr>
        </tbody>
    </table>

    @if($payment->transaction_id)
        <p class="muted" style="margin-top:20px">Transaction ID: {{ $payment->transaction_id }}</p>
    @endif
</body>
</html>
