{{-- Shared metadata fields for upload / new version / edit. $doc may be null. --}}
@php
    $sfVal = fn ($field, $default = '') => old($field, $doc?->{$field} ?? $default);
    $sfInput = 'w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500';
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" value="{{ $sfVal('title') }}" required maxlength="150" placeholder="e.g. Parking Rules" class="{{ $sfInput }}">
        @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Category <span class="text-red-500">*</span></label>
        <select name="category" required class="{{ $sfInput }}" id="sf-doc-category">
            @foreach ($categories as $key => $label)
                <option value="{{ $key }}" @selected($sfVal('category', 'society_rules') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('category') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Version label <span class="text-red-500">*</span></label>
        <input type="text" name="version" value="{{ old('version', $versionDefault ?? ($doc?->version ?? 'v1')) }}" required maxlength="30" placeholder="v1" class="{{ $sfInput }}">
        @error('version') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Effective from</label>
        <input type="date" name="effective_from" value="{{ old('effective_from', $doc?->effective_from?->toDateString()) }}" class="{{ $sfInput }}">
        @error('effective_from') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Short description</label>
        <textarea name="description" rows="3" maxlength="2000" placeholder="What this document covers, in a line or two." class="{{ $sfInput }}">{{ $sfVal('description') }}</textarea>
        @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
        <input type="hidden" name="visible_to_guards" value="0">
        <input type="checkbox" name="visible_to_guards" value="1" id="sf-doc-guards" class="mt-0.5 rounded text-purple-600 focus:ring-purple-500"
               @checked(old('visible_to_guards', $doc?->visible_to_guards ?? false))>
        <span>
            <span class="block text-sm font-medium text-gray-900">Guards can read this</span>
            <span class="block text-xs text-gray-500">For security and visitor &amp; gate rules.</span>
        </span>
    </label>

    <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
        <input type="hidden" name="requires_acknowledgement" value="0">
        <input type="checkbox" name="requires_acknowledgement" value="1" class="mt-0.5 rounded text-purple-600 focus:ring-purple-500"
               @checked(old('requires_acknowledgement', $doc?->requires_acknowledgement ?? false))>
        <span>
            <span class="block text-sm font-medium text-gray-900">Ask residents to confirm "I have read this"</span>
            <span class="block text-xs text-gray-500">Only for important rules. You'll see who confirmed.</span>
        </span>
    </label>
</div>

@if (!$doc || ($isUpload ?? false))
    @push('scripts')
    <script>
        // New uploads in Security / Visitor & Gate default to guard-visible.
        (function () {
            var cat = document.getElementById('sf-doc-category'), g = document.getElementById('sf-doc-guards');
            if (!cat || !g) return;
            var guardCats = @json(\App\Models\SocietyDocument::GUARD_DEFAULT_CATEGORIES), touched = false;
            g.addEventListener('change', function () { touched = true; });
            cat.addEventListener('change', function () { if (!touched) g.checked = guardCats.indexOf(cat.value) !== -1; });
        })();
    </script>
    @endpush
@endif
