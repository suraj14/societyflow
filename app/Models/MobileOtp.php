<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The one-time code behind OTP sign-in and OTP password reset. ONE implementation for both: issue() makes a code and
 * check() spends it.
 *
 *  - the code is 4 digits from a cryptographically secure generator and is stored only as a keyed hash (HMAC-SHA256 with
 *    the application key over phone, purpose and code), so the table never holds a usable code;
 *  - a code belongs to one phone number and one purpose ('login' / 'password_reset');
 *  - issuing a new code retires every unused older one for that number;
 *  - a code is spent exactly once, even when two requests present it at the same moment (the "mark used" is a
 *    conditional UPDATE that only one request can win);
 *  - it dies after TTL_MINUTES or after max_attempts wrong guesses.
 */
class MobileOtp extends Model
{
    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_RESET = 'password_reset';

    /**
     * How long a code is valid. The same number is sent to Fast2SMS as `otp_expiry` (minutes), so the provider
     * side and the app's own check can never disagree (it used to send 10 while the app expired codes at 5).
     */
    public const TTL_MINUTES = 5;

    protected $fillable = ['phone', 'purpose', 'otp', 'attempts', 'is_used', 'expires_at'];

    protected $hidden = ['otp'];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used'    => 'boolean',
    ];

    /** May the plain code be returned in the API response? Only on a local / development / testing server that opted in. */
    public static function exposesDevCode(): bool
    {
        return (bool) config('otp.expose_dev_code')
            && !app()->isProduction()
            && app()->environment(['local', 'development', 'testing']);
    }

    public static function hashCode(string $phone, string $purpose, string $code): string
    {
        return hash_hmac('sha256', $phone . '|' . $purpose . '|' . $code, (string) config('app.key'));
    }

    /**
     * Make a fresh code for this number and retire the old ones.
     *
     * @return array{0: self, 1: string} the stored row and the PLAIN code (to be sent by SMS - never stored, never logged)
     */
    public static function issue(string $phone, string $purpose): array
    {
        $code = (string) random_int(1000, 9999);

        $row = DB::transaction(function () use ($phone, $purpose, $code) {
            static::where('phone', $phone)->where('is_used', false)->update(['is_used' => true]);

            return static::create([
                'phone'      => $phone,
                'purpose'    => $purpose,
                'otp'        => static::hashCode($phone, $purpose, $code),
                'attempts'   => 0,
                'is_used'    => false,
                'expires_at' => now()->addMinutes((int) config('otp.ttl_minutes', self::TTL_MINUTES)),
            ]);
        });

        return [$row, $code];
    }

    /** Retire the code that was just issued (the SMS could not be sent, so there is nothing to type). */
    public function retire(): void
    {
        static::whereKey($this->getKey())->update(['is_used' => true]);
    }

    /**
     * Try a code. Returns true exactly once per issued code; false for a wrong, expired, already-used, superseded or
     * exhausted code (the caller says one generic thing for all of them).
     */
    public static function check(string $phone, string $purpose, string $code): bool
    {
        $max = (int) config('otp.max_attempts', 5);

        return DB::transaction(function () use ($phone, $purpose, $code, $max) {
            $row = static::where('phone', $phone)->where('purpose', $purpose)->where('is_used', false)->latest('id')->lockForUpdate()->first();

            if (!$row || $row->expires_at->isPast() || $row->attempts >= $max) {
                return false;
            }

            if (!hash_equals($row->otp, static::hashCode($phone, $purpose, $code))) {
                static::whereKey($row->getKey())->increment('attempts');

                return false;
            }

            // Only one of several simultaneous correct submissions changes a row here.
            return static::whereKey($row->getKey())->where('is_used', false)->update(['is_used' => true]) === 1;
        });
    }
}
