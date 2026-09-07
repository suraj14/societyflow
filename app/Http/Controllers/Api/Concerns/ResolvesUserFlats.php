<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\User;

/**
 * Resolves every flat/villa a user is attached to, for API queries.
 *
 * Previously bills/dashboard only looked at the active resident record,
 * so owners (especially villa owners, who have no resident row) saw
 * empty bills and a zero dashboard. Ownership resolution mirrors the
 * logic already proven in ProfileApiController: primary link is
 * flats.owner_id -> owners.id; fallback matches the owner's recorded
 * villa_area_id/villa_no or building_id/flat_no when flats.owner_id
 * was never populated. Everything stays scoped to the user's society.
 */
trait ResolvesUserFlats
{
    /**
     * @return array<int> distinct flat IDs (residency + ownership)
     */
    protected function userFlatIds(User $user): array
    {
        $ids = $user->residents()
            ->where('status', 'active')
            ->pluck('flat_id')
            ->filter();

        if ($user->owner_id) {
            $owned = Flat::where('society_id', $user->society_id)
                ->where('owner_id', $user->owner_id)
                ->pluck('id');

            $ids = $ids->merge($owned);

            if ($owned->isEmpty()) {
                $owner = Owner::find($user->owner_id);

                if ($owner && $owner->society_id === $user->society_id) {
                    if ($owner->property_type === 'villa' && $owner->villa_area_id && $owner->villa_no) {
                        $ids = $ids->merge(
                            Flat::where('society_id', $user->society_id)
                                ->where('villa_area_id', $owner->villa_area_id)
                                ->where('flat_number', $owner->villa_no)
                                ->pluck('id')
                        );
                    } elseif ($owner->property_type === 'apartment' && $owner->building_id && $owner->flat_no) {
                        $ids = $ids->merge(
                            Flat::where('society_id', $user->society_id)
                                ->where('building_id', $owner->building_id)
                                ->where('flat_number', $owner->flat_no)
                                ->pluck('id')
                        );
                    }
                }
            }
        }

        return $ids->unique()->values()->map(fn ($id) => (int) $id)->all();
    }
}
