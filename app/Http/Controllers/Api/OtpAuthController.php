<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileOtp;
use App\Models\User;
use App\Support\MobileUserPayload;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpAuthController extends Controller
{
    /**
     * Sent by app builds that can show an account chooser. A number saved on
     * more than one active account then gets a choice after the OTP is
     * verified; a build without it is refused with AMBIGUOUS_MESSAGE rather
     * than being logged into whichever account the database returned first.
     */
    private const CHOICE_FLAG = 'can_choose_account';

    /** How long a verified-but-not-yet-chosen sign-in stays usable. */
    private const CHOICE_TTL_MINUTES = 5;

    private const AMBIGUOUS_MESSAGE = 'This mobile number is linked to more than one account. Please log in with your email and password, or ask your society office to update the numbers.';

    /**
     * Step 1: Send OTP to mobile number.
     * Only works for users already registered in the system.
     * Does NOT affect web login at all.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone'           => 'required|string|min:10|max:15',
            self::CHOICE_FLAG => 'sometimes|boolean',
        ]);

        $phone = $this->mobileNumber($request->phone);

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

        // Check if an active account has this phone.
        $accounts = $this->activeAccountsFor($phone);

        if ($accounts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active account found with this mobile number. Please contact your society admin.',
            ], 404);
        }

        // Several active accounts share this number. An app build that can
        // show an account chooser carries on (the choice is offered after the
        // OTP proves the caller holds the phone). An older build would be
        // logged into whichever account came first, so it is told honestly
        // instead - before any SMS is sent.
        if ($accounts->count() > 1 && !$request->boolean(self::CHOICE_FLAG)) {
            Log::warning('OTP send blocked: phone matches more than one active account', [
                'masked_phone' => $this->maskPhone($phone),
            ]);
            return response()->json([
                'success' => false,
                'code'    => 'phone_ambiguous',
                'message' => self::AMBIGUOUS_MESSAGE,
            ], 409);
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
            'expires_at' => now()->addMinutes(MobileOtp::TTL_MINUTES),
        ]);

        // Send OTP via the SMS settings of the account's society (Fast2SMS;
        // falls back to dev mode if not configured). A number on accounts in
        // several societies is tried society by society until one provider
        // accepts it, so one society's broken setup does not lock out a
        // person another of their societies can reach.
        $societyIds = $accounts->pluck('society_id')->unique()->values();
        $smsResult  = null;
        foreach ($societyIds as $societyId) {
            $smsResult = SmsService::sendOtp($phone, $otp, $societyId);
            if (!empty($smsResult['sent'])) {   // accepted by the provider - a skip is not a send
                break;
            }
        }

        $responseData = ['success' => true, 'message' => 'OTP sent successfully to ' . $this->maskPhone($phone)];

        // DEV ONLY — OTP is exposed in the response strictly in local/development.
        // Never in production or staging, even when the SMS provider is
        // unconfigured or fails ('dev_mode' fallback previously leaked it).
        // 'testing' is the automated suite (never deployed), which has no SMS
        // provider; it behaves like local so OTP flows can be exercised.
        $isDevEnv = app()->environment(['local', 'development', 'testing']);

        if ($isDevEnv) {
            $responseData['otp'] = $otp;
        }

        // Outside local/dev the OTP only ever reaches the user by SMS, so a
        // send that did not happen must be reported as a failure. This used to
        // answer success:true ("Could not send SMS") whenever Fast2SMS rejected
        // the request or SMS was not configured, and the app then waited on an
        // OTP screen for a code that was never sent.
        $notSent = empty($smsResult['sent']);
        $skipped = SmsService::wasSkipped($smsResult);   // switched off / not set up: nothing was attempted

        if ($notSent && !$isDevEnv) {
            Log::warning('OTP SMS not sent', [
                'society_id' => $societyIds->implode(','),
                'reason'     => $skipped ? (string) ($smsResult['status'] ?? 'sms_not_configured') : 'provider_rejected',
                'provider'   => SmsService::safeProviderMessage((string) ($smsResult['message'] ?? '')),
            ]);
            MobileOtp::where('phone', $phone)->where('is_used', false)->update(['is_used' => true]);
            RateLimiter::clear($rateLimitKey); // our failure, not the user's attempt

            return response()->json([
                'success' => false,
                'code'    => $skipped ? 'sms_not_configured' : 'sms_failed',
                'message' => $skipped
                    ? 'OTP login by SMS is not set up for your society yet. Please log in with email and password, or ask your society office.'
                    : 'We could not send the OTP SMS right now. Please try again in a minute, or log in with email and password.',
            ], 503);
        }

        if ($notSent) {
            $responseData['message'] = 'Could not send SMS. Dev OTP: ' . $otp;
        }

        return response()->json($responseData);
    }

    /**
     * Step 2: Verify OTP and return auth token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone'           => 'required|string|min:10|max:15',
            'otp'             => 'required|string|size:4',
            self::CHOICE_FLAG => 'sometimes|boolean',
        ]);

        $phone = $this->mobileNumber($request->phone);

        // Daily ceiling on wrong codes for this number. The 15-minute window
        // below resets on its own, so without this a 4-digit code could be
        // guessed at roughly 5 tries every 15 minutes indefinitely. Failed
        // guesses do not clear it; only the day ends it.
        $dailyKey = 'otp-verify-day:' . $phone;
        if (RateLimiter::tooManyAttempts($dailyKey, 20)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many incorrect codes for this number today. Try again tomorrow.',
            ], 429);
        }

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
            RateLimiter::hit($dailyKey, 86400);
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

        // Find the account(s). Same rule as sendOtp: a number that (by now)
        // matches more than one active account is never resolved by picking
        // whichever row came first.
        $accounts = $this->activeAccountsFor($phone);

        if ($accounts->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        if ($accounts->count() > 1) {
            if (!$request->boolean(self::CHOICE_FLAG)) {
                Log::warning('OTP verify blocked: phone matches more than one active account', [
                    'masked_phone' => $this->maskPhone($phone),
                ]);
                return response()->json([
                    'success' => false,
                    'code'    => 'phone_ambiguous',
                    'message' => self::AMBIGUOUS_MESSAGE,
                ], 409);
            }

            // The code proved the caller holds this phone, and every account
            // here lists it - the same proof that logs a single-account
            // number straight in. So the caller may pick any of them. The
            // selection token is single-use, short-lived and bound to this
            // number and exactly these account ids.
            $selectionToken = Str::random(64);
            Cache::put($this->choiceKey($selectionToken), [
                'phone'    => $phone,
                'user_ids' => $accounts->pluck('id')->all(),
            ], now()->addMinutes(self::CHOICE_TTL_MINUTES));

            return response()->json([
                'success'                 => true,
                'requires_account_choice' => true,
                'message'                 => 'This number is linked to more than one account. Choose the one to sign in to.',
                'selection_token'         => $selectionToken,
                'accounts'                => $accounts->map(fn (User $u) => $this->accountOption($u))->values(),
            ]);
        }

        return $this->issueLogin($accounts->first());
    }

    /**
     * Step 3, only after verify-otp answered `requires_account_choice`: sign
     * in to the account the user picked from that list.
     */
    public function selectAccount(Request $request): JsonResponse
    {
        $request->validate([
            'selection_token' => 'required|string|max:128',
            'user_id'         => 'required|integer',
        ]);

        $expired = response()->json([
            'success' => false,
            'code'    => 'selection_expired',
            'message' => 'This sign-in has expired. Please request a new OTP.',
        ], 422);

        // pull(): the token is spent by this attempt, whatever happens next.
        $choice = Cache::pull($this->choiceKey($request->selection_token));
        if (!is_array($choice) || !in_array((int) $request->user_id, $choice['user_ids'] ?? [], true)) {
            return $expired;
        }

        // The account must still be active and still carry this number.
        $user = User::where('id', (int) $request->user_id)->where('status', 'active')->first();
        if (!$user || $this->normalizePhone((string) $user->phone) !== ($choice['phone'] ?? null)) {
            return $expired;
        }

        return $this->issueLogin($user);
    }

    /** Token + full user payload for a phone-verified sign-in. */
    private function issueLogin(User $user): JsonResponse
    {
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
            'expires_at' => now()->addMinutes(MobileOtp::TTL_MINUTES),
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
            // the policy of the account's own society (the platform's if the email is unknown)
            'password' => \App\Support\PasswordPolicy::required(\App\Support\PasswordPolicy::forSociety(User::where('email', $request->email)->value('society_id'))),
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

    /**
     * Bare 10-digit Indian mobile, from any typed or stored shape ("+91 98…",
     * "+91-98…", "91…", "098…"). Same rules as the SMS sender, so the number
     * an OTP is filed under is the number it was sent to.
     */
    private function normalizePhone(string $phone): string
    {
        return SmsService::normalizePhone($phone);
    }

    /**
     * The request's number, normalised, or a 422. OTP login is SMS-only and
     * Fast2SMS reaches 10-digit Indian mobiles only; this also keeps the
     * account lookup below from running on an empty or partial number.
     */
    private function mobileNumber(string $input): string
    {
        $phone = $this->normalizePhone($input);
        if (!preg_match('/^\d{10}$/', $phone)) {
            throw ValidationException::withMessages([
                'phone' => ['Please enter a valid 10-digit mobile number.'],
            ]);
        }

        return $phone;
    }

    /**
     * Every active account whose saved number is this one, in whatever format
     * it was saved ("+91-98…", "098…", "98765 43210"). An exact-string match
     * missed those, so their owners could not use OTP login at all.
     *
     * Callers decide what several matches mean; never ->first() them. The dev
     * DB had 5 numbers on two active accounts each (e.g. a Guard and the Admin
     * they report to) - first() logged the guard into the admin's account.
     *
     * @return Collection<int, User>
     */
    private function activeAccountsFor(string $phone): Collection
    {
        // Narrow in SQL on the number with separators stripped (a suffix
        // match also catches a stored country code or trunk 0), then compare
        // the fully normalised number in PHP. Single-quoted literals and a
        // bound parameter: portable across SQLite and MySQL.
        return User::where('status', 'active')
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') LIKE ?",
                ['%' . $phone]
            )
            ->with(['society', 'roles'])
            ->orderBy('id')
            ->get()
            ->filter(fn (User $u) => $this->normalizePhone((string) $u->phone) === $phone)
            ->values();
    }

    private function choiceKey(string $selectionToken): string
    {
        return 'otp-account-choice:' . hash('sha256', $selectionToken);
    }

    /**
     * One row of the account chooser: enough to tell the accounts apart
     * (two homes in one society share a name, role and society, not a unit).
     * Shown only after the OTP is verified, to the holder of the number.
     */
    private function accountOption(User $user): array
    {
        $payload = MobileUserPayload::make($user);
        $unit = null;
        if (!empty($payload['villa_number'])) {
            $unit = str_starts_with((string) $payload['villa_number'], 'Villa ')
                ? $payload['villa_number']
                : 'Villa ' . $payload['villa_number'];
        } elseif (!empty($payload['flat_number'])) {
            $unit = trim(($payload['building_name'] ? $payload['building_name'] . ' - ' : '') . 'Flat ' . $payload['flat_number']);
        }

        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'role'         => $payload['role'],
            'society_name' => $payload['society_name'],
            'unit'         => $unit,
        ];
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
        return MobileUserPayload::make($user);
    }
}
