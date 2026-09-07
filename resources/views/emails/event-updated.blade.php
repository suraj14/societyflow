@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>Important update: The event "{{ $event->event_name }}" in {{ $society_name }} has been updated.</p>
    
    <div class="info-box">
        <h4>Updated Event Details</h4>
        <p><strong>Event Name:</strong> {{ $event->event_name }}</p>
        <p><strong>Date & Time:</strong> {{ $event->start_date->format('F j, Y \a\t g:i A') }}</p>
        @if($event->end_date && !$event->start_date->isSameDay($event->end_date))
            <p><strong>End Date:</strong> {{ $event->end_date->format('F j, Y \a\t g:i A') }}</p>
        @elseif($event->end_date)
            <p><strong>End Time:</strong> {{ $event->end_date->format('g:i A') }}</p>
        @endif
        <p><strong>Location:</strong> {{ $event->location }}</p>
        <p><strong>Status:</strong> {{ ucfirst($event->status) }}</p>
        <p><strong>Last Updated:</strong> {{ $event->updated_at->format('F j, Y \a\t g:i A') }}</p>
    </div>

    @if($event->status === 'cancelled')
        <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #721c24;"><strong>❌ Event Cancelled:</strong> Unfortunately, this event has been cancelled.</p>
        </div>
    @elseif($event->status === 'postponed')
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>⏰ Event Postponed:</strong> This event has been rescheduled to a new date.</p>
        </div>
    @else
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>📝 Event Updated:</strong> Please review the updated event information.</p>
        </div>
    @endif

    <p><strong>Updated Description:</strong></p>
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 4px; margin: 15px 0; line-height: 1.6;">
        {!! nl2br(e($event->description)) !!}
    </div>

    @if($event->update_reason ?? false)
        <p><strong>Reason for Update:</strong></p>
        <div style="background-color: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0;">
            {{ $event->update_reason }}
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View Updated Details</a>
</div>

<div class="content">
    @if($event->status === 'cancelled')
        <p><strong>Event Cancelled:</strong></p>
        <ul>
            <li>This event will not take place as scheduled</li>
            <li>Any registrations have been automatically cancelled</li>
            <li>Watch for announcements about future events</li>
            <li>Contact organizers if you have questions</li>
        </ul>
    @elseif($event->status === 'postponed')
        <p><strong>Event Postponed:</strong></p>
        <ul>
            <li>Please note the new date and time</li>
            <li>Your registration remains valid for the new date</li>
            <li>Update your calendar accordingly</li>
            <li>Contact organizers if the new date doesn't work for you</li>
        </ul>
    @else
        <p><strong>Please Note:</strong></p>
        <ul>
            <li>Review all updated information carefully</li>
            <li>Update your calendar with any changes</li>
            <li>Check if any action is required from your side</li>
            <li>Contact organizers for clarifications</li>
        </ul>
    @endif

    <p>We apologize for any inconvenience caused by these changes and appreciate your understanding.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Events Team</p>
</div>
@endsection