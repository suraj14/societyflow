<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Enums\SocietyPropertyType;
use App\Models\User;
use App\Models\Society;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;

class AuthController extends Controller
{
    /**
     * Show the login form
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }
        
        return view('auth.login');
    }

    /**
     * Handle login request with rate limiting
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Failed-password lockout, shared with the app's login (App\Support\LoginThrottle): the society's Settings -> Security attempt limit
        if ($seconds = \App\Support\LoginThrottle::lockedFor($request)) {
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // an address nobody holds costs the same time as a wrong password, so the answer does not tell them apart
        if (!User::whereEmail($request->email)->exists()) {
            \App\Support\LoginThrottle::spendPasswordCheck($request->password);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            \App\Support\LoginThrottle::clear($request);
            $request->session()->regenerate();

            $user = Auth::user();

            // inactive / suspended: same wording as the API, and no session is kept
            if ($user->isBlocked()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors(['email' => \App\Http\Middleware\EnsureAccountActive::MESSAGE])->onlyInput('email');
            }

            // Update last login timestamp
            $user->update(['last_login_at' => now()]);

            // Validate society access for non-Super Admin users
            if (!$user->hasRole('Super Admin')) {
                // Check if user belongs to a society
                if (!$user->society_id || !$user->society) {
                    Auth::logout();
                    return back()->withErrors([
                        'email' => 'Your account is not associated with any society.',
                    ])->onlyInput('email');
                }

                // Check if society is active
                if (!$user->society->isActive()) {
                    Auth::logout();
                    return back()->withErrors([
                        'email' => 'Your society is currently inactive. Please contact support.',
                    ])->onlyInput('email');
                }

                // SUBSCRIPTION VALIDATION DISABLED FOR DEVELOPMENT TESTING
                // All users get unlimited free access - no subscription checks
            }

            return $this->redirectBasedOnRole($user);
        }

        \App\Support\LoginThrottle::fail($request);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Redirect user based on their role
     */
    protected function redirectBasedOnRole($user)
    {
        // Check roles in order of priority
        if ($user->hasRole('Super Admin')) {
            return redirect()->route('super-admin.dashboard');
        }

        if ($user->hasAnyRole(['Admin', 'Society Admin'])) {
            return redirect()->route('dashboard');
        }

        // Villa Owner is a legacy role name; an 'Owner' holder with only a
        // villa (no apartment) routes the same way. DashboardController's
        // own index() is the authority on this - this just picks the
        // initial redirect target.
        if ($user->hasRole('Villa Owner') || ($user->hasRole('Owner') && $user->ownedVilla && !$user->ownedFlat)) {
            return redirect()->route('villa-owner.dashboard');
        }

        // Guards land at the gate, not on the society's admin dashboard.
        if ($user->hasRole('Guard')) {
            return redirect()->route('guard.dashboard');
        }

        if ($user->hasAnyRole(['Owner', 'Apartment Owner', 'Tenant', 'Staff', 'Accountant', 'Manager'])) {
            return redirect()->route('dashboard');
        }

        // Default fallback - should not reach here if roles are properly assigned
        return redirect()->route('dashboard');
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear all session data
        $request->session()->flush();

        // Return redirect with cache-busting headers
        return redirect()
            ->route('login')
            ->with('success', 'You have been logged out successfully.')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle forgot password request. Uses Laravel's own password broker
     * (the ->exists:users,email rule this used to have told an attacker
     * which emails are registered via the validation error alone; the
     * broker's own response is enumeration-safe by design, so the same
     * generic message is shown either way).
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // The same answer whether or not the account exists AND whether or not the mail could be sent: a transport failure
        // used to surface as a server error for registered addresses only, which both broke the page and revealed which
        // addresses are registered. A failure is logged for the operator (class only: an SMTP message can name the server
        // or user) and, with a queue worker, retried by the queued notification.
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            // the code is the SMTP reply code for a refused message (535 bad login, 553 sender not allowed...) - safe to log, unlike the text
            \Illuminate\Support\Facades\Log::error('Password reset email could not be sent', ['exception' => get_class($e), 'code' => $e->getCode() ?: null]);
        }

        return back()->with('success', 'If an account exists for that email, a password reset link has been sent.');
    }

    /**
     * Show the reset-password form for a mailed token.
     */
    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Complete a password reset via the mailed token.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            // the policy of the society this account belongs to (the platform's when the email is unknown)
            'password' => \App\Support\PasswordPolicy::required(\App\Support\PasswordPolicy::forSociety(User::whereEmail($request->email)->value('society_id'))),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // A password only the account holder now knows must
                // invalidate any API tokens issued under the old one.
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Your password has been reset. Please log in.');
        }

        return back()->withErrors(['email' => __($status)])->onlyInput('email');
    }

    /**
     * Show registration form
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        // one identity per address whatever its letter case: the unique rule below compares what is stored, and only some engines ignore case
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $request->validate([
            'society_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:15',
            // a new society has no policy of its own yet: the platform's applies
            'password' => \App\Support\PasswordPolicy::required(\App\Support\PasswordPolicy::forSociety(null)),
            'terms' => 'required|accepted',
            // Required, not optional: a society created without this silently
            // became "both", which then offered villa menus to apartment
            // blocks and vice versa. Asked once at registration (brief §1).
            'property_type' => ['required', Rule::in(SocietyPropertyType::values())],
        ]);

        // A concurrent registration can take the same address between the check and the insert: that one collision is retried (the next try
        // sees the other society and picks the next free suffix); anything else fails once, safely.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            DB::beginTransaction();

            // Get Free Forever subscription plan
            $defaultPlan = SubscriptionPlan::where('slug', 'free-forever')->first();
            
            if (!$defaultPlan) {
                $defaultPlan = SubscriptionPlan::create([
                    'name' => 'Free Forever',
                    'slug' => 'free-forever',
                    'description' => 'Unlimited free access to all SocietyFlow features',
                    'monthly_price' => 0.00,
                    'yearly_price' => 0.00,
                    'max_flats' => 999999,
                    'max_users' => 999999,
                    'max_staff' => 999999,
                    'features' => ['unlimited_access', 'all_modules'],   // the model casts to JSON; pre-encoding double-encoded it
                    'trial_days' => 0,
                    'status' => 'active',
                ]);
            }

            // Create society (auto-approved for immediate use)
            $society = Society::create([
                'name' => $request->society_name,
                'property_type' => $request->property_type,
                'slug' => Str::slug($request->society_name),
                'subdomain' => Str::slug($request->society_name),
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => 'To be updated',
                'city' => 'To be updated',
                'state' => 'To be updated',
                'country' => 'India',
                'pincode' => '000000',
                'subscription_plan_id' => $defaultPlan->id,
                'status' => 'active', // Auto-approved for immediate use
                'trial_ends_at' => null, // No trial needed - free forever
            ]);

            // Create admin user (active for immediate use)
            $admin = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'society_id' => $society->id,
                'status' => 'active', // Active for immediate use
            ]);

            // Assign Admin role
            $admin->assignRole('Admin');

            // Link admin to society
            $society->update(['admin_id' => $admin->id]);

            // Create subscription record (free forever)
            Subscription::create([
                'society_id' => $society->id,
                'subscription_plan_id' => $defaultPlan->id,
                'billing_cycle' => 'yearly',
                'amount' => 0.00,
                'start_date' => now(),
                'end_date' => now()->addYears(100), // Effectively unlimited
                'next_billing_date' => null, // No billing needed
                'status' => 'active',
                'auto_renew' => false, // No renewal needed for free plan
            ]);

            DB::commit();

            return redirect()->route('login')->with('success', 
                'Registration successful! Your society has unlimited free access to all SocietyFlow features. You can now login and start using the system.');

        } catch (\Exception $e) {
            DB::rollBack();
            if ($e instanceof \Illuminate\Database\UniqueConstraintViolationException && $attempt < 3 && preg_match('/slug|subdomain/i', $e->getMessage())) {
                continue;
            }
            // the cause goes to the log, never to the visitor (it can name tables and constraints)
            \Illuminate\Support\Facades\Log::error('Society registration failed', ['exception' => get_class($e), 'message' => $e->getMessage()]);

            return back()->withInput()->withErrors([
                'error' => 'Registration could not be completed. Please try again, or contact support if it keeps happening.'
            ]);
        }
        }
    }
}
