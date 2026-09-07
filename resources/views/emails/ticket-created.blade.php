@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'User' }},
</div>

<div class="content">
    <p>A new support ticket has been created in {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Ticket Details</h4>
        <p><strong>Ticket Number:</strong> {{ $complaint->complaint_number }}</p>
        <p><strong>Title:</strong> {{ $complaint->title }}</p>
        <p><strong>Category:</strong> {{ $complaint->category->name ?? 'General' }}</p>
        <p><strong>Priority:</strong> 
            <span style="color: {{ $complaint->priority === 'urgent' ? '#dc3545' : ($complaint->priority === 'high' ? '#fd7e14' : ($complaint->priority === 'medium' ? '#ffc107' : '#28a745')) }};">
                {{ ucfirst($complaint->priority) }}
            </span>
        </p>
        <p><strong>Status:</strong> {{ ucfirst($complaint->status) }}</p>
        <p><strong>Created by:</strong> {{ $complaint->createdBy->name ?? 'System' }}</p>
        <p><strong>Created on:</strong> {{ $complaint->created_at->format('F j, Y \a\t g:i A') }}</p>
    </div>

    <p><strong>Description:</strong></p>
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;">
        {{ $complaint->description }}
    </div>

    @if($complaint->priority === 'urgent' || $complaint->priority === 'high')
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>⚠️ High Priority:</strong> This ticket requires immediate attention due to its {{ $complaint->priority }} priority level.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View Ticket Details</a>
</div>

<div class="content">
    <p><strong>What happens next:</strong></p>
    <ul>
        <li>Your ticket has been logged in our system</li>
        <li>It will be assigned to the appropriate team member</li>
        <li>You'll receive updates as progress is made</li>
        <li>You can track the status through your account</li>
    </ul>

    <p>We aim to resolve all tickets promptly. You will be notified of any updates or when the ticket is resolved.</p>
    
    <p>Thank you for bringing this to our attention,<br>
    {{ $society_name }} Support Team</p>
</div>
@endsection