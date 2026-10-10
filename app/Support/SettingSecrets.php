<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * Keeps saved provider credentials off the settings page.
 *
 * These values are stored encrypted at rest, but `SettingsController::index`
 * handed the *decrypted* value straight to the Blade view, which rendered it
 * into `value="..."`. `type="password"` only hides it on screen - the real
 * secret sat in the page source for anyone with View Source, DevTools, a
 * saved copy of the page, or a browser extension. One field
 * (`instamojo_api_key`) was `type="text"` and simply displayed.
 *
 * So the page now shows [self::MASK] wherever a secret is set, and a form
 * posting that mask back means "leave it alone". A real new value replaces
 * it; clearing the field clears the setting. That keeps the round trip
 * honest without ever sending the stored secret to the browser.
 *
 * The SMS and WhatsApp groups go through the same mask/resolve path as
 * email and payment below - SettingsController::updateSms/updateWhatsApp
 * also guard each secret with isMask() before saving, which is what keeps
 * the masked value from ever being written back as the real one.
 */
class SettingSecrets
{
    /**
     * What the browser sees instead of a stored secret. Deliberately not a
     * plausible credential, so a value that somehow reached a provider
     * would fail loudly rather than look real.
     */
    public const MASK = '********';

    /** group => the keys within it that must never reach the browser. */
    private const SENSITIVE = [
        'sms' => [
            'fast2sms_api_key',
            'bulksmsplans_api_id',
            'bulksmsplans_api_password',
        ],
        'email' => [
            'mail_password',
        ],
        'payment' => [
            'razorpay_secret',
            'stripe_secret',
            'paypal_secret',
            'square_access_token',
            'cashfree_secret_key',
            'instamojo_api_key',
            'instamojo_auth_token',
        ],
        'whatsapp' => [
            'whatsapp_api_key',
        ],
        'storage' => [
            'aws_key',
            'aws_secret',
        ],
    ];

    /** @return array<string,string> the sensitive keys of one group */
    public static function keysFor(string $group): array
    {
        return self::SENSITIVE[$group] ?? [];
    }

    /**
     * Replace every set secret in $values with the mask. An unset secret
     * stays empty, so the form still shows an empty box rather than
     * implying a value exists.
     *
     * @param  array<string,mixed>  $values
     * @return array<string,mixed>
     */
    public static function mask(string $group, array $values): array
    {
        foreach (self::keysFor($group) as $key) {
            if (isset($values[$key]) && $values[$key] !== '') {
                $values[$key] = self::MASK;
            }
        }

        return $values;
    }

    /** Mask every group of a settings payload at once. */
    public static function maskAll(array $settings): array
    {
        foreach (array_keys(self::SENSITIVE) as $group) {
            if (isset($settings[$group]) && is_array($settings[$group])) {
                $settings[$group] = self::mask($group, $settings[$group]);
            }
        }

        return $settings;
    }

    public static function isMask(mixed $value): bool
    {
        return is_string($value) && $value === self::MASK;
    }

    /**
     * What to actually store for a secret the form submitted.
     *
     * The mask means "unchanged", so the stored value is read back and
     * returned. Anything else - including an empty string, which is how a
     * credential is deliberately removed - is taken at face value.
     */
    public static function resolve(string $group, string $key, mixed $submitted, ?int $societyId): mixed
    {
        if (!self::isMask($submitted)) {
            return $submitted;
        }

        return SystemSetting::get($group, $key, null, $societyId);
    }
}
