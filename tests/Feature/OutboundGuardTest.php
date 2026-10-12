<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\Fast2SmsService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No automated test may send a real SMS or WhatsApp message. tests/TestCase.php installs a guard that is called right
 * before any real outbound provider request; it blocks the request and fails the test in tearDown() even if the
 * application swallowed the error. This proves the guard actually trips on every real transport (and that the kill
 * switch still comes first, so a switched-off server never reaches it).
 */
class OutboundGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_real_provider_transport_is_blocked_in_tests(): void
    {
        config(['messaging.enabled' => true]);

        try {
            Fast2SmsService::get('https://www.fast2sms.com/dev/bulkV2?route=q', 'not-a-real-key');
            $this->fail('the Fast2SMS transport reached the network');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Blocked', $e->getMessage());
        }

        // Every other path is blocked the same way, and each attempt is recorded for the tearDown() check.
        $society = $this->makeSociety();
        SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $society->id);
        SystemSetting::set('sms', 'fast2sms_api_key', 'not-a-real-key', 'encrypted', $society->id);
        $this->assertBlocked(fn () => SmsService::sendOtp('9876543210', '1234', $society->id));

        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'not-a-real-key', 'encrypted', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $society->id);
        $this->assertBlocked(fn () => WhatsAppService::sendSessionMessage('9876543210', 'hello', $society->id));

        $blocked = $this->consumeBlockedOutbound();
        $this->assertCount(3, $blocked);
        $this->assertStringContainsString('Fast2SMS', $blocked[0]);
        $this->assertStringContainsString('Fast2SMS', $blocked[1]);
        $this->assertStringContainsString('WhatsApp', $blocked[2]);
    }

    private function assertBlocked(callable $send): void
    {
        try {
            $send();
            $this->fail('a real provider request was not blocked');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Blocked', $e->getMessage());
        }
    }

    public function test_with_messaging_switched_off_nothing_even_reaches_the_guard(): void
    {
        config(['messaging.enabled' => false]);

        [$body, $code] = Fast2SmsService::get('https://www.fast2sms.com/dev/bulkV2?route=q', 'not-a-real-key');

        $this->assertSame(0, $code);
        $this->assertTrue($body['skipped']);
        $this->assertSame([], $this->consumeBlockedOutbound(), 'the kill switch answers before any request is even attempted');
    }
}
