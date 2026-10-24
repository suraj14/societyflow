<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\Fast2SmsService;
use App\Services\SmsService;
use App\Support\ExternalMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The test allow-list fails closed, matches exactly, and covers every SMS provider. Nothing here can reach a real provider:
 * the outbound guard installed by tests/TestCase.php throws if a request would leave the process.
 */
class ExternalMessagingHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function allow(?string $raw, bool $enabled = true): void
    {
        config(['messaging.enabled' => $enabled, 'messaging.allowlist' => $raw]);
    }

    public function test_any_malformed_entry_invalidates_the_whole_list_so_nobody_is_allowed(): void
    {
        foreach (['me@example.com,', 'me@example.com,,9876543210', 'me@example.com,not-an-email@', 'me@example.com,12345', 'me@example.com,+44 7911 123456', 'me@example.com,abc9876543210', 'me@example.com,9876543210;9811111111'] as $raw) {
            $this->allow($raw);

            $this->assertNotNull(ExternalMessaging::allowlistProblem(), $raw);
            $this->assertFalse(ExternalMessaging::allows('me@example.com'), "even the valid entry is refused while the list is invalid: $raw");
            $this->assertFalse(ExternalMessaging::allows('9876543210'), $raw);
        }
        $this->assertStringNotContainsString('me@example.com', (string) ExternalMessaging::allowlistProblem(), 'the problem text never echoes the list');
    }

    public function test_matching_is_exact_after_normalisation_never_a_substring_wildcard_domain_or_partial_number(): void
    {
        $this->allow('Me@Example.com, 98765 43210');

        $this->assertNull(ExternalMessaging::allowlistProblem());
        foreach (['me@example.com', 'ME@EXAMPLE.COM', '9876543210', '+91 98765 43210', '919876543210', '09876543210'] as $ok) {
            $this->assertTrue(ExternalMessaging::allows($ok), $ok);
        }
        foreach ([
            'xme@example.com', 'me@example.com.evil.test', 'me@sub.example.com', 'other@example.com', '*@example.com', '@example.com', 'example.com', 'me@example',
            '987654321', '98765432100', '8876543210', '+44 9876543210', '+1 9876543210', '919876543211', '9876543210abc', '', null,
        ] as $no) {
            $this->assertFalse(ExternalMessaging::allows($no), (string) $no);
        }
    }

    public function test_an_empty_allowlist_allows_nobody_and_an_absent_one_means_unrestricted(): void
    {
        $this->allow('');
        $this->assertTrue(ExternalMessaging::restricted());
        $this->assertFalse(ExternalMessaging::allows('me@example.com'));
        $this->assertNull(ExternalMessaging::allowlistProblem());

        $this->allow(null);
        $this->assertFalse(ExternalMessaging::restricted());
        $this->assertTrue(ExternalMessaging::allows('anyone@example.com'));
    }

    public function test_an_unreadable_recipient_in_a_request_is_never_allowed(): void
    {
        $this->allow('9876543210');

        $this->assertFalse(ExternalMessaging::requestAllowed('https://x.test/send?numbers=garbage'));
        $this->assertFalse(ExternalMessaging::requestAllowed('https://x.test/send', ['recipients' => ['9876543210', '9811111111']]));
        $this->assertFalse(ExternalMessaging::requestAllowed('https://x.test/send', ['msisdn' => '9811111111']));
        $this->assertFalse(ExternalMessaging::requestAllowed('https://x.test/send', ['number' => '9811111111']));
        $this->assertTrue(ExternalMessaging::requestAllowed('https://x.test/send', ['number' => '9876543210']));
    }

    public function test_an_invalid_list_blocks_a_provider_request_with_its_own_reason(): void
    {
        $this->allow('9876543210,,');
        ExternalMessaging::$outboundGuard = function () { throw new \RuntimeException('a request left the process'); };

        [$body, $code] = Fast2SmsService::send('GET', 'https://www.fast2sms.com/dev/bulkV2?numbers=9876543210', 'k');

        $this->assertSame(0, $code);
        $this->assertSame(ExternalMessaging::BLOCKED_NOT_ALLOWLISTED, $body['blocked_reason']);
        $this->assertStringContainsString('invalid entry', $body['transport_error']);
    }

    public function test_the_two_gates_report_different_reasons(): void
    {
        $this->allow('9876543210', false);
        $this->assertSame(ExternalMessaging::BLOCKED_DISABLED, ExternalMessaging::blockedReason('9876543210')['code']);

        $this->allow('9876543210', true);
        $this->assertSame(ExternalMessaging::BLOCKED_NOT_ALLOWLISTED, ExternalMessaging::blockedReason('9811111111')['code']);
        $this->assertNull(ExternalMessaging::blockedReason('9876543210'));

        $r = ExternalMessaging::blockedResponse(ExternalMessaging::BLOCKED_DISABLED);
        $this->assertTrue($r['blocked']);
        $this->assertFalse($r['success']);
        $this->assertStringStartsWith('Not sent:', $r['message']);
    }

    // ── SMS providers that name the recipient differently ───────────────────

    private function smsSociety(string $provider, array $extra = []): int
    {
        $society = $this->makeSociety();
        foreach (['sms_enabled' => true, 'sms_provider' => $provider] + $extra as $k => $v) {
            SystemSetting::set('sms', $k, $v, is_bool($v) ? 'boolean' : 'string', $society->id);
        }

        return $society->id;
    }

    public function test_bulksmsplans_names_the_recipient_number_and_is_still_held_to_the_allowlist(): void
    {
        $sid = $this->smsSociety('bulksmsplans', ['bulksmsplans_api_id' => 'id', 'bulksmsplans_api_password' => 'pw']);
        $this->allow('9876543210');
        $calls = [];
        SmsService::$transportOverride = function (string $url, array $data) use (&$calls) { $calls[] = $data['number'] ?? null; return [['code' => 'SMS'], 200]; };

        try {
            $blocked = SmsService::sendOtp('9811111111', '4821', $sid);
            $allowed = SmsService::sendOtp('9876543210', '4821', $sid);
        } finally {
            SmsService::$transportOverride = null;
        }

        $this->assertSame(SmsService::STATUS_SKIPPED_NOT_ALLOWLISTED, $blocked['status']);
        $this->assertFalse($blocked['sent']);
        $this->assertSame(['9876543210'], $calls, 'only the listed number reached the provider call');
        $this->assertTrue($allowed['success']);
    }

    public function test_the_custom_http_api_is_held_to_the_allowlist_whatever_its_url_looks_like(): void
    {
        $sid = $this->smsSociety('custom', ['custom_sms_api_url' => 'https://sms.example.invalid/hook?dest={phone}&code={otp}']);
        $this->allow('9876543210');
        ExternalMessaging::$outboundGuard = function () { throw new \RuntimeException('a request left the process'); };

        $r = SmsService::sendOtp('9811111111', '4821', $sid);

        $this->assertSame(SmsService::STATUS_SKIPPED_NOT_ALLOWLISTED, $r['status']);
        $this->assertFalse($r['sent']);
        $this->assertStringContainsString('allow-list', $r['message']);
    }

    public function test_with_the_kill_switch_off_the_status_is_disabled_not_allowlisted(): void
    {
        $sid = $this->smsSociety('bulksmsplans', ['bulksmsplans_api_id' => 'id', 'bulksmsplans_api_password' => 'pw']);
        $this->allow('9876543210', false);
        ExternalMessaging::$outboundGuard = function () { throw new \RuntimeException('a request left the process'); };

        $r = SmsService::sendOtp('9876543210', '4821', $sid);

        $this->assertFalse($r['sent']);
        $this->assertNotSame(SmsService::STATUS_SKIPPED_NOT_ALLOWLISTED, $r['status'] ?? null);
    }
}
