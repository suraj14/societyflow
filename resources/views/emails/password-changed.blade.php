@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name }},
</div>

<div class="content">
    <p>This is to confirm that your password for {{ $society_name }} has been successfully changed.</p>
    
    <div class="info-box">
        <h4>Password Change Details</h4>
        <p><strong>Date & Time:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>Account:</strong> {{ $user->email }}</p>
    </div>

    <p>If you made this change, no further action is required.</p>
    
    <p><strong>If you did not change your password:</strong></p>
    <ul>
        <li>Your account may have been compromised</li>
        <li>Please contact support immediately</li>
        <li>Consider changing your password again</li>
    </ul>

    <p>For your security, we recommend:</p>
    <ul>
        <li>Using a strong, unique password</li>
        <li>Not sharing your login credentials</li>
        <li>Logging out from shared devices</li>
    </ul>
    
    <p>Best regards,<br>
    {{ $society_name }} Management</p>
</div>
@endsection