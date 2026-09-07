@extends('layouts.app')

@section('title', 'Book Facilities')
@section('page-title', 'Book Facilities')

@section('content')
<div class="p-6">
    <div class="mb-6">
        <p class="text-gray-500">Book society facilities for your events and activities</p>
    </div>

    <!-- Facilities Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($facilities as $facility)
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
                <!-- Facility Image -->
                <div class="h-40 bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center">
                    <i class="fas fa-{{ $facility->icon ?? 'building' }} text-white text-4xl"></i>
                </div>

                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $facility->name }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ Str::limit($facility->description, 80) }}</p>

                    <div class="mt-4 space-y-2">
                        @if($facility->capacity)
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-users w-5 text-gray-400"></i>
                                <span>Capacity: {{ $facility->capacity }} people</span>
                            </div>
                        @endif

                        @if($facility->booking_charge)
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-rupee-sign w-5 text-gray-400"></i>
                                <span>₹{{ number_format($facility->booking_charge, 0) }} per booking</span>
                            </div>
                        @else
                            <div class="flex items-center text-sm text-green-600">
                                <i class="fas fa-check w-5"></i>
                                <span>Free to book</span>
                            </div>
                        @endif

                        @if($facility->requires_approval)
                            <div class="flex items-center text-sm text-orange-600">
                                <i class="fas fa-clock w-5"></i>
                                <span>Requires approval</span>
                            </div>
                        @endif
                    </div>

                    <a href="{{ route('villa-owner.facilities.book', $facility) }}" 
                       class="mt-4 block w-full text-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Book Now
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <i class="fas fa-swimming-pool text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">No facilities available for booking</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
