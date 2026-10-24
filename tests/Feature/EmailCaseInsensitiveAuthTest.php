<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * An address is the same address whatever its letter case. Accounts created before registration lower-cased e-mails
 * hold them as typed (NAME@EXAMPLE.COM); SQLite compares `email = ?` case-sensitively, so such an owner could not sign
 * in - not even right after an admin set a new password - and asked in vain for a reset link.
 */
class EmailCaseInsensitiveAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Brand-New-Pass-2026!';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $society = $this->makeSociety('Alpha');
        $this->user = $this->makeUserWithRole($society, 'Owner', ['email' => 'ASHA.OWNER@EXAMPLE.TEST', 'password' => Hash::make(self::PASSWORD)]);
    }

    public function test_the_web_login_finds_the_account_whatever_case_is_typed(): void
    {
        $this->post(route('login'), ['email' => 'asha.owner@example.test', 'password' => self::PASSWORD])->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_wrong_password_is_still_refused(): void
    {
        $this->post(route('login'), ['email' => 'asha.owner@example.test', 'password' => 'not-the-password'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_app_login_finds_the_account_whatever_case_is_typed(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'Asha.Owner@example.test', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_after_an_admin_sets_a_new_password_the_owner_signs_in_with_it(): void
    {
        $owner = Owner::create(['society_id' => $this->user->society_id, 'name' => 'Asha', 'email' => 'ASHA.OWNER@EXAMPLE.TEST', 'phone' => '9000000001', 'status' => 'active']);
        $this->user->update(['owner_id' => $owner->id]);
        $admin = $this->makeUserWithRole($this->user->society, 'Admin');

        $this->actingAs($admin)
            ->post(route('owners.reset-password', $owner), ['password' => 'Admin-Set-Pass-2026!', 'password_confirmation' => 'Admin-Set-Pass-2026!'])
            ->assertSessionHas('success');
        auth()->logout();

        $this->post(route('login'), ['email' => 'asha.owner@example.test', 'password' => 'Admin-Set-Pass-2026!'])->assertRedirect();
        $this->assertAuthenticatedAs($this->user->fresh());
    }

    public function test_a_reset_link_is_sent_whatever_case_is_typed(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'asha.owner@example.test'])->assertSessionHas('success');

        Notification::assertSentToTimes($this->user, ResetPasswordNotification::class, 1);
    }

    public function test_the_reset_link_works_whatever_case_is_typed_on_the_form(): void
    {
        $token = Password::createToken($this->user);

        $this->post(route('password.update'), ['token' => $token, 'email' => 'asha.owner@example.test', 'password' => 'Another-Strong-2026!', 'password_confirmation' => 'Another-Strong-2026!'])
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Another-Strong-2026!', $this->user->fresh()->password));
    }

    public function test_a_different_address_is_still_a_different_account(): void
    {
        $this->post(route('login'), ['email' => 'asha.owner@example.tes', 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertNull(User::whereEmail('asha.owner@example')->first());
        $this->assertTrue(User::whereEmail('  Asha.Owner@Example.Test ')->first()->is($this->user));
    }
}
