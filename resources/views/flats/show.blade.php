@extends('layouts.app')

@section('title', 'Flat Details')
@section('page-title', 'Flat ' . $flat->flat_number)

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('flats.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Apartments
            </a>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('flats.edit', $flat) }}" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Flat Details -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-blue-500 to-blue-600 rounded-t-xl">
                    <div class="text-white">
                        <p class="text-blue-100 text-sm">Apartment</p>
                        <h2 class="text-2xl font-bold">{{ $flat->flat_number }}</h2>
                        <p class="text-blue-100 mt-1">{{ $flat->building->name ?? 'Building' }}</p>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Society</p>
                        <p class="font-medium text-gray-800">{{ $flat->society->name ?? $flat->building->society->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Floor</p>
                        <p class="font-medium text-gray-800">{{ $flat->floor ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Type</p>
                        <p class="font-medium text-gray-800">{{ $flat->type ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 py-1 text-xs rounded-full 
                            {{ $flat->status === 'occupied' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $flat->status === 'vacant' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $flat->status === 'maintenance' || $flat->status === 'under_maintenance' ? 'bg-red-100 text-red-700' : '' }}">
                            {{ ucfirst(str_replace('_', ' ', $flat->status)) }}
                        </span>
                    </div>
                    @if($flat->maintenance_amount)
                    <div>
                        <p class="text-sm text-gray-500">Monthly Maintenance</p>
                        <p class="font-medium text-gray-800">₹{{ number_format($flat->maintenance_amount, 0) }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Property Details -->
            <div class="bg-white rounded-xl border border-gray-200 mt-6">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Property Details</h3>
                </div>
                <div class="p-6 space-y-4">
                    @if($flat->carpet_area)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Carpet Area</span>
                        <span class="font-medium">{{ number_format($flat->carpet_area) }} sq.ft</span>
                    </div>
                    @endif
                    @if($flat->built_up_area)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Built-up Area</span>
                        <span class="font-medium">{{ number_format($flat->built_up_area) }} sq.ft</span>
                    </div>
                    @endif
                    @if($flat->bedrooms)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Bedrooms</span>
                        <span class="font-medium">{{ $flat->bedrooms }}</span>
                    </div>
                    @endif
                    @if($flat->bathrooms)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Bathrooms</span>
                        <span class="font-medium">{{ $flat->bathrooms }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Current Resident -->
            @if($flat->activeResident)
            <div class="bg-white rounded-xl border border-gray-200 mt-6">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Current Resident</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-gray-500"></i>
                        </div>
                        <div class="ml-4">
                            <p class="font-medium text-gray-800">{{ $flat->activeResident->name }}</p>
                            <p class="text-sm text-gray-500">{{ ucfirst($flat->activeResident->type) }}</p>
                        </div>
                    </div>
                    @if($flat->activeResident->phone)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-sm text-gray-500">Phone</p>
                        <p class="font-medium text-gray-800">{{ $flat->activeResident->phone }}</p>
                    </div>
                    @endif
                    @if($flat->activeResident->email)
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-medium text-gray-800">{{ $flat->activeResident->email }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Stats & Activity -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Billing Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <p class="text-sm text-gray-500">Total Bills</p>
                    <p class="text-2xl font-bold text-gray-800">{{ $stats['total_bills'] }}</p>
                </div>
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <p class="text-sm text-gray-500">Paid Bills</p>
                    <p class="text-2xl font-bold text-green-600">{{ $stats['paid_bills'] }}</p>
                </div>
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <p class="text-sm text-gray-500">Pending Bills</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending_bills'] }}</p>
                </div>
                <div class="bg-white rounded-xl p-4 border border-gray-200">
                    <p class="text-sm text-gray-500">Total Pending</p>
                    <p class="text-2xl font-bold text-red-600">₹{{ number_format($stats['total_pending'], 0) }}</p>
                </div>
            </div>

            <!-- Recent Bills -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Recent Maintenance Bills</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($flat->maintenanceBills->take(5) as $bill)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-medium text-gray-800">{{ $bill->description ?? 'Maintenance Bill' }}</p>
                                        <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($bill->bill_date)->format('M Y') }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-800">
                                        ₹{{ number_format($bill->amount, 0) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ \Carbon\Carbon::parse($bill->due_date)->format('d M Y') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full 
                                            {{ $bill->status === 'paid' ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $bill->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $bill->status === 'overdue' ? 'bg-red-100 text-red-700' : '' }}">
                                            {{ ucfirst($bill->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                        No maintenance bills yet
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Description -->
            @if($flat->description)
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Description</h3>
                </div>
                <div class="p-6">
                    <p class="text-gray-700">{{ $flat->description }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
