<?php

namespace Tests\Feature;

use App\Models\MobileOtp;
use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Phone numbers are not identities. (1) A password-reset code is bound to ONE account and purpose; self-service reset is refused (with the
 * same answer as an unknown e-mail) for an Admin / Super Admin whose number is on another active account, and for any account on a number
 * that spans several societies. (2) A number alone never signs in an Admin / Super Admin while the number is shared. Synthetic accounts
 * only; the SMS transport is a test double, so nothing is sent.
 */
class OtpAccountBindingTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '9877001122';
    private const NEW_PASSWORD = 'a-new-Strong-pass-9';

    private array $codes = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.expose_dev_code' => false]);
        $this->app['env'] = 'testing';
        SmsService::$transportOverride = function ($url, $data) {
            $this->codes[] = (string) ($data['otp'] ?? '');

            return [['return' => true, 'request_id' => 'r' . count($this->codes)], 200];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function society(string $name): Society
    {
        $s = $this->makeSociety($name);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $s->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'KEY-abc', 'encrypted', $s->id);
        SystemSetting::set('sms', 'fast2sms_route', 'otp', 'string', $s->id);
        SystemSetting::set('sms', 'fast2sms_otp_id', 'otpid12345', 'string', $s->id);

        return $s;
    }

    private function user(Society $s, string $role, string $phone = self::PHONE, array $over = []): User
    {
        return $this->makeUserWithRole($s, $role, ['phone' => $phone] + $over);
    }

    private function forgot(User $u)
    {
        RateLimiter::clear('otp-cooldown:' . substr($u->phone, -10));

        return $this->postJson('/api/v1/auth/forgot-password', ['email' => $u->email]);
    }

    private function reset(User $u, string $otp)
    {
        return $this->postJson('/api/v1/auth/reset-password', ['email' => $u->email, 'otp' => $otp, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD]);
    }

    // ── reset codes belong to one account ───────────────────────────────────

    public function test_a_code_requested_for_one_account_cannot_reset_another_on_the_same_number(): void
    {
        $s = $this->society('Alpha');
        $a = $this->user($s, 'Owner');
        $b = $this->user($s, 'Tenant');
        $this->forgot($a)->assertOk();
        $code = end($this->codes);
        $hashBefore = $b->fresh()->password;

        $this->reset($b, $code)->assertStatus(422);
        $this->assertSame($hashBefore, $b->fresh()->password, 'B was not reset with A\'s code');

        $this->reset($a, $code)->assertOk()->assertJson(['success' => true]);   // the code still works for the account it was made for
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $a->fresh()->password));
    }

    public function test_asking_for_b_does_not_retire_a_and_each_code_is_spent_once(): void
    {
        $s = $this->society('Alpha');
        $a = $this->user($s, 'Owner');
        $b = $this->user($s, 'Tenant');
        $this->forgot($a)->assertOk();
        $codeA = end($this->codes);
        $this->forgot($b)->assertOk();
        $codeB = end($this->codes);

        $this->reset($a, $codeA)->assertOk();
        $this->reset($a, $codeA)->assertStatus(422);   // spent
        $this->reset($b, $codeB)->assertOk();
    }

    public function test_the_bound_code_is_voided_by_a_change_of_number_and_a_user_less_row_never_resets(): void
    {
        $s = $this->society('Alpha');
        $a = $this->user($s, 'Owner');
        $this->forgot($a)->assertOk();
        $code = end($this->codes);
        $a->update(['phone' => '9877009999']);
        $this->reset($a, $code)->assertStatus(422);

        [, $legacy] = MobileOtp::issue('9877009999', MobileOtp::PURPOSE_RESET);   // a user-less row (how codes were issued before)
        $this->reset($a, $legacy)->assertStatus(422);
        $this->assertFalse(MobileOtp::check('9877009999', MobileOtp::PURPOSE_RESET, $legacy, $a->id));
    }

    public function test_the_hash_depends_on_the_account(): void
    {
        $this->assertNotSame(MobileOtp::hashCode(self::PHONE, 'password_reset', '1234', 1), MobileOtp::hashCode(self::PHONE, 'password_reset', '1234', 2));
        $this->assertSame(MobileOtp::hashCode(self::PHONE, 'login', '1234'), MobileOtp::hashCode(self::PHONE, 'login', '1234', null), 'login codes hash exactly as before');
    }

    // ── shared-number refusals ──────────────────────────────────────────────

    public function test_an_admin_on_a_shared_number_cannot_use_sms_reset_and_gets_the_unknown_email_answer(): void
    {
        $s = $this->society('Alpha');
        $admin = $this->user($s, 'Admin');
        $this->user($s, 'Guard');   // same number
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertOk()->json();

        $refused = $this->forgot($admin)->assertOk()->json();

        $this->assertSame($unknown, $refused, 'privacy-safe: indistinguishable from an unknown address');
        $this->assertSame(0, MobileOtp::where('purpose', 'password_reset')->count());
        $this->assertSame([], $this->codes, 'no SMS was made');
    }

    public function test_an_admin_alone_on_their_number_and_a_resident_sharing_within_one_society_can_still_reset(): void
    {
        $s = $this->society('Alpha');
        $soleAdmin = $this->user($s, 'Admin', '9877000001');
        $this->forgot($soleAdmin)->assertOk();
        $this->reset($soleAdmin, end($this->codes))->assertOk();

        $owner = $this->user($s, 'Owner', '9877000002');
        $tenant = $this->user($s, 'Tenant', '9877000002');   // two non-privileged accounts, one society
        $this->forgot($owner)->assertOk();
        $this->reset($owner, end($this->codes))->assertOk();
        $this->assertNotNull($tenant->fresh());
    }

    public function test_a_number_spanning_two_societies_is_refused_for_every_account_on_it(): void
    {
        $a = $this->society('Alpha');
        $b = $this->society('Beta');
        $ownerA = $this->user($a, 'Owner');
        $ownerB = $this->user($b, 'Tenant');

        $this->forgot($ownerA)->assertOk();
        $this->forgot($ownerB)->assertOk();

        $this->assertSame(0, MobileOtp::where('purpose', 'password_reset')->count());
        $this->assertSame([], $this->codes);
    }

    public function test_the_rule_is_checked_again_when_the_code_is_redeemed(): void
    {
        $a = $this->society('Alpha');
        $owner = $this->user($a, 'Owner');
        $this->forgot($owner)->assertOk();
        $code = end($this->codes);

        $this->user($this->society('Beta'), 'Owner');   // the number becomes shared across societies after the code was sent
        $hash = $owner->fresh()->password;

        $this->reset($owner, $code)->assertStatus(422);
        $this->assertSame($hash, $owner->fresh()->password);
    }

    public function test_a_successful_reset_revokes_only_that_accounts_tokens(): void
    {
        $s = $this->society('Alpha');
        $a = $this->user($s, 'Owner', '9877000011');
        $other = $this->user($s, 'Owner', '9877000012');
        $a->createToken('mobile-app');
        $other->createToken('mobile-app');
        $this->forgot($a)->assertOk();

        $this->reset($a, end($this->codes))->assertOk();

        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $a->id)->count(), 'only the fresh sign-in token');
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $other->id)->count(), 'the other account is untouched');
    }

    // ── phone-only LOGIN never reaches a privileged account on a shared number ──

    private function loginAs(string $phone, bool $chooser = true)
    {
        RateLimiter::clear('otp-cooldown:' . $phone);
        RateLimiter::clear('otp-send:' . $phone);
        $this->app['env'] = 'testing';
        $send = $this->postJson('/api/v1/auth/send-otp', ['phone' => $phone, 'can_choose_account' => $chooser]);
        if (!$send->json('success') || empty($this->codes)) {
            return $send;
        }

        return $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'otp' => end($this->codes), 'can_choose_account' => $chooser]);
    }

    public function test_an_admin_sharing_a_number_is_not_a_login_candidate_but_the_resident_still_signs_in(): void
    {
        $s = $this->society('Alpha');
        $admin = $this->user($s, 'Admin');
        $owner = $this->user($s, 'Owner');

        $r = $this->loginAs(self::PHONE)->assertOk();

        $this->assertSame($owner->id, $r->json('user.id'), 'the only candidate left signs in directly');
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertArrayNotHasKey('accounts', $r->json());
    }

    public function test_the_chooser_never_lists_an_admin_or_super_admin(): void
    {
        $s = $this->society('Alpha');
        $this->user($s, 'Admin');
        $super = $this->user($s, 'Super Admin', self::PHONE, ['society_id' => null]);
        $o = $this->user($s, 'Owner');
        $t = $this->user($s, 'Tenant');

        $r = $this->loginAs(self::PHONE)->assertOk();

        $this->assertTrue($r->json('requires_account_choice'));
        $this->assertEqualsCanonicalizing([$o->id, $t->id], array_column($r->json('accounts'), 'id'));
        $this->assertNotContains($super->id, array_column($r->json('accounts'), 'id'));
    }

    public function test_a_tampered_selection_that_names_an_admin_is_refused(): void
    {
        $s = $this->society('Alpha');
        $admin = $this->user($s, 'Admin');
        $this->user($s, 'Owner');
        $this->user($s, 'Tenant');
        // a selection token as the server would issue it, but with the Admin's id slipped into its list
        Cache::put('otp-account-choice:' . hash('sha256', 'forged-token'), ['phone' => self::PHONE, 'user_ids' => [$admin->id]], now()->addMinutes(5));

        $this->postJson('/api/v1/auth/select-account', ['selection_token' => 'forged-token', 'user_id' => $admin->id])->assertStatus(422);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_a_privileged_account_that_owns_its_number_alone_may_still_use_phone_login(): void
    {
        $s = $this->society('Alpha');
        $admin = $this->user($s, 'Admin', '9877000033');

        $r = $this->loginAs('9877000033')->assertOk();

        $this->assertSame($admin->id, $r->json('user.id'));
    }

    public function test_two_admins_sharing_one_number_cannot_use_phone_login_at_all(): void
    {
        $s = $this->society('Alpha');
        $this->user($s, 'Admin');
        $this->user($this->society('Beta'), 'Admin');

        $r = $this->loginAs(self::PHONE);

        $r->assertOk();   // the same answer as a number nobody has
        $this->assertSame(0, MobileOtp::count());
        $this->assertSame([], $this->codes);
        $this->assertSame(0, PersonalAccessToken::count());
    }
}