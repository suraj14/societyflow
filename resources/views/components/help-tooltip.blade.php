@props(['text', 'position' => 'top'])

@php
    $positions = [
        'top' => 'bottom-full mb-2',
        'bottom' => 'top-full mt-2',
        'left' => 'right-full mr-2',
        'right' => 'left-full ml-2',
    ];
    $positionClass = $positions[$position] ?? $positions['top'];
@endphp

<div class="relative inline-block group">
    <button type="button" 
            class="text-gray-400 hover:text-gray-600 focus:outline-none"
            aria-label="Help"
            tabindex="0">
        <i class="fas fa-question-circle"></i>
    </button>
    
    <div class="hidden group-hover:block group-focus-within:block absolute {{ $positionClass }} left-1/2 transform -translate-x-1/2 w-48 bg-gray-900 text-white text-xs rounded p-2 z-10 pointer-events-none group-hover:pointer-events-auto">
        {{ $text }}
        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
    </div>
    
    {{ $slot }}
</div>
