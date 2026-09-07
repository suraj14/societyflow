@extends('layouts.app')

@section('title', 'Edit Building')
@section('page-title', 'Edit Building - ' . $building->name)

@section('content')
<div class="p-6">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('buildings.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Buildings
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Edit Building</h2>
            </div>

            <form action="{{ route('buildings.update', $building) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <!-- Society -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Society *</label>
                        <select name="society_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Society</option>
                            @foreach($societies as $society)
                                <option value="{{ $society->id }}" {{ old('society_id', $building->society_id) == $society->id ? 'selected' : '' }}>
                                    {{ $society->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('society_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Building Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Building Name *</label>
                        <input type="text" name="name" value="{{ old('name', $building->name) }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., Tower A, Block 1">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Total Floors -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Floors *</label>
                        <input type="number" name="total_floors" value="{{ old('total_floors', $building->total_floors) }}" required min="1" max="100"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @error('total_floors')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Additional details about the building">{{ old('description', $building->description) }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('buildings.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Update Building
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
