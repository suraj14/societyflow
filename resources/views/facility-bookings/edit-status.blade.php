@extends('layouts.app')

@section('title', 'Update Booking Status - Admin Only')
@section('page-title', 'Update Booking Status - Admin Only')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('facility-bookings.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Update Booking Status</h1>
            <p class="text-gray-600 mt-1">Booking #{{ $facilityBooking->booking_number }} - Admin Only</p>
        </div>
    </div>

    <div class="max-w-3xl">
        <!-- Booking Details Card -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Booking Details</h2>
            </div>
            <div class="px-6 py-4">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Facility</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->facility->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Booked By</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->user->name ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Property</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            @if($facilityBooking->flat->property_type === 'apartment')
                                {{ $facilityBooking->flat->building->name ?? '' }} - {{ $facilityBooking->flat->flat_number ?? '' }}
                            @else
                                {{ $facilityBooking->flat->villaArea->name ?? '' }} - {{ $facilityBooking->flat->villa_name ?? $facilityBooking->flat->flat_number ?? '' }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Booking Date</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($facilityBooking->booking_date)->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Time Slot</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ \Carbon\Carbon::parse($facilityBooking->start_time)->format('h:i A') }} - 
                            {{ \Carbon\Carbon::parse($facilityBooking->end_time)->format('h:i A') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Amount</dt>
                        <dd class="mt-1 text-sm text-gray-900">₹{{ number_format($facilityBooking->booking_amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Purpose</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->purpose ?? 'N/A' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Expected Guests</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->expected_guests }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Update Status Form -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Update Status (Admin Only)</h2>
                <p class="text-sm text-gray-600 mt-1">Only Admin users can change booking status</p>
            </div>
            
            <form action="{{ route('facility-bookings.update-status', $facilityBooking) }}" method="POST" class="px-6 py-6">
                @csrf
                @method('PUT')
                
                <div class="space-y-6">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select name="status" id="status" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="pending" {{ old('status', $facilityBooking->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ old('status', $facilityBooking->status) == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ old('status', $facilityBooking->status) == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="cancelled" {{ old('status', $facilityBooking->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            <option value="completed" {{ old('status', $facilityBooking->status) == 'completed' ? 'selected' : '' }}>Completed</option>
                        </select>
                    </div>

                    <div>
                        <label for="admin_notes" class="block text-sm font-medium text-gray-700 mb-2">
                            Admin Notes
                        </label>
                        <textarea name="admin_notes" id="admin_notes" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Add notes or reason for rejection...">{{ old('admin_notes', $facilityBooking->admin_notes) }}</textarea>
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
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection