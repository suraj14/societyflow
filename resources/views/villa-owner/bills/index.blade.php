@extends('layouts.app')

@section('title', 'My Bills')
@section('page-title', 'My Bills')

@section('content')
<div class="p-6">
    <!-- Summary Card -->
    <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl p-6 text-white mb-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-red-100 text-sm">Total Pending Amount</p>
                <h2 class="text-3xl font-bold">₹{{ number_format($pendingAmount, 0) }}</h2>
            </div>
            @if($pendingAmount > 0)
                <a href="#" class="px-4 py-2 bg-white text-red-600 rounded-lg font-medium hover:bg-red-50 transition-colors">
                    Pay Now
                </a>
            @endif
        </div>
    </div>

    <!-- Bills List -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Bill History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($bills as $bill)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-gray-800">{{ $bill->description ?? 'Maintenance Bill' }}</p>
                                <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($bill->bill_date)->format('M Y') }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-gray-800">₹{{ number_format($bill->amount, 0) }}</p>
                                @if($bill->balance_amount > 0 && $bill->balance_amount < $bill->amount)
                                    <p class="text-xs text-red-500">Balance: ₹{{ number_format($bill->balance_amount, 0) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ \Carbon\Carbon::parse($bill->due_date)->format('d M Y') }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $bill->status === 'paid' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $bill->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $bill->status === 'partial' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $bill->status === 'overdue' ? 'bg-red-100 text-red-700' : '' }}">
                                    {{ ucfirst($bill->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('villa-owner.bills.show', $bill) }}" class="text-green-600 hover:text-green-800" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($bill->status !== 'paid')
                                        <a href="#" class="text-blue-600 hover:text-blue-800" title="Pay">
                                            <i class="fas fa-credit-card"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-file-invoice text-4xl text-gray-300 mb-3"></i>
                                <p>No bills yet</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bills->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $bills->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
