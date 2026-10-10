@php
    $sfBadge = [
        'draft'       => 'bg-amber-50 text-amber-700 ring-amber-200',
        'published'   => 'bg-green-50 text-green-700 ring-green-200',
        'unpublished' => 'bg-gray-100 text-gray-600 ring-gray-200',
        'archived'    => 'bg-slate-100 text-slate-500 ring-slate-200',
    ][$status] ?? 'bg-gray-100 text-gray-600 ring-gray-200';
@endphp
<span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold ring-1 {{ $sfBadge }}">{{ $status === 'published' ? 'Current' : ucfirst($status) }}</span>
