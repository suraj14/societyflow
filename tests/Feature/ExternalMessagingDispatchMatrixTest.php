<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Support\ExternalMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every route a message can take to a provider, in two states:
 *   - EXTERNAL_MESSAGING_ENABLED=false  -> not one request reaches the provider boundary, for anyone;
 *   - enabled + an allow-list           -> only the exact listed recipient reaches it; every other number is stopped first.
 *
 * "Reaches the provider boundary" is the test guard in tests/TestCase.php, which sits immediately before the real HTTP call and
 * throws (so nothing leaves the process). Mail has no such guard, so its boundary is an unreachable SMTP host: a send that was
 * attempted fails with a transport error, a cancelled one is silent.
 */
class ExternalMessagingDispatchMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED = '9876543210';
    private const DENIED = '9811111111';

    private int $sid;

    protected function setUp(): void
    {
        parent::setUp();
        $society = $this->makeSociety('Royal');
        $this->sid = $society->id;
        foreach (['sms_enabled' => true, 'notification_sms_enabled' => true, 'otp_sms_enabled' => true] as $k => $v) {
            SystemSetting::set('sms', $k, $v, 'boolean', $this->sid);
        }
        foreach (['fast2sms_api_key' => ['SMS-KEY-0123456789abcdef', 'encrypted'], 'fast2sms_sender_id' => ['SAIDIG', 'string'], 'fast2sms_otp_id' => ['otp123', 'string'], 'fast2sms_route' => ['dlt', 'string']] as $k => [$v, $t]) {
            SystemSetting::set('sms', $k, $v, $t, $this->sid);
        }
        SmsTemplate::create(['society_id' => $this->sid, 'name' => 'Notice', 'purpose' => 'notice', 'audience' => 'resident', 'dlt_message_id' => '228199', 'is_active' => true,
            'body' => 'SocietyFlow: New notice {{notice_title}} published by your society. Please check the SocietyFlow app for details. Thank you.']);
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $this->sid);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'WA-KEY-0123456789abcdefghij', 'encrypted', $this->sid);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $this->sid);
        SystemSetting::set('whatsapp', 'whatsapp_visitor_template_name', 'visitor_arrived', 'string', $this->sid);
        WhatsAppTemplate::create(['society_id' => $this->sid, 'external_id' => '2012345678901234', 'message_id' => '8340', 'name' => 'visitor_arrived', 'status' => 'APPROVED',
            'language' => 'en', 'var_count' => 2, 'body_text' => 'Hi {{1}}, {{2}} is at the gate']);
        $this->consumeBlockedOutbound();
    }

    /** @return array<string, callable(string):void> */
    private function paths(): array
    {
        $values = ['resident_name' => 'Asha Rao', 'visitor_name' => 'Ravi', 'visit_date' => '12 Nov', 'society_name' => 'Royal'];

        return [
            'Fast2SMS DLT notification'        => fn (string $p) => SmsService::sendDltNotification('notification', $p, '', $this->sid, false, 'notice', ['notice_title' => 'Water']),
            'Fast2SMS one-time-code API'        => fn (string $p) => SmsService::sendOtp($p, '4821', $this->sid, 'otp'),
            'Fast2SMS quick SMS (settings test)' => fn (string $p) => SmsService::sendTest($p, 'hello', $this->sid),
            'WhatsApp template (Fast2SMS)'      => fn (string $p) => WhatsAppService::sendNotification('visitor', $p, $values, $this->sid),
            'WhatsApp queued job'               => fn (string $p) => (new SendWhatsAppNotification('visitor', $p, $values, $this->sid))->handle(),
            'WhatsApp queued job, retried'      => function (string $p) use ($values) {
                $job = new SendWhatsAppNotification('visitor', $p, $values, $this->sid);
                $job->handle();
                $job->handle();
            },
            'WhatsApp session message'          => fn (string $p) => WhatsAppService::sendSessionMessage($p, 'hello', $this->sid),
            'BulkSMSPlans'                      => function (string $p) {
                SystemSetting::set('sms', 'sms_provider', 'bulksmsplans', 'string', $this->sid);
                SystemSetting::set('sms', 'bulksmsplans_api_id', 'id', 'string', $this->sid);
                SystemSetting::set('sms', 'bulksmsplans_api_password', 'pw', 'string', $this->sid);
                SmsService::sendOtp($p, '4821', $this->sid);
            },
            'custom SMS API (POST)'             => function (string $p) {
                SystemSetting::set('sms', 'sms_provider', 'custom', 'string', $this->sid);
                SystemSetting::set('sms', 'custom_sms_api_url', 'https://sms.example.invalid/send', 'string', $this->sid);
                SmsService::sendOtp($p, '4821', $this->sid);
            },
            'custom SMS API (GET)'              => function (string $p) {
                SystemSetting::set('sms', 'sms_provider', 'custom', 'string', $this->sid);
                SystemSetting::set('sms', 'custom_sms_api_url', 'https://sms.example.invalid/send?to={phone}&code={otp}', 'string', $this->sid);
                SmsService::sendOtp($p, '4821', $this->sid);
            },
        ];
    }

    private function reached(callable $fn, string $phone): int
    {
        $this->consumeBlockedOutbound();
        // the guard throws mid-send, which leaves a "this message is in flight" duplicate-suppression token behind; clear it so each
        // path is judged on its own (production code never throws there - it catches transport errors)
        \Illuminate\Support\Facades\Cache::flush();
        try {
            $fn($phone);
        } catch (\Throwable $e) {
            // the guard throws once a request would have left; nothing else is expected to
        }

        return count($this->consumeBlockedOutbound());
    }

    public function test_with_messaging_disabled_no_route_reaches_a_provider_for_anyone(): void
    {
        config(['messaging.enabled' => false, 'messaging.allowlist' => null]);

        foreach ($this->paths() as $name => $fn) {
            foreach ([self::ALLOWED, self::DENIED] as $phone) {
                $this->assertSame(0, $this->reached($fn, $phone), "$name -> $phone must not reach a provider while messaging is disabled");
            }
        }
    }

    public function test_with_messaging_disabled_even_a_listed_number_is_blocked(): void
    {
        config(['messaging.enabled' => false, 'messaging.allowlist' => self::ALLOWED]);

        foreach ($this->paths() as $name => $fn) {
            $this->assertSame(0, $this->reached($fn, self::ALLOWED), "$name: the allow-list does not override the kill switch");
        }
    }

    public function test_with_messaging_enabled_and_an_allowlist_only_the_exact_listed_number_reaches_a_provider(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => self::ALLOWED]);

        foreach ($this->paths() as $name => $fn) {
            $this->assertSame(0, $this->reached($fn, self::DENIED), "$name -> an unlisted number must be stopped before any provider call");
            $this->assertGreaterThanOrEqual(1, $this->reached($fn, self::ALLOWED), "$name -> the listed number is the one that gets through (to the mocked boundary)");
        }
    }

    public function test_lookalike_numbers_and_forms_never_reach_a_provider(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => self::ALLOWED]);
        $fn = $this->paths()['Fast2SMS DLT notification'];

        // a partial number, an extra digit, a different last digit, another country code, letters mixed in
        foreach (['876543210', '98765432100', '9876543211', '+44 9876543210', '+1 9876543210', '98765-4321'] as $lookalike) {
            $this->assertSame(0, $this->reached($fn, $lookalike), $lookalike);
        }
        // the same person written differently is the same person (SmsService strips punctuation and stray characters before the check,
        // so even "9876543210x" is delivered to - and checked as - 9876543210, the listed number itself; it can never become another one)
        foreach (['98765 43210', '+91 98765 43210', '919876543210', '09876543210', '9876543210x'] as $same) {
            $this->assertSame(1, $this->reached($fn, $same), $same);
        }
    }

    public function test_an_empty_or_malformed_list_stops_every_route(): void
    {
        foreach (['', self::ALLOWED . ',', self::ALLOWED . ',12345', ',', 'garbage'] as $raw) {
            config(['messaging.enabled' => true, 'messaging.allowlist' => $raw]);
            foreach ($this->paths() as $name => $fn) {
                $this->assertSame(0, $this->reached($fn, self::ALLOWED), "$name with list [$raw] fails closed");
            }
        }
    }

    public function test_numeric_phone_values_and_duplicate_entries_are_handled(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => '9876543210, 98765 43210, +919876543210']);   // the same person three ways

        $this->assertNull(ExternalMessaging::allowlistProblem());
        $this->assertTrue(ExternalMessaging::allows(9876543210), 'a phone number that arrives as an integer');
        $this->assertFalse(ExternalMessaging::allows(9811111111));
        $this->assertSame(['9876543210'], ExternalMessaging::parseAllowlist('9876543210, 98765 43210, +919876543210')['entries'], 'duplicates collapse to one');
        $this->assertSame(1, $this->reached($this->paths()['Fast2SMS DLT notification'], '9876543210'), 'one attempt, not three');
    }

    public function test_domain_lookalikes_and_the_wrong_mailbox_are_not_allowed(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => 'me@example.com']);

        foreach (['me@example.com.evil.test', 'me@example.co', 'me@exampIe.com', 'me@sub.example.com', 'xme@example.com', 'me+tag@example.com', 'me@example.com ', ' ME@EXAMPLE.COM'] as $email) {
            $expected = trim(strtolower($email)) === 'me@example.com';
            $this->assertSame($expected, ExternalMessaging::allows($email), $email);
        }
    }

    // ── e-mail ──────────────────────────────────────────────────────────────

    private function attemptMail(string $to): string
    {
        config(['mail.default' => 'probe', 'mail.mailers.probe' => ['transport' => 'smtp', 'host' => 'smtp.example.invalid', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'p', 'timeout' => 2]]);
        try {
            Mail::mailer('probe')->raw('hello', fn ($m) => $m->to($to)->subject('s'));

            return 'cancelled';           // no exception: the listener stopped it before the transport
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            return 'attempted';           // it reached the (unreachable) SMTP transport
        }
    }

    public function test_mail_is_cancelled_while_disabled_and_only_a_listed_address_reaches_the_transport_when_enabled(): void
    {
        config(['messaging.enabled' => false, 'messaging.allowlist' => 'me@example.com']);
        $this->assertSame('cancelled', $this->attemptMail('me@example.com'), 'disabled: even a listed address');
        $this->assertSame('cancelled', $this->attemptMail('resident@example.com'));

        config(['messaging.enabled' => true, 'messaging.allowlist' => 'me@example.com']);
        $this->assertSame('cancelled', $this->attemptMail('resident@example.com'), 'enabled: an unlisted address');
        $this->assertSame('attempted', $this->attemptMail('me@example.com'), 'enabled: the listed address is the only one that is tried');

        config(['messaging.enabled' => true, 'messaging.allowlist' => 'me@example.com,']);
        $this->assertSame('cancelled', $this->attemptMail('me@example.com'), 'a malformed list fails closed for mail too');
    }

    public function test_mail_with_one_unlisted_recipient_among_several_is_cancelled_whole(): void
    {
        config(['messaging.enabled' => true, 'messaging.allowlist' => 'me@example.com']);
        config(['mail.default' => 'probe', 'mail.mailers.probe' => ['transport' => 'smtp', 'host' => 'smtp.example.invalid', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'p', 'timeout' => 2]]);

        Mail::mailer('probe')->raw('hello', fn ($m) => $m->to('me@example.com')->cc('resident@example.com')->subject('s'));
        Mail::mailer('probe')->raw('hello', fn ($m) => $m->to('me@example.com')->bcc('resident@example.com')->subject('s'));

        $this->assertTrue(true, 'neither send reached the transport (a reach would have thrown a TransportException)');
    }
}
