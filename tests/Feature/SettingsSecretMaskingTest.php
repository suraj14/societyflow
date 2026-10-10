<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Support\SettingSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A saved provider credential must never reach the browser.
 *
 * These settings are encrypted at rest, but the settings page decrypted
 * them and rendered them into `value="..."`. `type="password"` hides a
 * value on screen and nowhere else: it was in the page source, in DevTools,
 * in a saved copy of the page. One field was `type="text"` and simply
 * displayed. The page now shows a mask, and posting that mask back means
 * "unchanged" - the test below that presses Save without touching anything
 * is the one that matters, because that is what an admin actually does.
 */
class SettingsSecretMaskingTest extends TestCase
{
    use RefreshDatabase;

    private const MAIL_SECRET    = 'smtp-p4ssw0rd-not-for-the-browser';
    private const RAZORPAY_SECRET = 'rzp-live-secret-not-for-the-browser';

    private function admin()
    {
        $society = $this->makeSociety();

        return $this->makeUserWithRole($society, 'Admin');
    }

    private function seedSecrets(?int $societyId): void
    {
        SystemSetting::set('email', 'mail_password', self::MAIL_SECRET, 'encrypted', $societyId);
        SystemSetting::set('payment', 'razorpay_secret', self::RAZORPAY_SECRET, 'encrypted', $societyId);
        SystemSetting::set('payment', 'instamojo_api_key', 'instamojo-key-not-for-the-browser', 'encrypted', $societyId);
    }

    public function test_the_settings_page_never_renders_a_saved_secret(): void
    {
        $admin = $this->admin();
        $this->seedSecrets($admin->society_id);

        $html = $this->actingAs($admin)->get('/settings')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::MAIL_SECRET, $html);
        $this->assertStringNotContainsString(self::RAZORPAY_SECRET, $html);
        $this->assertStringNotContainsString('instamojo-key-not-for-the-browser', $html);
        $this->assertStringContainsString(SettingSecrets::MASK, $html);
    }

    /**
     * The regression that would hurt most: an admin opens Settings to
     * change the "from" name, presses Save, and every gateway credential
     * becomes asterisks.
     */
    public function test_saving_the_form_unchanged_preserves_the_stored_email_password(): void
    {
        $admin = $this->admin();
        $this->seedSecrets($admin->society_id);

        $this->actingAs($admin)->post('/settings/email', [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.example.com',
            'mail_port'         => 587,
            'mail_username'     => 'postmaster@example.com',
            'mail_password'     => SettingSecrets::MASK, // what the page rendered
            'mail_encryption'   => 'tls',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name'    => 'SocietyFlow',
        ]);

        $this->assertSame(
            self::MAIL_SECRET,
            SystemSetting::get('email', 'mail_password', null, $admin->society_id),
            'posting the mask back must leave the stored password alone'
        );
    }

    public function test_saving_the_form_unchanged_preserves_stored_payment_secrets(): void
    {
        $admin = $this->admin();
        $this->seedSecrets($admin->society_id);

        $this->actingAs($admin)->post('/settings/payment', [
            'razorpay_secret'   => SettingSecrets::MASK,
            'instamojo_api_key' => SettingSecrets::MASK,
        ]);

        $this->assertSame(
            self::RAZORPAY_SECRET,
            SystemSetting::get('payment', 'razorpay_secret', null, $admin->society_id)
        );
        $this->assertSame(
            'instamojo-key-not-for-the-browser',
            SystemSetting::get('payment', 'instamojo_api_key', null, $admin->society_id)
        );
    }

    /** A real new value must still replace the old one. */
    public function test_a_genuinely_new_secret_replaces_the_stored_one(): void
    {
        $admin = $this->admin();
        $this->seedSecrets($admin->society_id);

        $this->actingAs($admin)->post('/settings/email', [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.example.com',
            'mail_port'         => 587,
            'mail_username'     => 'postmaster@example.com',
            'mail_password'     => 'a-brand-new-password',
            'mail_encryption'   => 'tls',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name'    => 'SocietyFlow',
        ]);

        $this->assertSame(
            'a-brand-new-password',
            SystemSetting::get('email', 'mail_password', null, $admin->society_id)
        );
    }

    /** An unset secret shows an empty box, not a mask implying one exists. */
    public function test_an_unset_secret_is_not_masked(): void
    {
        $values = SettingSecrets::mask('email', ['mail_password' => '']);

        $this->assertSame('', $values['mail_password']);
    }

    public function test_the_secret_is_stored_encrypted_not_in_clear_text(): void
    {
        $admin = $this->admin();
        $this->seedSecrets($admin->society_id);

        $raw = SystemSetting::where('group', 'email')
            ->where('key', 'mail_password')
            ->value('value');

        $this->assertNotSame(self::MAIL_SECRET, $raw, 'the credential must not sit in the column in clear text');
    }
}
