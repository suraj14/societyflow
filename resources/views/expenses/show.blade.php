@extends('layouts.app')

@section('title', 'Expense')
@section('page-title', 'Expense')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <a href="{{ route('expenses.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $expense->title }}</h1>
                <p class="text-gray-600 mt-1">{{ $expense->expense_date?->format('d M Y') }} &middot; {{ $expense->category->name ?? 'Uncategorised' }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('expenses.edit', $expense) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium"><i class="fas fa-edit mr-1"></i> Edit</a>
            @if($canDelete)
                <form action="{{ route('expenses.destroy', $expense) }}" method="POST" onsubmit="return confirm('Delete this expense? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 border border-red-300 text-red-700 rounded-lg hover:bg-red-50 text-sm font-medium"><i class="fas fa-trash mr-1"></i> Delete</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="max-w-3xl bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">Society / common expense</span>
                <p class="text-xs text-gray-500 mt-2">Counts towards the society's total expenses in the financial report.</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-500">Amount</p>
                <p class="text-2xl font-bold text-gray-900">₹{{ number_format($expense->amount, 2) }}</p>
            </div>
        </div>
        <dl class="divide-y divide-gray-100 text-sm">
            <div class="px-6 py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">Category</dt><dd class="col-span-2 text-gray-900">{{ $expense->category->name ?? '—' }}</dd></div>
            <div class="px-6 py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">Expense date</dt><dd class="col-span-2 text-gray-900">{{ $expense->expense_date?->format('d M Y') }}</dd></div>
            <div class="px-6 py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">Paid to / vendor</dt><dd class="col-span-2 text-gray-900">{{ $expense->vendor_name ?: '—' }}</dd></div>
            <div class="px-6 py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">Payment method</dt><dd class="col-span-2 text-gray-900">{{ strtoupper($expense->payment_method) === 'UPI' ? 'UPI' : ucfirst($expense->payment_method) }}</dd></div>
            <div class="px-6 py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">Invoice / reference</dt><dd class="col-span-2 text-gray-900">{{ $expense->bill_number ?: '—' }}</dd></div>
            <div class="px-6 py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">Description</dt><dd class="col-span-2 text-gray-900 whitespace-pre-line">{{ $expense->description ?: '—' }}</dd></div>
            <div class="px-6 py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">Receipt</dt>
                <dd class="col-span-2">
                    @if($expense->receipt)
                        <a href="{{ $expense->receipt_url }}" target="_blank" class="text-purple-600 hover:underline"><i class="fas fa-paperclip mr-1"></i> View receipt</a>
                    @else
                        <span class="text-gray-400">None attached</span>
                    @endif
                </dd>
            </div>
            <div class="px-6 py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">Recorded by</dt><dd class="col-span-2 text-gray-900">{{ $expense->createdBy->name ?? '—' }} &middot; {{ $expense->created_at?->format('d M Y, H:i') }}</dd></div>
        </dl>
    </div>
</div>
@endsection
