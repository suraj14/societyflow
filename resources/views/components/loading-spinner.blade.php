@props(['text' => 'Loading...', 'size' => 'md'])

@php
    $sizes = [
        'sm' => 'h-6 w-6',
        'md' => 'h-12 w-12',
        'lg' => 'h-16 w-16',
    ];
    $spinnerSize = $sizes[$size] ?? $sizes['md'];
@endphp

<div class="flex flex-col items-center justify-center py-12">
    <div class="animate-spin rounded-full {{ $spinnerSize }} border-b-2 border-blue-600"></div>
    @if($text)
        <p class="mt-4 text-gray-600 font-medium">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
