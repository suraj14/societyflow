@extends('layouts.app')

@section('title', 'Payment Details')
@section('page-title', 'Payment Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <a href="{{ route('payments.index') }}" 
               class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Payment Details</h1>
                <p class="text-gray-600 mt-1">Payment #{{ $payment->id }}</p>
            </div>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('payments.receipt', $payment) }}" 
               target="_blank"
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-print mr-2"></i>
                Print Receipt
            </a>
            <a href="{{ route('payments.edit', $payment) }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-edit mr-2"></i>
                Edit Payment
            </a>
        </div>
    </div>

    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden" id="payment-receipt">
            <div class="p-6">
                <!-- Payment Status -->
                <div class="mb-6">
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                        <i class="fas fa-check-circle mr-1"></i>
                        Payment Completed
                    </span>
                </div>

                <!-- Payment Information Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Amount -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Payment Amount</h3>
                            <div class="text-3xl font-bold text-green-600">₹{{ number_format($payment->amount, 2) }}</div>
                        </div>

                        <!-- Payment Details -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Details</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Date:</span>
                                    <span class="font-medium">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') : 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Due Date:</span>
                                    <span class="font-medium">{{ $payment->due_date ? \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') : 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Bill Type:</span>
                                    <span class="font-medium">{{ $payment->bill_type ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        {{ $payment->payment_method == 'cash' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($payment->payment_method == 'online' ? 'bg-blue-100 text-blue-800' : 
                                           ($payment->payment_method == 'upi' ? 'bg-green-100 text-green-800' : 
                                           ($payment->payment_method == 'cheque' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'))) }}">
                                        {{ ucfirst($payment->payment_method) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Recorded On:</span>
                                    <span class="font-medium">{{ $payment->created_at->format('M d, Y h:i A') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Property Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Property Information</h3>
                            @if($payment->flat)
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex items-center mb-3">
                                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                                        @if($payment->flat->building)
                                            <i class="fas fa-building text-blue-600"></i>
                                        @else
                                            <i class="fas fa-home text-blue-600"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900">
                                            {{ $payment->flat->building ? 'Apartment' : 'Villa' }}
                                        </h4>
                                        <p class="text-gray-600">
                                            {{ $payment->flat->flat_number ?? ($payment->flat->villa_name ?? 'N/A') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm">
                                    @if($payment->flat->building)
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Building:</span>
                                            <span class="font-medium">{{ $payment->flat->building->name }}</span>
                                        </div>
                                        @if($payment->flat->floor)
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Floor:</span>
                                            <span class="font-medium">{{ $payment->flat->floor }}</span>
                                        </div>
                                        @endif
                                    @elseif($payment->flat->villaArea)
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Villa Area:</span>
                                            <span class="font-medium">{{ $payment->flat->villaArea->name }}</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Society:</span>
                                        <span class="font-medium">{{ $payment->society->name ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>
                            @else
                            <p class="text-gray-500">No property information available</p>
                            @endif
                        </div>

                        <!-- Receipt Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Receipt</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Payment ID:</span>
                                        <span class="font-medium font-mono">{{ $payment->payment_id ?? $payment->id }}</span>
                                    </div>
                                    @if($payment->receipt_number)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Receipt Number:</span>
                                        <span class="font-medium font-mono">{{ $payment->receipt_number }}</span>
                                    </div>
                                    @endif
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Status:</span>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ ucfirst($payment->status ?? 'success') }}
                                        </span>
                                    </div>
                                </div>
                                
                                @if($payment->receipt_file_path)
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <i class="fas fa-file-pdf text-red-600 text-lg mr-2"></i>
                                            <span class="text-sm font-medium">Receipt File</span>
                                        </div>
                                        <a href="{{ asset('storage/' . $payment->receipt_file_path) }}" 
                                           target="_blank"
                                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            <i class="fas fa-external-link-alt mr-1"></i>
                                            View
                                        </a>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                @if($payment->notes)
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Notes</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-gray-700">{{ $payment->notes }}</p>
                    </div>
                </div>
                @endif

                <!-- Actions -->
                <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between print:hidden">
                    <div class="flex space-x-3">
                        <a href="{{ route('payments.edit', $payment) }}" 
                           class="text-blue-600 hover:text-blue-800 font-medium transition-colors">
                            <i class="fas fa-edit mr-1"></i>
                            Edit Payment
                        </a>
                        <a href="{{ route('payments.receipt', $payment) }}" 
                           target="_blank"
                           class="text-green-600 hover:text-green-800 font-medium transition-colors">
                            <i class="fas fa-print mr-1"></i>
                            Print Receipt
                        </a>
                    </div>
                    <form action="{{ route('payments.destroy', $payment) }}" 
                          method="POST" 
                          class="inline-block"
                          onsubmit="return confirm('Are you sure you want to delete this payment? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="text-red-600 hover:text-red-800 font-medium transition-colors">
                            <i class="fas fa-trash mr-1"></i>
                            Delete Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection