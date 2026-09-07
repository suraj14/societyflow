@extends('layouts.app')

@section('title', 'Booking Details')
@section('page-title', 'Booking Details')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center">
            <a href="{{ route('facility-bookings.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Booking #{{ $facilityBooking->booking_number }}</h1>
                <p class="text-gray-600 mt-1">Booking Details</p>
            </div>
        </div>
        <div class="flex space-x-3">
            @can('manage_facilities')
                @if($facilityBooking->status === 'pending')
                    <a href="{{ route('facility-bookings.edit-status', $facilityBooking) }}" 
                       class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                        <i class="fas fa-check mr-2"></i>Approve
                    </a>
                    <button onclick="openRejectModal({{ $facilityBooking->id }}, '{{ $facilityBooking->facility->name }}', '{{ $facilityBooking->user->name }}')" 
                            class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                        <i class="fas fa-times mr-2"></i>Reject
                    </button>
                @endif
                <a href="{{ route('facility-bookings.edit-status', $facilityBooking) }}" 
                   class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-cog mr-2"></i>Change Status
                </a>
            @endcan
            
            @if($facilityBooking->status === 'pending' && (auth()->user()->can('manage_facilities') || $facilityBooking->user_id === auth()->id()))
                <a href="{{ route('facility-bookings.edit', $facilityBooking) }}" 
                   class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-edit mr-2"></i>Edit Details
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Booking Information</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Booking Number</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $facilityBooking->booking_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-gray-100 text-gray-800',
                                        'completed' => 'bg-blue-100 text-blue-800',
                                    ];
                                @endphp
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $statusColors[$facilityBooking->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($facilityBooking->status) }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Facility</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->facility->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Booking Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($facilityBooking->booking_date)->format('l, M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Time Slot</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ \Carbon\Carbon::parse($facilityBooking->start_time)->format('h:i A') }} - 
                                {{ \Carbon\Carbon::parse($facilityBooking->end_time)->format('h:i A') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Expected Guests</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->expected_guests }}</dd>
                        </div>
                    </dl>

                    @if($facilityBooking->purpose)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <dt class="text-sm font-medium text-gray-500">Purpose</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->purpose }}</dd>
                        </div>
                    @endif

                    @if($facilityBooking->admin_notes)
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <dt class="text-sm font-medium text-gray-500">Admin Notes</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->admin_notes }}</dd>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Resident Details -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Resident Information</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->user->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->user->email ?? 'N/A' }}</dd>
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
                            <dt class="text-sm font-medium text-gray-500">Society</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->society->name ?? 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Payment Details -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Payment Details</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="space-y-4">
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Booking Amount</dt>
                            <dd class="text-sm text-gray-900">₹{{ number_format($facilityBooking->booking_amount, 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Security Deposit</dt>
                            <dd class="text-sm text-gray-900">₹{{ number_format($facilityBooking->security_deposit, 2) }}</dd>
                        </div>
                        <div class="flex justify-between pt-4 border-t border-gray-200">
                            <dt class="text-sm font-semibold text-gray-900">Total</dt>
                            <dd class="text-sm font-semibold text-gray-900">₹{{ number_format($facilityBooking->booking_amount + $facilityBooking->security_deposit, 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Payment Status</dt>
                            <dd>
                                @php
                                    $paymentColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'paid' => 'bg-green-100 text-green-800',
                                        'refunded' => 'bg-blue-100 text-blue-800',
                                    ];
                                @endphp
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $paymentColors[$facilityBooking->payment_status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($facilityBooking->payment_status) }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Timeline -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Timeline</h2>
                </div>
                <div class="px-6 py-4">
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $facilityBooking->created_at->format('M d, Y h:i A') }}</dd>
                        </div>
                        @if($facilityBooking->approved_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Approved At</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($facilityBooking->approved_at)->format('M d, Y h:i A') }}</dd>
                            </div>
                        @endif
                        @if($facilityBooking->cancelled_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Cancelled At</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($facilityBooking->cancelled_at)->format('M d, Y h:i A') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Booking Modal -->
<div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Reject Booking</h3>
                <form id="rejectForm" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <input type="hidden" name="status" value="rejected">
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 mb-2">You are about to reject the following booking:</p>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="font-medium" id="reject_facility_name"></p>
                            <p class="text-sm text-gray-600" id="reject_user_name"></p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="admin_notes" class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection *</label>
                        <textarea name="admin_notes" id="admin_notes" rows="3" 
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" 
                                  placeholder="Please provide a reason for rejecting this booking..."
                                  required></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRejectModal()" 
                                class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            <i class="fas fa-times mr-2"></i>Reject Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openRejectModal(bookingId, facilityName, userName) {
    document.getElementById('reject_facility_name').textContent = facilityName;
    document.getElementById('reject_user_name').textContent = 'Requested by: ' + userName;
    document.getElementById('rejectForm').action = '/facility-bookings/' + bookingId + '/update-status';
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('admin_notes').value = '';
}

// Close modal when clicking outside
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>
@endsection
