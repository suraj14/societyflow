@extends('layouts.app')

@section('title', 'Edit Facility')
@section('page-title', 'Edit Facility')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('facilities.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Facility</h1>
            <p class="text-gray-600 mt-1">Update facility information</p>
        </div>
    </div>

    <div class="max-w-4xl bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Facility Information</h2>
        </div>
        
        <form action="{{ route('facilities.update', $facility) }}" method="POST" enctype="multipart/form-data" class="px-6 py-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Facility Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="name" required value="{{ old('name', $facility->name) }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <select name="type" id="type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="clubhouse" {{ old('type', $facility->type) == 'clubhouse' ? 'selected' : '' }}>Clubhouse</option>
                            <option value="gym" {{ old('type', $facility->type) == 'gym' ? 'selected' : '' }}>Gym</option>
                            <option value="swimming_pool" {{ old('type', $facility->type) == 'swimming_pool' ? 'selected' : '' }}>Swimming Pool</option>
                            <option value="hall" {{ old('type', $facility->type) == 'hall' ? 'selected' : '' }}>Hall / Party Hall</option>
                            <option value="playground" {{ old('type', $facility->type) == 'playground' ? 'selected' : '' }}>Playground / Sports</option>
                            <option value="parking" {{ old('type', $facility->type) == 'parking' ? 'selected' : '' }}>Parking</option>
                            <option value="other" {{ old('type', $facility->type) == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>

                    <div>
                        <label for="capacity" class="block text-sm font-medium text-gray-700 mb-2">
                            Capacity (persons)
                        </label>
                        <input type="number" name="capacity" id="capacity" value="{{ old('capacity', $facility->capacity) }}" min="1"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="description" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('description', $facility->description) }}</textarea>
                </div>

                <!-- Booking Settings -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Booking Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="booking_charge" class="block text-sm font-medium text-gray-700 mb-2">Booking Charge (₹)</label>
                            <input type="number" name="booking_charge" id="booking_charge" value="{{ old('booking_charge', $facility->booking_charge) }}" min="0" step="0.01"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="advance_booking_days" class="block text-sm font-medium text-gray-700 mb-2">Advance Booking (days)</label>
                            <input type="number" name="advance_booking_days" id="advance_booking_days" value="{{ old('advance_booking_days', $facility->advance_booking_days) }}" min="1" max="365"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="max_booking_hours" class="block text-sm font-medium text-gray-700 mb-2">Max Booking Hours</label>
                            <input type="number" name="max_booking_hours" id="max_booking_hours" value="{{ old('max_booking_hours', $facility->max_booking_hours) }}" min="1" max="24"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Timings -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Operating Hours</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="opening_time" class="block text-sm font-medium text-gray-700 mb-2">Opening Time</label>
                            <input type="time" name="opening_time" id="opening_time" 
                                   value="{{ old('opening_time', $facility->opening_time ? \Carbon\Carbon::parse($facility->opening_time)->format('H:i') : '') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="closing_time" class="block text-sm font-medium text-gray-700 mb-2">Closing Time</label>
                            <input type="time" name="closing_time" id="closing_time" 
                                   value="{{ old('closing_time', $facility->closing_time ? \Carbon\Carbon::parse($facility->closing_time)->format('H:i') : '') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Available Days</label>
                        <div class="flex flex-wrap gap-4">
                            @php 
                                $availableDays = old('available_days', $facility->available_days ?? []);
                                if (is_string($availableDays)) {
                                    $availableDays = json_decode($availableDays, true) ?? [];
                                }
                            @endphp
                            @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="available_days[]" value="{{ $day }}" 
                                           {{ in_array($day, $availableDays) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">{{ ucfirst($day) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Status & Approval -->
                <div class="border-t border-gray-200 pt-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                            <select name="status" id="status" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="active" {{ old('status', $facility->status) == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $facility->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="maintenance" {{ old('status', $facility->status) == 'maintenance' ? 'selected' : '' }}>Under Maintenance</option>
                            </select>
                        </div>

                        <div class="flex items-center pt-8">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="requires_approval" value="1" 
                                       {{ old('requires_approval', $facility->requires_approval) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Requires Admin Approval for Bookings</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="border-t border-gray-200 pt-6">
                    <label for="image" class="block text-sm font-medium text-gray-700 mb-2">Facility Image</label>
                    @if($facility->image)
                        <div class="mb-4">
                            <img src="{{ asset('storage/' . $facility->image) }}" alt="{{ $facility->name }}" class="w-32 h-32 object-cover rounded-lg">
                        </div>
                    @endif
                    <input type="file" name="image" id="image" accept="image/*"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Leave empty to keep current image</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('facilities.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    Update Facility
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
