<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving the email form: the runtime mailer must receive the real stored password (not the mask the form echoes
 * back), and "no encryption" must not be stored as the literal string "null". Nothing is sent: only config is read.
 */
class EmailSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_with_the_masked_password_keeps_the_real_one_and_applies_it_to_the_mailer(): void
    {
        config(['messaging.enabled' => true]);   // the path that actually configures SMTP; no mail is sent
        $society = $this->makeSociety('Alpha');
        $admin = $this->makeUserWithRole($society, 'Admin');
        SystemSetting::set('email', 'mail_password', 'real-smtp-secret', 'encrypted', $society->id);

        $this->actingAs($admin)->post(route('settings.email.update'), [
            'mail_driver' => 'smtp', 'mail_host' => 'smtp.example.test', 'mail_port' => 587, 'mail_username' => 'u',
            'mail_password' => \App\Support\SettingSecrets::MASK, 'mail_encryption' => 'null',
            'mail_from_address' => 'noreply@example.test', 'mail_from_name' => 'Alpha',
        ])->assertSessionHasNoErrors();

        $this->assertSame('real-smtp-secret', SystemSetting::get('email', 'mail_password', null, $society->id));
        $this->assertSame('real-smtp-secret', config('mail.mailers.smtp.password'));
        $this->assertNotSame('null', SystemSetting::get('email', 'mail_encryption', 'x', $society->id));
    }
}
