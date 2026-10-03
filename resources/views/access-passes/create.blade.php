@extends('layouts.app')

@section('title', 'New Access Pass')
@section('page-title', 'New Access Pass')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center mb-6">
        <a href="{{ route('access-passes.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">New Access Pass</h1>
            <p class="text-gray-600 mt-1">For a recurring helper or a one-time service visit. A QR is generated automatically - the guard scans it, nothing is typed at the gate.</p>
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

    <div class="max-w-2xl bg-white rounded-lg shadow-md p-6">
        <form action="{{ route('access-passes.store') }}" method="POST" class="space-y-6" id="pass-form">
            @csrf
            @include('partials.idempotency')

            @if($canCreateSocietyWide)
            {{--
                Society-wide staff (common-area cleaner, the society's own
                electrician) have no single property - Admin only, since a
                resident authorising their own household help has no
                business granting society-wide access.
            --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Access Scope</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="border rounded-lg p-3 cursor-pointer has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50">
                        <input type="radio" name="access_scope" value="property" class="sr-only" onchange="toggleScopeFields()" checked>
                        <div class="font-semibold text-gray-900 text-sm">Property-Specific</div>
                        <p class="text-xs text-gray-500 mt-1">A resident's own maid, driver, cook...</p>
                    </label>
                    <label class="border rounded-lg p-3 cursor-pointer has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50">
                        <input type="radio" name="access_scope" value="society" class="sr-only" onchange="toggleScopeFields()">
                        <div class="font-semibold text-gray-900 text-sm">Society-Wide</div>
                        <p class="text-xs text-gray-500 mt-1">Common-area staff, no single property.</p>
                    </label>
                </div>
            </div>
            @endif

            {{--
                Property: only asked at all when there is a real choice.
                A single-property resident's flat is preselected and hidden -
                the same "ask only what the system doesn't know" rule the
                rest of the app follows. Hidden entirely for a society-wide
                pass, which has no property at all.
            --}}
            <div id="property-field">
                <label class="block text-sm font-medium text-gray-700 mb-2">Property</label>
                @if($flats->count() === 1)
                    <input type="hidden" name="flat_id" value="{{ $flats->first()->id }}">
                    <div class="px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700">
                        {{ $flats->first()->property_type === 'villa'
                            ? 'Villa ' . $flats->first()->flat_number
                            : ($flats->first()->building->name ?? 'Building') . ' - ' . $flats->first()->flat_number }}
                    </div>
                @else
                    <select name="flat_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Property</option>
                        @foreach($flats as $flat)
                            <option value="{{ $flat->id }}" {{ old('flat_id') == $flat->id ? 'selected' : '' }}>
                                {{ $flat->property_type === 'villa'
                                    ? 'Villa ' . $flat->flat_number
                                    : ($flat->building->name ?? 'Building') . ' - ' . $flat->flat_number }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pass Type</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="border rounded-lg p-4 cursor-pointer has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50">
                        <input type="radio" name="pass_type" value="recurring_staff" class="sr-only" onchange="togglePassFields()" {{ old('pass_type', 'recurring_staff') === 'recurring_staff' ? 'checked' : '' }}>
                        <div class="font-semibold text-gray-900"><i class="fas fa-user-clock mr-1"></i> Recurring Staff</div>
                        <p class="text-xs text-gray-500 mt-1">Cleaner, cook, driver, maid... same days every week.</p>
                    </label>
                    <label class="border rounded-lg p-4 cursor-pointer has-[:checked]:border-purple-600 has-[:checked]:bg-purple-50">
                        <input type="radio" name="pass_type" value="temporary_service" class="sr-only" onchange="togglePassFields()" {{ old('pass_type') === 'temporary_service' ? 'checked' : '' }}>
                        <div class="font-semibold text-gray-900"><i class="fas fa-wrench mr-1"></i> Temporary Service</div>
                        <p class="text-xs text-gray-500 mt-1">Electrician, plumber... one visit, one window.</p>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" name="person_name" required value="{{ old('person_name') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="e.g., Ramesh Kumar">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <input type="text" name="category" value="{{ old('category') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Cleaner, Electrician, ...">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone (optional)</label>
                <input type="text" name="person_phone" value="{{ old('person_phone') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Recurring-only fields --}}
            <div id="recurring-fields" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Allowed Days</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $i => $day)
                            <label class="px-3 py-1.5 border rounded-lg cursor-pointer text-sm has-[:checked]:bg-purple-600 has-[:checked]:text-white has-[:checked]:border-purple-600">
                                <input type="checkbox" name="allowed_days[]" value="{{ $i }}" class="sr-only" {{ in_array($i, [1,2,3,4,5,6]) ? 'checked' : '' }}>
                                {{ $day }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until (optional - leave blank for indefinite)</label>
                    <input type="date" name="valid_until" value="{{ old('valid_until') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            {{-- Temporary-only fields --}}
            <div id="temporary-fields" class="space-y-4 hidden">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input type="date" name="service_date" value="{{ old('service_date', now()->toDateString()) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time (optional)</label>
                    <input type="time" name="start_time" value="{{ old('start_time') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Time (optional)</label>
                    <input type="time" name="end_time" value="{{ old('end_time') }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
                <a href="{{ route('access-passes.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-purple-700 hover:bg-purple-800 text-white rounded-lg font-medium">
                    <i class="fas fa-qrcode mr-2"></i>Create Pass &amp; Generate QR
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePassFields() {
    const type = document.querySelector('input[name="pass_type"]:checked')?.value;
    document.getElementById('recurring-fields').classList.toggle('hidden', type !== 'recurring_staff');
    document.getElementById('temporary-fields').classList.toggle('hidden', type !== 'temporary_service');
}
function toggleScopeFields() {
    const scope = document.querySelector('input[name="access_scope"]:checked')?.value;
    document.getElementById('property-field').classList.toggle('hidden', scope === 'society');
}
document.addEventListener('DOMContentLoaded', function () {
    togglePassFields();
    toggleScopeFields();
});
</script>
@endsection
