@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Administrator' }},
</div>

<div class="content">
    <p>Thank you for your subscription purchase! Your payment has been processed successfully for {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Subscription Details</h4>
        <p><strong>Plan Name:</strong> {{ $subscription->plan->name ?? 'Premium Plan' }}</p>
        <p><strong>Billing Cycle:</strong> {{ ucfirst($subscription->billing_cycle ?? 'monthly') }}</p>
        <p><strong>Amount Paid:</strong> ₹{{ number_format($subscription->amount ?? 0, 2) }}</p>
        <p><strong>Start Date:</strong> {{ $subscription->start_date->format('F j, Y') }}</p>
        <p><strong>End Date:</strong> {{ $subscription->end_date->format('F j, Y') }}</p>
        <p><strong>Payment Method:</strong> {{ ucfirst($subscription->payment_method ?? 'Online') }}</p>
        <p><strong>Transaction ID:</strong> {{ $subscription->transaction_id ?? 'N/A' }}</p>
    </div>

    <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #155724;"><strong>✅ Payment Successful:</strong> Your subscription is now active and all features are unlocked!</p>
    </div>

    <p><strong>Your plan includes:</strong></p>
    <ul>
        @if($subscription->plan->features ?? false)
            @foreach(json_decode($subscription->plan->features, true) as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        @else
            <li>Unlimited residents and properties</li>
            <li>Advanced billing and payment management</li>
            <li>Comprehensive reporting and analytics</li>
            <li>Priority customer support</li>
            <li>Mobile app access</li>
            <li>Data backup and security</li>
        @endif
    </ul>

    @if($subscription->auto_renewal ?? true)
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>🔄 Auto-Renewal:</strong> Your subscription will automatically renew on {{ $subscription->end_date->format('F j, Y') }}.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ route('dashboard') }}" class="button">Access Your Dashboard</a>
</div>

<div class="content">
    <p><strong>What's Next:</strong></p>
    <ul>
        <li>All premium features are now available</li>
        <li>Your data limits have been increased</li>
        <li>Priority support is activated</li>
        <li>Download the mobile app for on-the-go access</li>
    </ul>

    <p><strong>Billing Information:</strong></p>
    <ul>
        <li>Keep this email as your payment receipt</li>
        <li>Your next billing date is {{ $subscription->end_date->format('F j, Y') }}</li>
        <li>Manage your subscription settings in your account</li>
        <li>Contact support for any billing questions</li>
    </ul>

    <p>Thank you for choosing SocietyFlow! We're committed to helping you manage your society more efficiently.</p>
    
    <p>Best regards,<br>
    The SocietyFlow Team</p>
</div>
@endsection