<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Web password reset: the paths PasswordManagementTest does not pin - token expiry, invalid/reused/mismatched
 * tokens, link throttling, login throttling, and what the mailed link actually points at. Notifications are
 * faked, so nothing is delivered (and EXTERNAL_MESSAGING_ENABLED is false suite-wide).
 */
class PasswordResetEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'resident@test.local')
    {
        return $this->makeUserWithRole($this->makeSociety(), 'Tenant', ['email' => $email, 'password' => Hash::make('Old-password-1')]);
    }

    private function token($user): string
    {
        return Password::broker()->createToken($user);
    }

    private function reset(string $email, string $token, string $password = 'New-password-9', ?string $confirm = null)
    {
        return $this->post('/reset-password', ['email' => $email, 'token' => $token, 'password' => $password, 'password_confirmation' => $confirm ?? $password]);
    }

    public function test_the_mailed_link_targets_this_app_reset_page_with_the_token_and_the_right_recipient(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);

            return str_contains($mail->actionUrl, '/reset-password/' . $n->token) && str_contains($mail->actionUrl, urlencode($user->email));
        });
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_the_confirmation_messages_are_actually_shown_to_the_person(): void
    {
        Notification::fake();
        $user = $this->user();

        // Found in the browser: the controller flashed `success` but neither page rendered it, so after
        // "Send Reset Link" and after a successful reset the user saw an unchanged form and no feedback.
        $this->followingRedirects()->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@test.local'])
            ->assertSee('If an account exists for that email, a password reset link has been sent.');

        $this->followingRedirects()->reset($user->email, $this->token($user))
            ->assertSee('Your password has been reset. Please log in.');
    }

    public function test_a_second_request_inside_the_throttle_window_sends_no_second_mail(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/forgot-password', ['email' => $user->email]);
        $again = $this->post('/forgot-password', ['email' => $user->email]);

        $again->assertSessionHas('success');   // identical wording: the throttle is not an oracle
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_an_expired_token_is_refused_and_the_password_is_unchanged(): void
    {
        $user = $this->user();
        $token = $this->token($user);

        $this->travel(61)->minutes();
        $this->reset($user->email, $token)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Old-password-1', $user->fresh()->password));
    }

    public function test_a_token_just_inside_the_expiry_still_works_once_and_cannot_be_replayed(): void
    {
        $user = $this->user();
        $token = $this->token($user);

        $this->travel(59)->minutes();
        $this->reset($user->email, $token)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('New-password-9', $user->fresh()->password));

        $this->reset($user->email, $token, 'Attacker-pass-7')->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('New-password-9', $user->fresh()->password), 'a used token cannot change the password again');
    }

    public function test_an_invalid_token_or_a_token_for_another_account_is_refused(): void
    {
        $victim = $this->user('victim@test.local');
        $other = $this->user('other@test.local');
        $otherToken = $this->token($other);

        $this->reset($victim->email, 'not-a-real-token')->assertSessionHasErrors('email');
        $this->reset($victim->email, $otherToken)->assertSessionHasErrors('email');
        $this->reset('nobody@test.local', $otherToken)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Old-password-1', $victim->fresh()->password));
        $this->assertTrue(Hash::check('Old-password-1', $other->fresh()->password));
    }

    public function test_a_weak_or_unconfirmed_new_password_is_rejected_without_burning_the_token(): void
    {
        $user = $this->user();
        $token = $this->token($user);

        $this->reset($user->email, $token, 'short')->assertSessionHasErrors('password');
        $this->reset($user->email, $token, 'Long-enough-1', 'different-confirmation')->assertSessionHasErrors('password');

        $this->reset($user->email, $token)->assertRedirect(route('login'));
    }

    public function test_the_login_form_locks_after_five_failures_even_for_the_right_password(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-' . $i])->assertSessionHasErrors('email');
        }

        $locked = $this->post('/login', ['email' => $user->email, 'password' => 'Old-password-1']);
        $locked->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_a_successful_login_clears_the_failure_count(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'Old-password-1'])->assertSessionHasNoErrors();
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('Too many', session('errors')->first('email'));
    }
}
