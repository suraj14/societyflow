@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>A new notice has been published for {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Notice Details</h4>
        <p><strong>Title:</strong> {{ $notice->title }}</p>
        <p><strong>Published by:</strong> {{ $notice->createdBy->name ?? 'Administration' }}</p>
        <p><strong>Published on:</strong> {{ $notice->created_at->format('F j, Y \a\t g:i A') }}</p>
        @if($notice->priority)
            <p><strong>Priority:</strong> 
                <span style="color: {{ $notice->priority === 'urgent' ? '#dc3545' : ($notice->priority === 'high' ? '#fd7e14' : '#28a745') }};">
                    {{ ucfirst($notice->priority) }}
                </span>
            </p>
        @endif
        @if($notice->category)
            <p><strong>Category:</strong> {{ $notice->category }}</p>
        @endif
    </div>

    @if($notice->priority === 'urgent')
        <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #721c24;"><strong>🚨 Urgent Notice:</strong> This notice requires your immediate attention.</p>
        </div>
    @elseif($notice->priority === 'high')
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>⚠️ Important Notice:</strong> Please read this notice carefully.</p>
        </div>
    @endif

    <p><strong>Notice Content:</strong></p>
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 4px; margin: 15px 0; line-height: 1.6;">
        {!! nl2br(e($notice->content)) !!}
    </div>

    @if($notice->effective_date && $notice->effective_date->isFuture())
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>📅 Effective Date:</strong> This notice will take effect from {{ $notice->effective_date->format('F j, Y') }}.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View Full Notice</a>
</div>

<div class="content">
    <p><strong>Important:</strong></p>
    <ul>
        <li>Please read this notice carefully</li>
        <li>Keep this for your records</li>
        <li>Contact the administration if you have questions</li>
        <li>Comply with any requirements mentioned</li>
    </ul>

    <p>For any clarifications or questions regarding this notice, please contact the society administration office.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Administration</p>
</div>
@endsection