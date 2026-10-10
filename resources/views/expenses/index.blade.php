@extends('layouts.app')

@section('title', 'Society Expenses')
@section('page-title', 'Society Expenses')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Society Expenses</h1>
            <p class="text-gray-600 mt-1">Where society money goes: security, electricity, water, gardening and other common expenses.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('expenses.categories') }}" class="px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium inline-flex items-center">
                <i class="fas fa-tags mr-2"></i> Categories
            </a>
            <a href="{{ route('expenses.create') }}" class="bg-purple-700 hover:bg-purple-800 text-white px-5 py-2.5 rounded-lg font-medium inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Add Expense
            </a>
        </div>
    </div>


    {{-- Totals for the current filter --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-gray-500 text-sm font-medium">Total Society Expenses</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($societyTotal, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
            <p class="text-gray-500 text-sm font-medium">Transactions</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $societyCount }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('expenses.index') }}" class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
                <select name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (string) ($filters['category_id'] ?? '') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Payment</label>
                <select name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">All</option>
                    @foreach(['online' => 'Online', 'upi' => 'UPI', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card'] as $value => $label)
                        <option value="{{ $value }}" {{ ($filters['payment_method'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Vendor</label>
                <input type="text" name="vendor" value="{{ $filters['vendor'] ?? '' }}" placeholder="Paid to" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Title, invoice no." class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-3">
            <a href="{{ route('expenses.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Clear</a>
            <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-sm font-medium"><i class="fas fa-filter mr-1"></i> Filter</button>
        </div>
    </form>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        @if($expenses->isEmpty())
            <div class="p-10 text-center text-gray-500">
                <i class="fas fa-wallet text-4xl mb-3 text-gray-300"></i>
                <p>No society/common expenses recorded{{ array_filter($filters) ? ' for these filters' : ' for this period' }}.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Expense</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Paid to</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($expenses as $expense)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $expense->expense_date?->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ $expense->title }}</div>
                                    @if($expense->bill_number)
                                        <div class="text-xs text-gray-500">Ref: {{ $expense->bill_number }}</div>
                                    @endif
                                    @if($isSuperAdmin && $expense->society)
                                        <div class="text-xs text-gray-400">{{ $expense->society->name }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $expense->category->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $expense->vendor_name ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ strtoupper($expense->payment_method) === 'UPI' ? 'UPI' : ucfirst($expense->payment_method) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">₹{{ number_format($expense->amount, 2) }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if($expense->receipt)
                                        <a href="{{ $expense->receipt_url }}" target="_blank" class="text-gray-500 hover:text-gray-800 mr-2" title="Receipt"><i class="fas fa-paperclip"></i></a>
                                    @endif
                                    <a href="{{ route('expenses.show', $expense) }}" class="text-blue-600 hover:underline mr-2">View</a>
                                    <a href="{{ route('expenses.edit', $expense) }}" class="text-purple-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $expenses->links() }}</div>
        @endif
    </div>
</div>
@endsection
