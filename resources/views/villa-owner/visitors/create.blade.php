@extends('layouts.app')

@section('title', 'Add Visitor')
@section('page-title', 'Add Visitor')

@section('content')
<div class="p-6">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Add Expected Visitor</h2>
                <p class="text-sm text-gray-500">Security will be notified about your visitor</p>
            </div>

            <form action="{{ route('villa-owner.visitors.store') }}" method="POST" class="p-6">
                @csrf

                <div class="space-y-6">
                    <!-- Visitor Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Visitor Name *</label>
                        <input type="text" name="visitor_name" value="{{ old('visitor_name') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Enter visitor's name">
                        @error('visitor_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Visitor Phone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                        <input type="text" name="visitor_phone" value="{{ old('visitor_phone') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Enter phone number">
                        @error('visitor_phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Visitor Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email (Optional)</label>
                        <input type="email" name="visitor_email" value="{{ old('visitor_email') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Enter email address">
                        @error('visitor_email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Purpose -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose of Visit *</label>
                        <select name="purpose" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Select purpose</option>
                            <option value="Guest" {{ old('purpose') === 'Guest' ? 'selected' : '' }}>Guest</option>
                            <option value="Family" {{ old('purpose') === 'Family' ? 'selected' : '' }}>Family</option>
                            <option value="Friend" {{ old('purpose') === 'Friend' ? 'selected' : '' }}>Friend</option>
                            <option value="Delivery" {{ old('purpose') === 'Delivery' ? 'selected' : '' }}>Delivery</option>
                            <option value="Service" {{ old('purpose') === 'Service' ? 'selected' : '' }}>Service (Plumber, Electrician, etc.)</option>
                            <option value="Cab/Taxi" {{ old('purpose') === 'Cab/Taxi' ? 'selected' : '' }}>Cab/Taxi</option>
                            <option value="Other" {{ old('purpose') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('purpose')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Visit Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Visit Date *</label>
                        <input type="date" name="visit_date" value="{{ old('visit_date', date('Y-m-d')) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        @error('visit_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Expected Time -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expected Time (Optional)</label>
                        <input type="time" name="expected_time" value="{{ old('expected_time') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        @error('expected_time')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Vehicle Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vehicle Number (Optional)</label>
                        <input type="text" name="vehicle_number" value="{{ old('vehicle_number') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="e.g., MH 01 AB 1234">
                        @error('vehicle_number')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea name="notes" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                  placeholder="Any additional information for security">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('villa-owner.visitors') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Add Visitor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
