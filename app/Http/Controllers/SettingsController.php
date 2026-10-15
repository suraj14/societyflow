<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Traits\HandlesFormSubmissions;
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
    use HandlesFormSubmissions;

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
            'sms' => SystemSetting::getGroup('sms', $societyId),
            'whatsapp' => SystemSetting::getGroup('whatsapp', $societyId),
            'security' => SystemSetting::getGroup('security', $societyId),
            'permissions' => SystemSetting::getGroup('permissions', $societyId),
            'currency' => SystemSetting::getGroup('currency', $societyId),
            'features' => SystemSetting::getGroup('features', $societyId),
        ];

        // Saved provider credentials are encrypted at rest, but were being
        // decrypted and rendered into value="..." - visible in page source
        // whatever the input type said. The form now receives a mask and
        // posting it back means "unchanged" (see SettingSecrets).
        $settings = \App\Support\SettingSecrets::maskAll($settings);

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
            'security' => SystemSetting::getGroup('security', null),
            'whatsapp' => SystemSetting::getGroup('whatsapp', null),
        ];

        // Same masking as the society branch - the global settings page
        // renders the same inputs from the same group names.
        $settings = \App\Support\SettingSecrets::maskAll($settings);

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
            // societies.address is a NOT NULL column - this used to be
            // 'nullable', so clearing the field (or Laravel's own
            // ConvertEmptyStringsToNull middleware turning "" into null)
            // crashed the whole request with an uncaught QueryException
            // (a 500, not a validation error) instead of telling the admin
            // what was wrong.
            'society_address' => 'required|string',
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

            // The sidebar/header branding text is a *separate* SystemSetting
            // ('app'.'app_name', read through ThemeHelper's own cache) that
            // this form never touched - so saving a new Society Name here
            // correctly updated societies.name, but the header kept showing
            // whatever app_name had been seeded with, which looked like the
            // save had no real effect. Keep them in sync by default; the
            // App Settings tab's own "Display Name" field can still be used
            // afterward to deliberately diverge from the official name.
            SystemSetting::set('app', 'app_name', $validated['society_name'], 'string', $user->society_id);
            \App\Helpers\ThemeHelper::clearCache($user->society_id);
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
                // The sidebar/header branding text - a real SystemSetting
                // ('app'.'app_name', scoped per society) that ThemeHelper
                // already reads and caches, but until now only Super Admin
                // could ever write it (and only the global row, never a
                // specific society's). This is the field admins actually
                // meant when they expected "Society Name" to change what
                // the sidebar shows - that field only ever updated the
                // Society model's own `name` column, a separate concept.
                'app_name' => 'nullable|string|max:255',
                'default_language' => 'required|string|max:10',
                'default_currency' => 'required|string|max:10',
                'timezone' => 'required|string|max:50',
                'date_format' => 'required|string|max:20',
                'enable_email_notifications' => 'nullable|boolean',
                'auto_approve_residents' => 'nullable|boolean',
            ]);

            if (!empty($validated['app_name'])) {
                SystemSetting::set('app', 'app_name', $validated['app_name'], 'string', $societyId);
            }
            SystemSetting::set('app', 'default_language', $validated['default_language'], 'string', $societyId);
            SystemSetting::set('app', 'default_currency', $validated['default_currency'], 'string', $societyId);
            SystemSetting::set('app', 'timezone', $validated['timezone'], 'string', $societyId);
            // The society's calendar (App\Support\SocietyClock reads
            // societies.timezone): "today", month boundaries, overdue and
            // access-pass hours all follow this. The SystemSetting row above
            // is kept for the settings page's own read-back.
            if ($societyId && in_array($validated['timezone'], timezone_identifiers_list(), true)) {
                \App\Models\Society::whereKey($societyId)->update(['timezone' => $validated['timezone']]);
            }
            SystemSetting::set('app', 'date_format', $validated['date_format'], 'string', $societyId);
            SystemSetting::set('app', 'enable_email_notifications', $request->boolean('enable_email_notifications'), 'boolean', $societyId);
            SystemSetting::set('app', 'auto_approve_residents', $request->boolean('auto_approve_residents'), 'boolean', $societyId);

            // app_name is read through ThemeHelper's own 1-hour cache layer,
            // not through $settings['app'] directly - without this, the
            // sidebar would keep showing the old name for up to an hour.
            \App\Helpers\ThemeHelper::clearCache($societyId);
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
            // The form is shown a mask for a saved key, never the key: posting the mask back means "unchanged".
            SystemSetting::set('storage', 'aws_key', \App\Support\SettingSecrets::resolve('storage', 'aws_key', $validated['aws_key'] ?? null, null), 'encrypted', null);
            SystemSetting::set('storage', 'aws_secret', \App\Support\SettingSecrets::resolve('storage', 'aws_secret', $validated['aws_secret'] ?? null, null), 'encrypted', null);
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
                if (!\App\Support\ExternalMessaging::enabled()) {
                    return response()->json(['success' => false, 'message' => 'Not tested: external services are disabled in this environment (EXTERNAL_MESSAGING_ENABLED=false).']);
                }

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
            // The provider's message can quote the access key; never hand a credential back to the browser.
            $message = \App\Services\SmsService::safeProviderMessage(mb_substr($e->getMessage(), 0, 300), (string) SystemSetting::get('storage', 'aws_secret', '', null));
            $key = (string) SystemSetting::get('storage', 'aws_key', '', null);
            if ($key !== '') {
                $message = str_replace($key, '[hidden]', $message);
            }

            return response()->json(['success' => false, 'message' => 'Connection failed: ' . $message]);
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

        // Handle logo upload (Admin only). Previously built the stored
        // filename from the client's own filename (the same bug confirmed
        // live in UserController's avatar upload - a colon in the source
        // filename crashes move() on Windows) and never deleted the old
        // logo "to avoid fileinfo error" - deleting a file touches no
        // fileinfo function; that comment described a workaround for this
        // same unfixed bug, not a reason to leak the old file forever.
        if ($request->hasFile('society_logo') && $user->hasRole('Admin')) {
            try {
                $oldLogo = SystemSetting::get('theme', 'society_logo', null, $societyId);
                $logoPath = $this->handleFileUpload($request, 'society_logo', 'society-logos', ['jpeg', 'png', 'jpg']);
                SystemSetting::set('theme', 'society_logo', $logoPath, 'string', $societyId);
                if ($oldLogo) {
                    $this->deleteUploadedFile($oldLogo);
                }

                \Log::info('Logo uploaded successfully', ['path' => $logoPath, 'society_id' => $societyId]);
            } catch (\Exception $e) {
                \Log::error('Logo upload failed', ['error' => $e->getMessage(), 'society_id' => $societyId]);
                return back()->with('error', 'Failed to upload logo: ' . $e->getMessage());
            }
        }

        // Handle favicon upload (Admin only) - see the logo block above.
        if ($request->hasFile('society_favicon') && $user->hasRole('Admin')) {
            try {
                $oldFavicon = SystemSetting::get('theme', 'society_favicon', null, $societyId);
                $faviconPath = $this->handleFileUpload($request, 'society_favicon', 'society-favicons', ['jpeg', 'png', 'jpg', 'ico']);
                SystemSetting::set('theme', 'society_favicon', $faviconPath, 'string', $societyId);
                if ($oldFavicon) {
                    $this->deleteUploadedFile($oldFavicon);
                }

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

        // "No encryption" is stored as empty, not the literal word "null" (which the mailer would receive as a scheme).
        if (($validated['mail_encryption'] ?? null) === 'null') {
            $validated['mail_encryption'] = null;
        }

        SystemSetting::set('email', 'mail_driver', $validated['mail_driver'], 'string', $societyId);
        SystemSetting::set('email', 'mail_host', $validated['mail_host'], 'string', $societyId);
        SystemSetting::set('email', 'mail_port', $validated['mail_port'], 'integer', $societyId);
        SystemSetting::set('email', 'mail_username', $validated['mail_username'], 'string', $societyId);
        // The form was shown a mask, not the stored password. Posting the
        // mask back means "unchanged" - without this, simply opening the
        // page and pressing Save would overwrite the real password with
        // asterisks and silently break outgoing mail.
        SystemSetting::set(
            'email',
            'mail_password',
            \App\Support\SettingSecrets::resolve('email', 'mail_password', $validated['mail_password'], $societyId),
            'encrypted',
            $societyId
        );
        SystemSetting::set('email', 'mail_encryption', $validated['mail_encryption'], 'string', $societyId);
        SystemSetting::set('email', 'mail_from_address', $validated['mail_from_address'], 'string', $societyId);
        SystemSetting::set('email', 'mail_from_name', $validated['mail_from_name'], 'string', $societyId);

        // Apply settings to current Laravel mail configuration
        // Apply the real stored password, never the mask the form echoed back.
        EmailConfigurationService::applyMailConfiguration(
            ['mail_password' => SystemSetting::get('email', 'mail_password', null, $societyId)] + $validated
        );

        // New settings have not been tested yet - the old "Connected" no
        // longer describes them.
        \App\Support\ChannelStatus::clear('email', $societyId);

        return redirect()->route('settings.index', ['tab' => 'email'])->with('success', 'Email settings saved. Send a test email to confirm they work.');
    }

    /**
     * Sends a real email through the society's SAVED settings (not whatever
     * is typed in the form) and records the outcome as the channel status.
     * The admin sees a plain result; the provider's own error is only in
     * "View technical details", sanitised so the password never appears.
     */
    public function testEmail(Request $request)
    {
        $request->validate(['test_email' => 'required|email']);

        $user = auth()->user();
        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;
        $emailSettings = EmailConfigurationService::loadAndApplySettings($societyId);

        // Never report "sent" for a message that was only written to the log.
        if (!\App\Support\ExternalMessaging::enabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Not sent: external messaging is disabled in this environment (EXTERNAL_MESSAGING_ENABLED=false).',
                'details' => null,
            ]);
        }

        if (empty($emailSettings['mail_host']) || empty($emailSettings['mail_from_address'])) {
            return response()->json([
                'success' => false,
                'message' => 'Email is not set up yet. Fill in the SMTP host and From email address, save, then test.',
                'details' => null,
            ]);
        }

        $subject = 'Test email - ' . ($user->society->name ?? 'SocietyFlow');

        try {
            Mail::raw(
                "This is a test email from SocietyFlow.\n\nIf you can read this, your email settings work and SocietyFlow will send society notifications from this address.",
                fn ($message) => $message->to($request->test_email)->subject($subject)
            );
        } catch (\Throwable $e) {
            $detail = \App\Services\SmsService::safeProviderMessage(
                mb_substr($e->getMessage(), 0, 500),
                (string) ($emailSettings['mail_password'] ?? '')
            );
            \App\Support\ChannelStatus::record('email', $societyId, false, 'Test email failed', $detail);
            Log::warning('Settings test email failed', ['society_id' => $societyId, 'error_class' => get_class($e)]);

            return response()->json([
                'success' => false,
                'message' => 'Test email failed. Please check your SMTP settings.',
                'details' => $detail,
            ]);
        }

        \App\Support\ChannelStatus::record('email', $societyId, true, "Test email sent to {$request->test_email}");

        return response()->json([
            'success' => true,
            'message' => "Test email sent successfully to {$request->test_email}. Check the inbox (and spam folder).",
            'details' => null,
        ]);
    }

    public function updatePayment(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        $rules = [
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
        ];
        $validated = $request->validate($rules);
        // validate() leaves out every optional field the form did not send; this method reads them
        // all by key, so a partial submission used to crash with "Undefined array key". Treat absent as empty.
        $validated += array_fill_keys(array_keys($rules), null);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        // The form was shown a mask for each saved credential, never the
        // credential itself. Blanking the masked ones here means the
        // existing `if ($validated[...])` guards below skip them and the
        // stored value survives - otherwise opening the page and pressing
        // Save would overwrite every live gateway secret with asterisks.
        foreach (\App\Support\SettingSecrets::keysFor('payment') as $secretKey) {
            if (\App\Support\SettingSecrets::isMask($validated[$secretKey] ?? null)) {
                $validated[$secretKey] = null;
            }
        }

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

    public function updateSms(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'sms_provider'               => 'required|in:fast2sms,bulksmsplans,custom',
            'sms_enabled'                => 'nullable|boolean',
            'fast2sms_api_key'           => 'nullable|string|max:500',
            // dlt = Sender ID + Message ID (default), quick = testing only (no DLT), otp = Fast2SMS OTP service (Advanced)
            'fast2sms_route'             => 'nullable|in:otp,dlt,quick',
            // DLT headers are 3-6 characters (letters; a digit is tolerated because older saved IDs such as DISP24 have one)
            'fast2sms_sender_id'         => ['nullable', 'regex:/^[A-Za-z][A-Za-z0-9]{2,5}$/'],
            'fast2sms_template_id'       => ['nullable', 'regex:/^[0-9]{1,15}$/'],
            'fast2sms_otp_id'            => 'nullable|string|max:50',
            // DLT compliance metadata (Entity ID). Fast2SMS's send APIs do not
            // take this as a request parameter - it is the registration record
            // kept for the admin's own reference, matched against what the
            // telecom DLT portal has on file.
            'fast2sms_entity_id'         => 'nullable|string|max:30',
            'otp_sms_enabled'            => 'nullable|boolean',
            'visitor_sms_enabled'        => 'nullable|boolean',
            'access_pass_sms_enabled'    => 'nullable|boolean',
            'notification_sms_enabled'   => 'nullable|boolean',
            'bulksmsplans_api_id'        => 'nullable|string|max:200',
            'bulksmsplans_api_password'  => 'nullable|string|max:200',
            'bulksmsplans_sender_id'     => 'nullable|string|max:20',
            'bulksmsplans_template_id'   => 'nullable|string|max:50',
            'custom_sms_api_url'         => 'nullable|string|max:1000',
        ], [
            'fast2sms_sender_id.regex'   => 'Sender ID must be 3 to 6 letters, for example BOOKMZ.',
            'fast2sms_template_id.regex' => 'Message ID is the number Fast2SMS shows next to your template, for example 223577.',
        ]);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        SystemSetting::set('sms', 'sms_enabled', $request->boolean('sms_enabled'), 'boolean', $societyId);
        SystemSetting::set('sms', 'sms_provider',  $request->sms_provider, 'string', $societyId);

        // A masked field means "unchanged" - never store the mask itself.
        // SettingSecrets::isMask() is checked here because filled() alone
        // treats the mask as a real new value and overwrites the saved key.
        if ($request->filled('fast2sms_api_key') && !\App\Support\SettingSecrets::isMask($request->fast2sms_api_key)) {
            SystemSetting::set('sms', 'fast2sms_api_key', $request->fast2sms_api_key, 'encrypted', $societyId);
        }
        // Routing between DLT and Quick SMS is automatic. The only saved choice is the Advanced OTP-service option
        // ('fast2sms_use_otp_service'); an explicit 'fast2sms_route' from older clients is still honoured.
        $route = $request->has('fast2sms_route')
            ? $request->fast2sms_route
            : ($request->boolean('fast2sms_use_otp_service') ? 'otp' : 'dlt');
        SystemSetting::set('sms', 'fast2sms_route', $route ?: 'dlt', 'string', $societyId);
        // Sender ID and the default Message ID are optional plain config: a field that is sent empty clears the value
        // (so a default can be removed); a field that is not sent at all leaves it alone.
        foreach (['fast2sms_sender_id' => 'strtoupper', 'fast2sms_template_id' => null, 'fast2sms_otp_id' => null, 'fast2sms_entity_id' => null] as $field => $normalise) {
            if ($request->has($field)) {
                $value = trim((string) $request->input($field, ''));
                SystemSetting::set('sms', $field, $normalise ? $normalise($value) : $value, 'string', $societyId);
            }
        }

        // Per-purpose channels. 'otp_sms_enabled' mirrors 'sms_enabled' (the
        // flag SmsService::sendOtp has always read) under the clearer name
        // the Settings page now shows; the other three gate DLT notification
        // sends a caller makes through SmsService::sendDltNotification() for
        // that purpose - see that method's docblock for what is, and is not,
        // wired to an actual trigger yet.
        SystemSetting::set('sms', 'otp_sms_enabled', $request->boolean('otp_sms_enabled', $request->boolean('sms_enabled')), 'boolean', $societyId);
        // Only toggles present in the form are written: the simplified SMS
        // page shows one "notifications by SMS" switch, and a toggle missing
        // from the form must keep its saved value rather than turn off.
        foreach (['visitor_sms_enabled', 'access_pass_sms_enabled', 'notification_sms_enabled'] as $toggle) {
            if ($request->has($toggle)) {
                SystemSetting::set('sms', $toggle, $request->boolean($toggle), 'boolean', $societyId);
            }
        }
        if ($request->filled('bulksmsplans_api_id') && !\App\Support\SettingSecrets::isMask($request->bulksmsplans_api_id)) {
            SystemSetting::set('sms', 'bulksmsplans_api_id', $request->bulksmsplans_api_id, 'encrypted', $societyId);
        }
        if ($request->filled('bulksmsplans_api_password') && !\App\Support\SettingSecrets::isMask($request->bulksmsplans_api_password)) {
            SystemSetting::set('sms', 'bulksmsplans_api_password', $request->bulksmsplans_api_password, 'encrypted', $societyId);
        }
        if ($request->filled('bulksmsplans_sender_id')) {
            SystemSetting::set('sms', 'bulksmsplans_sender_id', $request->bulksmsplans_sender_id, 'string', $societyId);
        }
        SystemSetting::set('sms', 'bulksmsplans_template_id', $request->bulksmsplans_template_id ?? '0', 'string', $societyId);
        if ($request->filled('custom_sms_api_url')) {
            SystemSetting::set('sms', 'custom_sms_api_url', $request->custom_sms_api_url, 'string', $societyId);
        }

        \App\Support\ChannelStatus::clear('sms', $societyId);

        return redirect()->route('settings.index', ['tab' => 'sms'])->with('success', 'SMS settings saved. Press "Send Test SMS" below to see Fast2SMS\'s reply.');
    }

    public function testSms(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
            // Lets the Settings page test OTP and DLT independently of
            // whichever route is currently saved - without this, there was
            // no way to verify the one not selected before switching to it.
            'route' => 'nullable|in:otp,dlt,quick',
            // 'notification' tests the template used for notification SMS.
            'template' => 'nullable|in:otp,notification',
            'test_value' => 'nullable|string|max:30',
            // pipe-separated values for the template's variables, e.g. "1234|Alpha Heights"
            'variables' => 'nullable|string|max:200',
            // free text from the Test SMS box; routed automatically (DLT when a Sender ID + Message ID are saved, else Quick SMS)
            'message' => 'nullable|string|max:320',
        ]);

        $user      = auth()->user();
        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;
        $value     = trim((string) $request->input('variables', '')) ?: (trim((string) $request->input('test_value', '')) ?: '1234');

        try {
            $result = match (true) {
                $request->input('template') === 'notification'
                    => \App\Services\SmsService::sendDltNotification('notification', $request->phone, $value, $societyId, force: true),
                $request->filled('message') && !$request->filled('route')
                    => \App\Services\SmsService::sendTest($request->phone, (string) $request->input('message'), $societyId),
                default
                    => \App\Services\SmsService::sendOtp($request->phone, $value, $societyId, $request->route),
            };
        } catch (\Throwable $e) {
            // Never a 500 for the admin, and never the exception text: it can
            // carry request detail. The class is enough to diagnose.
            \Illuminate\Support\Facades\Log::error('Test SMS failed', ['class' => get_class($e), 'society_id' => $societyId]);

            return response()->json([
                'success' => false,
                'message' => 'Test SMS failed. The SMS service could not be reached - try again.',
                'details' => 'Fast2SMS request failed: ' . get_class($e),
            ]);
        }

        // Success is only reported when the provider actually accepted the
        // request - never because SMS is switched off (dev_mode).
        $result = \App\Services\SmsService::stamp($result);

        // A skipped test never claims anything was sent, says exactly why, and does not touch the channel status
        // (nothing was checked with the provider).
        if (\App\Services\SmsService::wasSkipped($result)) {
            $why = match (true) {
                $result['status'] === \App\Services\SmsService::STATUS_SKIPPED_NOT_CONFIGURED && ($result['message'] ?? '') !== 'dev_mode'
                    => $result['message'],
                $result['status'] === \App\Services\SmsService::STATUS_SKIPPED_NOT_CONFIGURED
                    => 'the Fast2SMS API key is not saved yet. Enter it, save, then test again.',
                ($result['message'] ?? '') === 'dev_mode'
                    => 'SMS is turned off for this society. Switch on "Enable SMS / OTP", save, then test again.',
                default => 'real SMS delivery is switched off on this server, so no message was sent.',
            };

            return response()->json(['success' => false, 'skipped' => true, 'status' => $result['status'], 'message' => 'Not sent: ' . $why]);
        }

        if ($result['success'] && $result['message'] !== 'dev_mode') {
            \App\Support\ChannelStatus::record('sms', $societyId, true, 'Test SMS accepted for ' . \App\Services\SmsService::maskPhone($request->phone));

            return response()->json([
                'success' => true,
                'message' => 'Fast2SMS accepted the test SMS for ' . \App\Services\SmsService::maskPhone($request->phone) . '. That means it was handed over for sending - it is not a delivery confirmation.',
                'raw'     => $result['raw'] ?? null,
            ]);
        }

        if ($result['message'] === 'dev_mode') {
            return response()->json([
                'success' => false,
                'message' => 'SMS is turned off for this society. Switch on "Enable SMS / OTP", save, then test again.',
            ]);
        }

        // Provider messages are passed through only after the key and any
        // header value are stripped; the service never puts them in its text,
        // but this keeps that guarantee in one place.
        $provider = \App\Services\SmsService::safeProviderMessage($result['message'] ?? '');

        // Problems SocietyFlow itself found before any request was made: say exactly that, in its own plain words.
        if (in_array($result['error_code'] ?? '', ['sender_missing', 'invalid_message_id', 'missing_variables', 'invalid_number'], true)) {
            return response()->json(['success' => false, 'message' => 'Not sent: ' . preg_replace('/^Not sent:\s*/', '', (string) $result['message']), 'details' => null, 'raw' => null]);
        }

        \App\Support\ChannelStatus::record('sms', $societyId, false, 'Test SMS failed', $provider);
        $p = strtolower($provider);
        $reason = match (true) {
            str_contains($p, 'otp id is not set') => 'The Fast2SMS OTP ID is not set. Enter it under "How OTPs are sent", or switch to your DLT template.',
            str_contains($p, 'dlt template id') => 'The saved template is a telecom DLT Template ID. Use "Connect & Verify" and pick your template from the list.',
            str_contains($p, 'authori') || str_contains($p, 'authentic') => 'Fast2SMS did not accept the API key.',
            str_contains($p, 'not set') || str_contains($p, 'not configured') => 'The SMS template is not chosen yet.',
            str_contains($p, 'message') && str_contains($p, 'invalid') => 'Fast2SMS does not recognise the chosen template. Use "Connect & Verify" and pick it again.',
            str_contains($p, 'sender') => 'The sender ID does not match the template. Pick the template again from the list.',
            str_contains($p, 'balance') || str_contains($p, 'wallet') => 'Your Fast2SMS balance is too low.',
            str_contains($p, 'unavailable') => 'Could not reach Fast2SMS. Check the internet connection.',
            default => 'Check the number and the chosen template.',
        };

        return response()->json([
            'success' => false,
            'message' => "Test SMS failed. {$reason}",
            'details' => 'Fast2SMS request failed: ' . $provider,
            'raw'     => $result['raw'] ?? null,
        ]);
    }

    /**
     * WhatsApp (Fast2SMS WABA) configuration. Same masked-secret contract as
     * updateEmail/updateSms: a submitted mask means "unchanged", encrypted
     * at rest, never echoed back.
     */
    public function updateWhatsApp(Request $request)
    {
        $user = auth()->user();
        if (!$user->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'whatsapp_enabled'                      => 'nullable|boolean',
            'whatsapp_api_key'                       => 'nullable|string|max:500',
            'whatsapp_api_version'                    => 'nullable|string|max:20',
            'whatsapp_phone_number_id'                => ['nullable', 'string', 'max:40', function ($attribute, $value, $fail) {
                $digits = preg_replace('/\s+/', '', (string) $value);
                // The ID Fast2SMS shows for the WhatsApp number is a long numeric ID (about 15 digits). A 10-digit
                // value, or 91 + 10 digits, is the mobile number itself - the mistake this field invites.
                if ($digits !== '' && (!ctype_digit($digits) || strlen($digits) < 8 || preg_match('/^(91)?[6-9][0-9]{9}$/', $digits))) {
                    $fail('Phone Number ID is the long numeric ID from Fast2SMS (WhatsApp), not a mobile number.');
                }
                // The Business Account (WABA) ID is a different, equally long number and the usual wrong paste: Fast2SMS then
                // answers "(#100) Tried accessing nonexistent field".
                $societyId = auth()->user()->hasRole('Super Admin') ? null : auth()->user()->society_id;
                $waba = trim((string) (request()->input('whatsapp_waba_id') ?? SystemSetting::get('whatsapp', 'whatsapp_waba_id', '', $societyId)));
                if ($digits !== '' && $waba !== '' && $digits === $waba) {
                    $fail('That is the Business Account (WABA) ID, not the Phone Number ID. Copy the Phone Number ID of your WhatsApp number from Fast2SMS.');
                }
            }],
            // Added in front of 10-digit recipient numbers (91 for India).
            'whatsapp_country_code'                   => ['nullable', 'regex:/^[0-9]{1,4}$/'],
            // WhatsApp Business Account ID - needed only for the approved-template
            // sync (GET .../message_templates reads it from the URL), not for
            // sending; sending uses whatsapp_phone_number_id instead.
            'whatsapp_waba_id'                        => 'nullable|string|max:40',
            'whatsapp_template_language'              => 'nullable|string|max:10',
            'whatsapp_otp_template_name'              => 'nullable|string|max:255',
            'whatsapp_visitor_template_name'          => 'nullable|string|max:255',
            'whatsapp_access_pass_template_name'      => 'nullable|string|max:255',
            'whatsapp_complaint_template_name'        => 'nullable|string|max:255',
            'whatsapp_payment_template_name'          => 'nullable|string|max:255',
            'whatsapp_service_request_template_name'  => 'nullable|string|max:255',
            'whatsapp_emergency_template_name'        => 'nullable|string|max:255',
            'whatsapp_document_template_name'         => 'nullable|string|max:255',
        ], [
            'whatsapp_country_code.regex' => 'Country code is digits only, for example 91.',
        ]);

        $societyId = $user->hasRole('Super Admin') ? null : $user->society_id;

        // Switched on only when it can work: a key and a Phone Number ID must exist after this save. Everything else the
        // form carries is still saved; the page says why it stayed off. (Whether the ID is the RIGHT one is the provider's
        // to say - see Verify configuration - never inferred from its shape.)
        $hasKey = ($request->filled('whatsapp_api_key') && !\App\Support\SettingSecrets::isMask($request->whatsapp_api_key)) || \App\Services\WhatsAppService::apiKey($societyId) !== '';
        $pnid = $request->has('whatsapp_phone_number_id')
            ? trim((string) ($validated['whatsapp_phone_number_id'] ?? ''))
            : trim((string) SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', '', $societyId));
        $wantsOn = $request->boolean('whatsapp_enabled');
        $enabled = $wantsOn && $hasKey && $pnid !== '';
        $notEnabled = $wantsOn && !$enabled
            ? 'WhatsApp was not switched on: ' . (!$hasKey ? 'save the Fast2SMS API key' : 'save the Phone Number ID') . ' first.'
            : null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $validated, $societyId, $enabled) {
        SystemSetting::set('whatsapp', 'whatsapp_enabled', $enabled, 'boolean', $societyId);

        if ($request->has('whatsapp_country_code')) {
            SystemSetting::set('whatsapp', 'whatsapp_country_code', trim((string) ($validated['whatsapp_country_code'] ?? '')) ?: '91', 'string', $societyId);
        }

        if ($request->filled('whatsapp_api_key') && !\App\Support\SettingSecrets::isMask($request->whatsapp_api_key)) {
            SystemSetting::set('whatsapp', 'whatsapp_api_key', $request->whatsapp_api_key, 'encrypted', $societyId);
        }

        // Read unguarded before, so any save without this field was a 500.
        if ($request->has('whatsapp_api_version')) {
            SystemSetting::set('whatsapp', 'whatsapp_api_version', ($validated['whatsapp_api_version'] ?? null) ?: 'v26.0', 'string', $societyId);
        }

        // Only keys actually present in the form are written. The Settings
        // page no longer has one template box per notification (templates
        // are attached from the synced list instead), and writing every
        // missing key as '' used to wipe those assignments on every save.
        foreach ([
            'whatsapp_phone_number_id', 'whatsapp_waba_id', 'whatsapp_template_language',
            'whatsapp_otp_template_name', 'whatsapp_visitor_template_name',
            'whatsapp_access_pass_template_name', 'whatsapp_complaint_template_name',
            'whatsapp_payment_template_name', 'whatsapp_service_request_template_name',
            'whatsapp_emergency_template_name', 'whatsapp_document_template_name',
        ] as $key) {
            if ($request->has($key)) {
                SystemSetting::set('whatsapp', $key, trim((string) ($validated[$key] ?? '')), 'string', $societyId);
            }
        }

        \App\Support\ChannelStatus::clear('whatsapp', $societyId);
        });

        $back = redirect()->route('settings.index', ['tab' => 'whatsapp']);
        if ($notEnabled) {
            return $back->with('warning', 'WhatsApp settings saved. ' . $notEnabled);
        }

        return $back->with('success', 'WhatsApp settings saved. ' . $this->checkWhatsAppAfterSave($societyId));
    }

    /**
     * When WhatsApp is switched on with a key and a Phone Number ID, confirm they belong together using the read-only
     * business-profile request, and say so plainly. The settings are saved either way. A skipped check (messaging is
     * switched off on this server) is not recorded as a result, and nothing here claims a message was delivered.
     */
    private function checkWhatsAppAfterSave(?int $societyId): string
    {
        if (!\App\Services\WhatsAppService::isEnabled($societyId)) {
            return 'WhatsApp is switched off.';
        }
        if (\App\Services\WhatsAppService::apiKey($societyId) === '' || (string) SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', '', $societyId) === '') {
            return 'Add the API key and the Phone Number ID to start sending.';
        }

        $check = \App\Services\WhatsAppService::checkConnection($societyId);

        if (!empty($check['skipped'])) {
            return 'Not checked with Fast2SMS: real messaging is switched off on this server.';
        }
        if ($check['ok']) {
            \App\Support\ChannelStatus::record('whatsapp', $societyId, true, 'Fast2SMS accepted the API key and Phone Number ID');

            return 'Fast2SMS accepted the API key and Phone Number ID.';
        }

        \App\Support\ChannelStatus::record('whatsapp', $societyId, false, $check['message'], $check['detail'] ?? null);

        return 'Fast2SMS did not accept them - see the message at the top of this page.';
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
            'password_min_length' => 'required|integer|min:8|max:32',
            'password_require_uppercase' => 'nullable|boolean',
            'password_require_number' => 'nullable|boolean',
            'password_require_special' => 'nullable|boolean',
            // how long a visitor waits at the gate with no answer before the administrators are told (0 = never); a visitor is never admitted by waiting
            'visitor_no_response_minutes' => 'nullable|integer|min:0|max:240',
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
        if (isset($validated['visitor_no_response_minutes'])) {
            SystemSetting::set('security', 'visitor_no_response_minutes', (int) $validated['visitor_no_response_minutes'], 'integer', $societyId);
        }

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

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Role permissions updated successfully!']);
        }

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