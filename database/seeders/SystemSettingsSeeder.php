<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // App Settings
        SystemSetting::set('app', 'app_name', 'SocietyFlow');
        SystemSetting::set('app', 'default_language', 'en');
        SystemSetting::set('app', 'default_currency', 'USD');
        SystemSetting::set('app', 'society_requires_approval', true, 'boolean');

        // Language Settings
        SystemSetting::set('language', 'default_language', 'en');
        SystemSetting::set('language', 'enabled_languages', ['en', 'es', 'hi'], 'json');

        // Storage Settings
        SystemSetting::set('storage', 'storage_driver', 'local');

        // Theme Settings
        SystemSetting::set('theme', 'primary_color', '#7c3aed');
        SystemSetting::set('theme', 'secondary_color', '#4f46e5');
        SystemSetting::set('theme', 'sidebar_theme', 'light');
        SystemSetting::set('theme', 'button_style', 'rounded');

        // Currency Settings
        SystemSetting::set('currency', 'default_currency', 'USD');
        SystemSetting::set('currency', 'currency_symbol', '$');
        SystemSetting::set('currency', 'currency_position', 'left');
        SystemSetting::set('currency', 'decimal_separator', '.');
        SystemSetting::set('currency', 'thousand_separator', ',');

        // Email Settings
        SystemSetting::set('email', 'mail_driver', 'smtp');
        SystemSetting::set('email', 'mail_host', 'smtp.mailtrap.io');
        SystemSetting::set('email', 'mail_port', 587, 'integer');
        SystemSetting::set('email', 'mail_encryption', 'tls');
        SystemSetting::set('email', 'mail_from_address', 'noreply@societyflow.com');
        SystemSetting::set('email', 'mail_from_name', 'SocietyFlow');

        // Payment Settings
        SystemSetting::set('payment', 'payment_test_mode', true, 'boolean');
        SystemSetting::set('payment', 'razorpay_enabled', false, 'boolean');
        SystemSetting::set('payment', 'stripe_enabled', false, 'boolean');
        SystemSetting::set('payment', 'paypal_enabled', false, 'boolean');

        // Push Notification Settings
        SystemSetting::set('push', 'push_enabled', false, 'boolean');

        // Security Settings
        SystemSetting::set('security', 'enable_2fa', false, 'boolean');
        SystemSetting::set('security', 'login_attempt_limit', 5, 'integer');
        SystemSetting::set('security', 'session_timeout', 120, 'integer');
        SystemSetting::set('security', 'force_https', false, 'boolean');
        SystemSetting::set('security', 'password_min_length', 8, 'integer');
        SystemSetting::set('security', 'password_require_uppercase', true, 'boolean');
        SystemSetting::set('security', 'password_require_number', true, 'boolean');
        SystemSetting::set('security', 'password_require_special', false, 'boolean');
    }
}
