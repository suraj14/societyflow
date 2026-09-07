@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>A visitor entry has been created for your property in {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Visitor Details</h4>
        <p><strong>Visitor Name:</strong> {{ $visitor->name }}</p>
        <p><strong>Phone Number:</strong> {{ $visitor->phone }}</p>
        <p><strong>Purpose of Visit:</strong> {{ $visitor->purpose ?? 'General Visit' }}</p>
        <p><strong>Expected Date:</strong> {{ \Carbon\Carbon::parse($visitor->visit_date ?? $visitor->created_at)->format('F j, Y') }}</p>
        <p><strong>Expected Time:</strong> {{ \Carbon\Carbon::parse($visitor->visit_time ?? $visitor->created_at)->format('g:i A') }}</p>
        <p><strong>Status:</strong> {{ ucfirst($visitor->status ?? 'pending') }}</p>
        @if($visitor->flat)
            <p><strong>Property:</strong> {{ $visitor->flat->flat_number ?? 'N/A' }}</p>
        @endif
    </div>

    @if($visitor->status === 'pending')
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>⏳ Approval Pending:</strong> This visitor entry is waiting for your approval.</p>
        </div>
    @elseif($visitor->status === 'approved')
        <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #155724;"><strong>✅ Approved:</strong> This visitor has been approved for entry.</p>
        </div>
    @endif

    @if($visitor->additional_info ?? false)
        <p><strong>Additional Information:</strong></p>
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;">
            {{ $visitor->additional_info }}
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url ?? route('visitors.index') }}" class="button">Manage Visitor Entry</a>
</div>

<div class="content">
    @if($visitor->status === 'pending')
        <p><strong>Action Required:</strong></p>
        <ul>
            <li>Review the visitor details above</li>
            <li>Approve or deny the visitor entry</li>
            <li>Add any special instructions if needed</li>
            <li>The security team will be notified of your decision</li>
        </ul>
    @else
        <p><strong>For Your Information:</strong></p>
        <ul>
            <li>Keep this record for your reference</li>
            <li>Inform your visitor about the approval status</li>
            <li>Ensure your visitor carries valid ID</li>
            <li>Contact security if there are any issues</li>
        </ul>
    @endif

    <p>For any questions about visitor management, please contact the security desk or society administration.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Security Team</p>
</div>
@endsection