@props(['icon' => 'fas fa-inbox', 'title' => 'No data found', 'description' => null, 'action' => null, 'actionText' => 'Add New'])

<div class="text-center py-12">
    <div class="flex justify-center mb-4">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center">
            <i class="{{ $icon }} text-gray-400 text-3xl"></i>
        </div>
    </div>
    
    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ $title }}</h3>
    
    @if($description)
        <p class="text-gray-600 mb-6">{{ $description }}</p>
    @endif
    
    @if($action)
        <a href="{{ $action }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i>
            {{ $actionText }}
        </a>
    @endif
    
    {{ $slot }}
</div>
