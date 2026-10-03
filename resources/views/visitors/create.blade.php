@extends('layouts.app')

@section('title', $isResident ? 'Invite Visitor' : 'Add Visitor')
@section('page-title', $isResident ? 'Invite Visitor' : 'Add Visitor')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('visitors.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $isResident ? 'Invite Visitor' : 'Add New Visitor' }}</h1>
            <p class="text-gray-600 mt-1">
                {{ $isResident ? 'Your visitor is pre-approved - security can verify them at the gate.' : 'Register a new visitor entry' }}
            </p>
        </div>
    </div>

    <div class="max-w-3xl bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Visitor Information</h2>
        </div>

        <form action="{{ route('visitors.store') }}" method="POST" enctype="multipart/form-data" class="px-6 py-6">
            @csrf
            @include('partials.idempotency')

            <div class="space-y-6">
                {{-- Destination. The server already knows a single-property
                     resident's unit, so nothing is submitted for it. --}}
                @if($fixedProperty)
                    <div class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700 flex items-center gap-2">
                        <i class="fas fa-{{ $fixedProperty->property_type === 'villa' ? 'house-user' : 'building' }} text-gray-400"></i>
                        <span>Visiting: <span class="font-semibold text-gray-900">{{ $fixedPropertyLabel }}</span></span>
                    </div>
                @endif
                <!-- Visitor Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="visitor_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Visitor Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="visitor_name" id="visitor_name" required value="{{ old('visitor_name') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('visitor_name') border-red-500 @enderror"
                               placeholder="Enter visitor name">
                        @error('visitor_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="visitor_phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Mobile Number <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" name="visitor_phone" id="visitor_phone" required value="{{ old('visitor_phone') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('visitor_phone') border-red-500 @enderror"
                               placeholder="Enter mobile number">
                        @error('visitor_phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="visitor_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Visitor Type <span class="text-red-500">*</span>
                        </label>
                        <select name="visitor_type" id="visitor_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('visitor_type') border-red-500 @enderror">
                            <option value="">Select Type</option>
                            <option value="guest" {{ old('visitor_type') == 'guest' ? 'selected' : '' }}>Guest</option>
                            <option value="delivery" {{ old('visitor_type') == 'delivery' ? 'selected' : '' }}>Delivery</option>
                            <option value="cab" {{ old('visitor_type') == 'cab' ? 'selected' : '' }}>Cab</option>
                            <option value="service" {{ old('visitor_type') == 'service' ? 'selected' : '' }}>Service Staff</option>
                            <option value="other" {{ old('visitor_type') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('visitor_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @unless($fixedProperty)
                        <div>
                            <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Visiting Property <span class="text-red-500">*</span>
                            </label>
                            <select name="flat_id" id="flat_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('flat_id') border-red-500 @enderror">
                                <option value="">{{ count($properties) ? 'Select Property' : 'No properties set up yet' }}</option>
                                @foreach($properties as $id => $label)
                                    <option value="{{ $id }}" {{ (string) old('flat_id') === (string) $id ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if(!count($properties))
                                <p class="mt-1 text-xs text-gray-500">Add villas or flats to this society before registering visitors.</p>
                            @endif
                            @error('flat_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endunless
                </div>

                <!-- Visit Details -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Visit Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="expected_entry_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Expected Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="expected_entry_time" id="expected_entry_time" required
                                   value="{{ old('expected_entry_time', now()->format('Y-m-d')) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('expected_entry_time') border-red-500 @enderror">
                            @error('expected_entry_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="in_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Expected Time <span class="text-red-500">*</span>
                            </label>
                            <input type="time" name="in_time" id="in_time" required
                                   value="{{ old('in_time', now()->format('H:i')) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('in_time') border-red-500 @enderror">
                            @error('in_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="vehicle_number" class="block text-sm font-medium text-gray-700 mb-2">
                                Vehicle Number
                            </label>
                            <input type="text" name="vehicle_number" id="vehicle_number"
                                   value="{{ old('vehicle_number') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('vehicle_number') border-red-500 @enderror"
                                   placeholder="e.g., MH12AB1234">
                            @error('vehicle_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Purpose and Photo Upload -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                            Purpose of Visit
                        </label>
                        <textarea name="purpose" id="purpose" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('purpose') border-red-500 @enderror"
                                  placeholder="Enter purpose of visit...">{{ old('purpose') }}</textarea>
                        @error('purpose')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">
                            Upload Photo (Optional)
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="photo" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>Upload Photo</span>
                                        <input id="photo" name="photo" type="file" accept="image/*" class="sr-only" onchange="previewPhoto(this)">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                        </div>
                        <div id="photo-preview" class="mt-2 hidden">
                            <img id="preview-image" class="h-20 w-20 object-cover rounded-lg" src="" alt="Preview">
                        </div>
                        @error('photo')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('visitors.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    {{ $isResident ? 'Invite Visitor' : 'Register Visitor' }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Photo preview function
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
            document.getElementById('photo-preview').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endsection
