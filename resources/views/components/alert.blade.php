@props(['type' => 'info', 'dismissible' => true, 'autoDismiss' => true])

@php
    $colors = [
        'success' => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'text' => 'text-green-800', 'icon' => 'fas fa-check-circle', 'bg-icon' => 'bg-green-100'],
        'error' => ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'text' => 'text-red-800', 'icon' => 'fas fa-exclamation-circle', 'bg-icon' => 'bg-red-100'],
        'warning' => ['bg' => 'bg-yellow-50', 'border' => 'border-yellow-200', 'text' => 'text-yellow-800', 'icon' => 'fas fa-exclamation-triangle', 'bg-icon' => 'bg-yellow-100'],
        'info' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'text' => 'text-blue-800', 'icon' => 'fas fa-info-circle', 'bg-icon' => 'bg-blue-100'],
    ];
    $color = $colors[$type] ?? $colors['info'];
@endphp

<div x-data="{ show: true }" 
     x-show="show" 
     x-init="@if($autoDismiss) setTimeout(() => show = false, 5000) @endif"
     :class="{ 'toast-exit': !show }"
     class="toast-enter {{ $color['bg'] }} border-l-4 {{ $color['border'] }} {{ $color['text'] }} p-4 mb-6 rounded-lg"
     role="alert">
    <div class="flex items-start">
        <div class="flex-shrink-0">
            <i class="{{ $color['icon'] }} mr-3"></i>
        </div>
        <div class="flex-1">
            {{ $slot }}
        </div>
        @if($dismissible)
            <button @click="show = false" 
                    class="ml-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                    aria-label="Dismiss alert">
                <i class="fas fa-times"></i>
            </button>
        @endif
    </div>
</div>
