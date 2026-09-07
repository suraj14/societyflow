@extends('layouts.app')

@section('title', 'Raise Request')
@section('page-title', 'Raise Request')

@section('content')
<div class="p-6">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Submit a Request</h2>
                <p class="text-sm text-gray-500">Report an issue or request maintenance service</p>
            </div>

            <form action="{{ route('villa-owner.requests.store') }}" method="POST" class="p-6">
                @csrf

                <div class="space-y-6">
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Select category</option>
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

                    <!-- Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" name="title" value="{{ old('title') }}" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Brief description of the issue">
                        @error('title')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <textarea name="description" rows="4" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                  placeholder="Provide detailed information about the issue">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Priority -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority *</label>
                        <select name="priority" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low - Can wait</option>
                            <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>Medium - Normal</option>
                            <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High - Important</option>
                            <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgent - Needs immediate attention</option>
                        </select>
                        @error('priority')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Quick Select Categories -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Common Issues</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <button type="button" onclick="setTitle('Plumbing Issue')" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                                <i class="fas fa-faucet mr-1"></i> Plumbing
                            </button>
                            <button type="button" onclick="setTitle('Electrical Issue')" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                                <i class="fas fa-bolt mr-1"></i> Electrical
                            </button>
                            <button type="button" onclick="setTitle('Cleaning Required')" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                                <i class="fas fa-broom mr-1"></i> Cleaning
                            </button>
                            <button type="button" onclick="setTitle('Gardening Service')" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                                <i class="fas fa-leaf mr-1"></i> Gardening
                            </button>
                            <button type="button" onclick="setTitle('Street Light Issue')" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                                <i class="fas fa-lightbulb mr-1"></i> Street Light
                            </button>
                            <button type="button" onclick="setTitle('Security Concern')" class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">
                                <i class="fas fa-shield-alt mr-1"></i> Security
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3">
                    <a href="{{ route('villa-owner.requests') }}" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</a>
                    <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function setTitle(title) {
    document.querySelector('input[name="title"]').value = title;
}
</script>
@endpush
@endsection
