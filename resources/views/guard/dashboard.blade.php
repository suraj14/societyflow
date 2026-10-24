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
<div class="p-4 space-y-5 md:p-6 md:space-y-6">

    {{-- Four numbers, nothing else. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
        @php
            $sfCards = [
                ['key' => 'waiting',  'label' => 'Waiting',   'value' => $stats['waiting'],  'bg' => 'bg-amber-500',  'icon' => 'fa-hourglass-half'],
                ['key' => 'expected', 'label' => 'Expected',  'value' => $stats['expected'], 'bg' => 'bg-blue-500',   'icon' => 'fa-calendar-check'],
                ['key' => 'inside',   'label' => 'Inside',    'value' => $stats['inside'],   'bg' => 'bg-green-600',  'icon' => 'fa-user-check'],
                ['key' => 'departed', 'label' => 'Departed',  'value' => $stats['departed'], 'bg' => 'bg-gray-500',   'icon' => 'fa-right-from-bracket'],
            ];
        @endphp
        @foreach($sfCards as $sfCard)
            <div class="{{ $sfCard['bg'] }} text-white rounded-xl p-4 shadow">
                <div class="flex items-center justify-between">
                    <span class="text-3xl font-bold" data-stat="{{ $sfCard['key'] }}">{{ $sfCard['value'] }}</span>
                    <i class="fas {{ $sfCard['icon'] }} text-xl opacity-70"></i>
                </div>
                <p class="text-sm mt-1 opacity-90">{{ $sfCard['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- The primary action, impossible to miss: scan first. Recurring staff,
         temporary service providers and pre-approved guests all go through
         here with nothing to type - "Register Visitor" is for the one case
         that genuinely has no pass: an unannounced, unknown visitor. --}}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-3 md:gap-4">
    <a href="{{ route('guard.scan') }}"
       class="tap-target w-full bg-purple-700 hover:bg-purple-800 text-white rounded-xl flex items-center justify-center gap-3 text-lg font-semibold shadow py-4">
        <i class="fas fa-qrcode"></i> Scan QR
    </a>
    <a href="{{ route('guard.visitors.create') }}"
       class="tap-target w-full bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl flex items-center justify-center gap-3 text-base font-medium py-3">
        <i class="fas fa-user-plus"></i> Register Unknown Visitor
    </a>
    <a href="{{ route('guard.shift.current') }}"
       class="tap-target w-full bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl flex items-center justify-center gap-3 text-base font-medium py-3">
        <i class="fas fa-clock"></i> My Shift
    </a>
    </div>

    {{-- The three lists: one under another on a phone, side by side on a wide screen. --}}
    <div class="space-y-5 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">

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
        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2" data-section-count="expected">
            Expected Today ({{ $expected->count() }})
        </h2>
        <div class="space-y-2" data-section-list="expected">
            @forelse($expected as $visitor)
                <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-blue-500" data-visitor-card="{{ $visitor->id }}">
                    <p class="font-semibold text-gray-900 truncate">{{ $visitor->visitor_name }}</p>
                    <p class="text-sm text-gray-600">{{ $sfGuardProperty($visitor->flat) }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-clock"></i>
                        {{ $visitor->expected_entry_time?->format('h:i A') ?? 'Any time' }}
                    </p>
                    <form method="POST" action="{{ route('guard.visitors.check-in', $visitor) }}" class="mt-3" data-gate-action="check-in">
                        @csrf
                        <button type="submit"
                                class="tap-target w-full bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold">
                            <i class="fas fa-arrow-right-to-bracket"></i> Check In
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-500 bg-white rounded-xl p-4 text-center" data-section-empty="expected">No expected visitors.</p>
            @endforelse
        </div>
    </section>

    {{-- Currently on the premises --}}
    <section>
        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-2" data-section-count="inside">
            Currently Inside ({{ $inside->count() }})
        </h2>
        <div class="space-y-2" data-section-list="inside">
            @forelse($inside as $visitor)
                <div class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-green-600" data-visitor-card="{{ $visitor->id }}">
                    <p class="font-semibold text-gray-900 truncate">{{ $visitor->visitor_name }}</p>
                    <p class="text-sm text-gray-600">{{ $sfGuardProperty($visitor->flat) }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-arrow-right-to-bracket"></i>
                        In at {{ $visitor->actual_entry_time?->format('h:i A') ?? '-' }}
                    </p>
                    <form method="POST" action="{{ route('guard.visitors.check-out', $visitor) }}" class="mt-3" data-gate-action="check-out">
                        @csrf
                        <button type="submit"
                                class="tap-target w-full bg-gray-700 hover:bg-gray-800 text-white rounded-lg font-semibold">
                            <i class="fas fa-right-from-bracket"></i> Check Out
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-gray-500 bg-white rounded-xl p-4 text-center" data-section-empty="inside">Nobody inside.</p>
            @endforelse
        </div>
    </section>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    // §18/§4: a guard checks visitors in and out rapidly; a full page reload
    // after every tap is exactly the friction this removes. Progressive
    // enhancement only - the form still has a real action and method, so it
    // works with JS disabled exactly as it did before this change.
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function toast(message, isError) {
        const el = document.createElement('div');
        el.textContent = message;
        el.className = 'fixed left-4 right-4 bottom-20 z-30 md:left-auto md:right-6 md:bottom-6 md:w-96 rounded-lg px-4 py-3 text-sm font-medium shadow-lg text-white text-center ' +
            (isError ? 'bg-red-600' : 'bg-green-600');
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 2500);
    }

    function updateStats(stats) {
        if (!stats) return;
        Object.keys(stats).forEach(function (key) {
            const el = document.querySelector('[data-stat="' + key + '"]');
            if (el) el.textContent = stats[key];
        });
    }

    function updateSectionCount(section, delta) {
        const heading = document.querySelector('[data-section-count="' + section + '"]');
        if (!heading) return;
        const match = heading.textContent.match(/\((\d+)\)/);
        const current = match ? parseInt(match[1], 10) : 0;
        const next = Math.max(0, current + delta);
        heading.textContent = heading.textContent.replace(/\(\d+\)/, '(' + next + ')');

        // The list emptying by this action never ran the server-side "empty"
        // branch of the loop that rendered it, so its placeholder text has
        // to be added by hand once the last card is gone.
        if (next === 0) {
            const list = document.querySelector('[data-section-list="' + section + '"]');
            if (list && !list.querySelector('[data-section-empty]')) {
                const label = section === 'expected' ? 'No expected visitors.' : 'Nobody inside.';
                list.insertAdjacentHTML(
                    'beforeend',
                    '<p class="text-sm text-gray-500 bg-white rounded-xl p-4 text-center" data-section-empty="' + section + '">' + label + '</p>'
                );
            }
        }
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form.matches('[data-gate-action]')) return;

        e.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        if (button.disabled) return; // already submitting - the tap that matters is the first one
        button.disabled = true;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working...';

        const card = form.closest('[data-visitor-card]');
        const action = form.getAttribute('data-gate-action');
        const section = action === 'check-in' ? 'expected' : 'inside';

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (result) {
            if (!result.ok || !result.data.success) {
                toast(result.data.message || 'That did not work.', true);
                button.disabled = false;
                button.innerHTML = originalHtml;
                return;
            }

            toast(result.data.message);
            updateStats(result.data.stats);
            updateSectionCount(section, -1);
            if (card) card.remove();

            // A checked-in visitor belongs in "Currently Inside" now; the
            // simplest correct move without duplicating that card's markup
            // in JavaScript is to fetch the page's own current state -
            // still zero full-page navigations from the guard's point of
            // view, since this happens invisibly after the tap already
            // resolved.
            if (action === 'check-in') {
                fetch(window.location.pathname, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const freshList = doc.querySelector('[data-section-list="inside"]');
                        const freshHeading = doc.querySelector('[data-section-count="inside"]');
                        const ourList = document.querySelector('[data-section-list="inside"]');
                        const ourHeading = document.querySelector('[data-section-count="inside"]');
                        if (freshList && ourList) ourList.innerHTML = freshList.innerHTML;
                        if (freshHeading && ourHeading) ourHeading.textContent = freshHeading.textContent;
                    })
                    .catch(function () { /* the tap already succeeded; a stale inside-list is cosmetic */ });
            }
        })
        .catch(function () {
            toast('Network error. Please try again.', true);
            button.disabled = false;
            button.innerHTML = originalHtml;
        });
    });
})();
</script>
@endpush
@endsection
