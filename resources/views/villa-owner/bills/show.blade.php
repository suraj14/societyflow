@extends('layouts.app')

@section('title', 'Bill Details')
@section('page-title', 'Bill Details')

@section('content')
<div class="p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('villa-owner.bills') }}" class="text-green-600 hover:text-green-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Bills
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">{{ $bill->description ?? 'Maintenance Bill' }}</h2>
                        <p class="text-gray-500 text-sm mt-1">
                            Bill Date: {{ \Carbon\Carbon::parse($bill->bill_date)->format('d M Y') }}
                        </p>
                    </div>
                    <span class="px-3 py-1 text-sm rounded-full 
                        {{ $bill->status === 'paid' ? 'bg-green-100 text-green-700' : '' }}
                        {{ $bill->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                        {{ $bill->status === 'partial' ? 'bg-blue-100 text-blue-700' : '' }}
                        {{ $bill->status === 'overdue' ? 'bg-red-100 text-red-700' : '' }}">
                        {{ ucfirst($bill->status) }}
                    </span>
                </div>
            </div>

            <div class="p-6">
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <p class="text-sm text-gray-500">Bill Amount</p>
                        <p class="text-2xl font-bold text-gray-800">₹{{ number_format($bill->amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Due Date</p>
                        <p class="text-lg font-medium text-gray-800">{{ \Carbon\Carbon::parse($bill->due_date)->format('d M Y') }}</p>
                    </div>
                </div>

                @if($bill->balance_amount > 0)
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-red-600">Balance Amount</p>
                            <p class="text-xl font-bold text-red-700">₹{{ number_format($bill->balance_amount, 2) }}</p>
                        </div>
                        <a href="#" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            Pay Now
                        </a>
                    </div>
                </div>
                @endif

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Bill Details</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Villa</span>
                            <span class="font-medium">{{ $villa->villa_name ?? $villa->flat_number }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Bill Period</span>
                            <span class="font-medium">{{ \Carbon\Carbon::parse($bill->bill_date)->format('F Y') }}</span>
                        </div>
                        @if($bill->paid_amount > 0)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Paid Amount</span>
                            <span class="font-medium text-green-600">₹{{ number_format($bill->paid_amount, 2) }}</span>
                        </div>
                        @endif
                        @if($bill->late_fee > 0)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100">
                            <span class="text-gray-600">Late Fee</span>
                            <span class="font-medium text-red-600">₹{{ number_format($bill->late_fee, 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                @if($bill->status === 'paid')
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <a href="#" class="inline-flex items-center text-green-600 hover:text-green-800">
                        <i class="fas fa-download mr-2"></i> Download Receipt
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
