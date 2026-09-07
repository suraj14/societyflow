@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name }},
</div>

<div class="content">
    <p>We received a request to reset your password for your {{ $society_name }} account.</p>
    
    <p>If you requested this password reset, click the button below to create a new password:</p>
</div>

<div style="text-align: center;">
    <a href="{{ $reset_url }}" class="button">Reset Your Password</a>
</div>

<div class="content">
    <div class="info-box">
        <h4>Security Information</h4>
        <p>This password reset link will expire in 60 minutes for your security.</p>
        <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
    </div>

    <p>For security reasons, if you don't reset your password within 60 minutes, you'll need to request a new reset link.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Management</p>
</div>
@endsection