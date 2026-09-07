@extends('emails.layout')

@section('content')
<div class="greeting">
    Hello {{ $user->name ?? 'Resident' }},
</div>

<div class="content">
    <p>This is a friendly reminder that you have an outstanding bill due for {{ $society_name }}.</p>
    
    <div class="info-box">
        <h4>Outstanding Bill Details</h4>
        <p><strong>Bill Number:</strong> {{ $bill->bill_number ?? $bill->id }}</p>
        <p><strong>Bill Type:</strong> {{ ucfirst($bill_type ?? 'Maintenance') }}</p>
        <p><strong>Amount Due:</strong> ₹{{ number_format($amount, 2) }}</p>
        <p><strong>Original Due Date:</strong> {{ \Carbon\Carbon::parse($due_date)->format('F j, Y') }}</p>
        <p><strong>Days Overdue:</strong> {{ \Carbon\Carbon::parse($due_date)->diffInDays(now()) }} days</p>
        @if(isset($bill->flat))
            <p><strong>Property:</strong> {{ $bill->flat->flat_number ?? 'N/A' }}</p>
        @endif
    </div>

    @if(\Carbon\Carbon::parse($due_date)->isPast())
        <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #721c24;"><strong>⚠️ Overdue Payment:</strong> This bill is now overdue. Please make the payment immediately to avoid additional penalties.</p>
        </div>
    @else
        <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
            <p style="margin: 0; color: #856404;"><strong>⏰ Payment Due Soon:</strong> This bill is due within the next few days.</p>
        </div>
    @endif

    <p>To avoid any inconvenience or additional charges, please make your payment as soon as possible.</p>
</div>

<div style="text-align: center;">
    <a href="{{ $view_url }}" class="button">Pay Now</a>
</div>

<div class="content">
    <p><strong>Payment Options Available:</strong></p>
    <ul>
        <li>Online payment through society portal</li>
        <li>Bank transfer to society account</li>
        <li>Cash payment at society office</li>
        <li>Cheque payment (where applicable)</li>
    </ul>

    <p><strong>Late Payment Policy:</strong></p>
    <ul>
        <li>Late fees may apply for overdue payments</li>
        <li>Continued non-payment may affect society services</li>
        <li>Contact accounts department for payment plans if needed</li>
    </ul>

    <p>If you have already made this payment, please ignore this reminder. If you're facing financial difficulties, please contact our accounts team to discuss payment options.</p>
    
    <p>Thank you for your cooperation,<br>
    {{ $society_name }} Accounts Team</p>
</div>
@endsection