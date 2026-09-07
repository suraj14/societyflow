@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name }},
</div>

<div class="content">
    <p>We're writing to inform you that your account for {{ $society_name }} has been deactivated.</p>
    
    <div class="info-box">
        <h4>Account Status</h4>
        <p><strong>Status:</strong> <span style="color: #e74c3c;">Deactivated</span></p>
        <p><strong>Deactivated on:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>Account:</strong> {{ $user->email }}</p>
    </div>

    <p><strong>What this means:</strong></p>
    <ul>
        <li>You will no longer be able to log into your account</li>
        <li>Access to society features and services is suspended</li>
        <li>You will not receive further email notifications</li>
    </ul>

    <p><strong>If you believe this is an error:</strong></p>
    <ul>
        <li>Contact the society administration immediately</li>
        <li>Provide your account details for verification</li>
        <li>Request account reactivation if appropriate</li>
    </ul>

    <p>For assistance or to appeal this decision, please contact the society management office.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Management</p>
</div>
@endsection