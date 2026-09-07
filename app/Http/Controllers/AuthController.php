<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        // Rate limiting
        $throttleKey = strtolower($request->email) . '|' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            // Update last login timestamp
            $user = Auth::user();
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

        RateLimiter::hit($throttleKey, 60);

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

        if ($user->hasRole('Villa Owner')) {
            return redirect()->route('villa-owner.dashboard');
        }

        if ($user->hasAnyRole(['Apartment Owner', 'Tenant', 'Staff', 'Accountant', 'Manager'])) {
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
     * Handle forgot password request
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // TODO: Implement password reset email logic
        
        return back()->with('success', 'Password reset link has been sent to your email.');
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
        $request->validate([
            'society_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:15',
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'required|accepted',
        ]);

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
                    'features' => json_encode(['unlimited_access', 'all_modules']),
                    'trial_days' => 0,
                    'status' => 'active',
                ]);
            }

            // Create society (auto-approved for immediate use)
            $society = Society::create([
                'name' => $request->society_name,
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
            
            return back()->withInput()->withErrors([
                'error' => 'Registration failed: ' . $e->getMessage()
            ]);
        }
    }
}
