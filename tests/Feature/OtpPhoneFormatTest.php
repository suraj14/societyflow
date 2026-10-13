<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: OTP login matched the stored phone as an exact string (the
 * typed number, or its 10-digit form). The dev DB stores numbers as
 * "+91-98…" (5 accounts), "098…" (4) and "91…"/"+91…" - none of which matched
 * a typed 10-digit number, so those people could not use OTP login at all
 * ("No active account found").
 */
class OtpPhoneFormatTest extends TestCase
{
    use RefreshDatabase;

    private function loginByOtp(string $typed): \Illuminate\Testing\TestResponse
    {
        $send = $this->postJson('/api/v1/auth/send-otp', ['phone' => $typed])->assertOk();
        $this->assertNotNull(MobileOtp::where('phone', '9811112222')->latest()->first(), 'the OTP is filed under the bare 10-digit number');

        return $this->postJson('/api/v1/auth/verify-otp', ['phone' => $typed, 'otp' => $send->json('otp')]);
    }

    /** @dataProvider storedFormats */
    public function test_a_number_saved_in_any_common_format_can_log_in(string $stored): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, 'Tenant', ['phone' => $stored]);

        $this->loginByOtp('9811112222')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public static function storedFormats(): array
    {
        return [
            'plus91 dash'      => ['+91-9811112222'],
            'trunk zero'       => ['09811112222'],
            'country code'     => ['919811112222'],
            'plus country'     => ['+919811112222'],
            'spaced'           => ['98111 12222'],
            'plus91 spaced'    => ['+91 98111 12222'],
            'bare'             => ['9811112222'],
        ];
    }

    /** @dataProvider typedFormats */
    public function test_a_number_typed_in_any_common_format_is_understood(string $typed): void
    {
        $society = $this->makeSociety();
        $user = $this->makeUserWithRole($society, 'Tenant', ['phone' => '9811112222']);

        $this->loginByOtp($typed)->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public static function typedFormats(): array
    {
        return [
            'plus91 spaced' => ['+91 98111 12222'],
            'trunk zero'    => ['09811112222'],
            'dashed'        => ['98111-12222'],
        ];
    }

    public function test_a_different_number_ending_in_the_same_digits_is_not_a_match(): void
    {
        $society = $this->makeSociety();
        // 11 digits that end in the typed number but are not it (not a
        // trunk-0 form): the SQL suffix narrowing must not accept it.
        $this->makeUserWithRole($society, 'Tenant', ['phone' => '19811112222']);

        // an unknown number gets the same answer as a known one - and no code, no SMS
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9811112222'])->assertOk()->assertJsonMissingPath('otp');
        $this->assertSame(0, MobileOtp::count());
    }

    public function test_something_that_is_not_a_mobile_number_is_refused_up_front(): void
    {
        $this->makeUserWithRole($this->makeSociety(), 'Tenant', ['phone' => '9811112222']);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => 'abcdefghij'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/auth/send-otp', ['phone' => '981111222233'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->assertSame(0, MobileOtp::count());
    }
}
