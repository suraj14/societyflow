@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>A new {{ $bill_type }} bill has been generated for your property in {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Bill Details</h4>
        <p><strong>Bill Number:</strong> {{ $bill->bill_number ?? $bill->id }}</p>
        <p><strong>Bill Type:</strong> {{ ucfirst($bill_type) }}</p>
        <p><strong>Amount:</strong> ₹{{ number_format($amount, 2) }}</p>
        <p><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($due_date)->format('F j, Y') }}</p>
        @if(isset($bill->flat))
            <p><strong>Property:</strong> {{ $bill->flat->flat_number ?? 'N/A' }}</p>
        @endif
    </div>

    <p>Please review your bill and make the payment before the due date to avoid any late fees.</p>

    @if(\Carbon\Carbon::parse($due_date)->diffInDays(now()) <= 3)
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>⚠️ Payment Due Soon:</strong> This bill is due within the next 3 days. Please make your payment promptly.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">View & Pay Bill</a>
</div>

<div class="content">
    <p><strong>Payment Options:</strong></p>
    <ul>
        <li>Online payment through the society portal</li>
        <li>Bank transfer to society account</li>
        <li>Cash payment at the society office</li>
    </ul>

    <p>For any questions about this bill, please contact the society accounts department.</p>
    
    <p>Thank you,<br>
    {{ $society_name }} Accounts Team</p>
</div>
@endsection