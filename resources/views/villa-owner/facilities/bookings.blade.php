@extends('layouts.app')

@section('title', 'My Bookings')
@section('page-title', 'My Bookings')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <p class="text-gray-500">Your facility bookings</p>
        <a href="{{ route('villa-owner.facilities') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
            <i class="fas fa-plus mr-2"></i> New Booking
        </a>
    </div>

    <!-- Bookings List -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Facility</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purpose</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($bookings as $booking)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-{{ $booking->facility->icon ?? 'building' }} text-green-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-800">{{ $booking->facility->name ?? 'Facility' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-800">{{ \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $booking->start_time }} - {{ $booking->end_time }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $booking->purpose ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $booking->status === 'approved' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $booking->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                    {{ $booking->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $booking->status === 'cancelled' ? 'bg-gray-100 text-gray-700' : '' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($booking->status !== 'cancelled' && \Carbon\Carbon::parse($booking->booking_date)->isFuture())
                                    <form action="{{ route('villa-owner.bookings.cancel', $booking) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Are you sure you want to cancel this booking?')">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-calendar text-4xl text-gray-300 mb-3"></i>
                                <p>No bookings yet</p>
                                <a href="{{ route('villa-owner.facilities') }}" class="text-green-600 hover:underline mt-2 inline-block">Book a facility</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bookings->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
