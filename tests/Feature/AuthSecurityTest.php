<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ── register() ───────────────────────────────────────────────────────────

    public function test_register_cannot_take_over_an_active_account(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $victim  = $this->makeResidentUser($society, $flat, [
            'password' => Hash::make('victim-secret'),
        ]);

        // Attacker knows the phone number and tries the old phone-only path
        // with a wrong email.
        $res = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'attacker@evil.local',
            'phone'                 => $victim->phone,
            'password'              => 'hacked123',
            'password_confirmation' => 'hacked123',
        ]);
        $res->assertStatus(404);

        // Even with the correct email+phone, an active account with a
        // password cannot be re-registered.
        $res = $this->postJson('/api/v1/auth/register', [
            'email'                 => $victim->email,
            'phone'                 => $victim->phone,
            'password'              => 'hacked123',
            'password_confirmation' => 'hacked123',
        ]);
        $res->assertStatus(409);

        $this->assertTrue(Hash::check('victim-secret', $victim->fresh()->password));
    }

    public function test_register_activates_a_pre_created_account(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));

        // Admin pre-created user: no password yet, inactive
        $user = User::create([
            'society_id' => $society->id,
            'name'       => 'New Resident',
            'email'      => 'new@test.local',
            'phone'      => '9111111111',
            'password'   => '',
            'status'     => 'inactive',
        ]);

        $res = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'new@test.local',
            'phone'                 => '9111111111',
            'password'              => 'mypassword',
            'password_confirmation' => 'mypassword',
        ]);

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($res->json('token'));
        $this->assertSame('active', $user->fresh()->status);
        $this->assertTrue(Hash::check('mypassword', $user->fresh()->password));
    }

    public function test_check_account_requires_email_and_phone_to_match(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        // phone-only (wrong email) must not leak the account
        $this->postJson('/api/v1/auth/check-account', [
            'email' => 'wrong@test.local',
            'phone' => $user->phone,
        ])->assertStatus(404);

        $this->postJson('/api/v1/auth/check-account', [
            'email' => $user->email,
            'phone' => $user->phone,
        ])->assertOk()->assertJsonPath('data.user_id', $user->id);
    }

    // ── OTP login ────────────────────────────────────────────────────────────

    public function test_inactive_user_cannot_receive_otp(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat, ['status' => 'inactive']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => $user->phone])
            ->assertStatus(404);

        $this->assertSame(0, MobileOtp::where('phone', $user->phone)->count());
    }

    public function test_otp_login_full_flow(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat, ['phone' => '9222222222']);

        $send = $this->postJson('/api/v1/auth/send-otp', ['phone' => '9222222222']);
        $send->assertOk();

        $otp = MobileOtp::where('phone', '9222222222')->latest()->first();
        $this->assertNotNull($otp);

        $verify = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '9222222222',
            'otp'   => $otp->otp,
        ]);
        $verify->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($verify->json('token'));
        $this->assertSame($user->id, $verify->json('user.id'));
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $this->makeResidentUser($society, $flat, ['phone' => '9333333333']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9333333333'])->assertOk();

        $otp = MobileOtp::where('phone', '9333333333')->latest()->first();
        $wrong = $otp->otp === '1234' ? '4321' : '1234';

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '9333333333',
            'otp'   => $wrong,
        ])->assertStatus(422);
    }

    // ── forgot / reset password ──────────────────────────────────────────────

    public function test_forgot_password_response_is_identical_for_unknown_email(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        $known   = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@test.local']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
        // OTP only created for the real account
        $this->assertSame(1, MobileOtp::count());
    }

    public function test_reset_password_with_otp_logs_user_in_and_revokes_old_tokens(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat, ['phone' => '9444444444']);

        $oldToken = $user->createToken('mobile-app')->plainTextToken;

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $otp = MobileOtp::where('phone', '9444444444')->latest()->first();

        $res = $this->postJson('/api/v1/auth/reset-password', [
            'email'                 => $user->email,
            'otp'                   => $otp->otp,
            'password'              => 'brandnewpass',
            'password_confirmation' => 'brandnewpass',
        ]);

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($res->json('token'));
        $this->assertTrue(Hash::check('brandnewpass', $user->fresh()->password));

        // Old token is dead
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer ' . $oldToken])
            ->assertStatus(401);
    }

    public function test_login_and_me(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));
        $user    = $this->makeResidentUser($society, $flat);

        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);
        $login->assertOk();
        $token = $login->json('token');

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer ' . $token])
            ->assertOk();
    }
}
