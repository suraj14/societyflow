<?php

namespace App\Support;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * The one definition of a property's occupancy.
 *
 *   active tenant                      -> Tenant Occupied (on rent)
 *   no active tenant, active owner     -> Owner Occupied
 *   neither                            -> Vacant
 *
 * Derived from the actual tenant and owner records, never from the stored
 * `flats.status` column. That column is written by a dozen different flows
 * with different vocabularies (owner assignment writes "occupied", tenancy
 * writes "on_rent", the resident updater can overwrite either), so screens
 * that read it disagreed with screens that read the tenant/owner records -
 * Villa 158 and 160 showed "Owner Occupied" in Property & Occupancy and
 * "Vacant" on the Villas page. Every screen, counter and API formatter that
 * needs occupancy must call this class instead of deciding for itself.
 *
 * - Active tenant: tenants.status = 'active' on that flat.
 * - Owner: flats.owner_id when set, else the owner record whose address
 *   (villa area + villa no, or building + flat no) matches - the same
 *   two-tier resolution Flat::resolvedOwner() documents, done here for many
 *   flats in two queries instead of one per flat. An inactive owner does
 *   not make a property owner-occupied.
 */
class PropertyOccupancy
{
    public const TENANT = 'tenant_occupied';
    public const OWNER  = 'owner_occupied';
    public const VACANT = 'vacant';

    public const STATES = [self::TENANT, self::OWNER, self::VACANT];

    /**
     * Owner for each flat: [flat id => Owner|null].
     *
     * @param  Collection<int,Flat>  $flats
     * @return array<int,?Owner>
     */
    public static function ownersFor(Collection $flats): array
    {
        if ($flats->isEmpty()) {
            return [];
        }

        $owners = Owner::whereIn('society_id', $flats->pluck('society_id')->unique())
            ->get(['id', 'society_id', 'name', 'status', 'property_type', 'villa_area_id', 'villa_no', 'building_id', 'flat_no']);

        $byId = $owners->keyBy('id');
        $byAddress = [];
        foreach ($owners as $owner) {
            if ($owner->property_type === 'villa' && $owner->villa_area_id && $owner->villa_no !== null) {
                $byAddress['v|' . $owner->society_id . '|' . $owner->villa_area_id . '|' . $owner->villa_no] ??= $owner;
            } elseif ($owner->property_type === 'apartment' && $owner->building_id && $owner->flat_no !== null) {
                $byAddress['a|' . $owner->society_id . '|' . $owner->building_id . '|' . $owner->flat_no] ??= $owner;
            }
        }

        $result = [];
        foreach ($flats as $flat) {
            if ($flat->owner_id) {
                $owner = $byId->get($flat->owner_id);
            } elseif ($flat->property_type === 'villa' && $flat->villa_area_id) {
                $owner = $byAddress['v|' . $flat->society_id . '|' . $flat->villa_area_id . '|' . $flat->flat_number] ?? null;
            } elseif ($flat->property_type === 'apartment' && $flat->building_id) {
                $owner = $byAddress['a|' . $flat->society_id . '|' . $flat->building_id . '|' . $flat->flat_number] ?? null;
            } else {
                $owner = null;
            }

            $result[$flat->id] = $owner;
        }

        return $result;
    }

    /**
     * Occupancy for many flats in two queries.
     *
     * @param  Collection<int,Flat>  $flats
     * @return array<int,array{state:string,owner:?Owner,tenant:?Tenant}> keyed by flat id
     */
    public static function statesFor(Collection $flats): array
    {
        if ($flats->isEmpty()) {
            return [];
        }

        // An unsaved flat has no id and cannot have tenants yet.
        $savedIds = $flats->pluck('id')->filter()->values();

        $tenants = $savedIds->isEmpty()
            ? collect()
            : Tenant::whereIn('flat_id', $savedIds)
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->groupBy('flat_id');

        $owners = self::ownersFor($flats);

        $result = [];
        foreach ($flats as $flat) {
            $tenant = $flat->id ? $tenants->get($flat->id)?->first() : null;
            $owner  = $owners[$flat->id] ?? null;
            $activeOwner = $owner && ($owner->status ?? 'active') === 'active';

            $result[$flat->id] = [
                'state'  => $tenant ? self::TENANT : ($activeOwner ? self::OWNER : self::VACANT),
                'owner'  => $owner,
                'tenant' => $tenant,
            ];
        }

        return $result;
    }

    /** @return array{state:string,owner:?Owner,tenant:?Tenant} */
    public static function stateFor(Flat $flat): array
    {
        return self::statesFor(collect([$flat]))[$flat->id];
    }

    /** Just the state string for one flat. */
    public static function stateOf(Flat $flat): string
    {
        return self::stateFor($flat)['state'];
    }

    /**
     * Society-wide (or all-society, when $societyId is null) counts, from
     * the same per-flat rule. "occupied" is owner + tenant occupied, so the
     * three non-overlapping figures always add up to total.
     *
     * @return array{total:int,owner_occupied:int,tenant_occupied:int,occupied:int,vacant:int,villas:int,apartments:int}
     */
    public static function counts(?int $societyId): array
    {
        $flats = Flat::query()
            ->when($societyId, fn ($q) => $q->where('society_id', $societyId))
            ->get(['id', 'society_id', 'property_type', 'villa_area_id', 'building_id', 'flat_number', 'owner_id']);

        $states = self::statesFor($flats);

        $counts = [
            'total'           => $flats->count(),
            'owner_occupied'  => 0,
            'tenant_occupied' => 0,
            'occupied'        => 0,
            'vacant'          => 0,
            'villas'          => $flats->where('property_type', 'villa')->count(),
            'apartments'      => $flats->where('property_type', 'apartment')->count(),
        ];

        foreach ($states as $row) {
            match ($row['state']) {
                self::TENANT => $counts['tenant_occupied']++,
                self::OWNER  => $counts['owner_occupied']++,
                default      => $counts['vacant']++,
            };
        }

        $counts['occupied'] = $counts['owner_occupied'] + $counts['tenant_occupied'];

        return $counts;
    }

    /** Ids of the flats currently in $state (for list filters). */
    public static function flatIdsInState(?int $societyId, string $state, ?string $propertyType = null): array
    {
        $flats = Flat::query()
            ->when($societyId, fn ($q) => $q->where('society_id', $societyId))
            ->when($propertyType, fn ($q) => $q->where('property_type', $propertyType))
            ->get(['id', 'society_id', 'property_type', 'villa_area_id', 'building_id', 'flat_number', 'owner_id']);

        return collect(self::statesFor($flats))
            ->filter(fn ($row) => $row['state'] === $state)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function label(string $state): string
    {
        return match ($state) {
            self::TENANT => 'Tenant Occupied',
            self::OWNER  => 'Owner Occupied',
            default      => 'Vacant',
        };
    }

    public static function badgeClass(string $state): string
    {
        return match ($state) {
            self::TENANT => 'bg-blue-100 text-blue-800',
            self::OWNER  => 'bg-green-100 text-green-800',
            default      => 'bg-gray-100 text-gray-600',
        };
    }
}
