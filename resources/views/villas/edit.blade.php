@extends('layouts.app')

@section('title', 'Edit Villa')
@section('page-title', 'Edit Villa')

@section('content')
<div class="p-6">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Edit Villa - {{ $villa->villa_name ?? $villa->flat_number }}</h2>
            </div>

            <form action="{{ route('villas.update', $villa) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Society -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Society *</label>
                            <select name="society_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Society</option>
                                @foreach($societies as $society)
                                    <option value="{{ $society->id }}" {{ old('society_id', $villa->society_id) == $society->id ? 'selected' : '' }}>
                                        {{ $society->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('society_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Villa Area -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Villa Area *</label>
                            <select name="villa_area_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Villa Area</option>
                                @foreach($villaAreas as $area)
                                    <option value="{{ $area->id }}" {{ old('villa_area_id', $villa->villa_area_id) == $area->id ? 'selected' : '' }}>
                                        {{ $area->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('villa_area_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Villa Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Villa Number *</label>
                            <input type="text" name="flat_number" value="{{ old('flat_number', $villa->flat_number) }}" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('flat_number')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Villa Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Villa Name</label>
                            <input type="text" name="villa_name" value="{{ old('villa_name', $villa->villa_name) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('villa_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Owner - Hidden to prevent multiple assignments -->
                    <!-- Owner assignment managed through Owners section -->
                    <input type="hidden" name="owner_id" value="{{ old('owner_id', $villa->owner_id) }}">
                    
                    <!-- Note about owner assignment -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Owner Assignment</h3>
                                <p class="text-sm text-blue-700 mt-1">
                                    @if($villa->owner_id)
                                        Current owner: <strong>{{ $villa->ownerModel ? $villa->ownerModel->name : 'Unknown' }}</strong><br>
                                    @endif
                                    Owner assignments are managed through the Owners section to prevent conflicts.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6">
                        <!-- Plot Area -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Plot Area (sq.ft)</label>
                            <input type="number" name="plot_area" value="{{ old('plot_area', $villa->plot_area) }}" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('plot_area')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Built-up Area -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Built-up Area (sq.ft)</label>
                            <input type="number" name="built_up_area" value="{{ old('built_up_area', $villa->built_up_area) }}" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('built_up_area')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Maintenance Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Maintenance (₹)</label>
                            <input type="number" name="maintenance_amount" value="{{ old('maintenance_amount', $villa->maintenance_amount) }}" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('maintenance_amount')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Bedrooms -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bedrooms</label>
                            <input type="number" name="bedrooms" value="{{ old('bedrooms', $villa->bedrooms) }}" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('bedrooms')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Bathrooms -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bathrooms</label>
                            <input type="number" name="bathrooms" value="{{ old('bathrooms', $villa->bathrooms) }}" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('bathrooms')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Amenities -->
                    <div class="grid md:grid-cols-3 gap-6">
                        <div class="flex items-center">
                            <input type="checkbox" name="has_garden" value="1" id="has_garden" {{ old('has_garden', $villa->has_garden) ? 'checked' : '' }}
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="has_garden" class="ml-2 text-sm text-gray-700">Has Garden</label>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="has_parking" value="1" id="has_parking" {{ old('has_parking', $villa->has_parking) ? 'checked' : '' }}
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="has_parking" class="ml-2 text-sm text-gray-700">Has Parking</label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Parking Slots</label>
                            <input type="number" name="parking_slots" value="{{ old('parking_slots', $villa->parking_slots ?? 1) }}" min="0"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select name="status" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="vacant" {{ old('status', $villa->status) === 'vacant' ? 'selected' : '' }}>Vacant</option>
                            <option value="occupied" {{ old('status', $villa->status) === 'occupied' ? 'selected' : '' }}>Occupied</option>
                            <option value="on_rent" {{ old('status', $villa->status) === 'on_rent' ? 'selected' : '' }}>On Rent</option>
                            <option value="under_maintenance" {{ old('status', $villa->status) === 'under_maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('description', $villa->description) }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('villas.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Update Villa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
