@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Administrator' }},
</div>

<div class="content">
    <p>Congratulations! Your society registration for {{ $society_name }} has been completed successfully.</p>
    
    <div class="info-box">
        <h4>Society Registration Details</h4>
        <p><strong>Society Name:</strong> {{ $society->name }}</p>
        <p><strong>Registration Date:</strong> {{ $society->created_at->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>Society ID:</strong> {{ $society->id }}</p>
        <p><strong>Admin Email:</strong> {{ $society->admin->email ?? $user->email }}</p>
        <p><strong>Status:</strong> <span style="color: #28a745;">Active</span></p>
        @if($society->trial_ends_at)
            <p><strong>Trial Period:</strong> Until {{ $society->trial_ends_at->format('F j, Y') }}</p>
        @endif
    </div>

    <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #155724;"><strong>🎉 Welcome to SocietyFlow:</strong> Your society management system is now ready to use!</p>
    </div>

    <p><strong>What's included in your registration:</strong></p>
    <ul>
        <li>Complete society management dashboard</li>
        <li>Resident and property management</li>
        <li>Bill generation and payment tracking</li>
        <li>Complaint and maintenance request system</li>
        <li>Notice and event management</li>
        <li>Visitor management system</li>
        <li>Financial reporting and analytics</li>
        <li>Multi-user access with role-based permissions</li>
    </ul>

    @if($society->trial_ends_at)
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>🆓 Free Trial:</strong> You have {{ $society->trial_ends_at->diffInDays(now()) }} days remaining in your free trial period.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ route('dashboard') }}" class="button">Access Your Dashboard</a>
</div>

<div class="content">
    <p><strong>Getting Started:</strong></p>
    <ul>
        <li>Complete your society profile setup</li>
        <li>Add buildings and properties</li>
        <li>Import or create resident records</li>
        <li>Configure email and notification settings</li>
        <li>Set up billing and payment methods</li>
        <li>Train your team on the system features</li>
    </ul>

    <p><strong>Need Help?</strong></p>
    <ul>
        <li>Check our comprehensive help documentation</li>
        <li>Contact our support team for assistance</li>
        <li>Schedule a demo or training session</li>
        <li>Join our community forum for tips and best practices</li>
    </ul>

    <p>We're excited to help you streamline your society management. Welcome to the SocietyFlow family!</p>
    
    <p>Best regards,<br>
    The SocietyFlow Team</p>
</div>
@endsection