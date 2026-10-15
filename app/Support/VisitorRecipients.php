<?php

namespace App\Support;

use App\Models\Flat;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Collection;

/**
 * Who hears about a visitor at the gate, and who may decide.
 *
 * The people of a unit are found through the property links, never a name or an arbitrary user id:
 *   - the active tenant's account (and household accounts on the legacy residents table),
 *   - the owner's account - owners.id resolved with PropertyOccupancy::ownersFor() (flats.owner_id first, then the
 *     owner record's own address), then users.owner_id. An owner is told even while the unit is rented: renting does not
 *     transfer ownership, and VisitorPropertyScope lets them decide for it.
 *
 * Every candidate is kept only if their account is active, in the unit's society, holds a resident role AND passes
 * VisitorPropertyScope::canManageFlat() - the check the approve/reject endpoints use. "Notified" and "allowed to decide"
 * therefore cannot drift apart. Nobody is invented: a unit with no linked account returns an empty collection, and the
 * caller says so to the guard and the administrators.
 */
class VisitorRecipients
{
    public const RESIDENT_ROLES = ['Owner', 'Villa Owner', 'Apartment Owner', 'Tenant'];

    /** Accounts that may be told about, and may decide on, a visitor to this unit. */
    public static function residents(Flat $flat): Collection
    {
        $candidates = collect();

        // active tenant's accounts, else (and as well) the owner's: occupantUsers() applies the tenant-before-owner rule
        $candidates = $candidates->merge(VisitorPropertyScope::occupantUsers($flat));

        $owner = PropertyOccupancy::ownersFor(collect([$flat]))[$flat->id] ?? null;
        if ($owner) {
            $candidates = $candidates->merge(
                User::query()->where('society_id', $flat->society_id)->where('owner_id', $owner->id)->get()
            );
        }

        // household accounts on the legacy residents table
        $residentUserIds = $flat->residents()->where('status', 'active')->pluck('user_id')->filter()->all();
        if ($residentUserIds !== []) {
            $candidates = $candidates->merge(
                User::query()->where('society_id', $flat->society_id)->whereIn('id', $residentUserIds)->get()
            );
        }

        return $candidates
            ->unique('id')
            ->filter(fn (User $u) => $u->status === 'active'
                && (int) $u->society_id === (int) $flat->society_id
                && $u->hasAnyRole(self::RESIDENT_ROLES)
                && !VisitorPropertyScope::isSocietyWide($u)
                && VisitorPropertyScope::canManageFlat($u, $flat))
            ->values();
    }

    /** Administrators of the society (never Super Admin, who is not tied to one society). */
    public static function admins(int $societyId, ?int $exceptUserId = null): Collection
    {
        return User::query()
            ->where('society_id', $societyId)
            ->where('status', 'active')
            ->when($exceptUserId, fn ($q) => $q->where('id', '!=', $exceptUserId))
            ->whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
            ->get();
    }

    /**
     * Everything an event about this visitor needs: the residents of the unit, the administrators, the guard who
     * logged the visitor, and whether the unit has anyone linked at all.
     *
     * @return array{residents: Collection<int,User>, admins: Collection<int,User>, requester: ?User, linked: bool}
     */
    public static function forVisitor(Visitor $visitor): array
    {
        $flat = $visitor->flat ?? Flat::find($visitor->flat_id);
        // a flat from another society is never read for this visitor's society
        $residents = $flat && (int) $flat->society_id === (int) $visitor->society_id ? self::residents($flat) : collect();

        $requester = $visitor->requested_by
            ? User::query()->where('society_id', $visitor->society_id)->whereKey($visitor->requested_by)->first()
            : null;

        return [
            'residents' => $residents,
            'admins'    => self::admins((int) $visitor->society_id),
            'requester' => $requester,
            'linked'    => $residents->isNotEmpty(),
        ];
    }
}
