@extends('layouts.app')

@section('title', 'Edit Rent Record')
@section('page-title', 'Edit Rent Record')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Rent Record</h1>
            <p class="text-gray-600 mt-1">Update rent record information</p>
        </div>
        <a href="{{ route('admin.rents.index') }}" 
           class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            Back to Rent Records
        </a>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
            <div class="flex items-center mb-2">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <p class="font-medium">Please fix the following errors:</p>
            </div>
            <ul class="list-disc list-inside ml-6">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <form action="{{ route('admin.rents.update', $rent) }}" method="POST" class="p-6">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Tenant & Unit Selection -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Tenant & Unit</h3>
                    
                    <!-- Current Assignment Info -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-blue-900 mb-2">Current Assignment</h4>
                        <p class="text-sm text-blue-800">
                            <strong>Tenant:</strong> {{ $rent->flat->tenants->first()->user->name ?? 'N/A' }}<br>
                            <strong>Unit:</strong> {{ $rent->flat->flat_number ?? $rent->flat->villa_name }}<br>
                            <strong>Location:</strong> {{ $rent->flat->building->name ?? $rent->flat->villaArea->name ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <!-- Unit Selection -->
                    <div>
                        <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">Change Tenant Unit *</label>
                        <select id="flat_id" 
                                name="flat_id" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                required>
                            <option value="">Select a tenant unit</option>
                            @foreach($tenantFlats as $flat)
                                <option value="{{ $flat->id }}" 
                                        {{ old('flat_id', $rent->flat_id) == $flat->id ? 'selected' : '' }}>
                                    {{ $flat->flat_number ?? $flat->villa_name }} - 
                                    {{ $flat->building->name ?? $flat->villaArea->name ?? 'N/A' }}
                                    @if($flat->tenants->first())
                                        ({{ $flat->tenants->first()->user->name ?? 'Tenant' }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Only units with active tenants are shown.</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="4"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                  placeholder="Enter rent description (optional)">{{ old('description', $rent->notes) }}</textarea>
                        <p class="text-sm text-gray-500 mt-1">Brief description of the rent charge.</p>
                    </div>
                </div>

                <!-- Rent Details -->
                <div class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Rent Details</h3>
                    
                    <!-- Amount -->
                    <div>
                        <label for="rent_amount" class="block text-sm font-medium text-gray-700 mb-2">Rent Amount *</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₹</span>
                            <input type="number" 
                                   id="rent_amount" 
                                   name="rent_amount" 
                                   value="{{ old('rent_amount', $rent->maintenance_amount) }}"
                                   step="0.01"
                                   min="0"
                                   class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="0.00"
                                   required>
                        </div>
                    </div>

                    <!-- Bill Date -->
                    <div>
                        <label for="bill_date" class="block text-sm font-medium text-gray-700 mb-2">Bill Date *</label>
                        <input type="date" 
                               id="bill_date" 
                               name="bill_date" 
                               value="{{ old('bill_date', $rent->bill_date instanceof \Carbon\Carbon ? $rent->bill_date->format('Y-m-d') : $rent->bill_date) }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               required>
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                        <input type="date" 
                               id="due_date" 
                               name="due_date" 
                               value="{{ old('due_date', $rent->due_date instanceof \Carbon\Carbon ? $rent->due_date->format('Y-m-d') : $rent->due_date) }}"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                               required>
                        <p class="text-sm text-gray-500 mt-1">Due date must be after the bill date.</p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                        @php
                            $statusClasses = [
                                'paid' => 'bg-green-100 text-green-800',
                                'partial' => 'bg-yellow-100 text-yellow-800',
                                'overdue' => 'bg-red-100 text-red-800',
                            ][$rent->status] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <div class="flex items-center gap-3">
                            <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium {{ $statusClasses }}">{{ ucfirst($rent->status) }}</span>
                            <span class="text-sm text-gray-600">Paid ₹{{ number_format($rent->paid_amount, 2) }} of ₹{{ number_format($rent->total_amount, 2) }}</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            Status is calculated from the payments recorded against this rent.
                            To mark it paid, use <a href="{{ route('admin.rents.index') }}" class="text-blue-600 hover:underline">Add Payment</a> on the rent list.
                        </p>
                    </div>

                    <!-- Payment Information -->
                    @if($rent->payments->count() > 0)
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-green-900 mb-2">Payment History</h4>
                            <div class="space-y-2">
                                @foreach($rent->payments as $payment)
                                    <p class="text-sm text-green-800">
                                        <strong>₹{{ number_format($payment->amount, 2) }}</strong> - 
                                        {{ $payment->created_at->format('M d, Y') }}
                                        ({{ ucfirst($payment->status) }})
                                    </p>
                                @endforeach
                            </div>
                            <p class="text-sm text-green-700 mt-2">
                                <strong>Total Paid:</strong> ₹{{ number_format($rent->payments->where('status', 'success')->sum('amount'), 2) }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.rents.index') }}" 
                   class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-save mr-2"></i>
                    Update Rent Record
                </button>
            </div>
        </form>
    </div>
</div>
@endsection