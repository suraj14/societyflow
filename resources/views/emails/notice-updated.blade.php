@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>An important notice has been updated in {{ $society_name }}. Please review the changes.</p>
    
    <div class="info-box">
        <h4>Updated Notice Details</h4>
        <p><strong>Title:</strong> {{ $notice->title }}</p>
        <p><strong>Originally Published:</strong> {{ $notice->created_at->format('F j, Y') }}</p>
        <p><strong>Last Updated:</strong> {{ $notice->updated_at->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>Updated by:</strong> {{ $notice->updatedBy->name ?? $notice->createdBy->name ?? 'Administration' }}</p>
        @if($notice->priority)
            <p><strong>Priority:</strong> 
                <span style="color: {{ $notice->priority === 'urgent' ? '#dc3545' : ($notice->priority === 'high' ? '#fd7e14' : '#28a745') }};">
                    {{ ucfirst($notice->priority) }}
                </span>
            </p>
        @endif
    </div>

    <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #856404;"><strong>📝 Notice Updated:</strong> This notice has been revised. Please review the updated content.</p>
    </div>

    <p><strong>Updated Content:</strong></p>
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 4px; margin: 15px 0; line-height: 1.6;">
        {!! nl2br(e($notice->content)) !!}
    </div>

    @if($notice->effective_date)
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>📅 Effective Date:</strong> {{ $notice->effective_date->format('F j, Y') }}</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View Updated Notice</a>
</div>

<div class="content">
    <p><strong>Action Required:</strong></p>
    <ul>
        <li>Review the updated content carefully</li>
        <li>Note any changes from the previous version</li>
        <li>Update your records accordingly</li>
        <li>Contact administration for clarifications</li>
    </ul>

    <p>It's important to stay informed about these updates as they may affect you directly.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Administration</p>
</div>
@endsection