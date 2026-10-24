<?php

namespace Tests\Feature;

use App\Models\Society;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\EmailConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Someone asking for a reset link is signed out, so nothing applied their society's mail settings to the request: the
 * link went out through the bare .env mailer - on a server set up through Settings, a placeholder that refuses the
 * message - and never arrived. The reset mail now goes through the account's society settings, and the kill switch and
 * test allow-list still decide at send time.
 *
 * No SMTP server is contacted: the smtp transport is replaced by an in-memory one that records what it was given.
 */
class PasswordResetSocietyMailerTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $user;
    private ArrayTransport $transport;
    /** @var list<array> the mailer configuration each SMTP transport was built from */
    private array $built = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->society = $this->makeSociety('Alpha');
        $this->user = $this->makeUserWithRole($this->society, 'Tenant', ['email' => 'asha@example.test']);

        foreach (['mail_driver' => 'smtp', 'mail_host' => 'smtp.alpha-society.invalid', 'mail_port' => 465, 'mail_username' => 'alpha-user',
                  'mail_encryption' => 'tls', 'mail_from_address' => 'gate@alpha-society.invalid', 'mail_from_name' => 'Alpha Society'] as $key => $value) {
            SystemSetting::set('email', $key, $value, 'string', $this->society->id);
        }
        SystemSetting::set('email', 'mail_password', 'alpha-secret', 'encrypted', $this->society->id);

        // the .env mailer of a server configured through Settings: SMTP with a placeholder sender
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'placeholder.invalid', 'mail.from.address' => 'hello@example.com']);

        $this->transport = new ArrayTransport();
        Mail::extend('smtp', function (array $config) {
            $this->built[] = $config;

            return $this->transport;
        });
    }

    private function askForLink(string $email = 'asha@example.test')
    {
        return $this->post(route('password.email'), ['email' => $email])->assertRedirect()->assertSessionHas('success');
    }

    public function test_a_signed_out_reset_request_is_sent_through_the_societys_mail_settings(): void
    {
        config(['messaging.enabled' => true]);

        $this->askForLink();

        $this->assertCount(1, $this->transport->messages(), 'exactly one reset mail');
        $this->assertSame(['smtp.alpha-society.invalid'], array_column($this->built, 'host'), 'built from the society settings, not the .env placeholder');
        $this->assertSame(465, $this->built[0]['port']);
        $this->assertSame('alpha-user', $this->built[0]['username']);
        $this->assertSame('alpha-secret', $this->built[0]['password'], 'the stored password, decrypted');

        $email = $this->transport->messages()->first()->getOriginalMessage();
        $this->assertSame('gate@alpha-society.invalid', $email->getFrom()[0]->getAddress(), 'from the society sender, not hello@example.com');
        $this->assertSame('asha@example.test', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('/reset-password/', $email->getHtmlBody());
    }

    public function test_an_account_without_a_society_uses_the_platform_settings(): void
    {
        config(['messaging.enabled' => true]);
        foreach (['mail_driver' => 'smtp', 'mail_host' => 'smtp.platform.invalid', 'mail_port' => 587, 'mail_from_address' => 'noreply@platform.invalid'] as $key => $value) {
            SystemSetting::set('email', $key, $value, 'string', null);
        }
        $root = User::create(['society_id' => null, 'name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('x'), 'status' => 'active']);

        $this->askForLink('root@example.test');

        $this->assertSame(['smtp.platform.invalid'], array_column($this->built, 'host'));
        $this->assertSame('noreply@platform.invalid', $this->transport->messages()->first()->getOriginalMessage()->getFrom()[0]->getAddress());
    }

    public function test_with_external_messaging_off_the_society_smtp_is_never_used(): void
    {
        config(['messaging.enabled' => false, 'mail.default' => 'log']);

        $this->askForLink();

        $this->assertNull(EmailConfigurationService::mailerFor($this->society->id));
        $this->assertSame([], $this->built, 'no SMTP transport was even built');
        $this->assertCount(0, $this->transport->messages());
    }

    public function test_the_test_allow_list_still_applies_to_the_society_mailer(): void
    {
        // the default mailer is local: only a check of the mailer actually used can stop this message
        config(['messaging.enabled' => true, 'messaging.allowlist' => 'someone-else@example.test', 'mail.default' => 'log']);

        $this->askForLink();

        $this->assertCount(0, $this->transport->messages(), 'cancelled: the recipient is not on the allow-list');

        config(['messaging.allowlist' => 'asha@example.test']);
        $this->travel(2)->minutes();   // past the broker's one-minute throttle
        $this->askForLink();

        $this->assertCount(1, $this->transport->messages(), 'an allow-listed recipient gets it');
    }

    public function test_the_kill_switch_checks_the_mailer_a_message_really_goes_through(): void
    {
        // switched off, default mailer local - but a path that names an SMTP mailer of its own must still be stopped
        config(['messaging.enabled' => false, 'mail.default' => 'log']);

        Mail::mailer('smtp')->raw('x', fn ($m) => $m->to('asha@example.test')->subject('s'));

        $this->assertCount(0, $this->transport->messages());
    }

    public function test_changed_settings_get_a_freshly_built_mailer(): void
    {
        config(['messaging.enabled' => true]);

        $first = EmailConfigurationService::mailerFor($this->society->id);
        SystemSetting::set('email', 'mail_host', 'smtp2.alpha-society.invalid', 'string', $this->society->id);
        $second = EmailConfigurationService::mailerFor($this->society->id);

        $this->assertNotSame($first, $second);
        $this->assertSame('smtp2.alpha-society.invalid', config("mail.mailers.{$second}.host"));
        $this->assertSame($second, EmailConfigurationService::mailerFor($this->society->id), 'same settings, same mailer');
    }
}
