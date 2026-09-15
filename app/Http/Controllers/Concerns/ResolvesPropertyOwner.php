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
    protected function resolveOwnerFor(User $user)
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
        // whatever society the *user* account claims.
        if ((int) $owner->society_id !== (int) $this->getSocietyId()) {
            return back()->with('error', 'Owner must belong to the same society.');
        }

        return $owner;
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
