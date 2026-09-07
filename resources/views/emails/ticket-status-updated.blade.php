@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'User' }},
</div>

<div class="content">
    <p>There's an update on your support ticket #{{ $complaint->complaint_number }} in {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Ticket Update</h4>
        <p><strong>Ticket Number:</strong> {{ $complaint->complaint_number }}</p>
        <p><strong>Title:</strong> {{ $complaint->title }}</p>
        <p><strong>Current Status:</strong> 
            <span style="color: {{ $complaint->status === 'resolved' ? '#28a745' : ($complaint->status === 'in_progress' ? '#007bff' : '#6c757d') }};">
                {{ ucfirst(str_replace('_', ' ', $complaint->status)) }}
            </span>
        </p>
        <p><strong>Priority:</strong> {{ ucfirst($complaint->priority) }}</p>
        <p><strong>Assigned to:</strong> {{ $complaint->assignedTo->name ?? 'Support Team' }}</p>
        <p><strong>Last Updated:</strong> {{ $complaint->updated_at->format('F j, Y \a\t g:i A') }}</p>
    </div>

    @if($complaint->status === 'in_progress')
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>🔄 In Progress:</strong> Work has begun on your ticket. Our team is actively working on a solution.</p>
        </div>
    @elseif($complaint->status === 'resolved')
        <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #155724;"><strong>✅ Resolved:</strong> Great news! Your ticket has been resolved.</p>
        </div>
    @elseif($complaint->status === 'closed')
        <div style="background-color: #e2e3e5; border-left: 4px solid #6c757d; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #383d41;"><strong>📋 Closed:</strong> This ticket has been closed.</p>
        </div>
    @endif

    @if($complaint->updates->count() > 0)
        <p><strong>Latest Update:</strong></p>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;">
            {{ $complaint->updates->last()->message ?? 'Status updated by support team.' }}
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View Full Details</a>
</div>

<div class="content">
    @if($complaint->status === 'resolved')
        <p><strong>Resolution Complete:</strong></p>
        <ul>
            <li>Please verify that the issue has been resolved</li>
            <li>If you're satisfied, no further action is needed</li>
            <li>If the issue persists, please reopen the ticket</li>
        </ul>
    @else
        <p>We'll continue to keep you updated as we work on your request. Thank you for your patience.</p>
    @endif
    
    <p>Best regards,<br>
    {{ $society_name }} Support Team</p>
</div>
@endsection