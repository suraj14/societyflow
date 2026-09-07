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
     * Get the society ID for the current user
     */
    public static function getCurrentSocietyId(): ?int
    {
        $user = auth()->user();
        return $user?->hasRole('Super Admin') ? null : $user?->society_id;
    }
}