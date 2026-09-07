@extends('layouts.app')

@section('title', 'Edit Booking')
@section('page-title', 'Edit Booking')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('facility-bookings.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Booking</h1>
            <p class="text-gray-600 mt-1">Booking #{{ $facilityBooking->booking_number }}</p>
        </div>
    </div>

    <div class="max-w-4xl">
        <!-- Current Status Info -->
        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-info-circle mr-3"></i>
                <div>
                    <p class="font-medium">Current Status: {{ ucfirst($facilityBooking->status) }}</p>
                    <p class="text-sm">You can edit booking details, but only Admin can change the status.</p>
                </div>
            </div>
        </div>

        <!-- Edit Booking Form -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Edit Booking Details</h2>
            </div>
            
            <form action="{{ route('facility-bookings.update', $facilityBooking) }}" method="POST" class="px-6 py-6">
                @csrf
                @method('PUT')
                
                <div class="space-y-6">
                    <!-- Booked By (Auto-filled, read-only) -->
                    <div>
                        <label for="booked_by" class="block text-sm font-medium text-gray-700 mb-2">
                            Booked By
                        </label>
                        <input type="text" id="booked_by" readonly
                               value="{{ $facilityBooking->user->name }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                    </div>

                    <!-- Facility Selection -->
                    <div>
                        <label for="facility_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Facility <span class="text-red-500">*</span>
                        </label>
                        <select name="facility_id" id="facility_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Facility</option>
                            @foreach($facilities as $facility)
                                <option value="{{ $facility->id }}" 
                                        data-charge="{{ $facility->booking_charge }}"
                                        {{ old('facility_id', $facilityBooking->facility_id) == $facility->id ? 'selected' : '' }}>
                                    {{ $facility->name }} (₹{{ number_format($facility->booking_charge, 2) }})
                                </option>
                            @endforeach
                        </select>
                        @error('facility_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Property Selection (Role-based) -->
                    @if($propertyType && $properties->count() > 0)
                        <div>
                            <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ ucfirst($propertyType) }} <span class="text-red-500">*</span>
                            </label>
                            <select name="flat_id" id="flat_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select {{ ucfirst($propertyType) }}</option>
                                @foreach($properties as $property)
                                    <option value="{{ $property['id'] }}" {{ old('flat_id', $facilityBooking->flat_id) == $property['id'] ? 'selected' : '' }}>
                                        {{ $property['display_name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('flat_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    @elseif($propertyType && $properties->count() == 0)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-yellow-900 mb-1">No Properties Available</h4>
                                    <p class="text-sm text-yellow-700">
                                        You don't have any {{ $propertyType }}s available for booking. Please contact your administrator.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Admin: show current property as read-only, use hidden flat_id -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Property</label>
                            <div class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                                @if($facilityBooking->flat)
                                    @if($facilityBooking->flat->property_type === 'villa')
                                        {{ $facilityBooking->flat->villa_name ?? $facilityBooking->flat->flat_number }}
                                        @if($facilityBooking->flat->villaArea) — {{ $facilityBooking->flat->villaArea->name }} @endif
                                    @else
                                        Flat {{ $facilityBooking->flat->flat_number }}
                                        @if($facilityBooking->flat->building) — {{ $facilityBooking->flat->building->name }} @endif
                                    @endif
                                @else
                                    N/A
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Property cannot be changed when editing a booking.</p>
                        </div>
                        <input type="hidden" name="flat_id" value="{{ $facilityBooking->flat_id }}">
                    @endif

                    <!-- Booking Date & Time -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="booking_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Booking Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="booking_date" id="booking_date" required
                                   value="{{ old('booking_date', $facilityBooking->booking_date) }}"
                                   min="{{ date('Y-m-d') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('booking_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Start Time <span class="text-red-500">*</span>
                            </label>
                            <input type="time" name="start_time" id="start_time" required
                                   value="{{ old('start_time', $facilityBooking->start_time) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('start_time')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">
                                End Time <span class="text-red-500">*</span>
                            </label>
                            <input type="time" name="end_time" id="end_time" required
                                   value="{{ old('end_time', $facilityBooking->end_time) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('end_time')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Purpose & Guests -->
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                            Purpose of Booking
                        </label>
                        <textarea name="purpose" id="purpose" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="e.g., Birthday party, Family gathering, etc.">{{ old('purpose', $facilityBooking->purpose) }}</textarea>
                        @error('purpose')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="expected_guests" class="block text-sm font-medium text-gray-700 mb-2">
                            Expected Number of Guests
                        </label>
                        <input type="number" name="expected_guests" id="expected_guests" min="0"
                               value="{{ old('expected_guests', $facilityBooking->expected_guests) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('expected_guests')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('facility-bookings.index') }}" 
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        Update Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Facility charge display update
    const facilitySelect = document.getElementById('facility_id');
    if (facilitySelect) {
        facilitySelect.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const charge = selected ? selected.getAttribute('data-charge') : null;
            const chargeDisplay = document.getElementById('facility_charge');
            if (chargeDisplay && charge) {
                chargeDisplay.textContent = '₹' + parseFloat(charge).toFixed(2);
            }
        });
    }
});
</script>
@endsection
