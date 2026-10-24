<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The single place an authorized Admin/Super Admin sets a new password for
 * someone else's account - Owner, Tenant, Staff, Guard, Accountant, or any
 * other normal user. It only ever touches the password: no role, email,
 * society, or property/tenancy field is read or written here, and nothing
 * about it depends on occupancy (renting a property must never reach this
 * code path). Authorization and society-isolation checks belong to the
 * caller (OwnerController::resetPassword, UserController::resetPassword),
 * which already have the target model in hand and the app's existing
 * authorization conventions available - this service assumes that has
 * already happened.
 */
class AdminPasswordResetService
{
    public function reset(User $user, string $newPassword): void
    {
        // the remember token changes with the password, so a remember-me cookie issued before it stops working too
        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->setRememberToken(\Illuminate\Support\Str::random(60));
        $user->save();

        // A password only the account holder now knows must invalidate any
        // API tokens issued under the old one.
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    }
}
