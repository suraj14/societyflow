<?php

namespace App\Helpers;

use Illuminate\Support\Str;

/**
 * Generates the one-time password assigned to accounts that an
 * administrator creates on someone else's behalf.
 *
 * This exists because OwnerController and UserController each hardcoded a
 * constant ('password' and 'password123') and then echoed it into a flash
 * message. Every account created through either screen shared the same
 * well-known credential, and the mobile API accepts email + password with
 * no second factor, so knowing a resident's email was enough to sign in as
 * them.
 *
 * The generated value is delivered to the account holder by email via the
 * UserCreated event. It is deliberately never returned to the creating
 * admin's screen.
 */
class TemporaryPassword
{
    /** Character set: unambiguous only - no O/0, l/1/I, to keep it dictatable. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    private const LENGTH = 16;

    /**
     * A cryptographically random temporary password.
     *
     * Str::password() is not used because it includes symbols that get
     * mangled when a resident is read the password over the phone.
     */
    public static function generate(): string
    {
        $alphabet = self::ALPHABET;
        $max      = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    /**
     * The message shown to the admin who created the account.
     *
     * Never contains the password itself.
     */
    public static function noticeFor(string $email): string
    {
        return "Account created for {$email}. A temporary password has been emailed to them; "
            . 'it is not shown here. If the email does not arrive, use the password reset flow.';
    }
}
