@extends('layouts.guard')

@section('title', 'Gate')
@section('page-title', "Today's Gate")

@php
    /**
     * Render a property the way the record already describes it. The guard
     * never chooses "apartment" or "villa" - the flat knows which it is, so
     * a villa township never shows the word "Flat" (brief §7, §9).
     */
    $sfGuardProperty = function ($flat) {
        if (!$flat) return 'Unassigned';
        if ($flat->property_type === 'villa') {
            $area = $flat->villaArea?->name;
            return trim('Villa ' . $flat->flat_number . ($area ? ' - ' . $area : ''));
        }
        $building = $flat->building?->name;
        return trim(($building ? $building . ' / ' : '') . 'Flat ' . $flat->flat_number);
    };
@endphp

@section('content')
<div class="p-4 space-y-5">

    {{-- Four numbers, nothing else. --}}
    <div class="grid grid-cols-2 gap-3">
        @php
            $sfCards = [
                ['label' => 'Waiting',   'value' => $stats['waiting'],  'bg' => 'bg-amber-500',  'icon' => 'fa-hourglass-half'],
                ['label' => 'Expected',  'value' => $stats['expected'], 'bg' => 'bg-blue-500',   'icon' => 'fa-calendar-check'],
                ['label' => 'Inside',    'value' => $stats['inside'],   'bg' => 'bg-green-600',  'icon' => 'fa-person-walking-arrow-right'],
                ['label' => 'Departed',  'value' => $stats['departed'], 'bg' => 'bg-gray-500',   'icon' => 'fa-right-from-bracket'],
            ];
        @endphp
        @foreach($sfCards as $sfCard)
            <div class="{{ $sfCard['bg'] }} text-white rounded-xl p-4 shadow">
                <div class="flex items-center justify-between">
                    <span class="text-3xl font-bold">{{ $sfCard['value'] }}</span>
                    <i class="fas {{ $sfCard['icon'] }} text-xl opacity-70"></i>
                </div>
                <p class="text-sm mt-1 opacity-90">{{ $sfCard['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- The primary action, impossible to miss. --}}
    <a href="{{ route('guard.visitors.create') }}"
       class="tap-target w-full bg-purple-700 hover:bg-purple-800 text-white rounded-xl flex items-center justify-center gap-3 text-lg font-semibold shadow py-4">
        <i class="fas fa-user-plus"></i> Register Visitor
    </a>

    {{-- Waiting on resident approval --}}
    <section>
        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">
            Waiting for Approval ({{ $waiting->count() }})
        </h2>
        <div class="space-y-2">
            @forelse($waiting as $visitor)
                <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-amber-500">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $visitor->visitor_name }}</p>
                            <p class="text-sm text-gray-600">{{ $sfGuardProperty($visitor->flat) }}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-tag"></i> {{ ucfirst($visitor->visitor_type) }}
                                @if($visitor->vehicle_number)
                                    &middot; <i class="fas fa-car"></i> {{ $visitor->vehicle_number }}
                                @endif
                            </p>
                        </div>
                        <a href="tel:{{ $visitor->visitor_phone }}"
                           class="tap-target px-3 flex items-center rounded-lg bg-gray-100 text-gray-700 shrink-0">
                            <i class="fas fa-phone"></i>
                        </a>
                    </div>
                    <p class="text-xs text-amber-700 mt-2 font-medium">
                        <i class="fas fa-hourglass-half"></i> Awaiting resident decision
                    </p>
                </div>
            @empty
                <p class="text-sm text-gray-500 bg-white rounded-xl p-4 text-center">Nobody waiting.</p>
            @endforelse
        </div>
    </section>

    {{-- Approved, not yet arrived: one tap to admit --}}
    <section>
        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">
            Expected Today ({{ $expected->count() }})
        </h2>
        <div class="space-y-2">
            @forelse($expected as $visitor)
                <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-blue-500">
                    <p class="font-semibold text-gray-900 truncate">{{ $visitor->visitor_name }}</p>
                    <p class="text-sm text-gray-600">{{ $sfGuardProperty($visitor->flat) }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-clock"></i>
                        {{ $visitor->expected_entry_time?->format('h:i A') ?? 'Any time' }}
                    </p>
                    <form method="POST" action="{{ route('guard.visitors.check-in', $visitor) }}" class="mt-3">
                        @csrf
                        <button type="submit"
                                class="tap-target w-full bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold">
                            <i class="fas fa-arrow-right-to-bracket"></i> Check In
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-500 bg-white rounded-xl p-4 text-center">No expected visitors.</p>
            @endforelse
        </div>
    </section>

    {{-- Currently on the premises --}}
    <section>
        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2">
            Currently Inside ({{ $inside->count() }})
        </h2>
        <div class="space-y-2">
            @forelse($inside as $visitor)
                <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-green-600">
                    <p class="font-semibold text-gray-900 truncate">{{ $visitor->visitor_name }}</p>
                    <p class="text-sm text-gray-600">{{ $sfGuardProperty($visitor->flat) }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-arrow-right-to-bracket"></i>
                        In at {{ $visitor->actual_entry_time?->format('h:i A') ?? '-' }}
                    </p>
                    <form method="POST" action="{{ route('guard.visitors.check-out', $visitor) }}" class="mt-3">
                        @csrf
                        <button type="submit"
                                class="tap-target w-full bg-gray-700 hover:bg-gray-800 text-white rounded-lg font-semibold">
                            <i class="fas fa-right-from-bracket"></i> Check Out
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-500 bg-white rounded-xl p-4 text-center">Nobody inside.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
