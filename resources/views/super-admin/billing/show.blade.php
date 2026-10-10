@extends('layouts.super-admin')

@section('title', 'Payment #' . $payment->id)
@section('page-title', 'Payment Details')

@section('content')
@php
    $badge = [
        'success'  => 'bg-green-100 text-green-800',
        'pending'  => 'bg-yellow-100 text-yellow-800',
        'failed'   => 'bg-red-100 text-red-800',
        'refunded' => 'bg-gray-100 text-gray-800',
    ][$payment->status] ?? 'bg-gray-100 text-gray-800';
@endphp
<div class="max-w-3xl space-y-6">
    <div>
        <a href="{{ route('super-admin.billing.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Billing
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-r text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">Payment #{{ $payment->id }}</h2>
            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $badge }}">{{ ucfirst($payment->status) }}</span>
        </div>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 p-6 text-sm">
            <div><dt class="text-gray-500">Society</dt><dd class="font-medium text-gray-900">{{ $payment->society->name ?? 'N/A' }}</dd></div>
            <div><dt class="text-gray-500">Package</dt><dd class="font-medium text-gray-900">{{ $payment->subscriptionPlan->name ?? 'N/A' }}</dd></div>
            <div><dt class="text-gray-500">Billing cycle</dt><dd class="font-medium text-gray-900">{{ ucfirst($payment->billing_cycle ?? 'N/A') }}</dd></div>
            <div><dt class="text-gray-500">Amount</dt><dd class="font-medium text-gray-900">&#8377;{{ number_format($payment->amount ?? 0, 2) }}</dd></div>
            <div><dt class="text-gray-500">Payment method</dt><dd class="font-medium text-gray-900">{{ ucfirst($payment->payment_method ?? '-') }}</dd></div>
            <div><dt class="text-gray-500">Payment date</dt><dd class="font-medium text-gray-900">{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : '-' }}</dd></div>
            <div><dt class="text-gray-500">Transaction ID</dt><dd class="font-medium text-gray-900">{{ $payment->transaction_id ?: '-' }}</dd></div>
            <div><dt class="text-gray-500">Payment reference</dt><dd class="font-medium text-gray-900">{{ $payment->payment_id }}</dd></div>
            <div><dt class="text-gray-500">Subscription ends</dt><dd class="font-medium text-gray-900">{{ $payment->subscription?->end_date ? $payment->subscription->end_date->format('d M Y') : '-' }}</dd></div>
            <div><dt class="text-gray-500">Next billing date</dt><dd class="font-medium text-gray-900">{{ $payment->next_payment_date ? $payment->next_payment_date->format('d M Y') : '-' }}</dd></div>
            @if($payment->notes)
                <div class="sm:col-span-2"><dt class="text-gray-500">Notes</dt><dd class="text-gray-900">{{ $payment->notes }}</dd></div>
            @endif
        </dl>
        <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center gap-3">
            @if(in_array($payment->status, ['pending', 'failed'], true))
                <form action="{{ route('super-admin.billing.mark-paid', $payment) }}" method="POST"
                      onsubmit="return confirm('Mark this payment as received and extend the subscription?')">
                    @csrf
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-check mr-2"></i>Mark as paid
                    </button>
                </form>
            @endif
            <a href="{{ route('super-admin.billing.invoice', $payment) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                <i class="fas fa-download mr-2"></i>Download invoice
            </a>
        </div>
    </div>
</div>
@endsection
