@extends('layouts.guard')

@section('title', 'Register Visitor')
@section('page-title', 'Register Visitor')

@section('content')
<div class="p-4 md:p-6 md:max-w-3xl">
    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-red-100 border border-red-300 text-red-800 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('guard.visitors.store') }}" class="space-y-4">
        @csrf
        @include('partials.idempotency')

        {{--
            One search box for every property type. The guard types a flat
            number, a villa number, a block or a resident's name; the server
            decides what matched. They are never asked "apartment or villa?"
            because the society and the record already know (brief §7, §9).
        --}}
        <div>
            <label for="property-search" class="block text-sm font-semibold text-gray-700 mb-1">
                Which property? <span class="text-red-500">*</span>
            </label>
            <input type="text" id="property-search" autocomplete="off"
                   placeholder="Flat no, villa no, block or resident name"
                   class="tap-target w-full px-4 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            <input type="hidden" name="flat_id" id="flat-id" value="{{ old('flat_id') }}">

            <div id="property-selected" class="hidden mt-2 p-3 rounded-lg bg-purple-50 border border-purple-200">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-purple-900 truncate" id="selected-label"></p>
                        <p class="text-sm text-purple-700 truncate" id="selected-resident"></p>
                    </div>
                    <button type="button" id="clear-property"
                            class="tap-target px-3 text-purple-700 shrink-0" aria-label="Clear selection">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>
            </div>

            <ul id="property-results" class="hidden mt-2 bg-white border border-gray-200 rounded-lg divide-y max-h-64 overflow-y-auto"></ul>
            <p id="property-empty" class="hidden mt-2 text-sm text-gray-500">No matching property.</p>
        </div>

        <div>
            <label for="visitor_name" class="block text-sm font-semibold text-gray-700 mb-1">
                Visitor name <span class="text-red-500">*</span>
            </label>
            <input type="text" name="visitor_name" id="visitor_name" required value="{{ old('visitor_name') }}"
                   class="tap-target w-full px-4 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>

        <div>
            <label for="visitor_phone" class="block text-sm font-semibold text-gray-700 mb-1">
                Phone <span class="text-red-500">*</span>
            </label>
            <input type="tel" inputmode="numeric" name="visitor_phone" id="visitor_phone" required value="{{ old('visitor_phone') }}"
                   class="tap-target w-full px-4 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>

        {{-- Buttons, not a dropdown: one tap instead of three. --}}
        <div>
            <span class="block text-sm font-semibold text-gray-700 mb-1">Type <span class="text-red-500">*</span></span>
            <div class="grid grid-cols-3 gap-2" id="visitor-type-group">
                @php
                    $sfTypes = [
                        'guest'    => 'fa-user',
                        'delivery' => 'fa-box',
                        'cab'      => 'fa-taxi',
                        'service'  => 'fa-screwdriver-wrench',
                        'other'    => 'fa-ellipsis',
                    ];
                    $sfCurrent = old('visitor_type', 'guest');
                @endphp
                @foreach($sfTypes as $sfValue => $sfIcon)
                    <label class="tap-target flex flex-col items-center justify-center rounded-lg border-2 cursor-pointer py-2
                                  {{ $sfCurrent === $sfValue ? 'border-purple-600 bg-purple-50 text-purple-800' : 'border-gray-200 bg-white text-gray-600' }}">
                        <input type="radio" name="visitor_type" value="{{ $sfValue }}" class="sr-only"
                               {{ $sfCurrent === $sfValue ? 'checked' : '' }}>
                        <i class="fas {{ $sfIcon }}"></i>
                        <span class="text-xs mt-1 capitalize">{{ $sfValue }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label for="vehicle_number" class="block text-sm font-semibold text-gray-700 mb-1">Vehicle number</label>
            <input type="text" name="vehicle_number" id="vehicle_number" value="{{ old('vehicle_number') }}"
                   class="tap-target w-full px-4 border border-gray-300 rounded-lg text-base uppercase focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>

        <div>
            <label for="purpose" class="block text-sm font-semibold text-gray-700 mb-1">Purpose</label>
            <input type="text" name="purpose" id="purpose" value="{{ old('purpose') }}"
                   class="tap-target w-full px-4 border border-gray-300 rounded-lg text-base focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        </div>

        <button type="submit"
                class="tap-target w-full bg-purple-700 hover:bg-purple-800 text-white rounded-xl text-lg font-semibold shadow py-4">
            <i class="fas fa-check"></i> Register Visitor
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const search    = document.getElementById('property-search');
    const results   = document.getElementById('property-results');
    const empty     = document.getElementById('property-empty');
    const hidden    = document.getElementById('flat-id');
    const selected  = document.getElementById('property-selected');
    const selLabel  = document.getElementById('selected-label');
    const selPerson = document.getElementById('selected-resident');
    const clearBtn  = document.getElementById('clear-property');

    if (!search) return;

    let timer = null;
    let controller = null;

    function hideResults() {
        results.classList.add('hidden');
        empty.classList.add('hidden');
        results.innerHTML = '';
    }

    function choose(item) {
        hidden.value = item.id;
        selLabel.textContent = item.label;
        selPerson.textContent = item.resident ? item.resident : 'No resident on record';
        selected.classList.remove('hidden');
        search.value = '';
        hideResults();
    }

    clearBtn.addEventListener('click', function () {
        hidden.value = '';
        selected.classList.add('hidden');
        search.focus();
    });

    search.addEventListener('input', function () {
        const term = search.value.trim();
        clearTimeout(timer);

        if (term.length < 2) { hideResults(); return; }

        // Debounced, and the previous request is abandoned, so a fast typist
        // on a slow gate connection does not queue up stale responses.
        timer = setTimeout(function () {
            if (controller) controller.abort();
            controller = new AbortController();

            fetch('{{ route('guard.search.properties') }}?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json' },
                signal: controller.signal
            })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                const rows = (payload && payload.data) ? payload.data : [];
                results.innerHTML = '';

                if (!rows.length) {
                    results.classList.add('hidden');
                    empty.classList.remove('hidden');
                    return;
                }

                empty.classList.add('hidden');
                rows.forEach(function (item) {
                    const li = document.createElement('li');
                    li.className = 'tap-target flex flex-col justify-center px-4 py-2 cursor-pointer hover:bg-purple-50';

                    const label = document.createElement('span');
                    label.className = 'font-medium text-gray-900';
                    label.textContent = item.label;

                    const who = document.createElement('span');
                    who.className = 'text-sm text-gray-500';
                    who.textContent = item.resident ? item.resident : 'No resident on record';

                    li.appendChild(label);
                    li.appendChild(who);
                    li.addEventListener('click', function () { choose(item); });
                    results.appendChild(li);
                });
                results.classList.remove('hidden');
            })
            .catch(function (err) {
                if (err.name === 'AbortError') return;
                results.classList.add('hidden');
                empty.textContent = 'Search unavailable. Check your connection.';
                empty.classList.remove('hidden');
            });
        }, 250);
    });

    // Highlight the chosen type button.
    document.getElementById('visitor-type-group').addEventListener('change', function (e) {
        if (e.target.name !== 'visitor_type') return;
        this.querySelectorAll('label').forEach(function (label) {
            const on = label.querySelector('input').checked;
            label.className = 'tap-target flex flex-col items-center justify-center rounded-lg border-2 cursor-pointer py-2 ' +
                (on ? 'border-purple-600 bg-purple-50 text-purple-800' : 'border-gray-200 bg-white text-gray-600');
        });
    });
})();
</script>
@endpush
