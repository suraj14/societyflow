@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>Thank you! We have successfully received your payment for {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Payment Confirmation</h4>
        <p><strong>Payment ID:</strong> {{ $payment->payment_id ?? $payment->id }}</p>
        <p><strong>Amount Paid:</strong> ₹{{ number_format($amount, 2) }}</p>
        <p><strong>Payment Date:</strong> {{ \Carbon\Carbon::parse($payment->payment_date ?? $payment->created_at)->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>Payment Method:</strong> {{ ucfirst($payment->payment_method ?? 'Online') }}</p>
        @if(isset($payment->bill))
            <p><strong>Bill Number:</strong> {{ $payment->bill->bill_number ?? 'N/A' }}</p>
        @endif
    </div>

    <p>Your payment has been processed and your account has been updated accordingly.</p>

    @if($payment->status === 'completed' || $payment->status === 'paid')
        <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #155724;"><strong>✅ Payment Successful:</strong> Your payment has been confirmed and processed.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $receipt_url }}" class="button">Download Receipt</a>
</div>

<div class="content">
    <p><strong>What's Next:</strong></p>
    <ul>
        <li>Keep this email as proof of payment</li>
        <li>Download your receipt for records</li>
        <li>Check your account for updated balance</li>
    </ul>

    <p>If you have any questions about this payment or need assistance, please contact our accounts department.</p>
    
    <p>Thank you for your prompt payment!<br>
    {{ $society_name }} Accounts Team</p>
</div>
@endsection