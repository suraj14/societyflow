@extends('layouts.guard')

@section('title', 'Gate History')
@section('page-title', 'Gate History')

@php
    // Same property rendering as the gate dashboard: the record says which
    // kind it is, so the guard is never asked (brief §7, §9).
    $sfGuardProperty = function ($flat) {
        if (!$flat) return 'Unassigned';
        if ($flat->property_type === 'villa') {
            $area = $flat->villaArea?->name;
            return trim('Villa ' . $flat->flat_number . ($area ? ' - ' . $area : ''));
        }
        $building = $flat->building?->name;
        return trim(($building ? $building . ' / ' : '') . 'Flat ' . $flat->flat_number);
    };

    $sfBadge = [
        'entered' => ['Inside',   'bg-green-100 text-green-800'],
        'exited'  => ['Departed', 'bg-gray-100 text-gray-700'],
        'pending' => ['Pending',  'bg-amber-100 text-amber-800'],
    ];
@endphp

@section('content')
<div class="p-4 space-y-2">
    @forelse($visitors as $visitor)
        @php [$sfLabel, $sfClass] = $sfBadge[$visitor->entry_status] ?? ['Unknown', 'bg-gray-100 text-gray-700']; @endphp
        <div class="bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900 truncate">{{ $visitor->visitor_name }}</p>
                    <p class="text-sm text-gray-600">{{ $sfGuardProperty($visitor->flat) }}</p>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full shrink-0 {{ $sfClass }}">{{ $sfLabel }}</span>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                <i class="fas fa-calendar"></i>
                {{ $visitor->expected_entry_time?->format('d M Y, h:i A') ?? '-' }}
                @if($visitor->actual_exit_time)
                    &middot; out {{ $visitor->actual_exit_time->format('h:i A') }}
                @endif
            </p>
        </div>
    @empty
        <p class="text-sm text-gray-500 bg-white rounded-xl p-6 text-center">No gate activity recorded yet.</p>
    @endforelse

    <div class="pt-2">
        {{ $visitors->links() }}
    </div>
</div>
@endsection
