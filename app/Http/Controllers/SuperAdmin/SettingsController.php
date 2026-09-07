<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Exception;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'app');
        
        // Super Admin always manages global settings (society_id = null)
        $settings = [
            'app' => SystemSetting::getGroup('app', null),
            'language' => SystemSetting::getGroup('language', null),
            'storage' => SystemSetting::getGroup('storage', null),
            'theme' => SystemSetting::getGroup('theme', null),
            'currency' => SystemSetting::getGroup('currency', null),
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

    public function updateApp(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:255',
            'default_language' => 'required|string|max:10',
            'default_currency' => 'required|string|max:10',
            'society_requires_approval' => 'nullable|boolean',
        ]);

        // Super Admin sets global defaults (society_id = null)
        SystemSetting::set('app', 'app_name', $validated['app_name'], 'string', null);
        SystemSetting::set('app', 'default_language', $validated['default_language'], 'string', null);
        SystemSetting::set('app', 'default_currency', $validated['default_currency'], 'string', null);
        SystemSetting::set('app', 'society_requires_approval', $request->boolean('society_requires_approval'), 'boolean', null);

        return back()->with('success', 'App settings updated successfully!');
    }

    public function updateLanguage(Request $request)
    {
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
        $validated = $request->validate([
            'primary_color' => 'required|string|max:20',
            'secondary_color' => 'required|string|max:20',
            'sidebar_theme' => 'required|in:light,dark',
            'button_style' => 'required|in:rounded,square,pill',
        ]);

        SystemSetting::setGroup('theme', $validated, [], null);

        return back()->with('success', 'Theme settings updated successfully!');
    }

    public function updateCurrency(Request $request)
    {
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

        SystemSetting::set('email', 'mail_driver', $validated['mail_driver'], 'string', null);
        SystemSetting::set('email', 'mail_host', $validated['mail_host'], 'string', null);
        SystemSetting::set('email', 'mail_port', $validated['mail_port'], 'integer', null);
        SystemSetting::set('email', 'mail_username', $validated['mail_username'], 'string', null);
        SystemSetting::set('email', 'mail_password', $validated['mail_password'], 'encrypted', null);
        SystemSetting::set('email', 'mail_encryption', $validated['mail_encryption'], 'string', null);
        SystemSetting::set('email', 'mail_from_address', $validated['mail_from_address'], 'string', null);
        SystemSetting::set('email', 'mail_from_name', $validated['mail_from_name'], 'string', null);

        return back()->with('success', 'Email settings updated successfully!');
    }

    public function testEmail(Request $request)
    {
        $request->validate(['test_email' => 'required|email']);

        try {
            Mail::raw('This is a test email from SocietyFlow.', function ($message) use ($request) {
                $message->to($request->test_email)
                    ->subject('Test Email - SocietyFlow');
            });

            return response()->json(['success' => true, 'message' => 'Test email sent successfully!']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()]);
        }
    }

    public function updatePayment(Request $request)
    {
        $validated = $request->validate([
            'payment_test_mode' => 'nullable|boolean',
            // Razorpay
            'razorpay_enabled' => 'nullable|boolean',
            'razorpay_key' => 'nullable|string|max:255',
            'razorpay_secret' => 'nullable|string|max:255',
            // Stripe
            'stripe_enabled' => 'nullable|boolean',
            'stripe_key' => 'nullable|string|max:255',
            'stripe_secret' => 'nullable|string|max:255',
            // PayPal
            'paypal_enabled' => 'nullable|boolean',
            'paypal_client_id' => 'nullable|string|max:255',
            'paypal_secret' => 'nullable|string|max:255',
            // Square
            'square_enabled' => 'nullable|boolean',
            'square_app_id' => 'nullable|string|max:255',
            'square_access_token' => 'nullable|string|max:255',
            'square_location_id' => 'nullable|string|max:255',
            'square_environment' => 'nullable|in:sandbox,production',
            // Paytm
            'paytm_enabled' => 'nullable|boolean',
            'paytm_merchant_id' => 'nullable|string|max:255',
            'paytm_merchant_key' => 'nullable|string|max:255',
            'paytm_website' => 'nullable|string|max:255',
            'paytm_industry_type' => 'nullable|string|max:255',
            // PhonePe
            'phonepe_enabled' => 'nullable|boolean',
            'phonepe_merchant_id' => 'nullable|string|max:255',
            'phonepe_salt_key' => 'nullable|string|max:255',
            'phonepe_salt_index' => 'nullable|string|max:10',
            'phonepe_environment' => 'nullable|in:sandbox,production',
            // Cashfree
            'cashfree_enabled' => 'nullable|boolean',
            'cashfree_app_id' => 'nullable|string|max:255',
            'cashfree_secret_key' => 'nullable|string|max:255',
            'cashfree_environment' => 'nullable|in:sandbox,production',
            // Instamojo
            'instamojo_enabled' => 'nullable|boolean',
            'instamojo_api_key' => 'nullable|string|max:255',
            'instamojo_auth_token' => 'nullable|string|max:255',
            'instamojo_environment' => 'nullable|in:test,production',
            // CCAvenue
            'ccavenue_enabled' => 'nullable|boolean',
            'ccavenue_merchant_id' => 'nullable|string|max:255',
            'ccavenue_working_key' => 'nullable|string|max:255',
            'ccavenue_access_code' => 'nullable|string|max:255',
        ]);

        SystemSetting::set('payment', 'payment_test_mode', $request->boolean('payment_test_mode'), 'boolean', null);
        
        // Razorpay
        SystemSetting::set('payment', 'razorpay_enabled', $request->boolean('razorpay_enabled'), 'boolean', null);
        if ($validated['razorpay_key']) {
            SystemSetting::set('payment', 'razorpay_key', $validated['razorpay_key'], 'encrypted', null);
        }
        if ($validated['razorpay_secret']) {
            SystemSetting::set('payment', 'razorpay_secret', $validated['razorpay_secret'], 'encrypted', null);
        }

        // Stripe
        SystemSetting::set('payment', 'stripe_enabled', $request->boolean('stripe_enabled'), 'boolean', null);
        if ($validated['stripe_key']) {
            SystemSetting::set('payment', 'stripe_key', $validated['stripe_key'], 'encrypted', null);
        }
        if ($validated['stripe_secret']) {
            SystemSetting::set('payment', 'stripe_secret', $validated['stripe_secret'], 'encrypted', null);
        }

        // PayPal
        SystemSetting::set('payment', 'paypal_enabled', $request->boolean('paypal_enabled'), 'boolean', null);
        if ($validated['paypal_client_id']) {
            SystemSetting::set('payment', 'paypal_client_id', $validated['paypal_client_id'], 'encrypted', null);
        }
        if ($validated['paypal_secret']) {
            SystemSetting::set('payment', 'paypal_secret', $validated['paypal_secret'], 'encrypted', null);
        }

        // Square
        SystemSetting::set('payment', 'square_enabled', $request->boolean('square_enabled'), 'boolean', null);
        if ($validated['square_app_id']) {
            SystemSetting::set('payment', 'square_app_id', $validated['square_app_id'], 'encrypted', null);
        }
        if ($validated['square_access_token']) {
            SystemSetting::set('payment', 'square_access_token', $validated['square_access_token'], 'encrypted', null);
        }
        if ($validated['square_location_id']) {
            SystemSetting::set('payment', 'square_location_id', $validated['square_location_id'], 'string', null);
        }
        if ($validated['square_environment']) {
            SystemSetting::set('payment', 'square_environment', $validated['square_environment'], 'string', null);
        }

        // Paytm
        SystemSetting::set('payment', 'paytm_enabled', $request->boolean('paytm_enabled'), 'boolean', null);
        if ($validated['paytm_merchant_id']) {
            SystemSetting::set('payment', 'paytm_merchant_id', $validated['paytm_merchant_id'], 'encrypted', null);
        }
        if ($validated['paytm_merchant_key']) {
            SystemSetting::set('payment', 'paytm_merchant_key', $validated['paytm_merchant_key'], 'encrypted', null);
        }
        if ($validated['paytm_website']) {
            SystemSetting::set('payment', 'paytm_website', $validated['paytm_website'], 'string', null);
        }
        if ($validated['paytm_industry_type']) {
            SystemSetting::set('payment', 'paytm_industry_type', $validated['paytm_industry_type'], 'string', null);
        }

        // PhonePe
        SystemSetting::set('payment', 'phonepe_enabled', $request->boolean('phonepe_enabled'), 'boolean', null);
        if ($validated['phonepe_merchant_id']) {
            SystemSetting::set('payment', 'phonepe_merchant_id', $validated['phonepe_merchant_id'], 'encrypted', null);
        }
        if ($validated['phonepe_salt_key']) {
            SystemSetting::set('payment', 'phonepe_salt_key', $validated['phonepe_salt_key'], 'encrypted', null);
        }
        if ($validated['phonepe_salt_index']) {
            SystemSetting::set('payment', 'phonepe_salt_index', $validated['phonepe_salt_index'], 'string', null);
        }
        if ($validated['phonepe_environment']) {
            SystemSetting::set('payment', 'phonepe_environment', $validated['phonepe_environment'], 'string', null);
        }

        // Cashfree
        SystemSetting::set('payment', 'cashfree_enabled', $request->boolean('cashfree_enabled'), 'boolean', null);
        if ($validated['cashfree_app_id']) {
            SystemSetting::set('payment', 'cashfree_app_id', $validated['cashfree_app_id'], 'encrypted', null);
        }
        if ($validated['cashfree_secret_key']) {
            SystemSetting::set('payment', 'cashfree_secret_key', $validated['cashfree_secret_key'], 'encrypted', null);
        }
        if ($validated['cashfree_environment']) {
            SystemSetting::set('payment', 'cashfree_environment', $validated['cashfree_environment'], 'string', null);
        }

        // Instamojo
        SystemSetting::set('payment', 'instamojo_enabled', $request->boolean('instamojo_enabled'), 'boolean', null);
        if ($validated['instamojo_api_key']) {
            SystemSetting::set('payment', 'instamojo_api_key', $validated['instamojo_api_key'], 'encrypted', null);
        }
        if ($validated['instamojo_auth_token']) {
            SystemSetting::set('payment', 'instamojo_auth_token', $validated['instamojo_auth_token'], 'encrypted', null);
        }
        if ($validated['instamojo_environment']) {
            SystemSetting::set('payment', 'instamojo_environment', $validated['instamojo_environment'], 'string', null);
        }

        // CCAvenue
        SystemSetting::set('payment', 'ccavenue_enabled', $request->boolean('ccavenue_enabled'), 'boolean', null);
        if ($validated['ccavenue_merchant_id']) {
            SystemSetting::set('payment', 'ccavenue_merchant_id', $validated['ccavenue_merchant_id'], 'encrypted', null);
        }
        if ($validated['ccavenue_working_key']) {
            SystemSetting::set('payment', 'ccavenue_working_key', $validated['ccavenue_working_key'], 'encrypted', null);
        }
        if ($validated['ccavenue_access_code']) {
            SystemSetting::set('payment', 'ccavenue_access_code', $validated['ccavenue_access_code'], 'string', null);
        }

        return back()->with('success', 'Payment gateway settings updated successfully!');
    }

    public function updatePush(Request $request)
    {
        $validated = $request->validate([
            'push_enabled' => 'nullable|boolean',
            'fcm_server_key' => 'nullable|string',
            'vapid_public_key' => 'nullable|string',
            'vapid_private_key' => 'nullable|string',
        ]);

        SystemSetting::set('push', 'push_enabled', $request->boolean('push_enabled'), 'boolean', null);
        
        if ($validated['fcm_server_key']) {
            SystemSetting::set('push', 'fcm_server_key', $validated['fcm_server_key'], 'encrypted', null);
        }
        if ($validated['vapid_public_key']) {
            SystemSetting::set('push', 'vapid_public_key', $validated['vapid_public_key'], 'string', null);
        }
        if ($validated['vapid_private_key']) {
            SystemSetting::set('push', 'vapid_private_key', $validated['vapid_private_key'], 'encrypted', null);
        }

        return back()->with('success', 'Push notification settings updated successfully!');
    }

    public function updateSecurity(Request $request)
    {
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

        SystemSetting::set('security', 'enable_2fa', $request->boolean('enable_2fa'), 'boolean', null);
        SystemSetting::set('security', 'login_attempt_limit', $validated['login_attempt_limit'], 'integer', null);
        SystemSetting::set('security', 'session_timeout', $validated['session_timeout'], 'integer', null);
        SystemSetting::set('security', 'force_https', $request->boolean('force_https'), 'boolean', null);
        SystemSetting::set('security', 'password_min_length', $validated['password_min_length'], 'integer', null);
        SystemSetting::set('security', 'password_require_uppercase', $request->boolean('password_require_uppercase'), 'boolean', null);
        SystemSetting::set('security', 'password_require_number', $request->boolean('password_require_number'), 'boolean', null);
        SystemSetting::set('security', 'password_require_special', $request->boolean('password_require_special'), 'boolean', null);

        return back()->with('success', 'Security settings updated successfully!');
    }

    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            SystemSetting::clearCache(null); // Clear global cache

            return back()->with('success', 'All caches cleared successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to clear cache: ' . $e->getMessage());
        }
    }
}
