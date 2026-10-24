<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailConfigurationService
{
    /**
     * Load and apply email settings for the specified society
     */
    public static function loadAndApplySettings(?int $societyId = null): array
    {
        try {
            $emailSettings = SystemSetting::getGroup('email', $societyId);
            
            // Only apply if we have the required settings
            if (!empty($emailSettings) && !empty($emailSettings['mail_driver']) && !empty($emailSettings['mail_from_address'])) {
                self::applyMailConfiguration($emailSettings);
                
                Log::info('Email settings loaded and applied', [
                    'society_id' => $societyId,
                    'driver' => $emailSettings['mail_driver'],
                    'host' => $emailSettings['mail_host'] ?? 'not set'
                ]);
            }
            
            return $emailSettings;
        } catch (Exception $e) {
            Log::warning('Failed to load society email settings: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Apply mail configuration to Laravel's mail config
     */
    public static function applyMailConfiguration(array $settings): void
    {
        // A society's stored SMTP credentials must never override the kill
        // switch: this is how MAIL_MAILER=log used to be bypassed.
        if (!\App\Support\ExternalMessaging::enabled()) {
            config(['mail.default' => 'log']);
            app()->forgetInstance('mail.manager');
            app()->forgetInstance('mailer');

            return;
        }

        try {
            // Only apply if we have the required settings
            if (empty($settings['mail_driver']) || empty($settings['mail_from_address'])) {
                Log::warning('Incomplete email settings provided', ['settings' => array_keys($settings)]);
                return;
            }

            // Set the mail configuration
            config([
                'mail.default' => $settings['mail_driver'],
                'mail.mailers.' . $settings['mail_driver'] . '.transport' => $settings['mail_driver'],
                'mail.mailers.' . $settings['mail_driver'] . '.host' => $settings['mail_host'] ?? '',
                'mail.mailers.' . $settings['mail_driver'] . '.port' => (int)($settings['mail_port'] ?? 587),
                'mail.mailers.' . $settings['mail_driver'] . '.username' => $settings['mail_username'] ?? '',
                'mail.mailers.' . $settings['mail_driver'] . '.password' => $settings['mail_password'] ?? '',
                'mail.mailers.' . $settings['mail_driver'] . '.encryption' => $settings['mail_encryption'] ?? 'tls',
                'mail.from.address' => $settings['mail_from_address'],
                'mail.from.name' => $settings['mail_from_name'] ?? 'SocietyFlow',
            ]);

            // Also set SMTP specific config for backward compatibility
            if ($settings['mail_driver'] === 'smtp') {
                config([
                    'mail.mailers.smtp.transport' => 'smtp',
                    'mail.mailers.smtp.host' => $settings['mail_host'] ?? '',
                    'mail.mailers.smtp.port' => (int)($settings['mail_port'] ?? 587),
                    'mail.mailers.smtp.username' => $settings['mail_username'] ?? '',
                    'mail.mailers.smtp.password' => $settings['mail_password'] ?? '',
                    'mail.mailers.smtp.encryption' => $settings['mail_encryption'] ?? 'tls',
                ]);
            }

            // Purge the mail manager to force reconfiguration
            app()->forgetInstance('mail.manager');
            app()->forgetInstance('mailer');
            
            Log::info('Mail configuration applied successfully', [
                'driver' => $settings['mail_driver'],
                'host' => $settings['mail_host'] ?? 'not set',
                'from' => $settings['mail_from_address'],
                'config_check' => config('mail.default')
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to apply mail configuration: ' . $e->getMessage());
        }
    }

    /**
     * The name of a mailer built from a society's stored e-mail settings (the platform's where the society has none), for
     * mail sent where no request applied them: a guest's password reset, a queue worker. Null means "use the default
     * mailer" - external messaging is off (a society's credentials never override the kill switch), or no usable SMTP
     * settings are stored.
     *
     * Built when the message is built, so a long-running worker never sends with another society's or a stale
     * configuration: the name carries a fingerprint of the settings, and changed settings get a freshly built mailer.
     * The kill switch and allow-list still apply at send time - AppServiceProvider's MessageSending listener checks the
     * mailer the message actually goes through.
     */
    public static function mailerFor(?int $societyId): ?string
    {
        if (!\App\Support\ExternalMessaging::enabled()) {
            return null;
        }

        $settings = SystemSetting::effectiveGroup('email', $societyId);

        if (($settings['mail_driver'] ?? null) !== 'smtp' || empty($settings['mail_host']) || empty($settings['mail_from_address'])) {
            return null;
        }

        $config = [
            'transport' => 'smtp',
            'host' => $settings['mail_host'],
            'port' => (int) ($settings['mail_port'] ?? 587),
            'encryption' => $settings['mail_encryption'] ?? 'tls',
            'username' => $settings['mail_username'] ?? null,
            'password' => $settings['mail_password'] ?? null,
            'timeout' => config('mail.mailers.smtp.timeout'),
            'local_domain' => config('mail.mailers.smtp.local_domain'),
            'from' => [
                'address' => $settings['mail_from_address'],
                'name' => $settings['mail_from_name'] ?? config('mail.from.name'),
            ],
        ];

        $name = 'society_' . ($societyId ?? 'platform') . '_' . substr(hash('sha256', serialize($config)), 0, 12);
        config(["mail.mailers.{$name}" => $config]);

        return $name;
    }

    /**
     * Get the society ID for the current user
     */
    public static function getCurrentSocietyId(): ?int
    {
        $user = auth()->user();
        return $user?->hasRole('Super Admin') ? null : $user?->society_id;
    }
}