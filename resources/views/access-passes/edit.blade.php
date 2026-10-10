@extends('layouts.app')

@section('title', 'Edit Access Pass')
@section('page-title', 'Edit Access Pass')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('access-passes.show', $pass) }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Pass</h1>
            <p class="text-gray-600 mt-1">
                The QR code stays the same - the gate reads this pass fresh on every scan, so changes here apply at the next scan with nothing to reprint.
            </p>
        </div>
    </div>

    @if($errors->any())
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($pass->isExpired())
        <div class="bg-amber-50 border-l-4 border-amber-500 text-amber-800 p-4 mb-6 rounded-lg text-sm">
            <i class="fas fa-clock mr-1"></i>
            This pass expired on {{ $pass->valid_until->format('d M Y') }}. Setting a new "Valid Until" date below brings it back into use.
        </div>
    @endif

    <div class="max-w-2xl bg-white rounded-lg shadow-md p-6">
        <form action="{{ route('access-passes.update', $pass) }}" method="POST" class="space-y-6" id="pass-edit-form">
            @csrf
            @method('PUT')
            @include('partials.idempotency')

            @if($canCreateSocietyWide)
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Access Scope</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="border rounded-lg p-3 cursor-pointer has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50">
                        <input type="radio" name="access_scope" value="property" class="sr-only" onchange="toggleScopeFields()"
                               {{ old('access_scope', $pass->access_scope) === 'property' ? 'checked' : '' }}>
                        <div class="font-semibold text-gray-900 text-sm">Property-Specific</div>
                        <p class="text-xs text-gray-500 mt-1">A resident's own maid, driver, cook...</p>
                    </label>
                    <label class="border rounded-lg p-3 cursor-pointer has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50">
                        <input type="radio" name="access_scope" value="society" class="sr-only" onchange="toggleScopeFields()"
                               {{ old('access_scope', $pass->access_scope) === 'society' ? 'checked' : '' }}>
                        <div class="font-semibold text-gray-900 text-sm">Society-Wide</div>
                        <p class="text-xs text-gray-500 mt-1">Common-area staff, no single property.</p>
                    </label>
                </div>
            </div>
            @endif

            <div id="property-field">
                <label class="block text-sm font-medium text-gray-700 mb-2">Property</label>
                @if($flats->count() === 1 && (int) $flats->first()->id === (int) $pass->flat_id)
                    <input type="hidden" name="flat_id" value="{{ $flats->first()->id }}">
                    <div class="px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                        {{ $flats->first()->property_type === 'villa'
                            ? 'Villa ' . $flats->first()->flat_number
                            : ($flats->first()->building->name ?? 'Building') . ' - ' . $flats->first()->flat_number }}
                    </div>
                @else
                    <select name="flat_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Property</option>
                        @foreach($flats as $flat)
                            <option value="{{ $flat->id }}" {{ (int) old('flat_id', $pass->flat_id) === (int) $flat->id ? 'selected' : '' }}>
                                {{ $flat->property_type === 'villa'
                                    ? 'Villa ' . $flat->flat_number
                                    : ($flat->building->name ?? 'Building') . ' - ' . $flat->flat_number }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="person_name" required value="{{ old('person_name', $pass->person_name) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <input type="text" name="category" value="{{ old('category', $pass->category) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Cleaner, Electrician, ...">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone (optional)</label>
                <input type="text" name="person_phone" value="{{ old('person_phone', $pass->person_phone) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Allowed days only mean anything for a recurring pass. --}}
            @if($pass->pass_type === 'recurring_staff')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Allowed Days</label>
                <div class="flex flex-wrap gap-2">
                    @php $days = old('allowed_days', $pass->allowed_days ?? []); @endphp
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $day)
                        <label class="px-3 py-1.5 border rounded-lg cursor-pointer text-sm has-[:checked]:bg-purple-600 has-[:checked]:text-white has-[:checked]:border-purple-600">
                            <input type="checkbox" name="allowed_days[]" value="{{ $i }}" class="sr-only"
                                   {{ in_array((string) $i, array_map('strval', (array) $days), true) ? 'checked' : '' }}>
                            {{ $day }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-2">Leave all unticked to allow every day.</p>
            </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valid From</label>
                    <input type="date" name="valid_from" required
                           value="{{ old('valid_from', $pass->valid_from?->toDateString()) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until</label>
                    <input type="date" name="valid_until"
                           value="{{ old('valid_until', $pass->valid_until?->toDateString()) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Leave blank for no end date.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Time</label>
                    <input type="time" name="start_time"
                           value="{{ old('start_time', $pass->start_time ? \Illuminate\Support\Carbon::parse($pass->start_time)->format('H:i') : '') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Time</label>
                    <input type="time" name="end_time"
                           value="{{ old('end_time', $pass->end_time ? \Illuminate\Support\Carbon::parse($pass->end_time)->format('H:i') : '') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Leave both blank to allow any time.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-semibold">
                    <i class="fas fa-save mr-1"></i> Save Changes
                </button>
                <a href="{{ route('access-passes.show', $pass) }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // Society-wide passes have no property at all - hide the field rather
    // than leaving a required-looking selector the server will ignore.
    function toggleScopeFields() {
        var scope = document.querySelector('input[name="access_scope"]:checked');
        var field = document.getElementById('property-field');
        if (!scope || !field) return;
        field.style.display = scope.value === 'society' ? 'none' : '';
    }
    toggleScopeFields();
</script>
@endsection
