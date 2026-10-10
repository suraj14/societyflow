<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * A 4-digit OTP can be guessed if its verify attempts are not capped over
 * time. The 15-minute window alone resets, so the daily ceiling is what
 * stops a sustained guessing run against one phone number.
 */
class OtpDailyGuessLimitTest extends TestCase
{
    use RefreshDatabase;

    private function residentWithPhone(string $phone): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $this->makeResidentUser($society, $flat, ['phone' => $phone]);
    }

    public function test_a_valid_code_is_refused_once_the_daily_ceiling_is_reached(): void
    {
        $phone = '9444444444';
        $this->residentWithPhone($phone);
        $this->postJson('/api/v1/auth/send-otp', ['phone' => $phone])->assertOk();
        $otp = MobileOtp::where('phone', $phone)->latest()->first()->otp;

        // Exhaust the daily ceiling directly, as a run of wrong guesses across
        // several 15-minute windows would have.
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit('otp-verify-day:' . $phone, 86400);
        }

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'otp' => $otp])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_wrong_guesses_count_toward_the_daily_ceiling(): void
    {
        $phone = '9555555555';
        $this->residentWithPhone($phone);
        $this->postJson('/api/v1/auth/send-otp', ['phone' => $phone])->assertOk();
        $otp   = MobileOtp::where('phone', $phone)->latest()->first()->otp;
        $wrong = $otp === '0000' ? '1111' : '0000';

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'otp' => $wrong])
            ->assertStatus(422);

        $this->assertSame(1, RateLimiter::attempts('otp-verify-day:' . $phone));
    }
}
