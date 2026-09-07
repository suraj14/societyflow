@extends('layouts.app')

@section('title', 'Rent Receipt')
@section('page-title', 'Rent Receipt')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6 print:hidden">
        <div class="flex items-center">
            <a href="{{ route('admin.rents.index') }}" 
               class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Rent Receipt</h1>
                <p class="text-gray-600 mt-1">{{ $rent->flat->flat_number ?? $rent->flat->villa_name }} - {{ \Carbon\Carbon::parse($rent->bill_date)->format('F Y') }}</p>
            </div>
        </div>
        <div class="flex space-x-3">
            <button onclick="window.print()" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-print mr-2"></i>
                Print Receipt
            </button>
            <a href="{{ route('admin.rents.edit', $rent) }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-edit mr-2"></i>
                Edit Rent
            </a>
        </div>
    </div>

    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden print:shadow-none" id="rent-receipt">
            <div class="p-8 print:p-6">
                <!-- Header -->
                <div class="text-center mb-8 print:mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">RENT RECEIPT</h2>
                    <div class="w-24 h-1 bg-blue-600 mx-auto mb-4"></div>
                    <p class="text-gray-600">{{ $rent->society->name ?? 'Society Management System' }}</p>
                </div>

                <!-- Receipt Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Left Column - Rent Details -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                            <i class="fas fa-home mr-2 text-blue-600"></i>
                            Rent Information
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Rent Period:</span>
                                <span class="font-medium">{{ \Carbon\Carbon::parse($rent->bill_date)->format('F Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Bill Date:</span>
                                <span class="font-medium">{{ \Carbon\Carbon::parse($rent->bill_date)->format('M d, Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Due Date:</span>
                                <span class="font-medium">{{ \Carbon\Carbon::parse($rent->due_date)->format('M d, Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Bill Number:</span>
                                <span class="font-medium font-mono">{{ $rent->bill_number ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-3">
                                <span class="text-gray-600 font-semibold">Total Rent Amount:</span>
                                <span class="font-bold text-lg text-green-600">₹{{ number_format($rent->total_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Property Details -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                            <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                            Property Details
                        </h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center mb-3">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                                    @if($rent->flat->building)
                                        <i class="fas fa-building text-blue-600"></i>
                                    @else
                                        <i class="fas fa-home text-blue-600"></i>
                                    @endif
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">
                                        {{ $rent->flat->building ? 'Apartment' : 'Villa' }}
                                    </h4>
                                    <p class="text-gray-600">
                                        {{ $rent->flat->flat_number ?? ($rent->flat->villa_name ?? 'N/A') }}
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-2 text-sm">
                                @if($rent->flat->building)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Building:</span>
                                        <span class="font-medium">{{ $rent->flat->building->name }}</span>
                                    </div>
                                    @if($rent->flat->floor)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Floor:</span>
                                        <span class="font-medium">{{ $rent->flat->floor }}</span>
                                    </div>
                                    @endif
                                @elseif($rent->flat->villaArea)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Villa Area:</span>
                                        <span class="font-medium">{{ $rent->flat->villaArea->name }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Society:</span>
                                    <span class="font-medium">{{ $rent->society->name ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenant Information -->
                @if($rent->flat->tenants && $rent->flat->tenants->count() > 0)
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                        <i class="fas fa-user mr-2 text-blue-600"></i>
                        Tenant Information
                    </h3>
                    @php $tenant = $rent->flat->tenants->first(); @endphp
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-600">Name:</span>
                                    <span class="font-medium">{{ $tenant->user->name ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="font-medium">{{ $tenant->user->email ?? 'N/A' }}</span>
                                </div>
                            </div>
                            <div>
                                @if($tenant->phone)
                                <div class="flex justify-between mb-2">
                                    <span class="text-gray-600">Phone:</span>
                                    <span class="font-medium">{{ $tenant->phone }}</span>
                                </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Tenant ID:</span>
                                    <span class="font-medium font-mono">{{ $tenant->id }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Payment History -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                        <i class="fas fa-credit-card mr-2 text-blue-600"></i>
                        Payment History
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment ID</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($rent->payments as $payment)
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                        ₹{{ number_format($payment->amount, 2) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            {{ $payment->payment_method == 'cash' ? 'bg-yellow-100 text-yellow-800' : 
                                               ($payment->payment_method == 'online' ? 'bg-blue-100 text-blue-800' : 
                                               ($payment->payment_method == 'upi' ? 'bg-green-100 text-green-800' : 
                                               ($payment->payment_method == 'cheque' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'))) }}">
                                            {{ ucfirst($payment->payment_method) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            {{ ucfirst($payment->status ?? 'success') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                        {{ $payment->payment_id ?? $payment->id }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Summary -->
                <div class="bg-blue-50 rounded-lg p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-calculator mr-2 text-blue-600"></i>
                        Payment Summary
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-gray-900">₹{{ number_format($rent->total_amount, 2) }}</div>
                            <div class="text-sm text-gray-600">Total Rent</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">₹{{ number_format($rent->paid_amount ?? $rent->payments->sum('amount'), 2) }}</div>
                            <div class="text-sm text-gray-600">Amount Paid</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold {{ ($rent->total_amount - ($rent->paid_amount ?? $rent->payments->sum('amount'))) <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                ₹{{ number_format($rent->total_amount - ($rent->paid_amount ?? $rent->payments->sum('amount')), 2) }}
                            </div>
                            <div class="text-sm text-gray-600">Balance</div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                @if($rent->notes)
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                        <i class="fas fa-sticky-note mr-2 text-blue-600"></i>
                        Notes
                    </h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-gray-700">{{ $rent->notes }}</p>
                    </div>
                </div>
                @endif

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 border-t border-gray-200 pt-6">
                    <p>This is a computer-generated receipt. Generated on {{ now()->format('M d, Y h:i A') }}</p>
                    <p class="mt-1">For any queries, please contact the society administration.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #rent-receipt, #rent-receipt * {
        visibility: visible;
    }
    #rent-receipt {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .print\:hidden {
        display: none !important;
    }
    .print\:shadow-none {
        box-shadow: none !important;
    }
    .print\:p-6 {
        padding: 1.5rem !important;
    }
    .print\:mb-6 {
        margin-bottom: 1.5rem !important;
    }
}
</style>

@endsection