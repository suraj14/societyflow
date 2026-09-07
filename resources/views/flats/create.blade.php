@extends('layouts.app')

@section('title', 'Add Flat')
@section('page-title', 'Add New Apartment')

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
                <h2 class="text-lg font-semibold text-gray-800">Create New Apartment</h2>
            </div>

            <form action="{{ route('flats.store') }}" method="POST" class="p-6">
                @csrf

                <div class="space-y-6">
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Society -->
                        @if(auth()->user()->hasRole('Super Admin'))
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Society *</label>
                                <select name="society_id" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Society</option>
                                    @foreach($societies as $society)
                                        <option value="{{ $society->id }}" {{ old('society_id') == $society->id ? 'selected' : '' }}>
                                            {{ $society->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('society_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <!-- Hidden field for Society Admin users -->
                            <input type="hidden" name="society_id" value="{{ auth()->user()->society_id }}">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Society</label>
                                <div class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                                    {{ auth()->user()->society->name }}
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Creating apartment for your society</p>
                            </div>
                        @endif

                        <!-- Building -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Building *</label>
                            <select name="building_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Building</option>
                                @foreach($buildings as $building)
                                    <option value="{{ $building->id }}" {{ old('building_id') == $building->id ? 'selected' : '' }}>
                                        {{ $building->name }}@if(auth()->user()->hasRole('Super Admin')) ({{ $building->society->name ?? '' }})@endif
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
                            <input type="text" name="flat_number" value="{{ old('flat_number') }}" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="e.g., A-101">
                            @error('flat_number')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Floor -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Floor *</label>
                            <input type="number" name="floor" value="{{ old('floor', 0) }}" required min="0" max="100"
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
                                    <option value="{{ $type }}" {{ old('type') === $type ? 'selected' : '' }}>{{ $type }}</option>
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
                            <input type="number" name="carpet_area" value="{{ old('carpet_area') }}" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('carpet_area')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Built-up Area -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Built-up Area (sq.ft)</label>
                            <input type="number" name="built_up_area" value="{{ old('built_up_area') }}" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @error('built_up_area')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Maintenance Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Maintenance (₹)</label>
                            <input type="number" name="maintenance_amount" value="{{ old('maintenance_amount') }}" step="0.01"
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
                            <option value="vacant" {{ old('status', 'vacant') === 'vacant' ? 'selected' : '' }}>Vacant</option>
                            <option value="occupied" {{ old('status') === 'occupied' ? 'selected' : '' }}>Occupied</option>
                            <option value="on_rent" {{ old('status') === 'on_rent' ? 'selected' : '' }}>On Rent</option>
                            <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Owner - Hidden during creation since owners already have property info -->
                    <!-- Owner assignment can be done later through property management -->
                    <input type="hidden" name="owner_id" value="">
                    
                    <!-- Note about owner assignment -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Owner Assignment</h3>
                                <p class="text-sm text-blue-700 mt-1">
                                    Owners are assigned during owner creation with their property information. 
                                    You can manage owner assignments through the Owners section.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Additional details about the flat">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('flats.index') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Create Flat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
