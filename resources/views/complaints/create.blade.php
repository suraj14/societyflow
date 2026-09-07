@extends('layouts.app')

@section('title', 'Create Complaint')
@section('page-title', 'Create Complaint')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('complaints.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Create Complaint</h1>
            <p class="text-gray-600 mt-1">Register a new complaint</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md">
                <form action="{{ route('complaints.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                    @csrf

                    <!-- Requested By (Auto-filled) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Requested By
                        </label>
                        <div class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                            {{ auth()->user()->name }}
                        </div>
                    </div>

                    <!-- Category Selection -->
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Category <span class="text-red-500">*</span>
                        </label>
                        <select name="category_id" id="category_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('category_id') border-red-500 @enderror">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Property Selection (for owners) -->
                    @if(auth()->user()->hasRole('Villa Owner') || auth()->user()->hasRole('Apartment Owner'))
                        @if($properties->count() > 0)
                            <div>
                                <label for="flat_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Property <span class="text-red-500">*</span>
                                </label>
                                <select name="flat_id" id="flat_id" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('flat_id') border-red-500 @enderror">
                                    <option value="">Select Property</option>
                                    @foreach($properties as $property)
                                        <option value="{{ $property['id'] }}" {{ old('flat_id') == $property['id'] ? 'selected' : '' }}>
                                            {{ $property['display_name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('flat_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    @endif

                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="title" id="title" required
                               value="{{ old('title') }}"
                               placeholder="Brief title of the complaint"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('title') border-red-500 @enderror">
                        @error('title')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <textarea name="description" id="description" rows="5" required
                                  placeholder="Detailed description of the complaint..."
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Priority -->
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
                            Priority <span class="text-red-500">*</span>
                        </label>
                        <select name="priority" id="priority" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('priority') border-red-500 @enderror">
                            <option value="">Select Priority</option>
                            <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                            <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                        @error('priority')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Images -->
                    <div>
                        <label for="images" class="block text-sm font-medium text-gray-700 mb-2">
                            Attach Images (Optional)
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-500 transition-colors"
                             onclick="document.getElementById('images').click()">
                            <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-2"></i>
                            <p class="text-gray-600">Click to upload or drag and drop</p>
                            <p class="text-sm text-gray-500">PNG, JPG, GIF up to 2MB (Max 5 files)</p>
                        </div>
                        <input type="file" name="images[]" id="images" multiple accept="image/*" class="hidden"
                               onchange="updateFileList(this)">
                        <div id="fileList" class="mt-3 space-y-2"></div>
                        @error('images')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                        <a href="{{ route('complaints.index') }}"
                           class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </a>
                        <button type="submit" id="submit-btn"
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-check mr-2"></i>Submit Complaint
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info Sidebar -->
        <div class="space-y-6">
            <!-- Tips -->
            <div class="bg-blue-50 rounded-lg shadow-md p-6 border border-blue-200">
                <h3 class="text-lg font-semibold text-blue-900 mb-4">
                    <i class="fas fa-lightbulb mr-2"></i>Tips
                </h3>
                <ul class="space-y-3 text-sm text-blue-800">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0"></i>
                        <span>Provide clear and detailed description</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0"></i>
                        <span>Attach relevant images for better understanding</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0"></i>
                        <span>Select appropriate priority level</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0"></i>
                        <span>You'll receive updates on your complaint</span>
                    </li>
                </ul>
            </div>

            <!-- Priority Guide -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Priority Levels</h3>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Low</span>
                        <span class="ml-3 text-sm text-gray-600">Non-urgent issues</span>
                    </div>
                    <div class="flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Medium</span>
                        <span class="ml-3 text-sm text-gray-600">Moderate issues</span>
                    </div>
                    <div class="flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">High</span>
                        <span class="ml-3 text-sm text-gray-600">Urgent issues</span>
                    </div>
                    <div class="flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Urgent</span>
                        <span class="ml-3 text-sm text-gray-600">Critical issues</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// COMPLAINT FORM - Direct submission handler (bypasses global handlers)
(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initComplaintForm);
    } else {
        initComplaintForm();
    }
    
    function initComplaintForm() {
        console.log('🔧 Complaint Form: Initializing direct handler...');
        
        const form = document.querySelector('form[action*="complaints.store"]');
        const submitBtn = document.getElementById('submit-btn');
        
        if (!form || !submitBtn) {
            console.error('❌ Complaint form elements not found');
            return;
        }
        
        // Remove any existing event listeners by cloning
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        
        // Get fresh references
        const freshForm = document.querySelector('form[action*="complaints.store"]');
        const freshSubmitBtn = document.getElementById('submit-btn');
        
        console.log('✅ Complaint form elements found and refreshed');
        
        // Add our direct event listener
        freshForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('📝 Complaint form submission started');
            
            // Disable submit button immediately
            freshSubmitBtn.disabled = true;
            freshSubmitBtn.innerHTML = '<span>Processing...</span><i class="fas fa-spinner fa-spin ml-2"></i>';
            
            // Validate required fields
            const categoryId = document.getElementById('category_id').value;
            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const priority = document.getElementById('priority').value;
            
            if (!categoryId || !title || !description || !priority) {
                alert('Please fill in all required fields');
                freshSubmitBtn.disabled = false;
                freshSubmitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Submit Complaint';
                return;
            }
            
            // Check if property selection is required (for owners)
            const flatIdSelect = document.getElementById('flat_id');
            if (flatIdSelect && flatIdSelect.offsetParent !== null) { // Check if visible
                if (!flatIdSelect.value) {
                    alert('Please select a property');
                    freshSubmitBtn.disabled = false;
                    freshSubmitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Submit Complaint';
                    return;
                }
            }
            
            console.log('✅ Form validation passed');
            
            // Submit directly
            console.log('🚀 Submitting form to:', freshForm.action);
            freshForm.submit();
        });
        
        console.log('✅ Complaint form handler attached');
    }
})();

// File list update function
function updateFileList(input) {
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '';
    
    if (input.files.length > 0) {
        const ul = document.createElement('ul');
        ul.className = 'space-y-2';
        
        for (let i = 0; i < input.files.length; i++) {
            const li = document.createElement('li');
            li.className = 'flex items-center text-sm text-gray-700 bg-gray-50 p-2 rounded';
            li.innerHTML = `
                <i class="fas fa-image text-blue-500 mr-2"></i>
                <span>${input.files[i].name}</span>
                <span class="ml-auto text-gray-500">${(input.files[i].size / 1024).toFixed(2)} KB</span>
            `;
            ul.appendChild(li);
        }
        
        fileList.appendChild(ul);
    }
}
</script>
@endpush
@endsection
