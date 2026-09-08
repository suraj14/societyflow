<?php

namespace App\Support;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;

/**
 * Resolves every flat/villa a user is attached to.
 *
 * A person can be attached to a unit three different ways in this schema:
 *
 *   residents.user_id  -> residents.flat_id      (the legacy model)
 *   users.owner_id     -> flats.owner_id         (ownership)
 *   users.tenant_id    -> tenants.flat_id        (tenancy)
 *
 * Callers previously implemented their own subset of that, each slightly
 * differently, and none of them covered tenancy - so a tenant-only account
 * saw an empty bill list and a zero dashboard on mobile. Collecting the
 * rules here means every caller resolves the same set.
 *
 * The ownership fallback exists because flats.owner_id is unpopulated for
 * the large majority of rows in real data (106 of 119 in the development
 * database), so ownership frequently has to be matched through the
 * denormalised building_id/flat_no and villa_area_id/villa_no fields the
 * owners table carries. That fallback is a workaround for unlinked data,
 * not the intended model: backfilling flats.owner_id is what actually
 * fixes it. See `php artisan owners:backfill-flat-links`.
 *
 * Every branch stays scoped to the user's own society.
 */
class UserFlatResolver
{
    /**
     * Distinct flat IDs the user resides in, owns, or rents.
     *
     * @return array<int,int>
     */
    public static function flatIdsFor(User $user): array
    {
        $ids = collect();

        // 1. Residency (legacy residents table).
        $ids = $ids->merge(
            $user->residents()->where('status', 'active')->pluck('flat_id')->filter()
        );

        // 2. Tenancy. Matched by users.tenant_id first, then by any tenant
        //    row pointing back at this user, since both links exist in data.
        $tenantFlatIds = Tenant::query()
            ->where('society_id', $user->society_id)
            ->where('status', 'active')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if ($user->tenant_id) {
                    $q->orWhere('id', $user->tenant_id);
                }
            })
            ->pluck('flat_id')
            ->filter();

        $ids = $ids->merge($tenantFlatIds);

        // 3. Ownership.
        $ids = $ids->merge(self::ownedFlatIds($user));

        return $ids->unique()->values()->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Flat IDs the user owns.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    public static function ownedFlatIds(User $user): \Illuminate\Support\Collection
    {
        if (!$user->owner_id) {
            return collect();
        }

        // Primary link: flats.owner_id -> owners.id.
        $owned = Flat::where('society_id', $user->society_id)
            ->where('owner_id', $user->owner_id)
            ->pluck('id');

        if ($owned->isNotEmpty()) {
            return $owned;
        }

        // Fallback for units whose owner_id was never populated: match the
        // address the owner record itself carries.
        $owner = Owner::find($user->owner_id);

        if (!$owner || (int) $owner->society_id !== (int) $user->society_id) {
            return collect();
        }

        if ($owner->property_type === 'villa' && $owner->villa_area_id && $owner->villa_no) {
            return Flat::where('society_id', $user->society_id)
                ->where('villa_area_id', $owner->villa_area_id)
                ->where('flat_number', $owner->villa_no)
                ->pluck('id');
        }

        if ($owner->property_type === 'apartment' && $owner->building_id && $owner->flat_no) {
            return Flat::where('society_id', $user->society_id)
                ->where('building_id', $owner->building_id)
                ->where('flat_number', $owner->flat_no)
                ->pluck('id');
        }

        return collect();
    }

    /** Whether $flatId is one of the user's units. */
    public static function userHasFlat(User $user, ?int $flatId): bool
    {
        if ($flatId === null) {
            return false;
        }

        return in_array((int) $flatId, self::flatIdsFor($user), true);
    }
}
