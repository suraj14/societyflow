@extends('layouts.app')

@section('title', 'View Utility Bill')
@section('page-title', 'View Utility Bill')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <a href="{{ route('utility-bills.index') }}" 
               class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $utilityBill->bill_type }}</h1>
                <p class="text-gray-600 mt-1">Apartment {{ $utilityBill->flat->flat_number ?? 'N/A' }}</p>
            </div>
        </div>
        <div class="flex space-x-3">
            <a href="{{ route('utility-bills.edit', $utilityBill) }}" 
               class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors inline-flex items-center">
                <i class="fas fa-edit mr-2"></i>
                Edit
            </a>
            <form action="{{ route('utility-bills.destroy', $utilityBill) }}" 
                  method="POST" 
                  class="inline-block"
                  onsubmit="return confirm('Are you sure you want to delete this bill?');">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-trash mr-2"></i>
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Bill Amount Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Bill Amount</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2">₹{{ number_format($utilityBill->bill_amount, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-rupee-sign text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Status Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Status</p>
                    <div class="mt-2">
                        <span class="px-3 py-1 text-sm font-semibold rounded-full 
                            {{ $utilityBill->status == 'paid' ? 'bg-green-100 text-green-800' : 
                               ($utilityBill->status == 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst($utilityBill->status) }}
                        </span>
                    </div>
                </div>
                <div class="w-12 h-12 {{ $utilityBill->status == 'paid' ? 'bg-green-100' : ($utilityBill->status == 'partial' ? 'bg-yellow-100' : 'bg-red-100') }} rounded-lg flex items-center justify-center">
                    <i class="fas {{ $utilityBill->status == 'paid' ? 'fa-check-circle text-green-600' : ($utilityBill->status == 'partial' ? 'fa-exclamation-circle text-yellow-600' : 'fa-times-circle text-red-600') }} text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Due Date Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Due Date</p>
                    <p class="text-lg font-bold text-gray-900 mt-2">{{ $utilityBill->due_date->format('M d, Y') }}</p>
                    @if($utilityBill->isOverdue())
                        <p class="text-red-600 text-xs font-medium mt-1">Overdue</p>
                    @endif
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-orange-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Bill Details -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Bill Details</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Bill Type</p>
                    <p class="text-lg text-gray-900 mt-1">{{ $utilityBill->bill_type }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 font-medium">Apartment Number</p>
                    <p class="text-lg text-gray-900 mt-1">{{ $utilityBill->flat->flat_number ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 font-medium">Bill Date</p>
                    <p class="text-lg text-gray-900 mt-1">{{ $utilityBill->bill_date->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 font-medium">Due Date</p>
                    <p class="text-lg text-gray-900 mt-1">{{ $utilityBill->due_date->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 font-medium">Bill Amount</p>
                    <p class="text-lg font-semibold text-green-600 mt-1">₹{{ number_format($utilityBill->bill_amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 font-medium">Status</p>
                    <p class="text-lg text-gray-900 mt-1">
                        <span class="px-3 py-1 text-sm font-semibold rounded-full 
                            {{ $utilityBill->status == 'paid' ? 'bg-green-100 text-green-800' : 
                               ($utilityBill->status == 'partial' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                            {{ ucfirst($utilityBill->status) }}
                        </span>
                    </p>
                </div>
            </div>

            @if($utilityBill->notes)
            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-600 font-medium">Notes</p>
                <p class="text-gray-900 mt-2">{{ $utilityBill->notes }}</p>
            </div>
            @endif

            @if($utilityBill->bill_file_path)
            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-600 font-medium mb-3">Attached Document</p>
                <a href="{{ asset('storage/' . $utilityBill->bill_file_path) }}" 
                   target="_blank"
                   class="inline-flex items-center px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg text-blue-600 hover:bg-blue-100 transition-colors">
                    <i class="fas fa-file mr-2"></i>
                    Download Bill Document
                </a>
            </div>
            @endif
        </div>
    </div>

    <!-- Timeline -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Timeline</h2>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center h-8 w-8 rounded-full bg-blue-100">
                            <i class="fas fa-plus text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-900">Bill Created</p>
                        <p class="text-sm text-gray-500">{{ $utilityBill->created_at->format('M d, Y \a\t h:i A') }}</p>
                    </div>
                </div>
                @if($utilityBill->updated_at != $utilityBill->created_at)
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center h-8 w-8 rounded-full bg-yellow-100">
                            <i class="fas fa-edit text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-900">Last Updated</p>
                        <p class="text-sm text-gray-500">{{ $utilityBill->updated_at->format('M d, Y \a\t h:i A') }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
