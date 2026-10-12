<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\Fast2SmsAccount;
use App\Services\SmsService;
use App\Support\ChannelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Could not reach Fast2SMS. Check the internet connection." - root cause. Fast2SmsAccount::failure() mapped EVERY
 * code-0 result to that sentence, but code 0 is also what request() returned when the EXTERNAL_MESSAGING_ENABLED kill
 * switch stopped the call before any network use. With messaging off, Connect & Verify never touched the network and
 * still told people to check their internet.
 *
 * Now: kill switch -> "Not checked - real messaging is switched off" (skipped, never a failure, never overwrites the
 * last real connection result); a REAL transport failure says which kind (DNS, timeout, certificate); an invalid key
 * and a rejected request stay distinct. No request leaves the process in these tests.
 */
class Fast2SmsConnectionStatusTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        SystemSetting::set('sms', 'fast2sms_api_key', 'unit-test-key-not-real', 'encrypted', $this->society->id);
    }

    protected function tearDown(): void
    {
        Fast2SmsAccount::$transportOverride = null;
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    public function test_with_messaging_off_connect_says_not_checked_and_never_blames_the_internet(): void
    {
        $this->assertFalse(\App\Support\ExternalMessaging::enabled());
        Fast2SmsAccount::$transportOverride = null;      // the real transport: the kill switch must stop it first

        $r = $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();

        $r->assertJson(['success' => false, 'skipped' => true, 'status' => 'not_sent']);
        $this->assertStringContainsString('Not checked', $r->json('message'));
        $this->assertStringContainsString('switched off', $r->json('message'));
        $this->assertStringNotContainsString('internet', $r->json('message'));
        $this->assertStringNotContainsString('Could not reach', $r->json('message'));
    }

    public function test_a_skipped_check_does_not_overwrite_the_last_real_connection_result(): void
    {
        ChannelStatus::record('sms', $this->society->id, true, 'Connected to Fast2SMS (balance 10)');

        $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();

        $status = ChannelStatus::get('sms', $this->society->id);
        $this->assertTrue($status['ok'], 'not checking is not a failure');
        $this->assertStringContainsString('Connected to Fast2SMS', $status['summary']);
    }

    public function test_the_same_holds_for_whatsapp_connect_and_template_sync(): void
    {
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'unit-test-key-not-real', 'encrypted', $this->society->id);

        foreach (['settings.whatsapp.connect', 'settings.whatsapp.sync'] as $route) {
            $r = $this->actingAs($this->admin)->postJson(route($route))->assertOk();
            $r->assertJson(['success' => false, 'skipped' => true]);
            $this->assertStringContainsString('Not checked', $r->json('message'), $route);
        }
        $this->assertNull(ChannelStatus::get('whatsapp', $this->society->id), 'nothing was recorded as a failure');
    }

    public function test_a_real_network_failure_says_which_kind_and_is_recorded_as_a_failure(): void
    {
        config(['messaging.enabled' => true]);
        $cases = [
            'Could not resolve host: www.fast2sms.com' => 'cannot find fast2sms.com',
            'Operation timed out after 20001 milliseconds' => 'did not answer in time',
            'SSL certificate problem: unable to get local issuer certificate' => 'secure certificate',
            'Connection refused' => 'Could not reach Fast2SMS',
        ];
        foreach ($cases as $curl => $expect) {
            Fast2SmsAccount::$transportOverride = fn () => [['transport_error' => $curl], 0];

            $r = $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();

            $r->assertJson(['success' => false]);
            $this->assertArrayNotHasKey('skipped', $r->json());
            $this->assertStringContainsString($expect, $r->json('message'), $curl);
            $this->assertFalse(ChannelStatus::get('sms', $this->society->id)['ok']);
        }
    }

    public function test_an_invalid_key_and_a_provider_rejection_stay_distinct_from_a_network_failure(): void
    {
        config(['messaging.enabled' => true]);

        Fast2SmsAccount::$transportOverride = fn () => [['return' => false, 'status_code' => 401, 'message' => 'Invalid Authorization key'], 401];
        $r = $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();
        $this->assertStringContainsString('did not accept this API key', $r->json('message'));

        Fast2SmsAccount::$transportOverride = fn () => [['message' => 'Something else'], 500];
        $r = $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();
        $this->assertStringContainsString('could not complete the request', $r->json('message'));
        $this->assertStringNotContainsString('internet', $r->json('message'));
    }

    public function test_a_good_connection_is_recorded_as_verified_not_as_delivered(): void
    {
        config(['messaging.enabled' => true]);
        Fast2SmsAccount::$transportOverride = function (string $method, string $url) {
            return str_contains($url, '/wallet')
                ? [['return' => true, 'wallet' => '120.5', 'sms_count' => 10], 200]
                : [['success' => true, 'data' => []], 200];
        };

        $r = $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();

        $r->assertJson(['success' => true]);
        $this->assertStringContainsString('Connected to Fast2SMS', ChannelStatus::get('sms', $this->society->id)['summary']);
        $this->assertStringNotContainsStringIgnoringCase('deliver', $r->json('message'));
    }

    public function test_no_key_saved_is_a_plain_setup_message_not_a_network_error(): void
    {
        SystemSetting::set('sms', 'fast2sms_api_key', '', 'encrypted', $this->society->id);

        $r = $this->actingAs($this->admin)->postJson(route('settings.sms.connect'))->assertOk();

        $this->assertStringContainsString('Enter your Fast2SMS API key', $r->json('message'));
    }
}
