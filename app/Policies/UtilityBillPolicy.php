<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UtilityBill;
use App\Support\UserFlatResolver;

/**
 * UtilityBillController calls $this->authorize('view'|'update'|'delete', $bill),
 * but no policy was ever registered and no matching Gate ability was defined.
 * With no policy and no ability, Gate denies - so every show/edit/update/destroy
 * in the module returned 403 for every role, including Super Admin. The whole
 * feature was unreachable.
 *
 * Residents may read bills raised against a unit they reside in, own or rent.
 * Only society management raises, edits or deletes them.
 */
class UtilityBillPolicy
{
    /**
     * Super Admin is global.
     *
     * Returning null (not false) for everyone else lets the individual
     * methods decide, which is what `before` is for.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('Super Admin') ? true : null;
    }

    /** The index action scopes the listing itself. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UtilityBill $bill): bool
    {
        if (!$this->sameSociety($user, $bill)) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        return UserFlatResolver::userHasFlat($user, $bill->flat_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    public function update(User $user, UtilityBill $bill): bool
    {
        return $this->sameSociety($user, $bill) && $user->hasRole('Admin');
    }

    public function delete(User $user, UtilityBill $bill): bool
    {
        return $this->sameSociety($user, $bill) && $user->hasRole('Admin');
    }

    /**
     * Record a payment against a bill.
     *
     * Management always may; a resident may pay a bill for their own unit.
     */
    public function addPayment(User $user, UtilityBill $bill): bool
    {
        if (!$this->sameSociety($user, $bill)) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        return UserFlatResolver::userHasFlat($user, $bill->flat_id);
    }

    private function sameSociety(User $user, UtilityBill $bill): bool
    {
        return $user->society_id !== null
            && (int) $user->society_id === (int) $bill->society_id;
    }
}
