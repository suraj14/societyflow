<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public pages never publish a login. The sign-in page and the landing page used to list demo accounts (and the landing page
 * real-looking addresses) together with their passwords, and the sign-in page offered to fill them in.
 */
class PublicPagesNoCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const LEAKS = [
        'Demo Credentials', 'demo credentials', 'fillCredentials', 'copyCredentials', 'Click to auto-fill',
        'superadmin@societyflow.com', 'admin@societyflow.com', 'villaowner@societyflow.com', 'tenant@societyflow.com', 'staff@societyflow.com', 'accountant@societyflow.com',
        'sahgunvilla@gmail.com', 'mohallal@gmail.com', 'urbanvilla@gmail.com', 'ubapartment@gmail.com', 'tenant@example.com',
        'password123', 'Login with Demo',
    ];

    public function test_the_sign_in_page_shows_no_account_and_prefills_nothing(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        foreach (self::LEAKS as $leak) {
            $this->assertStringNotContainsString($leak, $html, "the sign-in page must not contain: $leak");
        }
        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*value=""/', $html, 'the e-mail box starts empty');
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="password"[^>]*value=/', $html, 'the password box is never pre-filled');
        $this->assertStringContainsString('name="_token"', $html, 'the form is still CSRF-protected');
        $this->assertStringContainsString('autocomplete="current-password"', $html);
        $this->assertStringContainsString(route('password.request'), $html);
        $this->assertStringContainsString(route('register'), $html);
    }

    public function test_the_landing_page_shows_no_account_and_still_points_to_sign_in_and_register(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (self::LEAKS as $leak) {
            $this->assertStringNotContainsString($leak, $html, "the landing page must not contain: $leak");
        }
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringContainsString(route('register'), $html);
        $this->assertStringContainsString('Role-Based Access Control', $html, 'the role overview that is not a credential stays');
    }

    public function test_a_failed_sign_in_still_explains_itself_without_revealing_which_part_was_wrong(): void
    {
        $r = $this->from('/login')->post(route('login'), ['email' => 'nobody@example.com', 'password' => 'not-the-password']);

        $r->assertRedirect('/login')->assertSessionHasErrors();
        $page = $this->get('/login')->assertOk();
        $page->assertSee('nobody@example.com', false);                       // the e-mail is remembered so the person only retypes the password
        $this->assertStringNotContainsString('not-the-password', $page->getContent());
    }
}
