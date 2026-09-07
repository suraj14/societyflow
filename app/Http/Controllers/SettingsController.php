<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Services\EmailConfigurationService;
use Aws\S3\S3Client;
use Exception;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Both Super Admin and Admin can access settings
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access to settings.');
        }

        // Super Admin uses different view
        if ($user->hasRole('Super Admin')) {
            return $this->superAdminSettings($request);
        }

        // Admin settings
        $tab = $request->get('tab', 'society');
        $societyId = $user->society_id;
        
        // Load and apply email settings for this society
        EmailConfigurationService::loadAndApplySettings($societyId);
        
        // Get settings based on scope
        $settings = [
            'app' => SystemSetting::getGroup('app', $societyId),
            'theme' => SystemSetting::getGroup('theme', $societyId),
            'email' => SystemSetting::getGroup('email', $societyId),
            'payment' => SystemSetting::getGroup('payment', $societyId),
            'push' => SystemSetting::getGroup('push', $societyId),
            'sms' => SystemSetting::getGroup('sms', $societyId),
            'security' => SystemSetting::getGroup('security', $societyId),
            'permissions' => SystemSetting::getGroup('permissions', $societyId),
            'currency' => SystemSetting::getGroup('currency', $societyId),
            'features' => SystemSetting::getGroup('features', $societyId),
        ];

        // Get complaint categories for this society
        $complaintCategories = \App\Models\ComplaintCategory::where('society_id', $societyId)->get();

        $isGlobalAdmin = false;

        return view('settings.index', compact('tab', 'settings', 'isGlobalAdmin', 'complaintCategories'));
    }

    private function superAdminSettings(Request $request)
    {
        $tab = $request->get('tab', 'app');
        
        // Load and apply global email settings for Super Admin
        EmailConfigurationService::loadAndApplySettings(null);
        
        // Get global settings (society_id = null)
        $settings = [
            'app' => SystemSetting::getGroup('app', null),
            'theme' => SystemSetting::getGroup('theme', null),
            'email' => SystemSetting::getGroup('email', null),
            'payment' => SystemSetting::getGroup('payment', null),
            'push' => SystemSetting::getGroup('push', null),
            'security' => SystemSetting::getGroup('security', null),
        ];

        $languages = [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'hi' => 'Hindi',
            'ar' => 'Arabic',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
        ];

        $currencies = [
            'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
            'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥'],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
        ];

        return view('super-admin.settings.index', compact('tab', 'settings', 'languages', 'currencies'));
    }

    public function updateSociety(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'society_name' => 'required|string|max:255',
            'society_phone' => 'nullable|string|max:15',
            'society_email' => 'nullable|email|max:255',
            'society_address' => 'nullable|string',
            'society_country' => 'nullable|string|max:2',
        ]);

        if ($user->society) {
            $user->society->update([
                'name' => $validated['society_name'],
                'phone' => $validated['society_phone'],
                'email' => $validated['society_email'],
                'address' => $validated['society_address'],
                'country' => $validated['society_country'],
            ]);
        }

        return back()->with('success', 'Society information updated successfully!');
    }

    public function updateApp(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        if ($user->hasRole('Super Admin')) {
            // Super Admin settings
            $validated = $request->validate([
                'app_name' => 'required|string|max:255',
                'default_language' => 'required|string|max:10',
                'default_currency' => 'required|string|max:10',
                'society_requires_approval' => 'nullable|boolean',
            ]);

            SystemSetting::set('app', 'app_name', $validated['app_name'], 'string', $societyId);
            SystemSetting::set('app', 'default_language', $validated['default_language'], 'string', $societyId);
            SystemSetting::set('app', 'default_currency', $validated['default_currency'], 'string', $societyId);
            SystemSetting::set('app', 'society_requires_approval', $request->boolean('society_requires_approval'), 'boolean', $societyId);
        } else {
            // Admin settings
            $validated = $request->validate([
                'default_language' => 'required|string|max:10',
                'default_currency' => 'required|string|max:10',
                'timezone' => 'required|string|max:50',
                'date_format' => 'required|string|max:20',
                'enable_email_notifications' => 'nullable|boolean',
                'auto_approve_residents' => 'nullable|boolean',
            ]);

            SystemSetting::set('app', 'default_language', $validated['default_language'], 'string', $societyId);
            SystemSetting::set('app', 'default_currency', $validated['default_currency'], 'string', $societyId);
            SystemSetting::set('app', 'timezone', $validated['timezone'], 'string', $societyId);
            SystemSetting::set('app', 'date_format', $validated['date_format'], 'string', $societyId);
            SystemSetting::set('app', 'enable_email_notifications', $request->boolean('enable_email_notifications'), 'boolean', $societyId);
            SystemSetting::set('app', 'auto_approve_residents', $request->boolean('auto_approve_residents'), 'boolean', $societyId);
        }

        return back()->with('success', 'App settings updated successfully!');
    }

    public function updateLanguage(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Super Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'default_language' => 'required|string|max:10',
            'enabled_languages' => 'nullable|array',
        ]);

        SystemSetting::set('language', 'default_language', $validated['default_language'], 'string', null);
        SystemSetting::set('language', 'enabled_languages', $validated['enabled_languages'] ?? [], 'json', null);

        return back()->with('success', 'Language settings updated successfully!');
    }

    public function updateStorage(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Super Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'storage_driver' => 'required|in:local,public,s3',
            'aws_key' => 'nullable|string|max:255',
            'aws_secret' => 'nullable|string|max:255',
            'aws_region' => 'nullable|string|max:50',
            'aws_bucket' => 'nullable|string|max:255',
        ]);

        SystemSetting::set('storage', 'storage_driver', $validated['storage_driver'], 'string', null);
        
        if ($validated['storage_driver'] === 's3') {
            SystemSetting::set('storage', 'aws_key', $validated['aws_key'], 'encrypted', null);
            SystemSetting::set('storage', 'aws_secret', $validated['aws_secret'], 'encrypted', null);
            SystemSetting::set('storage', 'aws_region', $validated['aws_region'], 'string', null);
            SystemSetting::set('storage', 'aws_bucket', $validated['aws_bucket'], 'string', null);
        }

        return back()->with('success', 'Storage settings updated successfully!');
    }

    public function testStorage(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Super Admin')) {
            abort(403, 'Unauthorized access.');
        }

        try {
            $driver = SystemSetting::get('storage', 'storage_driver', 'local', null);
            
            if ($driver === 's3') {
                $client = new S3Client([
                    'version' => 'latest',
                    'region' => SystemSetting::get('storage', 'aws_region', null, null),
                    'credentials' => [
                        'key' => SystemSetting::get('storage', 'aws_key', null, null),
                        'secret' => SystemSetting::get('storage', 'aws_secret', null, null),
                    ],
                ]);
                
                $bucket = SystemSetting::get('storage', 'aws_bucket', null, null);
                $client->listObjects(['Bucket' => $bucket, 'MaxKeys' => 1]);
                
                return response()->json(['success' => true, 'message' => 'S3 connection successful!']);
            }
            
            // Test local storage
            $testFile = 'test_' . time() . '.txt';
            Storage::disk($driver)->put($testFile, 'test');
            Storage::disk($driver)->delete($testFile);
            
            return response()->json(['success' => true, 'message' => 'Storage connection successful!']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    public function updateTheme(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'primary_color' => 'required|string|max:20',
            'secondary_color' => 'required|string|max:20',
            'sidebar_theme' => 'required|in:light,dark',
            'button_style' => 'required|in:rounded,square,pill',
            'society_logo' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'society_favicon' => 'nullable|file|mimes:jpeg,png,jpg,ico|max:1024',
        ]);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        SystemSetting::set('theme', 'primary_color', $validated['primary_color'], 'string', $societyId);
        SystemSetting::set('theme', 'secondary_color', $validated['secondary_color'], 'string', $societyId);
        SystemSetting::set('theme', 'sidebar_theme', $validated['sidebar_theme'], 'string', $societyId);
        SystemSetting::set('theme', 'button_style', $validated['button_style'], 'string', $societyId);

        // Handle logo upload (Admin only)
        if ($request->hasFile('society_logo') && $user->hasRole('Admin')) {
            try {
                // Don't try to delete old logo - just skip it to avoid fileinfo error
                
                // Store new logo
                $file = $request->file('society_logo');
                $uploadPath = public_path('storage/society-logos');
                @mkdir($uploadPath, 0777, true);
                $filename = time() . '_' . $file->getClientOriginalName();
                try {
                    $file->move($uploadPath, $filename);
                } catch (\Exception $e) {
                    @chmod($uploadPath, 0777);
                    $file->move($uploadPath, $filename);
                }
                $logoPath = 'society-logos/' . $filename;
                SystemSetting::set('theme', 'society_logo', $logoPath, 'string', $societyId);
                
                \Log::info('Logo uploaded successfully', ['path' => $logoPath, 'society_id' => $societyId]);
            } catch (\Exception $e) {
                \Log::error('Logo upload failed', ['error' => $e->getMessage(), 'society_id' => $societyId]);
                return back()->with('error', 'Failed to upload logo: ' . $e->getMessage());
            }
        }

        // Handle favicon upload (Admin only)
        if ($request->hasFile('society_favicon') && $user->hasRole('Admin')) {
            try {
                // Don't try to delete old favicon - just skip it to avoid fileinfo error
                
                // Store new favicon
                $file = $request->file('society_favicon');
                $uploadPath = public_path('storage/society-favicons');
                @mkdir($uploadPath, 0777, true);
                $filename = time() . '_' . $file->getClientOriginalName();
                try {
                    $file->move($uploadPath, $filename);
                } catch (\Exception $e) {
                    @chmod($uploadPath, 0777);
                    $file->move($uploadPath, $filename);
                }
                $faviconPath = 'society-favicons/' . $filename;
                SystemSetting::set('theme', 'society_favicon', $faviconPath, 'string', $societyId);
                
                \Log::info('Favicon uploaded successfully', ['path' => $faviconPath, 'society_id' => $societyId]);
            } catch (\Exception $e) {
                \Log::error('Favicon upload failed', ['error' => $e->getMessage(), 'society_id' => $societyId]);
                return back()->with('error', 'Failed to upload favicon: ' . $e->getMessage());
            }
        }

        // Clear theme cache
        \App\Helpers\ThemeHelper::clearCache($societyId);

        return back()->with('success', 'Theme settings updated successfully!');
    }

    public function updateCurrency(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Super Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'default_currency' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:10',
            'currency_position' => 'required|in:left,right',
            'decimal_separator' => 'required|string|max:1',
            'thousand_separator' => 'required|string|max:1',
        ]);

        SystemSetting::setGroup('currency', $validated, [], null);

        return back()->with('success', 'Currency settings updated successfully!');
    }

    public function updateEmail(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'mail_driver' => 'required|in:smtp,sendmail,mailgun,ses,postmark',
            'mail_host' => 'nullable|string|max:255',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|in:tls,ssl,null',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        SystemSetting::set('email', 'mail_driver', $validated['mail_driver'], 'string', $societyId);
        SystemSetting::set('email', 'mail_host', $validated['mail_host'], 'string', $societyId);
        SystemSetting::set('email', 'mail_port', $validated['mail_port'], 'integer', $societyId);
        SystemSetting::set('email', 'mail_username', $validated['mail_username'], 'string', $societyId);
        SystemSetting::set('email', 'mail_password', $validated['mail_password'], 'encrypted', $societyId);
        SystemSetting::set('email', 'mail_encryption', $validated['mail_encryption'], 'string', $societyId);
        SystemSetting::set('email', 'mail_from_address', $validated['mail_from_address'], 'string', $societyId);
        SystemSetting::set('email', 'mail_from_name', $validated['mail_from_name'], 'string', $societyId);

        // Apply settings to current Laravel mail configuration
        EmailConfigurationService::applyMailConfiguration($validated);

        return back()->with('success', 'Email settings updated successfully!');
    }

    public function testEmail(Request $request)
    {
        $request->validate(['test_email' => 'required|email']);

        try {
            $user = auth()->user();
            $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;
            $subject = $user->hasRole('Super Admin') ? 'Test Email - SocietyFlow' : 'Test Email - ' . ($user->society->name ?? 'SocietyFlow');
            
            // Load and apply society-specific email settings
            $emailSettings = EmailConfigurationService::loadAndApplySettings($societyId);
            
            // Check if email settings are configured
            if (empty($emailSettings) || empty($emailSettings['mail_driver']) || empty($emailSettings['mail_from_address'])) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Email settings not configured for this society. Please configure email settings first.',
                    'details' => 'Go to Settings → Email Settings to configure SMTP settings.'
                ]);
            }
            
            // Get current mail configuration for debugging
            $mailConfig = config('mail');
            Log::info('Testing email with society-specific configuration', [
                'society_id' => $societyId,
                'society_name' => $user->society->name ?? 'Global',
                'driver' => $mailConfig['default'],
                'host' => $mailConfig['mailers']['smtp']['host'] ?? 'Not set',
                'port' => $mailConfig['mailers']['smtp']['port'] ?? 'Not set',
                'from' => $mailConfig['from']['address'] ?? 'Not set',
                'to' => $request->test_email
            ]);
            
            Mail::raw('This is a test email from SocietyFlow. If you received this, your email configuration is working correctly!', function ($message) use ($request, $subject) {
                $message->to($request->test_email)->subject($subject);
            });

            return response()->json(['success' => true, 'message' => 'Test email sent successfully!']);
        } catch (Exception $e) {
            Log::error('Email test failed', [
                'society_id' => $societyId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'Failed to send email: ' . $e->getMessage(),
                'details' => 'Check your SMTP settings and ensure your email credentials are correct.'
            ]);
        }
    }

    public function updatePayment(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'payment_test_mode' => 'nullable|boolean',
            'razorpay_enabled' => 'nullable|boolean',
            'razorpay_key' => 'nullable|string|max:255',
            'razorpay_secret' => 'nullable|string|max:255',
            'stripe_enabled' => 'nullable|boolean',
            'stripe_key' => 'nullable|string|max:255',
            'stripe_secret' => 'nullable|string|max:255',
            'paypal_enabled' => 'nullable|boolean',
            'paypal_client_id' => 'nullable|string|max:255',
            'paypal_secret' => 'nullable|string|max:255',
            'square_enabled' => 'nullable|boolean',
            'square_app_id' => 'nullable|string|max:255',
            'square_access_token' => 'nullable|string|max:255',
            'square_location_id' => 'nullable|string|max:255',
            'square_environment' => 'nullable|in:sandbox,production',
            'paytm_enabled' => 'nullable|boolean',
            'paytm_merchant_id' => 'nullable|string|max:255',
            'paytm_merchant_key' => 'nullable|string|max:255',
            'paytm_website' => 'nullable|string|max:255',
            'paytm_industry_type' => 'nullable|string|max:255',
            'phonepe_enabled' => 'nullable|boolean',
            'phonepe_merchant_id' => 'nullable|string|max:255',
            'phonepe_salt_key' => 'nullable|string|max:255',
            'phonepe_salt_index' => 'nullable|string|max:10',
            'phonepe_environment' => 'nullable|in:sandbox,production',
            'cashfree_enabled' => 'nullable|boolean',
            'cashfree_app_id' => 'nullable|string|max:255',
            'cashfree_secret_key' => 'nullable|string|max:255',
            'cashfree_environment' => 'nullable|in:sandbox,production',
            'instamojo_enabled' => 'nullable|boolean',
            'instamojo_api_key' => 'nullable|string|max:255',
            'instamojo_auth_token' => 'nullable|string|max:255',
            'instamojo_environment' => 'nullable|in:test,production',
            'ccavenue_enabled' => 'nullable|boolean',
            'ccavenue_merchant_id' => 'nullable|string|max:255',
            'ccavenue_working_key' => 'nullable|string|max:255',
            'ccavenue_access_code' => 'nullable|string|max:255',
        ]);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        SystemSetting::set('payment', 'payment_test_mode', $request->boolean('payment_test_mode'), 'boolean', $societyId);
        
        // Razorpay
        SystemSetting::set('payment', 'razorpay_enabled', $request->boolean('razorpay_enabled'), 'boolean', $societyId);
        if ($validated['razorpay_key']) {
            SystemSetting::set('payment', 'razorpay_key', $validated['razorpay_key'], 'encrypted', $societyId);
        }
        if ($validated['razorpay_secret']) {
            SystemSetting::set('payment', 'razorpay_secret', $validated['razorpay_secret'], 'encrypted', $societyId);
        }

        // Stripe
        SystemSetting::set('payment', 'stripe_enabled', $request->boolean('stripe_enabled'), 'boolean', $societyId);
        if ($validated['stripe_key']) {
            SystemSetting::set('payment', 'stripe_key', $validated['stripe_key'], 'encrypted', $societyId);
        }
        if ($validated['stripe_secret']) {
            SystemSetting::set('payment', 'stripe_secret', $validated['stripe_secret'], 'encrypted', $societyId);
        }

        // PayPal
        SystemSetting::set('payment', 'paypal_enabled', $request->boolean('paypal_enabled'), 'boolean', $societyId);
        if ($validated['paypal_client_id']) {
            SystemSetting::set('payment', 'paypal_client_id', $validated['paypal_client_id'], 'encrypted', $societyId);
        }
        if ($validated['paypal_secret']) {
            SystemSetting::set('payment', 'paypal_secret', $validated['paypal_secret'], 'encrypted', $societyId);
        }

        // Square
        SystemSetting::set('payment', 'square_enabled', $request->boolean('square_enabled'), 'boolean', $societyId);
        if ($validated['square_app_id']) {
            SystemSetting::set('payment', 'square_app_id', $validated['square_app_id'], 'encrypted', $societyId);
        }
        if ($validated['square_access_token']) {
            SystemSetting::set('payment', 'square_access_token', $validated['square_access_token'], 'encrypted', $societyId);
        }
        if ($validated['square_location_id']) {
            SystemSetting::set('payment', 'square_location_id', $validated['square_location_id'], 'string', $societyId);
        }
        if ($validated['square_environment']) {
            SystemSetting::set('payment', 'square_environment', $validated['square_environment'], 'string', $societyId);
        }

        // Paytm
        SystemSetting::set('payment', 'paytm_enabled', $request->boolean('paytm_enabled'), 'boolean', $societyId);
        if ($validated['paytm_merchant_id']) {
            SystemSetting::set('payment', 'paytm_merchant_id', $validated['paytm_merchant_id'], 'encrypted', $societyId);
        }
        if ($validated['paytm_merchant_key']) {
            SystemSetting::set('payment', 'paytm_merchant_key', $validated['paytm_merchant_key'], 'encrypted', $societyId);
        }
        if ($validated['paytm_website']) {
            SystemSetting::set('payment', 'paytm_website', $validated['paytm_website'], 'string', $societyId);
        }
        if ($validated['paytm_industry_type']) {
            SystemSetting::set('payment', 'paytm_industry_type', $validated['paytm_industry_type'], 'string', $societyId);
        }

        // PhonePe
        SystemSetting::set('payment', 'phonepe_enabled', $request->boolean('phonepe_enabled'), 'boolean', $societyId);
        if ($validated['phonepe_merchant_id']) {
            SystemSetting::set('payment', 'phonepe_merchant_id', $validated['phonepe_merchant_id'], 'encrypted', $societyId);
        }
        if ($validated['phonepe_salt_key']) {
            SystemSetting::set('payment', 'phonepe_salt_key', $validated['phonepe_salt_key'], 'encrypted', $societyId);
        }
        if ($validated['phonepe_salt_index']) {
            SystemSetting::set('payment', 'phonepe_salt_index', $validated['phonepe_salt_index'], 'string', $societyId);
        }
        if ($validated['phonepe_environment']) {
            SystemSetting::set('payment', 'phonepe_environment', $validated['phonepe_environment'], 'string', $societyId);
        }

        // Cashfree
        SystemSetting::set('payment', 'cashfree_enabled', $request->boolean('cashfree_enabled'), 'boolean', $societyId);
        if ($validated['cashfree_app_id']) {
            SystemSetting::set('payment', 'cashfree_app_id', $validated['cashfree_app_id'], 'encrypted', $societyId);
        }
        if ($validated['cashfree_secret_key']) {
            SystemSetting::set('payment', 'cashfree_secret_key', $validated['cashfree_secret_key'], 'encrypted', $societyId);
        }
        if ($validated['cashfree_environment']) {
            SystemSetting::set('payment', 'cashfree_environment', $validated['cashfree_environment'], 'string', $societyId);
        }

        // Instamojo
        SystemSetting::set('payment', 'instamojo_enabled', $request->boolean('instamojo_enabled'), 'boolean', $societyId);
        if ($validated['instamojo_api_key']) {
            SystemSetting::set('payment', 'instamojo_api_key', $validated['instamojo_api_key'], 'encrypted', $societyId);
        }
        if ($validated['instamojo_auth_token']) {
            SystemSetting::set('payment', 'instamojo_auth_token', $validated['instamojo_auth_token'], 'encrypted', $societyId);
        }
        if ($validated['instamojo_environment']) {
            SystemSetting::set('payment', 'instamojo_environment', $validated['instamojo_environment'], 'string', $societyId);
        }

        // CCAvenue
        SystemSetting::set('payment', 'ccavenue_enabled', $request->boolean('ccavenue_enabled'), 'boolean', $societyId);
        if ($validated['ccavenue_merchant_id']) {
            SystemSetting::set('payment', 'ccavenue_merchant_id', $validated['ccavenue_merchant_id'], 'encrypted', $societyId);
        }
        if ($validated['ccavenue_working_key']) {
            SystemSetting::set('payment', 'ccavenue_working_key', $validated['ccavenue_working_key'], 'encrypted', $societyId);
        }
        if ($validated['ccavenue_access_code']) {
            SystemSetting::set('payment', 'ccavenue_access_code', $validated['ccavenue_access_code'], 'string', $societyId);
        }

        return back()->with('success', 'Payment gateway settings updated successfully!');
    }

    public function updatePush(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'push_enabled' => 'nullable|boolean',
            'fcm_server_key' => 'nullable|string',
            'vapid_public_key' => 'nullable|string',
            'vapid_private_key' => 'nullable|string',
        ]);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        SystemSetting::set('push', 'push_enabled', $request->boolean('push_enabled'), 'boolean', $societyId);
        
        if ($validated['fcm_server_key']) {
            SystemSetting::set('push', 'fcm_server_key', $validated['fcm_server_key'], 'encrypted', $societyId);
        }
        if ($validated['vapid_public_key']) {
            SystemSetting::set('push', 'vapid_public_key', $validated['vapid_public_key'], 'string', $societyId);
        }
        if ($validated['vapid_private_key']) {
            SystemSetting::set('push', 'vapid_private_key', $validated['vapid_private_key'], 'encrypted', $societyId);
        }

        return back()->with('success', 'Push notification settings updated successfully!');
    }

    public function updateSms(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'sms_provider'               => 'required|in:fast2sms,bulksmsplans,custom',
            'sms_enabled'                => 'nullable|boolean',
            'fast2sms_api_key'           => 'nullable|string|max:500',
            'fast2sms_route'             => 'nullable|in:otp,dlt',
            'fast2sms_sender_id'         => 'nullable|string|max:20',
            'fast2sms_template_id'       => 'nullable|string|max:50',
            'bulksmsplans_api_id'        => 'nullable|string|max:200',
            'bulksmsplans_api_password'  => 'nullable|string|max:200',
            'bulksmsplans_sender_id'     => 'nullable|string|max:20',
            'bulksmsplans_template_id'   => 'nullable|string|max:50',
            'custom_sms_api_url'         => 'nullable|string|max:1000',
        ]);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        SystemSetting::set('sms', 'sms_enabled',  $request->boolean('sms_enabled'), 'boolean', $societyId);
        SystemSetting::set('sms', 'sms_provider',  $request->sms_provider, 'string', $societyId);

        if ($request->filled('fast2sms_api_key')) {
            SystemSetting::set('sms', 'fast2sms_api_key', $request->fast2sms_api_key, 'encrypted', $societyId);
        }
        SystemSetting::set('sms', 'fast2sms_route', $request->fast2sms_route ?? 'otp', 'string', $societyId);
        if ($request->filled('fast2sms_sender_id')) {
            SystemSetting::set('sms', 'fast2sms_sender_id', $request->fast2sms_sender_id, 'string', $societyId);
        }
        if ($request->filled('fast2sms_template_id')) {
            SystemSetting::set('sms', 'fast2sms_template_id', $request->fast2sms_template_id, 'string', $societyId);
        }
        if ($request->filled('bulksmsplans_api_id')) {
            SystemSetting::set('sms', 'bulksmsplans_api_id', $request->bulksmsplans_api_id, 'encrypted', $societyId);
        }
        if ($request->filled('bulksmsplans_api_password')) {
            SystemSetting::set('sms', 'bulksmsplans_api_password', $request->bulksmsplans_api_password, 'encrypted', $societyId);
        }
        if ($request->filled('bulksmsplans_sender_id')) {
            SystemSetting::set('sms', 'bulksmsplans_sender_id', $request->bulksmsplans_sender_id, 'string', $societyId);
        }
        SystemSetting::set('sms', 'bulksmsplans_template_id', $request->bulksmsplans_template_id ?? '0', 'string', $societyId);
        if ($request->filled('custom_sms_api_url')) {
            SystemSetting::set('sms', 'custom_sms_api_url', $request->custom_sms_api_url, 'string', $societyId);
        }

        return back()->with('success', 'SMS settings saved successfully!');
    }

    public function testSms(Request $request)
    {
        $request->validate(['phone' => 'required|string|min:10|max:15']);

        $user      = auth()->user();
        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        $result = \App\Services\SmsService::sendOtp($request->phone, '1234', $societyId);

        if ($result['success'] && $result['message'] !== 'dev_mode') {
            return response()->json(['success' => true, 'message' => 'Test SMS sent to ' . $request->phone . '. Check your phone for OTP: 1234']);
        }
        if ($result['message'] === 'dev_mode') {
            return response()->json(['success' => false, 'message' => 'SMS not configured. Enter your Fast2SMS API key and enable SMS first, then save settings.']);
        }
        return response()->json(['success' => false, 'message' => $result['message']]);
    }

    public function updateSecurity(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'enable_2fa' => 'nullable|boolean',
            'login_attempt_limit' => 'required|integer|min:3|max:10',
            'session_timeout' => 'required|integer|min:5|max:1440',
            'force_https' => 'nullable|boolean',
            'password_min_length' => 'required|integer|min:6|max:32',
            'password_require_uppercase' => 'nullable|boolean',
            'password_require_number' => 'nullable|boolean',
            'password_require_special' => 'nullable|boolean',
        ]);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        if ($user->hasRole('Super Admin')) {
            SystemSetting::set('security', 'enable_2fa', $request->boolean('enable_2fa'), 'boolean', $societyId);
            SystemSetting::set('security', 'force_https', $request->boolean('force_https'), 'boolean', $societyId);
        }

        SystemSetting::set('security', 'login_attempt_limit', $validated['login_attempt_limit'], 'integer', $societyId);
        SystemSetting::set('security', 'session_timeout', $validated['session_timeout'], 'integer', $societyId);
        SystemSetting::set('security', 'password_min_length', $validated['password_min_length'], 'integer', $societyId);
        SystemSetting::set('security', 'password_require_uppercase', $request->boolean('password_require_uppercase'), 'boolean', $societyId);
        SystemSetting::set('security', 'password_require_number', $request->boolean('password_require_number'), 'boolean', $societyId);
        SystemSetting::set('security', 'password_require_special', $request->boolean('password_require_special'), 'boolean', $societyId);

        return back()->with('success', 'Security settings updated successfully!');
    }

    public function updatePermissions(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'array',
            'permissions.*.*' => 'nullable|boolean',
        ]);

        $societyId = $user->society_id;
        $permissions = $validated['permissions'] ?? [];

        // Store the permissions configuration
        SystemSetting::set('permissions', 'module_permissions', $permissions, 'json', $societyId);

        return back()->with('success', 'Role permissions updated successfully!');
    }

    public function updateCurrencyAndFeatures(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'currency' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:5',
            'currency_position' => 'required|in:left,right',
            'decimal_places' => 'required|integer|min:0|max:4',
            'thousand_separator' => 'required|string|max:1',
            'decimal_separator' => 'required|string|max:1',
            'enable_facilities' => 'nullable|boolean',
            'enable_visitors' => 'nullable|boolean',
            'enable_complaints' => 'nullable|boolean',
            'enable_bills' => 'nullable|boolean',
            'enable_services' => 'nullable|boolean',
            'enable_notices' => 'nullable|boolean',
            'enable_parking' => 'nullable|boolean',
            'enable_reports' => 'nullable|boolean',
            'enable_events' => 'nullable|boolean',
            'enable_tenant_module' => 'nullable|boolean',
            'enable_villa_module' => 'nullable|boolean',
        ]);

        $societyId = $user->society_id;

        // Store currency settings
        SystemSetting::set('currency', 'currency', $validated['currency'], 'string', $societyId);
        SystemSetting::set('currency', 'currency_symbol', $validated['currency_symbol'], 'string', $societyId);
        SystemSetting::set('currency', 'currency_position', $validated['currency_position'], 'string', $societyId);
        SystemSetting::set('currency', 'decimal_places', $validated['decimal_places'], 'integer', $societyId);
        SystemSetting::set('currency', 'thousand_separator', $validated['thousand_separator'], 'string', $societyId);
        SystemSetting::set('currency', 'decimal_separator', $validated['decimal_separator'], 'string', $societyId);

        // Store feature toggles
        SystemSetting::set('features', 'enable_facilities', $request->boolean('enable_facilities'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_visitors', $request->boolean('enable_visitors'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_complaints', $request->boolean('enable_complaints'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_bills', $request->boolean('enable_bills'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_services', $request->boolean('enable_services'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_notices', $request->boolean('enable_notices'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_parking', $request->boolean('enable_parking'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_reports', $request->boolean('enable_reports'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_events', $request->boolean('enable_events'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_tenant_module', $request->boolean('enable_tenant_module'), 'boolean', $societyId);
        SystemSetting::set('features', 'enable_villa_module', $request->boolean('enable_villa_module'), 'boolean', $societyId);

        return back()->with('success', 'Currency and features updated successfully!');
    }

    public function clearCache()
    {
        $user = auth()->user();
        
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        try {
            $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;
            SystemSetting::clearCache($societyId);
            return back()->with('success', 'Settings cache cleared successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }

    public function storeComplaintCategory(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        \App\Models\ComplaintCategory::create([
            'society_id' => $user->society_id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'status' => 'active',
        ]);

        return back()->with('success', 'Complaint category added successfully!');
    }

    public function toggleComplaintCategory($id)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $category = \App\Models\ComplaintCategory::where('society_id', $user->society_id)->findOrFail($id);
        
        $category->update([
            'status' => $category->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'Complaint category status updated successfully!');
    }

    public function destroyComplaintCategory($id)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin')) {
            abort(403, 'Unauthorized access.');
        }

        $category = \App\Models\ComplaintCategory::where('society_id', $user->society_id)->findOrFail($id);
        $category->delete();

        return back()->with('success', 'Complaint category deleted successfully!');
    }
}