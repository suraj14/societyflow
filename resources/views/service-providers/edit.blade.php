@extends('layouts.app')

@section('title', 'Edit Service Provider')
@section('page-title', 'Edit Service Provider')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('service-providers.show', $serviceProvider) }}" 
           class="text-gray-600 hover:text-gray-900 mr-4 transition-colors">
            <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Service Provider</h1>
            <p class="text-gray-600 mt-1">{{ $serviceProvider->name }}</p>
        </div>
    </div>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <form action="{{ route('service-providers.update', $serviceProvider) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Service Type -->
                <div>
                    <label for="service_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Service Type <span class="text-red-500">*</span>
                    </label>
                    <select name="service_id" id="service_id" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('service_id') border-red-500 @enderror"
                            required>
                        <option value="">Select a service type</option>
                        @foreach($services->groupBy('category') as $category => $categoryServices)
                            <optgroup label="{{ $categoryServices->first()->category_label }}">
                                @foreach($categoryServices as $service)
                                    <option value="{{ $service->id }}" {{ old('service_id', $serviceProvider->service_id) == $service->id ? 'selected' : '' }}>
                                        {{ $service->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('service_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Provider Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Provider Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name"
                           value="{{ old('name', $serviceProvider->name) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                           placeholder="Enter provider name" required>
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Contact Number -->
                <div>
                    <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">
                        Contact Number <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="contact_number" id="contact_number"
                           value="{{ old('contact_number', $serviceProvider->contact_number) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('contact_number') border-red-500 @enderror"
                           placeholder="Enter contact number" required>
                    @error('contact_number')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Website Link -->
                <div>
                    <label for="website" class="block text-sm font-medium text-gray-700 mb-2">
                        Website Link
                    </label>
                    <input type="url" name="website" id="website"
                           value="{{ old('website', $serviceProvider->website) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('website') border-red-500 @enderror"
                           placeholder="https://example.com">
                    @error('website')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Photo Upload -->
                <div>
                    <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">
                        Provider Photo
                    </label>
                    @if($serviceProvider->photo)
                    <div class="mb-3">
                        <p class="text-sm text-gray-600 mb-2">Current Photo:</p>
                        <img src="{{ asset('storage/' . $serviceProvider->photo) }}" alt="{{ $serviceProvider->name }}" class="w-32 h-32 object-cover rounded-lg border border-gray-300">
                    </div>
                    @endif
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-500 transition-colors"
                         onclick="document.getElementById('photo').click()">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-600">Click to upload or drag and drop</p>
                        <p class="text-sm text-gray-500">PNG, JPG, GIF up to 2MB</p>
                    </div>
                    <input type="file" name="photo" id="photo" accept="image/*" class="hidden"
                           onchange="updatePhotoPreview(this)">
                    <div id="photoPreview" class="mt-3"></div>
                    @error('photo')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Availability -->
                <div>
                    <label for="availability" class="block text-sm font-medium text-gray-700 mb-2">
                        Availability <span class="text-red-500">*</span>
                    </label>
                    <select name="availability" id="availability" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('availability') border-red-500 @enderror"
                            required>
                        <option value="available" {{ old('availability', $serviceProvider->availability) == 'available' ? 'selected' : '' }}>Available</option>
                        <option value="not_available" {{ old('availability', $serviceProvider->availability) == 'not_available' ? 'selected' : '' }}>Not Available</option>
                    </select>
                    @error('availability')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Daily Help -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_daily_help" value="1" 
                               {{ old('is_daily_help', $serviceProvider->is_daily_help) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Daily Help Service</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">Check if this is a daily recurring service</p>
                </div>

                <!-- Price -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                            Price
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">₹</span>
                            </div>
                            <input type="number" name="price" id="price" step="0.01" min="0"
                                   value="{{ old('price', $serviceProvider->price) }}"
                                   class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('price') border-red-500 @enderror"
                                   placeholder="0.00">
                        </div>
                        @error('price')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="price_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Price Type <span class="text-red-500">*</span>
                        </label>
                        <select name="price_type" id="price_type" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('price_type') border-red-500 @enderror"
                                required>
                            <option value="per_visit" {{ old('price_type', $serviceProvider->price_type) == 'per_visit' ? 'selected' : '' }}>Per Visit</option>
                            <option value="per_day" {{ old('price_type', $serviceProvider->price_type) == 'per_day' ? 'selected' : '' }}>Per Day</option>
                            <option value="per_month" {{ old('price_type', $serviceProvider->price_type) == 'per_month' ? 'selected' : '' }}>Per Month</option>
                        </select>
                        @error('price_type')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="status" id="status" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror"
                            required>
                        <option value="active" {{ old('status', $serviceProvider->status) == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $serviceProvider->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea name="notes" id="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('notes') border-red-500 @enderror"
                              placeholder="Add any additional notes (optional)">{{ old('notes', $serviceProvider->notes) }}</textarea>
                    @error('notes')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="{{ route('service-providers.show', $serviceProvider) }}" 
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                        Update Provider
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updatePhotoPreview(input) {
    const preview = document.getElementById('photoPreview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const container = document.createElement('div');
            container.className = 'flex items-center space-x-3 mt-3';
            container.innerHTML = `
                <div>
                    <img src="${e.target.result}" class="w-32 h-32 object-cover rounded-lg border border-gray-300">
                </div>
                <div class="text-sm">
                    <p class="font-medium text-gray-900">${input.files[0].name}</p>
                    <p class="text-gray-500">${(input.files[0].size / 1024).toFixed(2)} KB</p>
                    <button type="button" onclick="clearPhoto()" class="text-red-600 hover:text-red-800 mt-2">
                        <i class="fas fa-trash mr-1"></i>Remove
                    </button>
                </div>
            `;
            preview.appendChild(container);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearPhoto() {
    document.getElementById('photo').value = '';
    document.getElementById('photoPreview').innerHTML = '';
}
</script>
@endsection