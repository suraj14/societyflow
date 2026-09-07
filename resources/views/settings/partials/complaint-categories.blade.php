<!-- Complaint Categories Tab -->
<div x-show="activeTab === 'complaint-categories'" x-cloak>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3 pb-4 border-b border-gray-200 flex-1">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-list text-red-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Complaint Categories</h3>
                    <p class="text-sm text-gray-500">Manage complaint categories for your society</p>
                </div>
            </div>
        </div>

        <!-- Add New Category Form -->
        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
            <h4 class="text-md font-semibold text-gray-900 mb-4">Add New Category</h4>
            <form action="{{ route('settings.complaint-categories.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                        <input type="text" name="name" placeholder="e.g., Maintenance" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <input type="text" name="description" placeholder="Brief description"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Category
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Categories List -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Category Name</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Description</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($complaintCategories ?? [] as $category)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-tag text-red-500"></i>
                                        <span>{{ $category->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $category->description ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $category->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($category->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm space-x-2">
                                    <form action="{{ route('settings.complaint-categories.toggle', $category->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-blue-600 hover:text-blue-800 font-medium">
                                            {{ $category->status === 'active' ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('settings.complaint-categories.destroy', $category->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-3xl mb-2 block opacity-50"></i>
                                    <p>No complaint categories yet. Add one to get started.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info Box -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <i class="fas fa-info-circle text-blue-600 mt-1"></i>
                <div>
                    <h5 class="text-sm font-medium text-blue-900 mb-1">Tips for Categories</h5>
                    <ul class="text-sm text-blue-800 space-y-1">
                        <li>• Create categories that match common complaint types in your society</li>
                        <li>• Use clear, descriptive names so residents can easily find the right category</li>
                        <li>• You can disable categories without deleting them</li>
                        <li>• Active categories will appear in the complaint form</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
