@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name }},
</div>

<div class="content">
    <p>Welcome to {{ $society_name }}! Your account has been successfully created and you now have access to our society management system.</p>
    
    @if($temporary_password ?? null)
        <div class="info-box">
            <h4>Your Login Credentials</h4>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            <p><strong>Temporary Password:</strong> {{ $temporary_password }}</p>
            <p><em>Please change your password after your first login for security.</em></p>
        </div>
    @endif

    <p>You can now:</p>
    <ul>
        <li>View and pay your bills online</li>
        <li>Submit maintenance requests and complaints</li>
        <li>Stay updated with society notices and events</li>
        <li>Book common facilities</li>
        <li>Manage visitor entries</li>
    </ul>

    <p>Click the button below to access your account:</p>
</div>

<div style="text-align: center;">
    <a href="{{ $login_url }}" class="button">Login to Your Account</a>
</div>

<div class="content">
    <p>If you have any questions or need assistance, please contact the society administration.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Management</p>
</div>
@endsection