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

        // Check if user exists with this phone
        $user = User::where('phone', $phone)
            ->orWhere('phone', $request->phone)
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

        // DEV ONLY — show OTP in response when SMS not configured
        if ($smsResult['message'] === 'dev_mode' || config('app.env') !== 'production') {
            $responseData['otp'] = $otp;
        }

        if (!$smsResult['success']) {
            $responseData['message'] = 'Could not send SMS. ' . (config('app.env') !== 'production' ? 'Dev OTP: ' . $otp : 'Please try again.');
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

        // Find user
        $user = User::where('phone', $phone)
            ->orWhere('phone', $request->phone)
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
