<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Failed-password lockout shared by the web login and the app's /api/v1/auth/login, so the two entry points cannot disagree.
 *
 * One counter per (e-mail address, client IP): a stranger guessing at somebody's account is locked out, while the account's owner
 * signing in from elsewhere is not. Two windows, both counting only failures:
 *   - short: the society's "login attempt limit" (Settings -> Security, 3-10, platform default 5) within 60 seconds;
 *   - long : four times that within 15 minutes, so patiently spacing guesses out does not defeat the short window.
 * A successful sign-in clears both. The e-mail is matched whatever its letter case, and the same limits apply to an address that
 * does not exist (otherwise the answer would tell registered addresses from unknown ones).
 *
 * The client IP comes from Request::ip(): behind a load balancer / reverse proxy set TRUSTED_PROXIES, or every visitor shares the
 * proxy's address.
 */
class LoginThrottle
{
    private const SHORT_WINDOW = 60;
    private const LONG_WINDOW = 900;
    private const LONG_FACTOR = 4;

    /** The attempts one (e-mail, IP) pair may fail within a minute: the account's society setting, 3-10, default 5. */
    public static function limitFor(?string $email): int
    {
        $societyId = User::whereEmail($email)->value('society_id');
        $setting = SystemSetting::get('security', 'login_attempt_limit', 5, $societyId);

        return (int) min(10, max(3, (int) $setting));
    }

    private static function keys(Request $request): array
    {
        $base = mb_strtolower(trim((string) $request->input('email'))) . '|' . $request->ip();

        return ['short' => $base, 'long' => $base . '|long'];
    }

    /** Seconds until this client may try again, or null when it may try now. */
    public static function lockedFor(Request $request): ?int
    {
        $keys = self::keys($request);
        $limit = self::limitFor($request->input('email'));

        $wait = 0;
        if (RateLimiter::tooManyAttempts($keys['short'], $limit)) {
            $wait = max($wait, RateLimiter::availableIn($keys['short']));
        }
        if (RateLimiter::tooManyAttempts($keys['long'], $limit * self::LONG_FACTOR)) {
            $wait = max($wait, RateLimiter::availableIn($keys['long']));
        }

        if ($wait > 0) {
            Log::notice('Login locked out after repeated failures', ['ip' => $request->ip(), 'seconds' => $wait]);
        }

        return $wait > 0 ? $wait : null;
    }

    public static function fail(Request $request): void
    {
        $keys = self::keys($request);
        RateLimiter::hit($keys['short'], self::SHORT_WINDOW);
        RateLimiter::hit($keys['long'], self::LONG_WINDOW);
    }

    public static function clear(Request $request): void
    {
        foreach (self::keys($request) as $key) {
            RateLimiter::clear($key);
        }
    }

    /**
     * Spend the time a real password check takes when there is no account to check against, so an unknown address does not
     * answer measurably faster than a known one. The hash is made once per process, with the application's own hashing cost.
     */
    public static function spendPasswordCheck(?string $password): void
    {
        static $dummy = null;
        $dummy ??= Hash::make('not-a-real-password');

        Hash::check((string) $password, $dummy);
    }
}
