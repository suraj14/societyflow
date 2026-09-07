@extends('layouts.app')

@section('title', 'Edit Payment')
@section('page-title', 'Edit Payment')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('payments.index') }}" 
           class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
            <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Payment</h1>
            <p class="text-gray-600 mt-1">Update payment details</p>
        </div>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <form action="{{ route('payments.update', $payment) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                {{-- Hidden fields - property is fixed on edit --}}
                <input type="hidden" name="property_type" value="{{ $payment->flat ? $payment->flat->property_type : 'apartment' }}">
                <input type="hidden" name="flat_id" value="{{ $payment->flat_id }}">

                {{-- Current Property (read-only display) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Property</label>
                    <div class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                        @if($payment->flat)
                            @if($payment->flat->property_type === 'villa')
                                {{ $payment->flat->villa_name ?? $payment->flat->flat_number }}
                                @if($payment->flat->villaArea) — {{ $payment->flat->villaArea->name }} @endif
                            @else
                                Flat {{ $payment->flat->flat_number }}
                                @if($payment->flat->building) — {{ $payment->flat->building->name }} @endif
                            @endif
                        @else
                            N/A
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Property cannot be changed when editing a payment.</p>
                </div>

                <!-- Bill Type -->
                <div>
                    <label for="bill_type" class="block text-sm font-medium text-gray-700 mb-2">
                        Bill Type <span class="text-red-500">*</span>
                    </label>
                    <select name="bill_type" id="bill_type" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('bill_type') border-red-500 @enderror"
                            required>
                        <option value="">Select Bill Type</option>
                        @foreach(['Maintenance','Water','Electricity','Gas','Internet','Cable TV','Sewage','Parking','Other'] as $type)
                            <option value="{{ $type }}" {{ old('bill_type', $payment->bill_type) == $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('bill_type')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Amount -->
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                        Amount <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">₹</span>
                        </div>
                        <input type="number" name="amount" id="amount" step="0.01" min="0"
                               value="{{ old('amount', $payment->amount) }}"
                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('amount') border-red-500 @enderror"
                               placeholder="0.00" required>
                    </div>
                    @error('amount')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Payment Method -->
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment Method <span class="text-red-500">*</span>
                    </label>
                    <select name="payment_method" id="payment_method" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('payment_method') border-red-500 @enderror"
                            required>
                        <option value="">Select payment method</option>
                        <option value="cash" {{ old('payment_method', $payment->payment_method) == 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="online" {{ old('payment_method', $payment->payment_method) == 'online' ? 'selected' : '' }}>Online Transfer</option>
                        <option value="upi" {{ old('payment_method', $payment->payment_method) == 'upi' ? 'selected' : '' }}>UPI</option>
                        <option value="cheque" {{ old('payment_method', $payment->payment_method) == 'cheque' ? 'selected' : '' }}>Cheque</option>
                    </select>
                    @error('payment_method')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Payment Date -->
                <div>
                    <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Payment Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="payment_date" id="payment_date"
                           value="{{ old('payment_date', $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') : '') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('payment_date') border-red-500 @enderror"
                           required>
                    @error('payment_date')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Bill Due Date -->
                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">
                        Bill Due Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="due_date" id="due_date"
                           value="{{ old('due_date', $payment->due_date ? \Carbon\Carbon::parse($payment->due_date)->format('Y-m-d') : '') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('due_date') border-red-500 @enderror"
                           required>
                    @error('due_date')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Upload Receipt (Optional) -->
                <div>
                    <label for="receipt_file" class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Receipt (Optional)
                    </label>
                    
                    @if($payment->receipt_file_path)
                        <div class="mb-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i class="fas fa-file-pdf text-red-600 text-xl mr-3"></i>
                                    <div>
                                        <p class="font-medium text-gray-900">Current Receipt</p>
                                        <p class="text-sm text-gray-500">{{ basename($payment->receipt_file_path) }}</p>
                                    </div>
                                </div>
                                <a href="{{ asset('storage/' . $payment->receipt_file_path) }}" target="_blank" 
                                   class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                    @endif
                    
                    <input type="file" name="receipt_file" id="receipt_file" accept=".pdf,.jpg,.jpeg,.png"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">PDF, JPG, PNG up to 5MB. Leave empty to keep existing receipt.</p>
                    @error('receipt_file')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Add any additional notes (optional)">{{ old('notes', $payment->notes) }}</textarea>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="{{ route('payments.index') }}" 
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        Update Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
