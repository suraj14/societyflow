<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PropertyType;
use App\Models\Society;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Society property-type enforcement for write endpoints.
 *
 * The society decides which property types exist. Every form and every
 * validator reads that decision from here, so the two cannot disagree and
 * a forged request cannot smuggle in a type the society does not support.
 *
 * Extracted from OwnerController once Rent and Tenant needed the same rules.
 */
trait ResolvesSocietyPropertyType
{
    /**
     * The society a write belongs to.
     *
     * Super Admin may name one; everyone else is pinned to their own,
     * whatever the request claims. Never read society_id straight from input.
     */
    protected function resolveSocietyIdForWrite(Request $request, $user): ?int
    {
        if ($user->hasRole('Super Admin')) {
            $requested = $request->input('society_id');

            return $requested !== null ? (int) $requested : null;
        }

        return $user->society_id !== null ? (int) $user->society_id : null;
    }

    /**
     * Validation rule limiting property_type to what the society supports.
     *
     * An unknown society falls back to the full set; the society_id rule
     * reports that problem on its own, and this should not mask it.
     *
     * @return array<int,mixed>
     */
    protected function propertyTypeRuleFor(?int $societyId, bool $required = false): array
    {
        $society = $societyId ? Society::find($societyId) : null;

        $allowed = $society
            ? $society->allowedPropertyTypeValues()
            : PropertyType::values();

        return [$required ? 'required' : 'nullable', Rule::in($allowed)];
    }

    /**
     * value => label for one society's supported types.
     *
     * @return array<string,string>
     */
    protected function propertyTypeOptionsForSociety(?Society $society): array
    {
        $options = [];

        if ($society === null) {
            foreach (PropertyType::cases() as $type) {
                $options[$type->value] = $type->label();
            }

            return $options;
        }

        foreach ($society->propertyType()->allows() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

    /**
     * value => label keyed by society id, so a Super Admin switching
     * societies gets the right choices without another round trip.
     *
     * @return array<int,array<string,string>>
     */
    protected function propertyTypeOptionsFor($user, $societies): array
    {
        $map = [];

        foreach ($societies as $society) {
            $map[$society->id] = $this->propertyTypeOptionsForSociety($society);
        }

        return $map;
    }
}
