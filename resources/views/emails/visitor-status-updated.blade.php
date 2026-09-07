@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>The status of a visitor entry for your property has been updated in {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Updated Visitor Status</h4>
        <p><strong>Visitor Name:</strong> {{ $visitor->name }}</p>
        <p><strong>Phone Number:</strong> {{ $visitor->phone }}</p>
        <p><strong>Visit Date:</strong> {{ \Carbon\Carbon::parse($visitor->visit_date ?? $visitor->created_at)->format('F j, Y') }}</p>
        <p><strong>Previous Status:</strong> {{ ucfirst($visitor->getOriginal('status') ?? 'pending') }}</p>
        <p><strong>Current Status:</strong> 
            <span style="color: {{ $visitor->status === 'approved' ? '#28a745' : ($visitor->status === 'denied' ? '#dc3545' : '#6c757d') }};">
                {{ ucfirst($visitor->status) }}
            </span>
        </p>
        <p><strong>Updated on:</strong> {{ $visitor->updated_at->format('F j, Y \a\t g:i A') }}</p>
    </div>

    @if($visitor->status === 'approved')
        <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #155724;"><strong>✅ Visitor Approved:</strong> Your visitor has been approved for entry.</p>
        </div>
    @elseif($visitor->status === 'denied')
        <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #721c24;"><strong>❌ Visitor Denied:</strong> The visitor entry has been denied.</p>
        </div>
    @elseif($visitor->status === 'checked_in')
        <div style="background-color: #cce5ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #004085;"><strong>🏠 Visitor Checked In:</strong> Your visitor has arrived and checked in.</p>
        </div>
    @elseif($visitor->status === 'checked_out')
        <div style="background-color: #e2e3e5; border-left: 4px solid #6c757d; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #383d41;"><strong>👋 Visitor Checked Out:</strong> Your visitor has checked out.</p>
        </div>
    @endif

    @if($visitor->status_notes ?? false)
        <p><strong>Status Notes:</strong></p>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;">
            {{ $visitor->status_notes }}
        </div>
    @endif

    @if($visitor->check_in_time && $visitor->status === 'checked_in')
        <p><strong>Check-in Details:</strong></p>
        <ul>
            <li>Check-in Time: {{ \Carbon\Carbon::parse($visitor->check_in_time)->format('F j, Y \a\t g:i A') }}</li>
            @if($visitor->security_guard)
                <li>Checked in by: {{ $visitor->security_guard }}</li>
            @endif
        </ul>
    @endif

    @if($visitor->check_out_time && $visitor->status === 'checked_out')
        <p><strong>Check-out Details:</strong></p>
        <ul>
            <li>Check-out Time: {{ \Carbon\Carbon::parse($visitor->check_out_time)->format('F j, Y \a\t g:i A') }}</li>
            <li>Total Visit Duration: {{ \Carbon\Carbon::parse($visitor->check_in_time)->diffForHumans(\Carbon\Carbon::parse($visitor->check_out_time), true) }}</li>
        </ul>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url ?? route('visitors.index') }}" class="button">View Visitor Details</a>
</div>

<div class="content">
    @if($visitor->status === 'approved')
        <p><strong>Next Steps:</strong></p>
        <ul>
            <li>Inform your visitor about the approval</li>
            <li>Remind them to carry valid identification</li>
            <li>Share the visitor entry details if needed</li>
            <li>Contact security if there are any changes</li>
        </ul>
    @elseif($visitor->status === 'denied')
        <p><strong>Visitor Entry Denied:</strong></p>
        <ul>
            <li>Inform your visitor about the denial</li>
            <li>Check the status notes for the reason</li>
            <li>Contact administration if you need clarification</li>
            <li>Create a new entry if circumstances change</li>
        </ul>
    @elseif($visitor->status === 'checked_in')
        <p><strong>Visitor Has Arrived:</strong></p>
        <ul>
            <li>Your visitor has successfully checked in</li>
            <li>They are now authorized to be on the premises</li>
            <li>Ensure they check out when leaving</li>
            <li>Contact security if there are any issues</li>
        </ul>
    @elseif($visitor->status === 'checked_out')
        <p><strong>Visit Completed:</strong></p>
        <ul>
            <li>Your visitor has successfully checked out</li>
            <li>The visit record is now complete</li>
            <li>Keep this for your records</li>
            <li>Create a new entry for future visits</li>
        </ul>
    @endif

    <p>For any questions about visitor management or security procedures, please contact the security desk.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Security Team</p>
</div>
@endsection