<?php

namespace App\Support;

use App\Models\Flat;
use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which properties a user may register, see and approve visitors for.
 *
 * Society-wide roles (Admin, Staff, Guard, Super Admin) act on any property
 * in their own society. Everyone else - tenants and owners - acts only on
 * the properties they occupy, resolved server-side from the tenancy and
 * ownership records. A browser-submitted flat_id is only ever checked
 * against this, never trusted on its own.
 *
 * A tenant is scoped to their active tenancy. Stale legacy `residents` rows
 * pointing a tenant at another unit exist in real data, so for a tenant who
 * is not also an owner the tenancy record is the authority. Owners keep
 * their owned units even while a unit is rented out - renting does not
 * transfer ownership.
 */
class VisitorPropertyScope
{
    public const SOCIETY_WIDE_ROLES = ['Super Admin', 'Admin', 'Staff', 'Guard'];

    public static function isSocietyWide(User $user): bool
    {
        return $user->hasRole(self::SOCIETY_WIDE_ROLES);
    }

    /**
     * Flat IDs a resident (non-society-wide user) manages visitors for.
     *
     * @return array<int,int>
     */
    public static function residentFlatIds(User $user): array
    {
        $tenancy = UserFlatResolver::tenantFlatIds($user);

        if ($tenancy !== [] && !$user->owner_id) {
            return $tenancy;
        }

        return UserFlatResolver::flatIdsFor($user);
    }

    /** Whether the user may register or approve visitors for this property. */
    public static function canManageFlat(User $user, Flat $flat): bool
    {
        if (!$user->hasRole('Super Admin') && (int) $flat->society_id !== (int) $user->society_id) {
            return false;
        }

        if (self::isSocietyWide($user)) {
            return true;
        }

        return in_array((int) $flat->id, self::residentFlatIds($user), true);
    }

    /** Whether the user may approve/deny this visitor. */
    public static function canManageVisitor(User $user, Visitor $visitor): bool
    {
        if (!$user->hasRole('Super Admin') && (int) $visitor->society_id !== (int) $user->society_id) {
            return false;
        }

        if (self::isSocietyWide($user)) {
            return true;
        }

        return in_array((int) $visitor->flat_id, self::residentFlatIds($user), true);
    }

    /**
     * Whether the user may view this visitor: anyone who can manage it, plus
     * the resident who originally registered it (their own history).
     */
    public static function canViewVisitor(User $user, Visitor $visitor): bool
    {
        if (self::canManageVisitor($user, $visitor)) {
            return true;
        }

        return (int) $visitor->society_id === (int) $user->society_id
            && (int) $visitor->host_user_id === (int) $user->id;
    }

    /** Restrict a Visitor query to what the user may see. */
    public static function scopeQuery(Builder $query, User $user): Builder
    {
        if (!$user->hasRole('Super Admin')) {
            $query->where('society_id', $user->society_id);
        }

        if (self::isSocietyWide($user)) {
            return $query;
        }

        $flatIds = self::residentFlatIds($user);

        // Grouped so the OR cannot escape the society scope above.
        return $query->where(function ($q) use ($user, $flatIds) {
            $q->where('host_user_id', $user->id);
            if ($flatIds !== []) {
                $q->orWhereIn('flat_id', $flatIds);
            }
        });
    }

    /** Properties the user may pick as a visitor's destination. */
    public static function selectableFlats(User $user): Collection
    {
        $query = Flat::with(['building:id,name', 'villaArea:id,name']);

        if (self::isSocietyWide($user)) {
            if (!$user->hasRole('Super Admin') || $user->society_id) {
                $query->where('society_id', $user->society_id);
            }
        } else {
            $query->where('society_id', $user->society_id)
                ->whereIn('id', self::residentFlatIds($user));
        }

        return $query->get()
            ->sortBy(fn (Flat $f) => ($f->property_type === 'villa' ? '1' : '0' . ($f->building?->name ?? ''))
                . '|' . str_pad((string) $f->flat_number, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * Login accounts that should decide on a visitor at the gate: the active
     * tenant's account if the property is rented, otherwise the owner's.
     */
    public static function occupantUsers(Flat $flat): Collection
    {
        $tenants = Tenant::where('flat_id', $flat->id)->where('status', 'active')->get(['id', 'user_id']);

        if ($tenants->isNotEmpty()) {
            $users = User::where('society_id', $flat->society_id)
                ->where(function ($q) use ($tenants) {
                    $q->whereIn('id', $tenants->pluck('user_id')->filter())
                        ->orWhereIn('tenant_id', $tenants->pluck('id'));
                })
                ->get();

            if ($users->isNotEmpty()) {
                return $users;
            }
        }

        $owner = $flat->resolvedOwner();

        return $owner
            ? User::where('society_id', $flat->society_id)->where('owner_id', $owner->id)->get()
            : collect();
    }

    /**
     * "Villa 159, Suman Sadan" / "Tower A - Flat 101", plus the villa's own
     * name if it has one. The area is the part a resident recognises.
     */
    public static function unitLabel(Flat $flat): string
    {
        $label = self::baseLabel($flat);

        if ($flat->property_type === 'villa' && $flat->villaArea?->name) {
            $label .= ', ' . $flat->villaArea->name;
        }

        return $label . ($flat->villa_name ? ' — ' . $flat->villa_name : '');
    }

    private static function baseLabel(Flat $flat): string
    {
        if ($flat->property_type === 'villa') {
            return 'Villa ' . $flat->flat_number;
        }

        $building = $flat->building?->name;

        return ($building ? $building . ' - ' : '') . 'Flat ' . $flat->flat_number;
    }

    /**
     * id => "Villa 159 — Lokesh Suman" for a destination dropdown.
     *
     * Owners are resolved the same two-tier way as Flat::resolvedOwner()
     * (flats.owner_id first, then the address the owner record carries), but
     * from one query for the whole list instead of one per flat.
     *
     * @return array<int,string>
     */
    public static function labelledOptions(Collection $flats): array
    {
        if ($flats->isEmpty()) {
            return [];
        }

        // Owner resolution lives in one place (PropertyOccupancy).
        $owners = PropertyOccupancy::ownersFor($flats);

        $options = [];
        foreach ($flats as $flat) {
            $owner = $owners[$flat->id] ?? null;

            $options[$flat->id] = self::baseLabel($flat)
                . ($flat->villa_name ? ' (' . $flat->villa_name . ')' : '')
                . ' — ' . ($owner?->name ?? 'Not Assigned');
        }

        return $options;
    }
}
