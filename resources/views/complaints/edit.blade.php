@extends('layouts.app')

@section('title', 'Edit Complaint')
@section('page-title', 'Edit Complaint')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('complaints.show', $complaint) }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Complaint</h1>
            <p class="text-gray-600 mt-1">Ticket #{{ $complaint->complaint_number }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md">
                <form action="{{ route('complaints.update', $complaint) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Requested By (Auto-filled) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Requested By
                        </label>
                        <div class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                            {{ $complaint->createdBy->name }}
                        </div>
                    </div>

                    <!-- Category Selection -->
                    <div>
                        <label for="complaint_category_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Category <span class="text-red-500">*</span>
                        </label>
                        <select name="complaint_category_id" id="complaint_category_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('complaint_category_id') border-red-500 @enderror">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ (old('complaint_category_id', $complaint->complaint_category_id) == $category->id) ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('complaint_category_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="title" id="title" required
                               value="{{ old('title', $complaint->title) }}"
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
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $complaint->description) }}</textarea>
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
                            <option value="low" {{ old('priority', $complaint->priority) == 'low' ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ old('priority', $complaint->priority) == 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high" {{ old('priority', $complaint->priority) == 'high' ? 'selected' : '' }}>High</option>
                            <option value="urgent" {{ old('priority', $complaint->priority) == 'urgent' ? 'selected' : '' }}>Urgent</option>
                        </select>
                        @error('priority')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status (Admin only) -->
                    @if(auth()->user()->hasRole(['Admin', 'Super Admin']))
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select name="status" id="status" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror">
                            <option value="open" {{ old('status', $complaint->status) == 'open' ? 'selected' : '' }}>Open</option>
                            <option value="in_progress" {{ old('status', $complaint->status) == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="resolved" {{ old('status', $complaint->status) == 'resolved' ? 'selected' : '' }}>Resolved</option>
                            <option value="closed" {{ old('status', $complaint->status) == 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    @endif

                    <!-- Current Attachments -->
                    @php
                        $attachments = $complaint->attachments;
                        if (is_string($attachments)) {
                            $attachments = json_decode($attachments, true) ?? [];
                        } elseif (!is_array($attachments)) {
                            $attachments = [];
                        }
                    @endphp
                    @if(!empty($attachments))
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Current Attachments
                        </label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach($attachments as $attachment)
                                @if($attachment)
                                    <div class="relative group">
                                        <img src="{{ asset('storage/' . $attachment) }}" 
                                             alt="Attachment" 
                                             class="w-full h-20 object-cover rounded-lg cursor-pointer hover:opacity-75"
                                             onclick="openImageModal('{{ asset('storage/' . $attachment) }}')">
                                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 rounded-lg transition-all"></div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Add New Images -->
                    <div>
                        <label for="images" class="block text-sm font-medium text-gray-700 mb-2">
                            Add New Images (Optional)
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
                        <a href="{{ route('complaints.show', $complaint) }}"
                           class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                            <i class="fas fa-save mr-2"></i>Update Complaint
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info Sidebar -->
        <div class="space-y-6">
            <!-- Complaint Info -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>Complaint Info
                </h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Ticket #:</span>
                        <span class="font-medium">{{ $complaint->complaint_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Created:</span>
                        <span class="font-medium">{{ $complaint->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            {{ $complaint->status === 'open' ? 'bg-red-100 text-red-800' : 
                               ($complaint->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 
                               ($complaint->status === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')) }}">
                            {{ ucfirst(str_replace('_', ' ', $complaint->status)) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="bg-blue-50 rounded-lg shadow-md p-6 border border-blue-200">
                <h3 class="text-lg font-semibold text-blue-900 mb-4">
                    <i class="fas fa-lightbulb mr-2"></i>Tips
                </h3>
                <ul class="space-y-3 text-sm text-blue-800">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0"></i>
                        <span>Only edit if necessary - updates are tracked</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0"></i>
                        <span>Use the reply system for additional information</span>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle mr-2 mt-1 flex-shrink-0"></i>
                        <span>New images will be added to existing ones</span>
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

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50" onclick="closeImageModal()">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative max-w-4xl max-h-full">
            <img id="modalImage" src="" alt="Full size image" class="max-w-full max-h-full object-contain">
            <button onclick="closeImageModal()" 
                    class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center hover:bg-opacity-75">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>

<script>
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

function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>
@endsection