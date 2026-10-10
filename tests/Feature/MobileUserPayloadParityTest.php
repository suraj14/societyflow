<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\MobileOtp;
use App\Models\Owner;
use App\Models\VillaArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression: the app stores the login response's `user` object as its
 * session, and three hand-copied builders had drifted. OTP login returned a
 * thin object with no villa_number, villa_area, is_owner, is_tenant, society
 * or property_type, so a villa owner who signed in by OTP saw no villa, lost
 * the owner-only screens (Family Members) and got the default society
 * configuration. Every way in must now return the same object.
 */
class MobileUserPayloadParityTest extends TestCase
{
    use RefreshDatabase;

    private function villaOwner(): \App\Models\User
    {
        $society = $this->makeSociety('Palm Villas');
        $society->update(['property_type' => 'villa']);
        $area = VillaArea::create(['society_id' => $society->id, 'name' => 'Phase 2', 'status' => 'active']);
        Flat::create(['society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id, 'flat_number' => '159', 'floor' => 0, 'status' => 'occupied']);
        $owner = Owner::create([
            'society_id' => $society->id, 'name' => 'Villa Owner 159', 'email' => 'o159' . uniqid() . '@t.local',
            'phone' => '9811113333', 'status' => 'active', 'property_type' => 'villa',
            'villa_area_id' => $area->id, 'villa_no' => '159',
        ]);

        return $this->makeUserWithRole($society, 'Villa Owner', [
            'phone' => '9811113333', 'owner_id' => $owner->id, 'email' => $owner->email,
        ]);
    }

    public function test_otp_login_returns_the_same_user_object_as_password_login(): void
    {
        $user = $this->villaOwner();

        $byPassword = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-horse-battery-staple'])
            ->assertOk()->json('user');

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9811113333'])->assertOk();
        $otp = MobileOtp::where('phone', '9811113333')->latest()->first()->otp;
        $byOtp = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '9811113333', 'otp' => $otp])
            ->assertOk()->json('user');

        $this->assertSame($byPassword, $byOtp);

        // The fields the thin OTP payload used to drop, with real values.
        $this->assertSame('159', $byOtp['villa_number']);
        $this->assertSame('Phase 2', $byOtp['villa_area']);
        $this->assertTrue($byOtp['is_owner']);
        $this->assertSame('villa', $byOtp['property_type']);
        $this->assertSame('Palm Villas', $byOtp['society']['name']);
    }

    public function test_me_and_profile_return_the_same_user_object(): void
    {
        $user = $this->villaOwner();
        $byPassword = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-horse-battery-staple'])
            ->json('user');

        Sanctum::actingAs($user);
        $me = $this->getJson('/api/v1/auth/me')->assertOk()->json('data');
        $profile = $this->getJson('/api/v1/profile')->assertOk()->json('data');

        $this->assertSame($byPassword, $me);
        $this->assertArrayHasKey('family_members', $profile, '/profile adds the owner\'s family on top');
        $this->assertSame($byPassword, Arr::except($profile, 'family_members'));
    }
}
