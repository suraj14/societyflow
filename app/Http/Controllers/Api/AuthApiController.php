<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobileUserPayload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact your society admin.',
            ], 403);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * Step 1 of registration: verify the resident exists in the system.
     * Returns their flat/society info so they can confirm before setting password.
     */
    public function checkAccount(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
        ]);

        $rateLimitKey = 'check-account:' . $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
            ], 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 900);

        // Email AND phone must both match. The old phone-only fallback let
        // anyone enumerate name/email/flat/society from a phone number.
        $user = User::where('email', $request->email)
            ->where('phone', $request->phone)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email and phone number. Please contact your society admin to get registered.',
            ], 404);
        }

        $resident = $user->resident()->with('flat.building')->first();
        $flat     = $resident?->flat;
        $building = $flat?->building;
        $society  = $user->society;

        return response()->json([
            'success' => true,
            'message' => 'Account found! Please set your password to continue.',
            'data'    => [
                'user_id'       => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'flat_number'   => $flat?->flat_number,
                'building_name' => $building?->name,
                'society_name'  => $society?->name,
                'role'          => $user->getRoleNames()->first() ?? 'Resident',
                'has_password'  => !empty($user->password),
            ],
        ]);
    }

    /**
     * Step 2 of registration: set password and activate account.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'phone'    => 'required|string|max:20',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $rateLimitKey = 'register:' . $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
            ], 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 900);

        // Email AND phone must both match the pre-created account.
        // No phone-only fallback: that allowed taking over any account
        // knowing just a phone number.
        $user = User::where('email', $request->email)
            ->where('phone', $request->phone)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found. Please contact your society admin.',
            ], 404);
        }

        // An account that is already active with a password is registered.
        // Never let this endpoint overwrite its password.
        if (!empty($user->password) && $user->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This account is already registered. Please login or use forgot password.',
            ], 409);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'status'   => 'active',
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account activated successfully! Welcome to SocietyFlow.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($request->user()),
        ]);
    }

    private function formatUser(User $user): array
    {
        return MobileUserPayload::make($user);
    }
}
