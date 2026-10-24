<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Services\Fast2SmsService;
use App\Support\ExternalMessaging;
use App\Support\MailDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * EXTERNAL_MESSAGING_ALLOWLIST: with real delivery switched on for a local test, only the listed addresses / numbers can receive
 * anything; every other recipient is simulated, so a copy of the real database cannot message real residents. No real send happens
 * here: the outbound guard installed by tests/TestCase.php throws if a request would actually leave the process.
 */
class ExternalMessagingAllowlistTest extends TestCase
{
    use RefreshDatabase;

    private function allow(?array $list, bool $enabled = true): void
    {
        config(['messaging.enabled' => $enabled, 'messaging.allowlist' => $list === null ? null : implode(',', $list)]);
    }

    public function test_the_environment_value_is_parsed_into_normalised_addresses_and_ten_digit_numbers(): void
    {
        foreach (['_ENV', '_SERVER'] as $bag) {
            $GLOBALS[$bag]['EXTERNAL_MESSAGING_ALLOWLIST'] = ' Me@Example.COM, +91 98765-43210 ,,other@x.in';
        }
        try {
            $config = require config_path('messaging.php');
        } finally {
            foreach (['_ENV', '_SERVER'] as $bag) {
                unset($GLOBALS[$bag]['EXTERNAL_MESSAGING_ALLOWLIST']);
            }
        }
        $this->assertSame(' Me@Example.COM, +91 98765-43210 ,,other@x.in', $config['allowlist'], 'kept raw: ExternalMessaging parses it and fails closed on the empty item');

        // really absent: a developer's own .env may define it, and the loaded value can live in $_ENV, $_SERVER and getenv()
        $saved = [$_ENV['EXTERNAL_MESSAGING_ALLOWLIST'] ?? null, $_SERVER['EXTERNAL_MESSAGING_ALLOWLIST'] ?? null, getenv('EXTERNAL_MESSAGING_ALLOWLIST')];
        unset($_ENV['EXTERNAL_MESSAGING_ALLOWLIST'], $_SERVER['EXTERNAL_MESSAGING_ALLOWLIST']);
        putenv('EXTERNAL_MESSAGING_ALLOWLIST');
        try {
            $absent = require config_path('messaging.php');
        } finally {
            foreach (['_ENV' => $saved[0], '_SERVER' => $saved[1]] as $bag => $v) {
                if ($v !== null) {
                    $GLOBALS[$bag]['EXTERNAL_MESSAGING_ALLOWLIST'] = $v;
                }
            }
            if ($saved[2] !== false) {
                putenv('EXTERNAL_MESSAGING_ALLOWLIST=' . $saved[2]);
            }
        }
        $this->assertNull($absent['allowlist'], 'absent = no restriction (what an approved production uses)');
    }

    public function test_no_allowlist_means_no_restriction_and_an_empty_one_means_nobody(): void
    {
        $this->allow(null);
        $this->assertFalse(ExternalMessaging::restricted());
        $this->assertTrue(ExternalMessaging::allows('anyone@example.com'));

        $this->allow([]);
        $this->assertTrue(ExternalMessaging::restricted());
        $this->assertFalse(ExternalMessaging::allows('anyone@example.com'));
        $this->assertFalse(ExternalMessaging::allows(null));
    }

    public function test_only_listed_people_are_allowed_however_the_number_is_written(): void
    {
        $this->allow(['me@example.com', '9876543210']);

        $this->assertTrue(ExternalMessaging::allows('ME@Example.com'));
        $this->assertTrue(ExternalMessaging::allows('+91 98765 43210'));
        $this->assertTrue(ExternalMessaging::allows('09876543210'));
        $this->assertFalse(ExternalMessaging::allows('resident@example.com'));
        $this->assertFalse(ExternalMessaging::allows('9811111111'));
    }

    public function test_a_provider_request_to_an_unlisted_number_never_leaves_and_a_listed_one_reaches_the_outbound_point(): void
    {
        $this->allow(['9876543210']);

        // unlisted: stopped before any network use, reported as skipped
        [$body, $code] = Fast2SmsService::send('GET', 'https://www.fast2sms.com/dev/bulkV2?route=dlt&numbers=9811111111&message=1', 'k');
        $this->assertSame(0, $code);
        $this->assertTrue($body['skipped']);
        $this->assertStringContainsString('allow-list', $body['transport_error']);

        // an account look-up names no recipient, so it is not restricted
        $this->assertSame([], ExternalMessaging::recipientsOf('https://www.fast2sms.com/dev/wallet'));
        $this->assertTrue(ExternalMessaging::requestAllowed('https://www.fast2sms.com/dev/wallet'));

        // listed: it gets as far as the real outbound point, which the test guard turns into an exception
        $reached = false;
        ExternalMessaging::$outboundGuard = function () use (&$reached) { $reached = true; throw new \RuntimeException('guard'); };
        try {
            Fast2SmsService::send('GET', 'https://www.fast2sms.com/dev/bulkV2?route=dlt&numbers=9876543210&message=1', 'k');
        } catch (\RuntimeException $e) {
        }
        $this->assertTrue($reached);
    }

    public function test_whatsapp_recipients_are_read_from_the_request_body_and_the_session_url(): void
    {
        $this->allow(['9876543210']);

        $this->assertFalse(ExternalMessaging::requestAllowed('https://x.test/messages', ['to' => '919811111111']));
        $this->assertTrue(ExternalMessaging::requestAllowed('https://x.test/messages', ['to' => '919876543210']));
        $this->assertFalse(ExternalMessaging::requestAllowed('https://x.test/whatsapp-session?to=919811111111&text=hi'));
        $this->assertFalse(ExternalMessaging::requestAllowed('https://x.test/m', ['numbers' => '9876543210,9811111111']), 'one unlisted number in a batch blocks the batch');
    }

    public function test_mail_to_an_unlisted_address_is_cancelled_before_a_real_transport_and_logged_as_simulated(): void
    {
        $this->allow(['me@example.com']);
        config(['mail.default' => 'probe', 'mail.mailers.probe' => ['transport' => 'smtp', 'host' => 'smtp.invalid', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'p']]);

        // cancelled by the MessageSending listener, so the unreachable host is never contacted and nothing throws
        Mail::mailer('probe')->raw('hello', fn ($m) => $m->to('resident@example.com')->subject('s'));
        $this->assertTrue(true);

        $this->assertSame(MailDelivery::BLOCKED, MailDelivery::statusAfterSend('resident@example.com'));
        $this->assertSame(MailDelivery::SENT, MailDelivery::statusAfterSend('me@example.com'), 'listed, real transport, delivery on: accepted by the server');
    }

    public function test_the_test_email_buttons_refuse_an_unlisted_address_instead_of_claiming_acceptance(): void
    {
        $this->allow(['me@example.com']);
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_settings', 'guard_name' => 'web']);
        $admin->givePermissionTo('manage_settings');
        $t = \App\Models\EmailTemplate::create(['society_id' => $society->id, 'name' => 'N', 'slug' => 'n-al', 'subject' => 'S', 'body' => 'B', 'type' => 'custom', 'status' => 'active', 'trigger_event' => 'x']);

        foreach ([
            route('settings.email.test') => ['test_email' => 'resident@example.com'],
            route('admin.email-templates.test', $t) => ['test_email' => 'resident@example.com'],
        ] as $url => $payload) {
            $r = $this->actingAs($admin)->postJson($url, $payload)->assertOk();
            $this->assertFalse($r->json('success'), $url);
            $this->assertStringContainsString('allow-list', $r->json('message'));
        }
        $this->assertSame(0, EmailLog::where('trigger_action', 'test_email')->count());
    }

    public function test_the_allowlist_changes_nothing_while_messaging_is_off(): void
    {
        $this->allow(['me@example.com'], false);

        $this->assertFalse(ExternalMessaging::enabled());
        $this->assertSame(MailDelivery::BLOCKED, MailDelivery::statusAfterSend('me@example.com'), 'off is off, listed or not');
    }
}
