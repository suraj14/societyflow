@extends('layouts.app')

@section('title', 'Create Building')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center mb-6">
            <a href="{{ route('buildings.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Create Building</h1>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <form action="{{ route('buildings.store') }}" method="POST">
                @csrf

                <div class="mb-6">
                    @if(auth()->user()->hasRole('Super Admin'))
                        <label for="society_id" class="block text-sm font-medium text-gray-700 mb-2">Society</label>
                        <select name="society_id" id="society_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Society</option>
                            @foreach($societies as $society)
                                <option value="{{ $society->id }}" {{ old('society_id') == $society->id ? 'selected' : '' }}>
                                    {{ $society->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('society_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @else
                        <!-- Hidden field for Society Admin users -->
                        <input type="hidden" name="society_id" value="{{ auth()->user()->society_id }}">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Society</label>
                        <div class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                            {{ auth()->user()->society->name }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Creating building for your society</p>
                    @endif
                </div>

                <div class="mb-6">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Building Name</label>
                    <input type="text" name="name" id="name" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                           value="{{ old('name') }}" placeholder="Enter building name">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="total_floors" class="block text-sm font-medium text-gray-700 mb-2">Number of Floors</label>
                    <input type="number" name="total_floors" id="total_floors" required min="1" max="100"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                           value="{{ old('total_floors', 5) }}" placeholder="5">
                    @error('total_floors')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="flats_per_floor" class="block text-sm font-medium text-gray-700 mb-2">Flats per Floor</label>
                    <input type="number" name="flats_per_floor" id="flats_per_floor" required min="1" max="20"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                           value="{{ old('flats_per_floor', 4) }}" placeholder="4">
                    @error('flats_per_floor')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description (Optional)</label>
                    <textarea name="description" id="description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Enter building description">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-blue-900 mb-1">Auto-generation Notice</h4>
                            <p class="text-sm text-blue-700">
                                Flats will be automatically generated based on the number of floors and flats per floor. 
                                Total flats: <span id="total-flats" class="font-semibold">20</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="{{ route('buildings.index') }}" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Create Building
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const floorsInput = document.getElementById('total_floors');
    const flatsPerFloorInput = document.getElementById('flats_per_floor');
    const totalFlatsSpan = document.getElementById('total-flats');

    function updateTotalFlats() {
        const floors = parseInt(floorsInput.value) || 0;
        const flatsPerFloor = parseInt(flatsPerFloorInput.value) || 0;
        totalFlatsSpan.textContent = floors * flatsPerFloor;
    }

    floorsInput.addEventListener('input', updateTotalFlats);
    flatsPerFloorInput.addEventListener('input', updateTotalFlats);
    
    updateTotalFlats(); // Initial calculation
});
</script>
@endsection