{{--
    Property-type <option> list for society-scoped forms.

    The society decides which types exist, so no form offers a type its
    society does not support. Backend validation enforces the same set via
    ResolvesSocietyPropertyType — this partial only keeps the UI honest.

    Super Admin has no society of their own and sees every type.

    Optional: $sfSelected (defaults to old('property_type')).
--}}
@php
    $sfPtSociety  = auth()->user()?->society;
    $sfPtTypes    = $sfPtSociety
        ? $sfPtSociety->propertyType()->allows()
        : \App\Enums\PropertyType::cases();
    $sfPtSelected = $sfSelected ?? old('property_type');
@endphp
@foreach($sfPtTypes as $sfPtType)
    <option value="{{ $sfPtType->value }}" {{ (string) $sfPtSelected === $sfPtType->value ? 'selected' : '' }}>{{ $sfPtType->label() }}</option>
@endforeach
