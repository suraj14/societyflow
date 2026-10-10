<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Owner;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Turning a login account into the Owner record a property belongs to.
 *
 * `flats.owner_id` is a foreign key to `owners.id`, never `users.id`. The two
 * tables are both integer-keyed from 1, so writing a user id there satisfies
 * the constraint and silently attributes the property to a different person -
 * it fails quietly or not at all, which is why it survived so long.
 *
 * Every assignment path resolves the owner through here instead.
 */
trait ResolvesPropertyOwner
{
    /**
     * The Owner record behind a user account.
     *
     * Returns an Owner on success, or a redirect response explaining why not,
     * so callers can hand it straight back to the browser:
     *
     *     $owner = $this->resolveOwnerFor($user);
     *     if (!$owner instanceof Owner) { return $owner; }
     *
     * @return Owner|\Illuminate\Http\RedirectResponse
     */
    protected function resolveOwnerFor(User $user, ?int $societyId = null)
    {
        if (!$user->owner_id) {
            return back()->with(
                'error',
                "{$user->name} has no owner record yet. Create the owner first, then assign a property."
            );
        }

        $owner = Owner::find($user->owner_id);

        if (!$owner) {
            return back()->with(
                'error',
                "{$user->name} is linked to an owner record that no longer exists."
            );
        }

        // A property may only be attributed to an owner of the same society,
        // whatever society the *user* account claims. $societyId is the
        // property's own society; omitted, it is the acting user's. (A Super
        // Admin has no society of their own, so their callers must pass it -
        // otherwise every assignment was refused.)
        if ((int) $owner->society_id !== (int) ($societyId ?? $this->getSocietyId())) {
            return back()->with('error', 'Owner must belong to the same society.');
        }

        return $owner;
    }

    /**
     * Mark each unit with the owner the rest of the app already resolves for it
     * (relation `declaredOwner`), even when flats.owner_id was never filled in.
     *
     * Owner Management and Property & Occupancy decide ownership through
     * PropertyOccupancy (owner_id first, then the address on the owner record).
     * The assignment screens only looked at owner_id, so a villa that the owner
     * record plainly claims was listed as "Available" - and could be handed to
     * somebody else. Display and enforcement now read the same rule. Nothing is
     * written: linking owner_id remains an explicit admin action.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\Flat>  $units
     */
    protected function annotateDeclaredOwners($units)
    {
        $owners = \App\Support\PropertyOccupancy::ownersFor($units);

        foreach ($units as $unit) {
            $unit->setRelation('declaredOwner', $owners[$unit->id] ?? null);
        }

        return $units;
    }

    /**
     * Refuse to give a unit to $owner when the canonical rule says it belongs
     * to a different owner. Returns a redirect to hand back, or null to go on.
     */
    protected function refuseIfDeclaredToAnother(\App\Models\Flat $unit, Owner $owner, string $noun): ?\Illuminate\Http\RedirectResponse
    {
        $declared = \App\Support\PropertyOccupancy::ownersFor(collect([$unit]))[$unit->id] ?? null;

        if ($declared && (int) $declared->id !== (int) $owner->id) {
            return back()->with(
                'error',
                "This {$noun} is recorded as owned by {$declared->name}. Correct the owner records first; it was not assigned."
            );
        }

        return null;
    }

    /**
     * An `exists` rule confined to one society.
     *
     * `exists:buildings,id` proves a building exists, not that it is *yours*.
     * Paired with a society_id taken from the authenticated user, that let a
     * posted foreign id produce a row stamped with one society but parented
     * to another - no error, just a quietly corrupt record.
     */
    protected function existsInSociety(string $table, ?int $societyId): Exists
    {
        return Rule::exists($table, 'id')->where(function ($query) use ($societyId) {
            $query->where('society_id', $societyId);
        });
    }
}
