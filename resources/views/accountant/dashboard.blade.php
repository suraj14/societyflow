@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Finance Dashboard')

@section('content')
<div class="p-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Monthly Collection</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">₹{{ number_format($stats['monthly_collection'], 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-hand-holding-usd text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Pending Dues</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">₹{{ number_format($stats['pending_dues'], 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Bills (Pending)</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['pending_bills'] }} / {{ $stats['total_bills'] }}</p>
                </div>
                <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-file-invoice-dollar text-amber-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Active Rent Tenants</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">{{ $stats['rent_tenants'] }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-key text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <a href="{{ route('expenses.index') }}" class="block bg-white rounded-lg shadow hover:shadow-md transition-shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Society Expenses (Month)</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">₹{{ number_format($stats['monthly_expenses'] ?? 0, 2) }}</p>
                    @php $sfNet = $stats['monthly_net'] ?? 0; @endphp
                    <p class="text-xs mt-1 {{ $sfNet >= 0 ? 'text-green-700' : 'text-red-700' }}">Net: {{ $sfNet < 0 ? '−' : '' }}₹{{ number_format(abs($sfNet), 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-wallet text-purple-600 text-xl"></i>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Payments -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Recent Payments</h3>
                <a href="{{ route('payments.index') }}" class="text-sm text-purple-600 hover:underline">View all</a>
            </div>
            <div class="p-6">
                @forelse($recentPayments as $payment)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $payment->flat->flat_number ?? 'Unit N/A' }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $payment->payment_date?->format('d M Y') }} &middot; {{ ucfirst($payment->status) }}</p>
                        </div>
                        <span class="text-sm font-semibold text-gray-900">₹{{ number_format($payment->amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No payments recorded yet.</p>
                @endforelse
            </div>
        </div>

        <!-- Pending Bills -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Pending Bills</h3>
                <a href="{{ route('utility-bills.index') }}" class="text-sm text-purple-600 hover:underline">View all</a>
            </div>
            <div class="p-6">
                @forelse($pendingBills as $bill)
                    <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $bill->flat->flat_number ?? 'Unit N/A' }}
                            </p>
                            <p class="text-xs text-gray-500">Due {{ $bill->due_date?->format('d M Y') }} &middot; {{ ucfirst($bill->status) }}</p>
                        </div>
                        <span class="text-sm font-semibold text-red-600">₹{{ number_format($bill->balance_amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No pending bills.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
