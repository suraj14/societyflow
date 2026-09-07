@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>We're sorry to inform you that your recent payment attempt for {{ $society_name }} was not successful.</p>
    
    <div class="info-box">
        <h4>Payment Attempt Details</h4>
        <p><strong>Transaction ID:</strong> {{ $payment->transaction_id ?? $payment->id }}</p>
        <p><strong>Amount:</strong> ₹{{ number_format($amount, 2) }}</p>
        <p><strong>Attempted on:</strong> {{ \Carbon\Carbon::parse($payment->created_at)->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>Payment Method:</strong> {{ ucfirst($payment->payment_method ?? 'Online') }}</p>
        @if(isset($payment->failure_reason))
            <p><strong>Reason:</strong> {{ $payment->failure_reason }}</p>
        @endif
    </div>

    <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #721c24;"><strong>❌ Payment Failed:</strong> Your payment could not be processed at this time.</p>
    </div>

    <p><strong>Common reasons for payment failure:</strong></p>
    <ul>
        <li>Insufficient funds in your account</li>
        <li>Incorrect card or bank details</li>
        <li>Network connectivity issues</li>
        <li>Bank security restrictions</li>
        <li>Card expiry or limits exceeded</li>
    </ul>
</div>

<div style="text-align: center;">
    <a href="{{ $retry_url ?? route('payments.index') }}" class="button">Try Payment Again</a>
</div>

<div class="content">
    <p><strong>Next Steps:</strong></p>
    <ul>
        <li>Check your account balance and card details</li>
        <li>Contact your bank if the issue persists</li>
        <li>Try a different payment method</li>
        <li>Contact our support team for assistance</li>
    </ul>

    <p>If you continue to experience issues, please contact our accounts department for alternative payment options.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Accounts Team</p>
</div>
@endsection