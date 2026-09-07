@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Administrator' }},
</div>

<div class="content">
    <p>This is a friendly reminder that your free trial for {{ $society_name }} is ending soon.</p>
    
    <div class="info-box">
        <h4>Trial Information</h4>
        <p><strong>Society:</strong> {{ $society->name }}</p>
        <p><strong>Trial Started:</strong> {{ $society->created_at->format('F j, Y') }}</p>
        <p><strong>Trial Ends:</strong> {{ $society->trial_ends_at->format('F j, Y') }}</p>
        <p><strong>Days Remaining:</strong> {{ $society->trial_ends_at->diffInDays(now()) }} days</p>
        <p><strong>Current Status:</strong> <span style="color: #ffc107;">Trial Active</span></p>
    </div>

    @if($society->trial_ends_at->diffInDays(now()) <= 3)
        <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #721c24;"><strong>⚠️ Trial Ending Soon:</strong> Your trial expires in {{ $society->trial_ends_at->diffInDays(now()) }} day(s). Subscribe now to continue using SocietyFlow.</p>
        </div>
    @else
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>⏰ Trial Reminder:</strong> Don't forget to subscribe before your trial ends to avoid service interruption.</p>
        </div>
    @endif

    <p><strong>What happens when your trial ends:</strong></p>
    <ul>
        <li>Access to your dashboard will be limited</li>
        <li>New data entry will be restricted</li>
        <li>Advanced features will be disabled</li>
        <li>Your existing data will be safely preserved</li>
    </ul>

    <p><strong>Why continue with SocietyFlow:</strong></p>
    <ul>
        <li>Streamlined society management</li>
        <li>Automated billing and payment tracking</li>
        <li>Improved resident communication</li>
        <li>Comprehensive reporting and analytics</li>
        <li>24/7 customer support</li>
        <li>Regular feature updates and improvements</li>
    </ul>
</div>

<div style="text-align: center;">
    <a href="{{ route('subscription.plans') }}" class="button">Choose Your Plan</a>
</div>

<div class="content">
    <p><strong>Available Plans:</strong></p>
    <ul>
        <li><strong>Basic Plan:</strong> Perfect for small societies (up to 50 units)</li>
        <li><strong>Standard Plan:</strong> Ideal for medium societies (up to 200 units)</li>
        <li><strong>Premium Plan:</strong> Complete solution for large societies (unlimited units)</li>
    </ul>

    <p><strong>Special Offer:</strong></p>
    <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #155724;"><strong>🎉 Limited Time:</strong> Subscribe within the next 3 days and get 20% off your first year!</p>
    </div>

    <p>Don't let your society management go back to manual processes. Subscribe today and keep enjoying the benefits of SocietyFlow!</p>
    
    <p>Questions? Our team is here to help you choose the right plan.</p>
    
    <p>Best regards,<br>
    The SocietyFlow Team</p>
</div>
@endsection