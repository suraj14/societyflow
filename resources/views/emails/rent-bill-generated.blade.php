@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Tenant' }},
</div>

<div class="content">
    <p>Your monthly rent bill has been generated for {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Rent Bill Details</h4>
        <p><strong>Bill Number:</strong> {{ $bill->bill_number ?? $bill->id }}</p>
        <p><strong>Month:</strong> {{ \Carbon\Carbon::parse($bill->bill_month ?? now())->format('F Y') }}</p>
        <p><strong>Rent Amount:</strong> ₹{{ number_format($amount, 2) }}</p>
        <p><strong>Due Date:</strong> {{ \Carbon\Carbon::parse($due_date)->format('F j, Y') }}</p>
        @if(isset($bill->flat))
            <p><strong>Property:</strong> {{ $bill->flat->flat_number ?? 'N/A' }}</p>
        @endif
    </div>

    <p>Please ensure your rent payment is made on or before the due date to maintain good standing.</p>

    @if(\Carbon\Carbon::parse($due_date)->isPast())
        <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #721c24;"><strong>⚠️ Overdue Payment:</strong> This rent payment is overdue. Please make the payment immediately to avoid penalties.</p>
        </div>
    @elseif(\Carbon\Carbon::parse($due_date)->diffInDays(now()) <= 3)
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>⚠️ Payment Due Soon:</strong> Your rent is due within the next 3 days.</p>
        </div>
    @endif
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">Pay Rent Now</a>
</div>

<div class="content">
    <p><strong>Payment Methods:</strong></p>
    <ul>
        <li>Online payment via society portal</li>
        <li>Direct bank transfer</li>
        <li>Cash payment at society office</li>
        <li>Cheque payment (if applicable)</li>
    </ul>

    <p>For payment assistance or queries, please contact the property management office.</p>
    
    <p>Best regards,<br>
    {{ $society_name }} Property Management</p>
</div>
@endsection