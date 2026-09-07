@extends('layouts.app')

@section('title', 'Edit Flat')
@section('page-title', 'Edit Flat - ' . $flat->flat_number)

@section('content')
<div class="p-6">
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <a href="{{ route('flats.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Apartments
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Edit Apartment</h2>
            </div>

            <form action="{{ route('flats.update', $flat) }}" method="POST" class="p-6">
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
                                    <option value="{{ $society->id }}" {{ old('society_id', $flat->society_id) == $society->id ? 'selected' : '' }}>
                                        {{ $society->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('society_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Building -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Building *</label>
                            <select name="building_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Building</option>
                                @foreach($buildings as $building)
                                    <option value="{{ $building->id }}" {{ old('building_id', $flat->building_id) == $building->id ? 'selected' : '' }}>
                                        {{ $building->name }} ({{ $building->society->name ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('building_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6">
                        <!-- Flat Number -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Flat Number *</label>
                            <input type="text" name="flat_number" value="{{ old('flat_number', $flat->flat_number) }}" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., A-101">
                            @error('flat_number')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Floor -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Floor *</label>
                            <input type="number" name="floor" value="{{ old('floor', $flat->floor) }}" required min="0" max="100"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('floor')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                            <select name="type" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Type</option>
                                @foreach(['1BHK', '2BHK', '3BHK', '4BHK', '5BHK', 'Studio', 'Penthouse', 'Shop', 'Office'] as $type)
                                    <option value="{{ $type }}" {{ old('type', $flat->type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('type')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6">
                        <!-- Carpet Area -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Carpet Area (sq.ft)</label>
                            <input type="number" name="carpet_area" value="{{ old('carpet_area', $flat->carpet_area) }}" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('carpet_area')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Built-up Area -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Built-up Area (sq.ft)</label>
                            <input type="number" name="built_up_area" value="{{ old('built_up_area', $flat->built_up_area) }}" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('built_up_area')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Maintenance Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Maintenance (₹)</label>
                            <input type="number" name="maintenance_amount" value="{{ old('maintenance_amount', $flat->maintenance_amount) }}" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('maintenance_amount')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                        <select name="status" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="vacant" {{ old('status', $flat->status) === 'vacant' ? 'selected' : '' }}>Vacant</option>
                            <option value="occupied" {{ old('status', $flat->status) === 'occupied' ? 'selected' : '' }}>Occupied</option>
                            <option value="on_rent" {{ old('status', $flat->status) === 'on_rent' ? 'selected' : '' }}>On Rent</option>
                            <option value="maintenance" {{ old('status', $flat->status) === 'maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Owner - Hidden to prevent multiple assignments -->
                    <!-- Owner assignment managed through Owners section -->
                    <input type="hidden" name="owner_id" value="{{ old('owner_id', $flat->owner_id) }}">
                    
                    <!-- Note about owner assignment -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Owner Assignment</h3>
                                <p class="text-sm text-blue-700 mt-1">
                                    @if($flat->owner_id)
                                        Current owner: <strong>{{ $flat->ownerModel ? $flat->ownerModel->name : 'Unknown' }}</strong><br>
                                    @endif
                                    Owner assignments are managed through the Owners section to prevent conflicts.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Additional details about the flat">{{ old('description', $flat->description) }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('flats.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Update Flat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
