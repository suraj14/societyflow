<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountActive;
use App\Models\MobileOtp;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** inactive and suspended accounts: refused at every sign-in, cut off mid-session, tokens revoked on the change - and nobody else affected. */
class InactiveAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'Right-pass-1!x';

    private function member(string $status = 'active', string $role = 'Owner', array $over = []): User
    {
        return $this->makeUserWithRole($this->makeSociety('S ' . uniqid()), $role, ['password' => Hash::make(self::PW), 'status' => $status] + $over);
    }

    /** @return array<string, array{string}> */
    public static function blockedStatuses(): array
    {
        return ['inactive' => ['inactive'], 'suspended' => ['suspended']];
    }

    /** @dataProvider blockedStatuses */
    public function test_web_login_refuses_a_blocked_account_with_the_same_wording_as_the_api(string $status): void
    {
        $user = $this->member($status);

        $this->post('/login', ['email' => $user->email, 'password' => self::PW])->assertSessionHasErrors(['email' => EnsureAccountActive::MESSAGE]);

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at, 'a refused sign-in is not a login');
    }

    /** @dataProvider blockedStatuses */
    public function test_api_login_refuses_a_blocked_account(string $status): void
    {
        $user = $this->member($status);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => self::PW])->assertForbidden()->assertJson(['success' => false]);
        $this->assertSame(0, PersonalAccessToken::count());
    }

    /** @dataProvider blockedStatuses */
    public function test_otp_login_never_offers_a_blocked_account(string $status): void
    {
        $user = $this->member($status, 'Owner', ['phone' => '9877555001']);
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $user->society_id);
        SmsService::$transportOverride = fn () => [['return' => true, 'request_id' => 'r'], 200];

        try {
            $this->postJson('/api/v1/auth/send-otp', ['phone' => '9877555001'])->assertOk();
            $this->assertSame(0, MobileOtp::count(), 'no code for a blocked account');
        } finally {
            SmsService::$transportOverride = null;
        }
    }

    public function test_active_users_and_the_super_admin_sign_in_as_before(): void
    {
        $owner = $this->member();
        $super = $this->makeUserWithRole($this->makeSociety('Home'), 'Super Admin', ['password' => Hash::make(self::PW), 'society_id' => null]);

        $this->post('/login', ['email' => $owner->email, 'password' => self::PW])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($owner);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $super->email, 'password' => self::PW])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($super);
    }

    /** @dataProvider blockedStatuses */
    public function test_a_user_deactivated_mid_session_is_signed_out_on_the_next_request(string $status): void
    {
        $user = $this->member();
        $this->post('/login', ['email' => $user->email, 'password' => self::PW]);   // a real sign-in, so the session holds only the user's id
        $this->get('/dashboard')->assertOk();

        User::whereKey($user->id)->update(['status' => $status]);   // as if another admin changed it meanwhile
        $this->app['auth']->forgetGuards();                       // the next request loads the user from the database again

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** @dataProvider blockedStatuses */
    public function test_changing_the_status_deletes_that_users_tokens_only(string $status): void
    {
        $user = $this->member();
        $bystander = $this->member();
        $super = $this->makeUserWithRole($this->makeSociety('Home'), 'Super Admin', ['society_id' => null]);
        foreach ([$user, $bystander, $super] as $u) {
            $u->createToken('mobile-app');
        }

        $user->update(['status' => $status]);

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $user->id)->count());
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $bystander->id)->count());
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $super->id)->count());
    }

    public function test_reactivating_an_account_does_not_touch_tokens_and_unrelated_edits_keep_them(): void
    {
        $user = $this->member();
        $user->createToken('mobile-app');
        $user->update(['name' => 'Renamed']);
        $this->assertSame(1, $user->tokens()->count(), 'a profile edit is not a status change');

        $user->update(['status' => 'inactive']);
        $user->update(['status' => 'active']);
        $user->createToken('mobile-app');
        $user->update(['status' => 'active']);
        $this->assertSame(1, $user->tokens()->count());
    }

    /** @dataProvider blockedStatuses */
    public function test_a_token_that_slipped_through_is_still_refused_on_the_api(string $status): void
    {
        $user = $this->member();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertOk();

        User::whereKey($user->id)->update(['status' => $status]);   // bypasses the model hook, as a direct database edit would

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::find($user->id));
        $this->getJson('/api/v1/auth/me')->assertForbidden()->assertJson(['account_inactive' => true]);
    }
}