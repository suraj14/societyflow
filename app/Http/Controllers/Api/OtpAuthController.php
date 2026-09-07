<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileOtp;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class OtpAuthController extends Controller
{
    /**
     * Step 1: Send OTP to mobile number.
     * Only works for users already registered in the system.
     * Does NOT affect web login at all.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
        ]);

        $phone = $this->normalizePhone($request->phone);

        // Rate limit: max 3 OTP requests per phone per 10 minutes
        $rateLimitKey = 'otp-send:' . $phone;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many OTP requests. Try again in {$seconds} seconds.",
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 600); // 10 min window

        // Check if user exists with this phone.
        // Phone match wrapped in a closure: the previous chained orWhere let
        // inactive/disabled users bypass the status check via SQL precedence.
        $user = User::where(function ($q) use ($phone, $request) {
                $q->where('phone', $phone)->orWhere('phone', $request->phone);
            })
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No active account found with this mobile number. Please contact your society admin.',
            ], 404);
        }

        // Invalidate any existing unused OTPs for this phone
        MobileOtp::where('phone', $phone)->where('is_used', false)->update(['is_used' => true]);

        // Generate 4-digit OTP
        $otp = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        MobileOtp::create([
            'phone'      => $phone,
            'otp'        => $otp,
            'attempts'   => 0,
            'is_used'    => false,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Send OTP via Fast2SMS (falls back to dev mode if not configured)
        $smsResult = SmsService::sendOtp($phone, $otp, $user->society_id ?? null);

        $responseData = ['success' => true, 'message' => 'OTP sent successfully to ' . $this->maskPhone($phone)];

        // DEV ONLY — OTP is exposed in the response strictly in local/development.
        // Never in production or staging, even when the SMS provider is
        // unconfigured or fails ('dev_mode' fallback previously leaked it).
        $isDevEnv = app()->environment(['local', 'development']);

        if ($isDevEnv) {
            $responseData['otp'] = $otp;
        }

        if (!$smsResult['success']) {
            $responseData['message'] = $isDevEnv
                ? 'Could not send SMS. Dev OTP: ' . $otp
                : 'Could not send SMS. Please try again.';
        }

        return response()->json($responseData);
    }

    /**
     * Step 2: Verify OTP and return auth token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
            'otp'   => 'required|string|size:4',
        ]);

        $phone = $this->normalizePhone($request->phone);

        // Rate limit: max 5 verify attempts per phone per 15 minutes
        $rateLimitKey = 'otp-verify:' . $phone;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        $otpRecord = MobileOtp::where('phone', $phone)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return response()->json(['success' => false, 'message' => 'No OTP found. Please request a new one.'], 422);
        }

        if ($otpRecord->isExpired()) {
            return response()->json(['success' => false, 'message' => 'OTP has expired. Please request a new one.'], 422);
        }

        if ($otpRecord->attempts >= 5) {
            return response()->json(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new OTP.'], 422);
        }

        if ($otpRecord->otp !== $request->otp) {
            $otpRecord->incrementAttempts();
            RateLimiter::hit($rateLimitKey, 900);
            $remaining = 5 - $otpRecord->fresh()->attempts;
            return response()->json([
                'success' => false,
                'message' => "Invalid OTP. {$remaining} attempt(s) remaining.",
            ], 422);
        }

        // OTP is valid
        $otpRecord->markUsed();
        RateLimiter::clear($rateLimitKey);
        RateLimiter::clear('otp-send:' . $phone);

        // Find user (phone match grouped so the status check always applies)
        $user = User::where(function ($q) use ($phone, $request) {
                $q->where('phone', $phone)->orWhere('phone', $request->phone);
            })
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        $user->update(['last_login_at' => now()]);

        // Revoke old mobile tokens to avoid accumulation
        $user->tokens()->where('name', 'mobile-otp')->delete();

        $token = $user->createToken('mobile-otp')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    /**
     * Forgot password — step 1.
     * Sends an OTP to the registered mobile number of the account matching
     * this email. Response is identical whether or not the account exists,
     * so emails cannot be enumerated.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $rateLimitKey = 'forgot-password:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 900);

        $generic = [
            'success' => true,
            'message' => 'If an account exists with this email, an OTP has been sent to its registered mobile number.',
        ];

        $user = User::where('email', $request->email)
            ->where('status', 'active')
            ->first();

        if (!$user || empty($user->phone)) {
            return response()->json($generic);
        }

        $phone = $this->normalizePhone($user->phone);

        MobileOtp::where('phone', $phone)->where('is_used', false)->update(['is_used' => true]);

        $otp = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        MobileOtp::create([
            'phone'      => $phone,
            'otp'        => $otp,
            'attempts'   => 0,
            'is_used'    => false,
            'expires_at' => now()->addMinutes(5),
        ]);

        SmsService::sendOtp($phone, $otp, $user->society_id ?? null);

        $responseData = $generic;
        if (app()->environment(['local', 'development'])) {
            $responseData['otp'] = $otp;
        }

        return response()->json($responseData);
    }

    /**
     * Forgot password — step 2.
     * Verifies the OTP sent to the account's phone, sets the new password,
     * revokes all existing tokens, and logs the user in.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'otp'      => 'required|string|size:4',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $rateLimitKey = 'reset-password:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 900);

        $user = User::where('email', $request->email)
            ->where('status', 'active')
            ->first();

        if (!$user || empty($user->phone)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.'], 422);
        }

        $phone = $this->normalizePhone($user->phone);

        $otpRecord = MobileOtp::where('phone', $phone)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otpRecord || $otpRecord->isExpired() || $otpRecord->attempts >= 5) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP. Please request a new one.'], 422);
        }

        if ($otpRecord->otp !== $request->otp) {
            $otpRecord->incrementAttempts();
            return response()->json(['success' => false, 'message' => 'Invalid OTP.'], 422);
        }

        $otpRecord->markUsed();
        RateLimiter::clear($rateLimitKey);

        $user->update([
            'password'      => \Illuminate\Support\Facades\Hash::make($request->password),
            'last_login_at' => now(),
        ]);

        // Password changed: every existing session/token is revoked.
        $user->tokens()->delete();

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        // Normalize Indian numbers: +91XXXXXXXXXX or 91XXXXXXXXXX → 10 digit
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        if (strlen($phone) === 13 && str_starts_with($phone, '+91')) {
            $phone = substr($phone, 3);
        }
        return $phone;
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) >= 10) {
            return substr($phone, 0, 2) . str_repeat('*', strlen($phone) - 4) . substr($phone, -2);
        }
        return $phone;
    }

    private function formatUser(User $user): array
    {
        $resident  = $user->resident()->with('flat.building')->first();
        $flat      = $resident?->flat;
        $building  = $flat?->building;

        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'avatar'        => $user->avatar_url,
            'role'          => $user->getRoleNames()->first() ?? 'Resident',
            'status'        => $user->status,
            'flat_number'   => $flat?->flat_number,
            'building_name' => $building?->name,
            'society_name'  => $user->society?->name,
            'society_id'    => $user->society_id,
        ];
    }
}
