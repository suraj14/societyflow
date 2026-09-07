@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name }},
</div>

<div class="content">
    <p>Great news! Your account for {{ $society_name }} has been activated and you now have full access to all features.</p>
    
    <div class="info-box">
        <h4>Account Status</h4>
        <p><strong>Status:</strong> <span style="color: #27ae60;">Active</span></p>
        <p><strong>Activated on:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>Role:</strong> {{ $user->getRoleNames()->first() ?? 'Resident' }}</p>
    </div>

    <p>You can now access all available features including:</p>
    <ul>
        <li>Dashboard and account management</li>
        <li>Bill payments and transaction history</li>
        <li>Complaint and request submissions</li>
        <li>Society notices and announcements</li>
        <li>Event calendar and bookings</li>
        <li>Visitor management</li>
    </ul>
</div>

<div style="text-align: center;">
    <a href="{{ route('login') }}" class="button">Access Your Account</a>
</div>

<div class="content">
    <p>If you have any questions about your account or need assistance, please contact the society administration.</p>
    
    <p>Welcome aboard!<br>
    {{ $society_name }} Management</p>
</div>
@endsection