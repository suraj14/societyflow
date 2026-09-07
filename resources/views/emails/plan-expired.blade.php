@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Administrator' }},
</div>

<div class="content">
    <p>We're writing to inform you that your subscription for {{ $society_name }} has expired.</p>
    
    <div class="info-box">
        <h4>Subscription Status</h4>
        <p><strong>Society:</strong> {{ $society->name }}</p>
        <p><strong>Plan:</strong> {{ $subscription->plan->name ?? 'Premium Plan' }}</p>
        <p><strong>Expired on:</strong> {{ $subscription->end_date->format('F j, Y') }}</p>
        <p><strong>Days Since Expiry:</strong> {{ $subscription->end_date->diffInDays(now()) }} days</p>
        <p><strong>Current Status:</strong> <span style="color: #dc3545;">Expired</span></p>
    </div>

    <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #721c24;"><strong>⚠️ Subscription Expired:</strong> Your access to SocietyFlow features is now limited.</p>
    </div>

    <p><strong>Current Limitations:</strong></p>
    <ul>
        <li>Dashboard access is restricted to view-only mode</li>
        <li>New data entry and updates are disabled</li>
        <li>Advanced features and reports are unavailable</li>
        <li>Email notifications have been suspended</li>
        <li>Mobile app access is limited</li>
    </ul>

    <p><strong>Your Data is Safe:</strong></p>
    <ul>
        <li>All your existing data is securely preserved</li>
        <li>No information has been deleted</li>
        <li>Full access will be restored upon renewal</li>
        <li>Data exports are available for a limited time</li>
    </ul>

    @if($grace_period_days ?? 0 > 0)
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>⏳ Grace Period:</strong> You have {{ $grace_period_days }} days remaining to renew before additional restrictions apply.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ route('subscription.renew') }}" class="button">Renew Subscription</a>
</div>

<div class="content">
    <p><strong>Renew Today and Get:</strong></p>
    <ul>
        <li>Immediate restoration of all features</li>
        <li>Continued access to your data</li>
        <li>Priority customer support</li>
        <li>Latest feature updates</li>
        <li>Flexible billing options</li>
    </ul>

    <p><strong>Renewal Options:</strong></p>
    <ul>
        <li>Monthly billing for flexibility</li>
        <li>Annual billing with significant savings</li>
        <li>Upgrade to a higher plan for more features</li>
        <li>Custom enterprise solutions available</li>
    </ul>

    @if($renewal_discount ?? false)
        <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #155724;"><strong>💰 Special Offer:</strong> Renew within 7 days and get {{ $renewal_discount }}% off your next billing cycle!</p>
        </div>
    @endif

    <p>Don't let your society management suffer. Renew your subscription today and get back to efficient society management!</p>
    
    <p>Need help with renewal? Our support team is ready to assist you.</p>
    
    <p>Best regards,<br>
    The SocietyFlow Team</p>
</div>
@endsection