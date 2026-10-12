<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\User;

/**
 * The one password policy. Every place that creates or changes a password - web, mobile API, admin resets - takes its
 * rules from here, and the strength meter on the web forms and in the app is built from the same array, so what the
 * screen asks for is exactly what the server enforces.
 *
 * Settings -> Security stores a society's own choices (minimum length, require an uppercase letter / a number / a
 * special character); a platform-level row (Super Admin) is the fallback. Until now those settings were saved and shown
 * but never applied, and the rules were scattered (web min 8, mobile min 6, no strength rules at all).
 *
 *   baseline for everyone: at least 8 characters, at most 64, not one of the well-known passwords below;
 *   society choices on top: a longer minimum (up to 32), uppercase, number, special character.
 *
 * A saved minimum below the baseline (the old form allowed 6) is treated as the baseline: a policy may be made
 * stricter, never weaker than what the web already demanded.
 */
class PasswordPolicy
{
    public const FLOOR = 8;
    public const CEILING = 64;   // bcrypt only reads the first 72 bytes; stay safely inside

    /** Passwords nobody should be allowed to choose (all compared lower-case). */
    private const COMMON = [
        'password', 'password1', 'password12', 'password123', 'password1234', '12345678', '123456789', '1234567890', '11111111', '00000000',
        'qwerty12', 'qwerty123', 'qwertyuiop', 'abc12345', 'abcd1234', 'admin123', 'admin1234', 'welcome1', 'welcome123', 'letmein1',
        'iloveyou', 'changeme', 'changeme123', 'passw0rd', 'p@ssw0rd', 'societyflow', 'societyflow123', 'temp1234', 'test1234',
    ];

    /** @var array<string, array> per-request cache keyed by society id ('' = platform) */
    private static array $cache = [];

    /**
     * @return array{min: int, uppercase: bool, number: bool, special: bool}
     */
    public static function forSociety(?int $societyId): array
    {
        $key = (string) $societyId;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $read = function (string $name, $default) use ($societyId) {
            // A direct read: SystemSetting::get() treats a null society as "the signed-in user's", which is not what a
            // platform fallback means.
            foreach ($societyId !== null ? [$societyId, null] : [null] as $id) {
                $row = SystemSetting::query()->where('group', 'security')->where('key', $name)
                    ->where(fn ($q) => $id === null ? $q->whereNull('society_id') : $q->where('society_id', $id))->first();
                if ($row !== null && $row->value !== null && $row->value !== '') {
                    return $row->value;
                }
            }

            return $default;
        };

        $policy = [
            'min'       => max(self::FLOOR, min(32, (int) $read('password_min_length', self::FLOOR))),
            'uppercase' => filter_var($read('password_require_uppercase', false), FILTER_VALIDATE_BOOLEAN),
            'number'    => filter_var($read('password_require_number', false), FILTER_VALIDATE_BOOLEAN),
            'special'   => filter_var($read('password_require_special', false), FILTER_VALIDATE_BOOLEAN),
        ];

        return self::$cache[$key] = $policy;
    }

    public static function forUser(?User $user): array
    {
        return self::forSociety($user?->society_id);
    }

    /** Forget cached policies (after a Security setting changes, and between tests). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Laravel rules for a new-password field.
     *
     * @param  array{min: int, uppercase: bool, number: bool, special: bool}  $policy
     * @param  bool  $confirmed  also require the matching <field>_confirmation
     */
    public static function rules(array $policy, bool $confirmed = true): array
    {
        $rules = ['string', 'min:' . $policy['min'], 'max:' . self::CEILING];
        if ($confirmed) {
            $rules[] = 'confirmed';
        }
        $rules[] = function (string $attribute, $value, \Closure $fail) use ($policy) {
            foreach (self::unmet((string) $value, $policy) as $unmet) {
                $fail(self::MESSAGES[$unmet]);
            }
        };

        return $rules;
    }

    /** Same, with `required` in front - the usual case. */
    public static function required(array $policy, bool $confirmed = true): array
    {
        return array_merge(['required'], self::rules($policy, $confirmed));
    }

    private const MESSAGES = [
        'uppercase' => 'The password must include an uppercase letter.',
        'number'    => 'The password must include a number.',
        'special'   => 'The password must include a special character such as ! @ # $ %.',
        'common'    => 'That password is too common. Choose something harder to guess.',
    ];

    /**
     * Which requirements a password does not meet (excluding length, which min:/max: report): any of
     * 'uppercase', 'number', 'special', 'common'.
     *
     * @return list<string>
     */
    public static function unmet(string $password, array $policy): array
    {
        $unmet = [];
        if ($policy['uppercase'] && !preg_match('/\p{Lu}/u', $password)) {
            $unmet[] = 'uppercase';
        }
        if ($policy['number'] && !preg_match('/\d/', $password)) {
            $unmet[] = 'number';
        }
        if ($policy['special'] && !preg_match('/[^\p{L}\p{N}\s]/u', $password)) {
            $unmet[] = 'special';
        }
        if (in_array(mb_strtolower($password), self::COMMON, true)) {
            $unmet[] = 'common';
        }

        return $unmet;
    }

    /**
     * The policy as the screens show it: the numbers, and the sentences for the checklist. The web script and the
     * mobile app both build their meter from this.
     *
     * @return array{min: int, max: int, uppercase: bool, number: bool, special: bool, requirements: list<array{key: string, label: string}>, common: list<string>}
     */
    public static function describe(array $policy): array
    {
        $requirements = [['key' => 'min', 'label' => "At least {$policy['min']} characters"]];
        if ($policy['uppercase']) {
            $requirements[] = ['key' => 'uppercase', 'label' => 'An uppercase letter (A-Z)'];
        }
        if ($policy['number']) {
            $requirements[] = ['key' => 'number', 'label' => 'A number (0-9)'];
        }
        if ($policy['special']) {
            $requirements[] = ['key' => 'special', 'label' => 'A special character (! @ # $ %)'];
        }

        return $policy + ['max' => self::CEILING, 'requirements' => $requirements, 'common' => self::COMMON];
    }
}
