@extends('layouts.app')

@section('title', $villa->villa_name ?? $villa->flat_number)
@section('page-title', $villa->villa_name ?? $villa->flat_number)

@section('content')
<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('villas.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Villas
            </a>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('villas.edit', $villa) }}" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Villa Details -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-green-500 to-green-600 rounded-t-xl">
                    <div class="text-white">
                        <p class="text-green-100 text-sm">Villa</p>
                        <h2 class="text-2xl font-bold">{{ $villa->villa_name ?? $villa->flat_number }}</h2>
                        <p class="text-green-100 mt-1">{{ $villa->villaArea->name ?? 'Villa Area' }}</p>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Villa Number</p>
                        <p class="font-medium text-gray-800">{{ $villa->flat_number }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Society</p>
                        <p class="font-medium text-gray-800">{{ $villa->society->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 py-1 text-xs rounded-full 
                            {{ $villa->status === 'occupied' ? 'bg-green-100 text-green-700' : '' }}
                            {{ $villa->status === 'vacant' ? 'bg-yellow-100 text-yellow-700' : '' }}
                            {{ $villa->status === 'under_maintenance' ? 'bg-red-100 text-red-700' : '' }}">
                            {{ ucfirst(str_replace('_', ' ', $villa->status)) }}
                        </span>
                    </div>
                    @if($villa->ownerUser)
                    <div>
                        <p class="text-sm text-gray-500">Owner</p>
                        <p class="font-medium text-gray-800">{{ $villa->ownerUser->name }}</p>
                        <p class="text-xs text-gray-500">{{ $villa->ownerUser->email }}</p>
                    </div>
                    @endif
                    @if($villa->maintenance_amount)
                    <div>
                        <p class="text-sm text-gray-500">Monthly Maintenance</p>
                        <p class="font-medium text-gray-800">₹{{ number_format($villa->maintenance_amount, 0) }}</p>
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
                    @if($villa->plot_area)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Plot Area</span>
                        <span class="font-medium">{{ number_format($villa->plot_area) }} sq.ft</span>
                    </div>
                    @endif
                    @if($villa->built_up_area)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Built-up Area</span>
                        <span class="font-medium">{{ number_format($villa->built_up_area) }} sq.ft</span>
                    </div>
                    @endif
                    @if($villa->bedrooms)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Bedrooms</span>
                        <span class="font-medium">{{ $villa->bedrooms }}</span>
                    </div>
                    @endif
                    @if($villa->bathrooms)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Bathrooms</span>
                        <span class="font-medium">{{ $villa->bathrooms }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Garden</span>
                        <span class="font-medium">{{ $villa->has_garden ? 'Yes' : 'No' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Parking</span>
                        <span class="font-medium">{{ $villa->has_parking ? ($villa->parking_slots . ' slots') : 'No' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Recent Visitors -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Recent Visitors</h3>
                </div>
                <div class="p-4">
                    @forelse($villa->visitors->take(5) as $visitor)
                        <div class="flex items-center py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-gray-500"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-800">{{ $visitor->visitor_name }}</p>
                                <p class="text-xs text-gray-500">{{ $visitor->purpose }}</p>
                            </div>
                            <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($visitor->visit_date)->format('d M') }}</span>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm text-center py-4">No visitors yet</p>
                    @endforelse
                </div>
            </div>

            <!-- Recent Complaints -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Recent Requests</h3>
                </div>
                <div class="p-4">
                    @forelse($villa->complaints->take(5) as $complaint)
                        <div class="flex items-center py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                            <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-tools text-orange-500"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-800">{{ $complaint->title }}</p>
                                <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($complaint->created_at)->format('d M Y') }}</p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full {{ $complaint->status === 'open' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' }}">
                                {{ ucfirst($complaint->status) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm text-center py-4">No requests yet</p>
                    @endforelse
                </div>
            </div>

            <!-- Facility Bookings -->
            <div class="bg-white rounded-xl border border-gray-200">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">Facility Bookings</h3>
                </div>
                <div class="p-4">
                    @forelse($villa->facilityBookings->take(5) as $booking)
                        <div class="flex items-center py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar text-purple-500"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-800">{{ $booking->facility->name ?? 'Facility' }}</p>
                                <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($booking->booking_date)->format('d M Y') }}</p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full {{ $booking->status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ ucfirst($booking->status) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm text-center py-4">No bookings yet</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
