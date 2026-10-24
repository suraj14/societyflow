<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Regression: send-otp and verify-otp resolved a phone number to a user
 * with first(), which silently logs the caller into whichever account
 * happened to come back first when two active accounts share a number.
 * Found on the dev box — a Guard and the Admin they report to had both
 * saved 7726884448, so OTP login with that number would have logged the
 * guard into the admin's account (5 such collisions existed across the
 * dev DB, three of them spanning two societies).
 *
 * Now: an app build that can show an account chooser (`can_choose_account`)
 * gets the code, and after verifying it picks which account to sign in to.
 * A build without the flag is refused honestly before any SMS is sent.
 */
class OtpAmbiguousPhoneTest extends TestCase
{
    use RefreshDatabase;

    private string $phone = '7726884448';

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    /** @return array{0: User, 1: User} */
    private function sharedNumber(): array
    {
        $society = $this->makeSociety('Royal Eden');
        // Two non-privileged accounts on one number (a household's owner and its tenant). Before 2026-10-22 this fixture was an Admin and a Guard;
        // an Admin/Super Admin is no longer offered by the phone-only chooser (see OtpPrivilegedAccountPhoneTest), so the chooser behaviour these
        // tests pin is unchanged but is now exercised with accounts that may use it.
        $admin = $this->makeUserWithRole($society, 'Owner', ['phone' => $this->phone, 'name' => 'Office Admin']);
        $guard = $this->makeUserWithRole($society, 'Tenant', ['phone' => $this->phone, 'name' => 'Gate Guard']);

        return [$admin, $guard];
    }

    /** Send + verify as the current app does; returns the verify response. */
    private function verifyAsChooserApp()
    {
        $send = $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone, 'can_choose_account' => true])->assertOk();

        return $this->postJson('/api/v1/auth/verify-otp', ['phone' => $this->phone, 'otp' => $send->json('otp'), 'can_choose_account' => true]);
    }

    // ── builds without an account chooser: refused, never guessed ─────────

    public function test_send_otp_refuses_a_number_shared_by_two_active_accounts(): void
    {
        $this->sharedNumber();

        $r = $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone]);

        $r->assertStatus(409)->assertJson(['success' => false, 'code' => 'phone_ambiguous']);
        $this->assertSame(0, MobileOtp::where('phone', $this->phone)->count(), 'no OTP/SMS should be sent for an ambiguous number');
    }

    public function test_send_otp_ignores_the_inactive_half_of_a_collision(): void
    {
        $society = $this->makeSociety();
        $this->makeUserWithRole($society, 'Admin', ['phone' => $this->phone]);
        $this->makeUserWithRole($society, 'Guard', ['phone' => $this->phone, 'status' => 'inactive']);

        // Only one of the two accounts is active, so this is not ambiguous.
        $r = $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone]);

        $r->assertOk();
        $this->assertSame(1, MobileOtp::where('phone', $this->phone)->count());
    }

    public function test_verify_otp_refuses_a_number_that_became_ambiguous_after_send(): void
    {
        $society = $this->makeSociety();
        // Owner + Tenant, not Admin + Guard: a privileged account on a shared number is no longer a phone-only candidate (OtpPrivilegedAccountPhoneTest)
        $this->makeUserWithRole($society, 'Owner', ['phone' => $this->phone]);

        $send = $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone])->assertOk();

        // A second account picks up the same number before the code is
        // verified (e.g. an office data-entry change mid-flow).
        $this->makeUserWithRole($society, 'Tenant', ['phone' => $this->phone]);

        $r = $this->postJson('/api/v1/auth/verify-otp', ['phone' => $this->phone, 'otp' => $send->json('otp')]);

        $r->assertStatus(409)->assertJson(['success' => false]);
        $this->assertStringNotContainsString('token', $r->getContent());
    }

    public function test_send_otp_still_works_for_a_number_with_exactly_one_active_account(): void
    {
        $society = $this->makeSociety();
        $this->makeUserWithRole($society, 'Admin', ['phone' => $this->phone]);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone])->assertOk();
        $this->assertSame(1, MobileOtp::where('phone', $this->phone)->count());
    }

    // ── the current app: verify, then choose ──────────────────────────────

    public function test_verify_offers_the_accounts_instead_of_picking_one(): void
    {
        [$admin, $guard] = $this->sharedNumber();

        $r = $this->verifyAsChooserApp()->assertOk();

        $r->assertJson(['success' => true, 'requires_account_choice' => true]);
        $this->assertNotEmpty($r->json('selection_token'));
        $this->assertNull($r->json('token'), 'no account is signed in until the user picks one');
        $this->assertSame(0, PersonalAccessToken::count());
        $this->assertEqualsCanonicalizing([$admin->id, $guard->id], array_column($r->json('accounts'), 'id'));
        $this->assertEqualsCanonicalizing(['Owner', 'Tenant'], array_column($r->json('accounts'), 'role'));   // was Admin + Guard: see sharedNumber()
        $this->assertSame(['Royal Eden', 'Royal Eden'], array_column($r->json('accounts'), 'society_name'));
    }

    public function test_the_chosen_account_is_signed_in_with_the_full_user_payload(): void
    {
        [$admin, $guard] = $this->sharedNumber();
        $selection = $this->verifyAsChooserApp()->json('selection_token');

        $r = $this->postJson('/api/v1/auth/select-account', ['selection_token' => $selection, 'user_id' => $guard->id]);

        $r->assertOk()->assertJson(['success' => true])->assertJsonPath('user.id', $guard->id)->assertJsonPath('user.role', 'Tenant');   // was Guard: see sharedNumber()
        $this->assertNotEmpty($r->json('token'));
        $this->assertArrayHasKey('society', $r->json('user'), 'same full payload as password login');
        $this->assertSame(1, $guard->tokens()->count());
        $this->assertSame(0, $admin->tokens()->count(), 'only the chosen account is signed in');

        // And the token really is the guard's.
        $this->withHeader('Authorization', 'Bearer ' . $r->json('token'))
            ->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $guard->id);
    }

    public function test_a_selection_token_is_single_use(): void
    {
        [$admin, $guard] = $this->sharedNumber();
        $selection = $this->verifyAsChooserApp()->json('selection_token');

        $this->postJson('/api/v1/auth/select-account', ['selection_token' => $selection, 'user_id' => $guard->id])->assertOk();

        $this->postJson('/api/v1/auth/select-account', ['selection_token' => $selection, 'user_id' => $admin->id])
            ->assertStatus(422)->assertJson(['success' => false, 'code' => 'selection_expired']);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_a_selection_cannot_sign_in_to_an_account_outside_the_list(): void
    {
        $this->sharedNumber();
        $stranger = $this->makeUserWithRole($this->makeSociety('Other Society'), 'Admin', ['phone' => '9000000077']);
        $selection = $this->verifyAsChooserApp()->json('selection_token');

        $this->postJson('/api/v1/auth/select-account', ['selection_token' => $selection, 'user_id' => $stranger->id])
            ->assertStatus(422)->assertJson(['code' => 'selection_expired']);
        $this->assertSame(0, $stranger->tokens()->count());
    }

    public function test_a_selection_is_refused_once_the_account_no_longer_has_the_number(): void
    {
        [, $guard] = $this->sharedNumber();
        $selection = $this->verifyAsChooserApp()->json('selection_token');

        $guard->update(['phone' => '9123456780']);

        $this->postJson('/api/v1/auth/select-account', ['selection_token' => $selection, 'user_id' => $guard->id])
            ->assertStatus(422);
        $this->assertSame(0, $guard->tokens()->count());
    }

    public function test_an_old_selection_is_refused(): void
    {
        [, $guard] = $this->sharedNumber();
        $selection = $this->verifyAsChooserApp()->json('selection_token');

        $this->travel(6)->minutes();

        $this->postJson('/api/v1/auth/select-account', ['selection_token' => $selection, 'user_id' => $guard->id])
            ->assertStatus(422)->assertJson(['code' => 'selection_expired']);
    }

    public function test_a_made_up_selection_token_is_refused(): void
    {
        [, $guard] = $this->sharedNumber();

        $this->postJson('/api/v1/auth/select-account', ['selection_token' => str_repeat('a', 64), 'user_id' => $guard->id])
            ->assertStatus(422);
        $this->assertSame(0, PersonalAccessToken::count());
    }

    // ── a number on accounts in two societies ─────────────────────────────

    public function test_a_shared_number_is_sent_through_whichever_society_can_reach_it(): void
    {
        $this->app['env'] = 'production';
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $this->makeUserWithRole($societyA, 'Owner', ['phone' => $this->phone]);
        $this->makeUserWithRole($societyB, 'Tenant', ['phone' => $this->phone]);
        foreach ([[$societyA, 'KEY-A-broken'], [$societyB, 'KEY-B-works']] as [$society, $key]) {
            SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $society->id);
            SystemSetting::set('sms', 'fast2sms_api_key', $key, 'encrypted', $society->id);
            SystemSetting::set('sms', 'fast2sms_route', 'otp', 'string', $society->id);
            SystemSetting::set('sms', 'fast2sms_otp_id', 'otpid12345', 'string', $society->id);
        }
        $keysTried = [];
        SmsService::$transportOverride = function ($url, $data, $headers) use (&$keysTried) {
            $works = str_contains(implode(' ', $headers), 'KEY-B-works');
            $keysTried[] = $works ? 'B' : 'A';

            return $works
                ? [['return' => true, 'request_id' => 'r1'], 200]
                : [['return' => false, 'status_code' => 412, 'message' => 'Invalid Authentication, Check Authorization Key'], 401];
        };

        $this->postJson('/api/v1/auth/send-otp', ['phone' => $this->phone, 'can_choose_account' => true])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(['A', 'B'], $keysTried, "society A's broken key must not lock out a person society B can reach");
    }
}
