@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'User' }},
</div>

<div class="content">
    <p>Ticket #{{ $complaint->complaint_number }} has been assigned to a team member for resolution.</p>
    
    <div class="info-box">
        <h4>Assignment Details</h4>
        <p><strong>Ticket Number:</strong> {{ $complaint->complaint_number }}</p>
        <p><strong>Title:</strong> {{ $complaint->title }}</p>
        <p><strong>Assigned to:</strong> {{ $complaint->assignedTo->name ?? 'Support Team' }}</p>
        <p><strong>Priority:</strong> 
            <span style="color: {{ $complaint->priority === 'urgent' ? '#dc3545' : ($complaint->priority === 'high' ? '#fd7e14' : ($complaint->priority === 'medium' ? '#ffc107' : '#28a745')) }};">
                {{ ucfirst($complaint->priority) }}
            </span>
        </p>
        <p><strong>Status:</strong> {{ ucfirst($complaint->status) }}</p>
        <p><strong>Assigned on:</strong> {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #155724;"><strong>✅ Good News:</strong> Your ticket has been assigned and work will begin shortly.</p>
    </div>

    <p>The assigned team member will review your request and begin working on a resolution. You can expect:</p>
    <ul>
        <li>Regular updates on progress</li>
        <li>Direct communication if more information is needed</li>
        <li>Notification when the issue is resolved</li>
    </ul>
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">Track Ticket Progress</a>
</div>

<div class="content">
    <p>If you have any additional information that might help resolve this issue faster, please add it to the ticket through your account.</p>
    
    <p>Thank you for your patience,<br>
    {{ $society_name }} Support Team</p>
</div>
@endsection