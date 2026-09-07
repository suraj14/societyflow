@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>An exciting new event has been scheduled for {{ $society_name }}!</p>
    
    <div class="info-box">
        <h4>Event Details</h4>
        <p><strong>Event Name:</strong> {{ $event->event_name }}</p>
        <p><strong>Date & Time:</strong> {{ $event->start_date->format('F j, Y \a\t g:i A') }}</p>
        @if($event->end_date && !$event->start_date->isSameDay($event->end_date))
            <p><strong>End Date:</strong> {{ $event->end_date->format('F j, Y \a\t g:i A') }}</p>
        @elseif($event->end_date)
            <p><strong>End Time:</strong> {{ $event->end_date->format('g:i A') }}</p>
        @endif
        <p><strong>Location:</strong> {{ $event->location }}</p>
        <p><strong>Organized by:</strong> {{ $event->createdBy->name ?? 'Administration' }}</p>
        <p><strong>Status:</strong> {{ ucfirst($event->status) }}</p>
    </div>

    @if($event->start_date->isToday())
        <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #721c24;"><strong>🎉 Today's Event:</strong> This event is happening today!</p>
        </div>
    @elseif($event->start_date->isTomorrow())
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>📅 Tomorrow's Event:</strong> Don't forget about this event tomorrow!</p>
        </div>
    @elseif($event->start_date->diffInDays(now()) <= 7)
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>📆 Upcoming Event:</strong> This event is coming up in {{ $event->start_date->diffInDays(now()) }} day(s).</p>
        </div>
    @endif

    <p><strong>Event Description:</strong></p>
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 4px; margin: 15px 0; line-height: 1.6;">
        {!! nl2br(e($event->description)) !!}
    </div>

    @if($event->registration_required ?? false)
        <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #155724;"><strong>📝 Registration Required:</strong> Please register for this event in advance.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View Event Details</a>
</div>

<div class="content">
    <p><strong>Event Information:</strong></p>
    <ul>
        <li>Mark your calendar for {{ $event->start_date->format('F j, Y') }}</li>
        <li>Arrive at {{ $event->location }} on time</li>
        @if($event->registration_required ?? false)
            <li>Complete registration through your account</li>
        @endif
        <li>Contact organizers for any questions</li>
    </ul>

    <p>We look forward to seeing you at this event! It's a great opportunity to connect with your neighbors and enjoy community activities.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Events Team</p>
</div>
@endsection