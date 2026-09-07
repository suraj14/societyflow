@extends('layouts.app')

@section('title', 'Book ' . $facility->name)
@section('page-title', 'Book ' . $facility->name)

@section('content')
<div class="p-6">
    <div class="max-w-2xl mx-auto">
        <!-- Facility Info -->
        <div class="bg-white rounded-xl border border-gray-200 mb-6">
            <div class="p-6">
                <div class="flex items-start">
                    <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-{{ $facility->icon ?? 'building' }} text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-xl font-bold text-gray-800">{{ $facility->name }}</h2>
                        <p class="text-gray-500 mt-1">{{ $facility->description }}</p>
                        <div class="mt-2 flex items-center space-x-4 text-sm">
                            @if($facility->capacity)
                                <span class="text-gray-600"><i class="fas fa-users mr-1"></i> {{ $facility->capacity }} people</span>
                            @endif
                            @if($facility->booking_charge)
                                <span class="text-green-600 font-medium">₹{{ number_format($facility->booking_charge, 0) }}</span>
                            @else
                                <span class="text-green-600">Free</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Form -->
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Booking Details</h3>
            </div>

            <form action="{{ route('villa-owner.facilities.store-booking', $facility) }}" method="POST" class="p-6">
                @csrf

                <div class="space-y-6">
                    <!-- Booking Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="booking_date" value="{{ old('booking_date') }}" required
                               min="{{ date('Y-m-d') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        @error('booking_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Time Slots -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                            <input type="time" name="start_time" value="{{ old('start_time') }}" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            @error('start_time')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                            <input type="time" name="end_time" value="{{ old('end_time') }}" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            @error('end_time')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Purpose -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purpose (Optional)</label>
                        <input type="text" name="purpose" value="{{ old('purpose') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="e.g., Birthday Party, Family Gathering">
                        @error('purpose')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Number of Guests -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Number of Guests</label>
                        <input type="number" name="guests_count" value="{{ old('guests_count', 1) }}" min="1"
                               @if($facility->capacity) max="{{ $facility->capacity }}" @endif
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        @if($facility->capacity)
                            <p class="text-xs text-gray-500 mt-1">Maximum capacity: {{ $facility->capacity }} people</p>
                        @endif
                        @error('guests_count')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    @if($facility->requires_approval)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-yellow-500 mt-0.5"></i>
                                <p class="ml-2 text-sm text-yellow-700">
                                    This facility requires approval. Your booking will be confirmed once approved by the admin.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('villa-owner.facilities') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Book Facility
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
