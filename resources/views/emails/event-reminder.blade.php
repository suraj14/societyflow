@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>This is a friendly reminder about the upcoming event "{{ $event->event_name }}" in {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Event Reminder</h4>
        <p><strong>Event Name:</strong> {{ $event->event_name }}</p>
        <p><strong>Date & Time:</strong> {{ $event->start_date->format('F j, Y \a\t g:i A') }}</p>
        @if($event->end_date && !$event->start_date->isSameDay($event->end_date))
            <p><strong>End Date:</strong> {{ $event->end_date->format('F j, Y \a\t g:i A') }}</p>
        @elseif($event->end_date)
            <p><strong>End Time:</strong> {{ $event->end_date->format('g:i A') }}</p>
        @endif
        <p><strong>Location:</strong> {{ $event->location }}</p>
        <p><strong>Time Until Event:</strong> {{ $event->start_date->diffForHumans() }}</p>
    </div>

    @if($event->start_date->isToday())
        <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #721c24;"><strong>🎉 Event Today:</strong> Don't forget! This event is happening today at {{ $event->start_date->format('g:i A') }}.</p>
        </div>
    @elseif($event->start_date->isTomorrow())
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>📅 Event Tomorrow:</strong> Just a reminder that this event is tomorrow!</p>
        </div>
    @else
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>📆 Upcoming Event:</strong> This event is coming up soon!</p>
        </div>
    @endif

    <p><strong>Event Description:</strong></p>
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 4px; margin: 15px 0; line-height: 1.6;">
        {!! nl2br(e($event->description)) !!}
    </div>

    @if($event->requirements ?? false)
        <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #155724;"><strong>📋 What to Bring:</strong> {{ $event->requirements }}</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View Event Details</a>
</div>

<div class="content">
    <p><strong>Reminder Checklist:</strong></p>
    <ul>
        <li>📅 Add to your calendar: {{ $event->start_date->format('F j, Y \a\t g:i A') }}</li>
        <li>📍 Location: {{ $event->location }}</li>
        @if($event->registration_required ?? false)
            <li>✅ Confirm your registration status</li>
        @endif
        <li>⏰ Plan to arrive a few minutes early</li>
        @if($event->requirements ?? false)
            <li>🎒 Bring: {{ $event->requirements }}</li>
        @endif
    </ul>

    <p>We're looking forward to seeing you at this event! It's going to be a great opportunity to connect with your community.</p>
    
    <p>See you there!<br>
    {{ $society_name }} Events Team</p>
</div>
@endsection