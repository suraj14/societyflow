<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\SettingSecrets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stored provider credentials must never reach the browser. SettingSecrets masks them on the society
 * Settings page; the SUPER ADMIN settings page was never converted and rendered the decrypted global SMTP
 * password, the gateway secrets and the AWS secret into value="..." (found by the web audit). Saving the
 * form must also leave a stored secret alone when the browser posts the mask back.
 */
class SuperAdminSettingsSecretsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = [
        ['email', 'mail_password', 'smtp-pass-ZZ-1234567890'],
        ['payment', 'razorpay_secret', 'rzp-secret-ZZ-1234567890'],
        ['payment', 'stripe_secret', 'stripe-secret-ZZ-1234567890'],
        ['storage', 'aws_key', 'AKIAZZZZ1234567890'],
        ['storage', 'aws_secret', 'aws-secret-ZZ-1234567890'],
    ];

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = $this->makeUserWithRole($this->makeSociety('Eden'), 'Super Admin', ['society_id' => null]);
        foreach (self::SECRETS as [$group, $key, $value]) {
            SystemSetting::set($group, $key, $value, 'encrypted', null);
        }
    }

    public function test_no_tab_of_the_super_admin_settings_page_contains_a_stored_secret(): void
    {
        foreach (['app', 'language', 'storage', 'theme', 'currency', 'email', 'payment', 'security'] as $tab) {
            $html = $this->actingAs($this->super)->get(route('settings.index', ['tab' => $tab]))->assertOk()->getContent();

            foreach (self::SECRETS as [, $key, $value]) {
                $this->assertStringNotContainsString($value, $html, "{$key} leaked into the '{$tab}' tab");
            }
        }
    }

    public function test_a_set_secret_shows_the_mask_so_the_form_still_says_something_is_saved(): void
    {
        $html = $this->actingAs($this->super)->get(route('settings.index', ['tab' => 'storage']))->getContent();

        $this->assertStringContainsString(SettingSecrets::MASK, $html);
    }

    public function test_saving_the_email_form_with_the_mask_keeps_the_stored_password(): void
    {
        $this->actingAs($this->super)->post(route('settings.email.update'), [
            'mail_driver' => 'smtp', 'mail_host' => 'smtp.example.test', 'mail_port' => 587, 'mail_username' => 'u',
            'mail_password' => SettingSecrets::MASK, 'mail_encryption' => 'tls', 'mail_from_address' => 'a@example.test', 'mail_from_name' => 'X',
        ])->assertSessionHas('success');

        $this->assertSame('smtp-pass-ZZ-1234567890', SystemSetting::get('email', 'mail_password', null, null), 'asterisks were saved over the real password');
    }

    public function test_saving_the_storage_form_with_the_mask_keeps_the_stored_keys_and_a_new_value_replaces_them(): void
    {
        $post = fn (array $over) => $this->actingAs($this->super)->post(route('settings.storage.update'), array_merge([
            'storage_driver' => 's3', 'aws_region' => 'ap-south-1', 'aws_bucket' => 'b',
        ], $over));

        $post(['aws_key' => SettingSecrets::MASK, 'aws_secret' => SettingSecrets::MASK])->assertSessionHas('success');
        $this->assertSame('AKIAZZZZ1234567890', SystemSetting::get('storage', 'aws_key', null, null));
        $this->assertSame('aws-secret-ZZ-1234567890', SystemSetting::get('storage', 'aws_secret', null, null));

        $post(['aws_key' => 'AKIANEWKEY', 'aws_secret' => 'new-secret-value'])->assertSessionHas('success');
        $this->assertSame('new-secret-value', SystemSetting::get('storage', 'aws_secret', null, null));
    }

    public function test_saving_the_payment_form_with_the_mask_keeps_the_gateway_secrets(): void
    {
        $this->actingAs($this->super)->post(route('settings.payment.update'), [
            'razorpay_secret' => SettingSecrets::MASK, 'stripe_secret' => SettingSecrets::MASK,
        ])->assertStatus(302);

        $this->assertSame('rzp-secret-ZZ-1234567890', SystemSetting::get('payment', 'razorpay_secret', null, null));
        $this->assertSame('stripe-secret-ZZ-1234567890', SystemSetting::get('payment', 'stripe_secret', null, null));
    }

    public function test_every_credential_we_store_is_registered_for_masking(): void
    {
        $this->assertEqualsCanonicalizing(['aws_key', 'aws_secret'], SettingSecrets::keysFor('storage'));

        $masked = SettingSecrets::maskAll(['storage' => ['aws_key' => 'AKIAREAL', 'aws_secret' => 'real-secret', 'aws_region' => 'ap-south-1']]);
        $this->assertSame(SettingSecrets::MASK, $masked['storage']['aws_key']);
        $this->assertSame(SettingSecrets::MASK, $masked['storage']['aws_secret']);
        $this->assertSame('ap-south-1', $masked['storage']['aws_region'], 'non-secret fields stay readable');
    }

    public function test_the_s3_connection_test_makes_no_network_call_while_external_services_are_disabled(): void
    {
        SystemSetting::set('storage', 'storage_driver', 's3', 'string', null);
        SystemSetting::set('storage', 'aws_region', 'ap-south-1', 'string', null);
        SystemSetting::set('storage', 'aws_bucket', 'b', 'string', null);

        $response = $this->actingAs($this->super)->postJson(route('settings.storage.test'))->assertOk();

        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('Not tested', (string) $response->json('message'));
    }

    public function test_the_storage_test_never_echoes_a_credential_in_its_error(): void
    {
        SystemSetting::set('storage', 'storage_driver', 's3', 'string', null);
        SystemSetting::set('storage', 'aws_region', 'ap-south-1', 'string', null);
        SystemSetting::set('storage', 'aws_bucket', 'nonexistent-bucket', 'string', null);

        $response = $this->actingAs($this->super)->postJson(route('settings.storage.test'));

        $this->assertStringNotContainsString('aws-secret-ZZ-1234567890', $response->getContent());
        $this->assertStringNotContainsString('AKIAZZZZ1234567890', $response->getContent());
    }
}
