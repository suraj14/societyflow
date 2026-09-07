@props(['status', 'label' => null])

@php
    $statuses = [
        'active' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'fas fa-check-circle'],
        'inactive' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'fas fa-times-circle'],
        'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'fas fa-clock'],
        'approved' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'fas fa-check-circle'],
        'rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'fas fa-times-circle'],
        'completed' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'fas fa-check-circle'],
        'cancelled' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'fas fa-times-circle'],
        'open' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'icon' => 'fas fa-folder-open'],
        'in_progress' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'icon' => 'fas fa-spinner'],
        'resolved' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'fas fa-check-circle'],
        'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'fas fa-file'],
        'published' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'fas fa-check-circle'],
    ];
    
    $statusKey = strtolower(str_replace(' ', '_', $status));
    $style = $statuses[$statusKey] ?? $statuses['pending'];
    $displayLabel = $label ?? ucfirst(str_replace('_', ' ', $statusKey));
@endphp

<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $style['bg'] }} {{ $style['text'] }}"
      title="{{ $displayLabel }}">
    <i class="{{ $style['icon'] }} mr-1"></i>
    {{ $displayLabel }}
</span>
