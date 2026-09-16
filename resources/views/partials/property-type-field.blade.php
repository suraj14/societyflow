{{--
    A complete Property Type field: label, select-or-fixed-value, hint, error.

    The one reusable place this decision is made, so no view hardcodes its
    own hide/show logic. For a society with exactly one supported type there
    is no real choice to offer - the select is hidden (its value is still
    submitted; only the display changes) and a plain read-only line takes
    its place. A mixed society keeps the ordinary required dropdown.

    This variant is for forms where the society is fixed for the life of the
    request - the ordinary case. It does NOT cover a Super Admin who can
    switch which society the form applies to live (owners/create.blade.php
    has that selector and keeps its own dropdown-only logic deliberately,
    since the value is not fixed under that role).

    Params:
      $sfLabel     (optional) the visible label text, e.g. "Visiting Property".
                   Defaults to "Property Type".
      $sfFieldName (optional) the form field name/id. Defaults to "property_type".
      $sfSelected  (optional) pre-selected value for the dropdown branch.
                   Defaults to old($sfFieldName).
      $sfOnChange  (optional) JS to run on the select's onchange, e.g. a
                   function call that loads the matching unit list. Also
                   invoked once via the field's own data attribute so a
                   caller can trigger it on page load for the fixed-value
                   case, where the user never touches a dropdown to fire it.
--}}
@php
    $sfPtSociety   = auth()->user()?->society;
    $sfPtFieldName = $sfFieldName ?? 'property_type';
    $sfPtLabelText = $sfLabel ?? 'Property Type';
    $sfPtSingle    = $sfPtSociety !== null && !$sfPtSociety->isMixedProperty();
    $sfPtType      = $sfPtSingle ? $sfPtSociety->propertyType()->allows()[0] : null;
@endphp
<div>
    <label for="{{ $sfPtFieldName }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ $sfPtLabelText }} @unless($sfPtSingle)<span class="text-red-500">*</span>@endunless
    </label>

    <select name="{{ $sfPtFieldName }}" id="{{ $sfPtFieldName }}"
            @unless($sfPtSingle) required @endunless
            @if(isset($sfOnChange)) onchange="{{ $sfOnChange }}" @endif
            data-property-type-autoload="{{ isset($sfOnChange) ? 'true' : 'false' }}"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error($sfPtFieldName) border-red-500 @enderror {{ $sfPtSingle ? 'hidden' : '' }}">
        <option value="">Select {{ $sfPtLabelText }}</option>
        @include('partials.property-type-options', ['sfSelected' => $sfPtSingle ? $sfPtType->value : ($sfSelected ?? null)])
    </select>

    @if($sfPtSingle)
        <p class="px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700 flex items-center gap-2">
            <i class="fas fa-{{ $sfPtType->value === 'villa' ? 'house-user' : 'building' }} text-gray-400"></i>
            {{ $sfPtLabelText }}: {{ $sfPtType->label() }}
        </p>
    @endif

    @error($sfPtFieldName)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

@if(isset($sfOnChange))
<script>
    // A single-type society never fires the select's onchange - nothing
    // ever changes it - so whatever loads the matching unit list has to be
    // triggered once on load instead, exactly as if the user had just
    // picked the only option there is.
    document.addEventListener('DOMContentLoaded', function () {
        var sfField = document.getElementById('{{ $sfPtFieldName }}');
        if (sfField && sfField.value) {
            {{ $sfOnChange }};
        }
    });
</script>
@endif
