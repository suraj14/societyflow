@extends('layouts.app')

@section('title', 'Edit Notice')
@section('page-title', 'Edit Notice')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('notices.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Notice</h1>
            <p class="text-gray-600 mt-1">Update notice information</p>
        </div>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-4xl bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Notice Details</h2>
        </div>
        
        <form action="{{ route('notices.update', $notice) }}" method="POST" enctype="multipart/form-data" class="px-6 py-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @if(auth()->user()->hasRole('Super Admin'))
                        <div>
                            <label for="society_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Society <span class="text-red-500">*</span>
                            </label>
                            <select name="society_id" id="society_id" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($societies as $society)
                                    <option value="{{ $society->id }}" {{ old('society_id', $notice->society_id) == $society->id ? 'selected' : '' }}>
                                        {{ $society->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <!-- Hidden field for Society Admin users -->
                        <input type="hidden" name="society_id" value="{{ auth()->user()->society_id }}">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Society
                            </label>
                            <div class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                                {{ $notice->society->name }}
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Editing notice for your society</p>
                        </div>
                    @endif

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            Notice Type <span class="text-red-500">*</span>
                        </label>
                        <select name="type" id="type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="general" {{ old('type', $notice->type) == 'general' ? 'selected' : '' }}>General</option>
                            <option value="urgent" {{ old('type', $notice->type) == 'urgent' ? 'selected' : '' }}>Urgent</option>
                            <option value="maintenance" {{ old('type', $notice->type) == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                            <option value="event" {{ old('type', $notice->type) == 'event' ? 'selected' : '' }}>Event</option>
                            <option value="meeting" {{ old('type', $notice->type) == 'meeting' ? 'selected' : '' }}>Meeting</option>
                        </select>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="title" id="title" required value="{{ old('title', $notice->title) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Content -->
                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                        Content <span class="text-red-500">*</span>
                    </label>
                    <textarea name="content" id="content" rows="6" required
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('content', $notice->content) }}</textarea>
                </div>

                <!-- Priority and Dates -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">
                            Priority <span class="text-red-500">*</span>
                        </label>
                        <select name="priority" id="priority" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="low" {{ old('priority', $notice->priority) == 'low' ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ old('priority', $notice->priority) == 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high" {{ old('priority', $notice->priority) == 'high' ? 'selected' : '' }}>High</option>
                        </select>
                    </div>

                    <div>
                        <label for="publish_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Publish Date
                        </label>
                        <input type="date" name="publish_date" id="publish_date" 
                               value="{{ old('publish_date', $notice->publish_date ? $notice->publish_date->format('Y-m-d') : '') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Expiry Date
                        </label>
                        <input type="date" name="expiry_date" id="expiry_date" 
                               value="{{ old('expiry_date', $notice->expiry_date ? $notice->expiry_date->format('Y-m-d') : '') }}"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <!-- Current Image -->
                @if($notice->image)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Image</label>
                        <div class="flex items-center space-x-4">
                            <img src="{{ asset('storage/' . $notice->image) }}" alt="{{ $notice->title }}" class="w-32 h-32 object-cover rounded-lg">
                        </div>
                    </div>
                @endif

                <!-- Image Upload -->
                <div>
                    <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ $notice->image ? 'Replace Image' : 'Notice Image' }}
                    </label>
                    <input type="file" name="image" id="image" accept="image/*"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-500">{{ $notice->image ? 'Leave empty to keep current image' : 'Upload an image to display with the notice (optional)' }}</p>
                </div>

                <!-- Notification Options -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Notification Options</h3>
                    <div class="flex flex-wrap gap-6">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="send_email" value="1" {{ old('send_email', $notice->send_email) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Send Email Notification</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="send_sms" value="1" {{ old('send_sms', $notice->send_sms) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Send SMS Notification</span>
                        </label>
                    </div>
                </div>

                <!-- Status -->
                <div class="border-t border-gray-200 pt-6">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select name="status" id="status" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="draft" {{ old('status', $notice->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ old('status', $notice->status) == 'pending' ? 'selected' : '' }}>Pending Approval</option>
                        <option value="published" {{ old('status', $notice->status) == 'published' ? 'selected' : '' }}>Published</option>
                        <option value="expired" {{ old('status', $notice->status) == 'expired' ? 'selected' : '' }}>Expired</option>
                    </select>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('notices.index') }}" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    Update Notice
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
