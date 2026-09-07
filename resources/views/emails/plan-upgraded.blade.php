@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Administrator' }},
</div>

<div class="content">
    <p>Great news! Your subscription plan for {{ $society_name }} has been successfully upgraded.</p>
    
    <div class="info-box">
        <h4>Plan Upgrade Details</h4>
        <p><strong>Previous Plan:</strong> {{ $old_plan->name ?? 'Basic Plan' }}</p>
        <p><strong>New Plan:</strong> {{ $subscription->plan->name ?? 'Premium Plan' }}</p>
        <p><strong>Upgrade Date:</strong> {{ $subscription->updated_at->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>New Billing Amount:</strong> ₹{{ number_format($subscription->amount ?? 0, 2) }}</p>
        <p><strong>Billing Cycle:</strong> {{ ucfirst($subscription->billing_cycle ?? 'monthly') }}</p>
        <p><strong>Next Billing Date:</strong> {{ $subscription->end_date->format('F j, Y') }}</p>
    </div>

    <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #155724;"><strong>🚀 Plan Upgraded:</strong> You now have access to enhanced features and capabilities!</p>
    </div>

    <p><strong>New features now available:</strong></p>
    <ul>
        @if($subscription->plan->features ?? false)
            @foreach(json_decode($subscription->plan->features, true) as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        @else
            <li>Increased storage and data limits</li>
            <li>Advanced reporting and analytics</li>
            <li>Priority customer support</li>
            <li>Enhanced security features</li>
            <li>Mobile app premium features</li>
            <li>Custom integrations and API access</li>
        @endif
    </ul>

    @if($upgrade_benefits ?? false)
        <p><strong>Upgrade Benefits:</strong></p>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;">
            {!! nl2br(e($upgrade_benefits)) !!}
        </div>
    @endif

    @if($proration_credit ?? false)
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>💰 Proration Credit:</strong> A credit of ₹{{ number_format($proration_credit, 2) }} has been applied to your account for the unused portion of your previous plan.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ route('dashboard') }}" class="button">Explore New Features</a>
</div>

<div class="content">
    <p><strong>Getting the Most from Your Upgrade:</strong></p>
    <ul>
        <li>Explore the new features in your dashboard</li>
        <li>Check out the advanced reporting section</li>
        <li>Set up any new integrations you need</li>
        <li>Contact support for a feature walkthrough</li>
    </ul>

    <p><strong>Billing Changes:</strong></p>
    <ul>
        <li>Your new billing amount is ₹{{ number_format($subscription->amount ?? 0, 2) }} per {{ $subscription->billing_cycle ?? 'month' }}</li>
        <li>The change takes effect immediately</li>
        <li>Your next billing date remains {{ $subscription->end_date->format('F j, Y') }}</li>
        <li>You can manage your subscription in account settings</li>
    </ul>

    <p>Thank you for upgrading! We're excited to help you get even more value from SocietyFlow.</p>
    
    <p>Best regards,<br>
    The SocietyFlow Team</p>
</div>
@endsection