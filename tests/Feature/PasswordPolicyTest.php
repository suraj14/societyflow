<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * One password policy for every place a password is created or changed. Before: the web asked for 8 characters, the
 * mobile API for 6, nothing checked strength, and the Security settings (minimum length, uppercase, number, special
 * character) were saved but never applied. Now App\Support\PasswordPolicy decides - the server enforces it and the
 * strength meter on the web forms and in the app is built from the same array.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        PasswordPolicy::flush();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin', ['password' => Hash::make('Old-Passw0rd!')]);
    }

    protected function tearDown(): void
    {
        PasswordPolicy::flush();
        parent::tearDown();
    }

    private function setPolicy(array $values, ?Society $society = null): void
    {
        foreach ($values as $key => $value) {
            SystemSetting::set('security', $key, $value, is_bool($value) ? 'boolean' : 'integer', $society?->id);
        }
        PasswordPolicy::flush();
    }

    private function passes(string $password, ?int $societyId, ?string $confirmation = null): bool
    {
        return !Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation ?? $password],
            ['password' => PasswordPolicy::required(PasswordPolicy::forSociety($societyId))]
        )->fails();
    }

    // ── what the policy is ──────────────────────────────────────────────────

    public function test_the_default_policy_is_eight_characters_and_nothing_else(): void
    {
        $this->assertSame(['min' => 8, 'uppercase' => false, 'number' => false, 'special' => false], PasswordPolicy::forSociety($this->society->id));
    }

    public function test_a_societys_own_security_settings_become_its_policy(): void
    {
        $this->setPolicy(['password_min_length' => 12, 'password_require_uppercase' => true, 'password_require_number' => true, 'password_require_special' => true], $this->society);

        $this->assertSame(['min' => 12, 'uppercase' => true, 'number' => true, 'special' => true], PasswordPolicy::forSociety($this->society->id));
        $this->assertSame(['min' => 8, 'uppercase' => false, 'number' => false, 'special' => false], PasswordPolicy::forSociety($this->makeSociety('Beta')->id), 'another society is unaffected');
    }

    public function test_the_platform_setting_is_the_fallback_for_a_society_with_none_of_its_own(): void
    {
        $this->setPolicy(['password_min_length' => 10, 'password_require_number' => true], null);

        $this->assertSame(['min' => 10, 'uppercase' => false, 'number' => true, 'special' => false], PasswordPolicy::forSociety($this->society->id));
        $this->assertSame(10, PasswordPolicy::forSociety(null)['min']);
    }

    public function test_a_saved_minimum_below_the_baseline_is_treated_as_the_baseline_and_a_huge_one_is_capped(): void
    {
        $this->setPolicy(['password_min_length' => 6], $this->society);
        $this->assertSame(8, PasswordPolicy::forSociety($this->society->id)['min'], 'the old form allowed 6; the web always demanded 8');

        $this->setPolicy(['password_min_length' => 500], $this->society);
        $this->assertSame(32, PasswordPolicy::forSociety($this->society->id)['min']);
    }

    // ── what it accepts and refuses ─────────────────────────────────────────

    /** @dataProvider passwords */
    public function test_weak_and_compliant_passwords_on_the_default_policy(string $password, bool $ok): void
    {
        $this->assertSame($ok, $this->passes($password, $this->society->id), $password);
    }

    public static function passwords(): array
    {
        return [
            'long enough, no tricks'          => ['correct horse battery', true],
            'eight characters exactly'        => ['abcdefgh', true],
            'seven characters'                => ['abcdefg', false],
            'empty'                           => ['', false],
            'a well-known password'           => ['password123', false],
            'a well-known password, cased'    => ['PassWord', false],
            'only digits that are well known' => ['12345678', false],
            'more than the ceiling'           => [str_repeat('a', 65), false],
            'at the ceiling'                  => [str_repeat('a1', 32), true],
            'unicode letters count'           => ['pässwörd-ñandú', true],
        ];
    }

    /** @dataProvider strictPasswords */
    public function test_each_requirement_a_society_switches_on_is_enforced(string $password, bool $ok): void
    {
        $this->setPolicy(['password_min_length' => 10, 'password_require_uppercase' => true, 'password_require_number' => true, 'password_require_special' => true], $this->society);

        $this->assertSame($ok, $this->passes($password, $this->society->id), $password);
    }

    public static function strictPasswords(): array
    {
        return [
            'meets all four'              => ['Sunrise-Villa-159', true],
            'too short for ten'           => ['Sun-1ise', false],
            'no uppercase'                => ['sunrise-villa-159', false],
            'no number'                   => ['Sunrise-Villa-One', false],
            'no special character'        => ['SunriseVilla159', false],
            'unicode special is special'  => ['SunriseVilla159€', true],
        ];
    }

    public function test_the_reason_is_a_plain_sentence_for_each_unmet_requirement(): void
    {
        $this->setPolicy(['password_require_uppercase' => true, 'password_require_number' => true, 'password_require_special' => true], $this->society);

        $v = Validator::make(['password' => 'abcdefghij', 'password_confirmation' => 'abcdefghij'], ['password' => PasswordPolicy::required(PasswordPolicy::forSociety($this->society->id))]);

        $this->assertEqualsCanonicalizing([
            'The password must include an uppercase letter.', 'The password must include a number.', 'The password must include a special character such as ! @ # $ %.',
        ], $v->errors()->get('password'));
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $this->assertFalse($this->passes('correct horse battery', $this->society->id, 'correct horse batterX'));
    }

    // ── the screens and the API describe the same policy ────────────────────

    public function test_the_description_lists_exactly_the_active_requirements(): void
    {
        $this->setPolicy(['password_min_length' => 12, 'password_require_number' => true], $this->society);

        $d = PasswordPolicy::describe(PasswordPolicy::forSociety($this->society->id));

        $this->assertSame([['key' => 'min', 'label' => 'At least 12 characters'], ['key' => 'number', 'label' => 'A number (0-9)']], $d['requirements']);
        $this->assertSame(12, $d['min']);
        $this->assertSame(64, $d['max']);
        $this->assertContains('password123', $d['common']);
    }

    public function test_web_forms_carry_the_servers_policy_for_the_meter(): void
    {
        $this->setPolicy(['password_min_length' => 11, 'password_require_special' => true], $this->society);

        foreach (['/profile/edit'] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<script type="application/json" id="sf-password-policy">(.+?)</script>#s', $html);
            preg_match('#<script type="application/json" id="sf-password-policy">(.+?)</script>#s', $html, $m);
            $policy = json_decode($m[1], true);
            $this->assertSame(11, $policy['min']);
            $this->assertTrue($policy['special']);
            $this->assertFalse($policy['uppercase']);
            $this->assertSame(PasswordPolicy::describe(PasswordPolicy::forSociety($this->society->id)), $policy, 'the page and the server are the same array');
            $this->assertStringContainsString('input[type="password"][name]', $html, 'the script that attaches the meter and show/hide is on the page');
        }
    }

    public function test_the_register_and_reset_pages_have_the_meter_and_the_login_page_does_not(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('id="sf-password-policy"', false);
        $this->get(route('password.reset', ['token' => 'x', 'email' => $this->admin->email]))->assertOk()->assertSee('id="sf-password-policy"', false);
        $this->get(route('login'))->assertOk()->assertDontSee('id="sf-password-policy"', false);
    }

    public function test_the_reset_page_shows_the_policy_of_the_account_being_reset(): void
    {
        $this->setPolicy(['password_min_length' => 14], $this->society);

        $html = $this->get(route('password.reset', ['token' => 'x', 'email' => $this->admin->email]))->getContent();
        preg_match('#id="sf-password-policy">(.+?)</script>#s', $html, $m);
        $this->assertSame(14, json_decode($m[1], true)['min']);

        $html = $this->get(route('password.reset', ['token' => 'x', 'email' => 'nobody@example.test']))->getContent();
        preg_match('#id="sf-password-policy">(.+?)</script>#s', $html, $m);
        $this->assertSame(8, json_decode($m[1], true)['min'], 'an unknown email gets the platform policy and reveals nothing');
    }

    public function test_the_mobile_policy_endpoint_is_public_and_follows_the_signed_in_users_society(): void
    {
        $this->setPolicy(['password_min_length' => 12, 'password_require_uppercase' => true], $this->society);

        $public = $this->getJson('/api/v1/password-policy')->assertOk()->json('data');
        $this->assertSame(8, $public['min'], 'signed out: the platform policy');
        $this->assertFalse($public['uppercase']);

        Sanctum::actingAs($this->admin);
        $own = $this->getJson('/api/v1/password-policy')->assertOk()->json('data');
        $this->assertSame(12, $own['min']);
        $this->assertTrue($own['uppercase']);
        $this->assertArrayNotHasKey('society', $own);
    }

    // ── web: password reset by emailed link ─────────────────────────────────

    private function resetByLink(User $user, string $password, ?string $confirmation = null)
    {
        $token = Password::createToken($user);

        return $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => $password, 'password_confirmation' => $confirmation ?? $password]);
    }

    public function test_the_emailed_reset_link_enforces_the_policy_and_still_works_for_a_good_password(): void
    {
        $this->resetByLink($this->admin, 'short')->assertSessionHasErrors('password');
        $this->resetByLink($this->admin, 'password123')->assertSessionHasErrors('password');
        $this->resetByLink($this->admin, 'correct horse battery', 'something else')->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('Old-Passw0rd!', $this->admin->fresh()->password), 'a refused reset changes nothing');

        $this->resetByLink($this->admin, 'correct horse battery')->assertRedirect(route('login'))->assertSessionHas('success');
        $this->assertTrue(Hash::check('correct horse battery', $this->admin->fresh()->password));
    }

    public function test_the_reset_link_uses_the_accounts_society_policy(): void
    {
        $this->setPolicy(['password_min_length' => 10, 'password_require_uppercase' => true, 'password_require_number' => true], $this->society);

        $this->resetByLink($this->admin, 'lowercase only here')->assertSessionHasErrors('password');
        $this->resetByLink($this->admin, 'Sunrise villa 159')->assertRedirect(route('login'));
    }

    public function test_a_reset_revokes_the_api_sessions(): void
    {
        $this->admin->createToken('phone');
        $this->assertSame(1, $this->admin->tokens()->count());

        $this->resetByLink($this->admin, 'correct horse battery')->assertRedirect(route('login'));

        $this->assertSame(0, $this->admin->tokens()->count());
    }

    // ── web: profile ────────────────────────────────────────────────────────

    private function profile(array $over = [])
    {
        return $this->actingAs($this->admin)->put(route('profile.update'), $over + ['name' => $this->admin->name, 'email' => $this->admin->email]);
    }

    public function test_changing_the_password_on_the_profile_needs_the_current_one(): void
    {
        $this->profile(['password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery'])->assertSessionHasErrors('current_password');
        $this->profile(['current_password' => 'wrong', 'password' => 'correct horse battery', 'password_confirmation' => 'correct horse battery'])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Old-Passw0rd!', $this->admin->fresh()->password));
    }

    public function test_the_profile_enforces_the_policy_and_a_change_ends_other_sessions(): void
    {
        $this->setPolicy(['password_require_special' => true], $this->society);
        $this->admin->createToken('phone');

        $this->profile(['current_password' => 'Old-Passw0rd!', 'password' => 'nospecialcharhere', 'password_confirmation' => 'nospecialcharhere'])->assertSessionHasErrors('password');
        $this->assertSame(1, $this->admin->tokens()->count(), 'a refused change ends nothing');

        $this->profile(['current_password' => 'Old-Passw0rd!', 'password' => 'with-a-special-char', 'password_confirmation' => 'with-a-special-char'])
            ->assertRedirect(route('profile.show'))->assertSessionHas('success', 'Profile updated and password changed.');

        $this->assertTrue(Hash::check('with-a-special-char', $this->admin->fresh()->password));
        $this->assertSame(0, $this->admin->tokens()->count(), 'the old API sessions are gone');
    }

    public function test_saving_the_profile_without_a_new_password_never_asks_for_one(): void
    {
        $this->profile(['name' => 'New Name'])->assertSessionHasNoErrors()->assertRedirect(route('profile.show'));

        $this->assertSame('New Name', $this->admin->fresh()->name);
        $this->assertTrue(Hash::check('Old-Passw0rd!', $this->admin->fresh()->password));
    }

    // ── mobile API ──────────────────────────────────────────────────────────

    public function test_the_apps_change_password_follows_the_same_policy(): void
    {
        $this->setPolicy(['password_min_length' => 10, 'password_require_number' => true], $this->society);
        Sanctum::actingAs($this->admin);

        $change = fn (string $new, string $current = 'Old-Passw0rd!', ?string $confirm = null) => $this->postJson('/api/v1/change-password', [
            'current_password' => $current, 'password' => $new, 'password_confirmation' => $confirm ?? $new,
        ]);

        $change('short1')->assertStatus(422)->assertJsonValidationErrors('password');
        $change('longenoughbutnodigit')->assertStatus(422)->assertJsonValidationErrors('password');
        $change('Valid-Passw0rd-9', 'wrong-current')->assertStatus(422);
        $change('Valid-Passw0rd-9', 'Old-Passw0rd!', 'Valid-Passw0rd-8')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('Old-Passw0rd!', $this->admin->fresh()->password), 'nothing changed');

        $change('Valid-Passw0rd-9')->assertOk();
        $this->assertTrue(Hash::check('Valid-Passw0rd-9', $this->admin->fresh()->password));
    }

    public function test_six_characters_are_no_longer_accepted_anywhere(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/change-password', ['current_password' => 'Old-Passw0rd!', 'password' => 'abc123', 'password_confirmation' => 'abc123'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    // ── admin-set passwords ─────────────────────────────────────────────────

    public function test_an_admin_setting_a_users_password_must_meet_the_societys_policy(): void
    {
        $this->setPolicy(['password_min_length' => 10, 'password_require_uppercase' => true], $this->society);
        $resident = $this->makeUserWithRole($this->society, 'Tenant');

        $this->actingAs($this->admin)->post(route('users.reset-password', $resident), ['password' => 'lowercaseonlyhere', 'password_confirmation' => 'lowercaseonlyhere'])->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->post(route('users.reset-password', $resident), ['password' => 'Sunrise-Villa-159', 'password_confirmation' => 'Sunrise-Villa-159'])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Sunrise-Villa-159', $resident->fresh()->password));
    }

    public function test_the_security_settings_form_cannot_save_a_minimum_below_the_baseline(): void
    {
        $this->actingAs($this->admin)->post(route('settings.security.update'), [
            'login_attempt_limit' => 5, 'session_timeout' => 60, 'password_min_length' => 6,
        ])->assertSessionHasErrors('password_min_length');

        $this->actingAs($this->admin)->post(route('settings.security.update'), [
            'login_attempt_limit' => 5, 'session_timeout' => 60, 'password_min_length' => 10, 'password_require_number' => '1',
        ])->assertSessionHasNoErrors();
        PasswordPolicy::flush();
        $this->assertSame(['min' => 10, 'uppercase' => false, 'number' => true, 'special' => false], PasswordPolicy::forSociety($this->society->id));
    }

    public function test_a_password_never_appears_in_a_validation_error_or_the_flash(): void
    {
        $secret = 'Tr1cky-Secret-Value';
        $r = $this->profile(['current_password' => 'wrong', 'password' => $secret, 'password_confirmation' => $secret . 'x']);

        $r->assertSessionHasErrors();
        $this->assertStringNotContainsString($secret, json_encode(session('errors')->getBag('default')->all()));
        $this->assertStringNotContainsString($secret, json_encode(session()->all() ?: []));
    }
}
