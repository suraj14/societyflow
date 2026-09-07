@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'User' }},
</div>

<div class="content">
    <p>Your support ticket #{{ $complaint->complaint_number }} has been successfully resolved and closed.</p>
    
    <div class="info-box">
        <h4>Ticket Resolution Summary</h4>
        <p><strong>Ticket Number:</strong> {{ $complaint->complaint_number }}</p>
        <p><strong>Title:</strong> {{ $complaint->title }}</p>
        <p><strong>Status:</strong> <span style="color: #28a745;">Closed - Resolved</span></p>
        <p><strong>Resolved by:</strong> {{ $complaint->assignedTo->name ?? 'Support Team' }}</p>
        <p><strong>Resolution Date:</strong> {{ $complaint->updated_at->format('F j, Y \a\t g:i A') }}</p>
        <p><strong>Total Time:</strong> {{ $complaint->created_at->diffForHumans($complaint->updated_at, true) }}</p>
    </div>

    <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
        <p style="margin: 0; color: #155724;"><strong>✅ Issue Resolved:</strong> Your request has been completed successfully.</p>
    </div>

    @if($complaint->updates->count() > 0)
        <p><strong>Final Resolution Notes:</strong></p>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;">
            {{ $complaint->updates->last()->message ?? 'Issue has been resolved as requested.' }}
        </div>
    @endif

    <p>We hope the resolution meets your expectations. Your feedback helps us improve our services.</p>
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View Resolution Details</a>
</div>

<div class="content">
    <p><strong>What's Next:</strong></p>
    <ul>
        <li>Please verify that the issue has been fully resolved</li>
        <li>If you're satisfied, no further action is needed</li>
        <li>If you encounter the same issue again, feel free to create a new ticket</li>
        <li>Your feedback on our service is always welcome</li>
    </ul>

    <p><strong>Need More Help?</strong></p>
    <ul>
        <li>Create a new ticket for different issues</li>
        <li>Contact support if you need clarification</li>
        <li>Reopen this ticket if the issue persists</li>
    </ul>

    <p>Thank you for using our support system. We're always here to help!</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Support Team</p>
</div>
@endsection