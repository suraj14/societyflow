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
 * code-0 result to that sentence, but code 0 is also what the transport returned when the EXTERNAL_MESSAGING_ENABLED
 * kill switch stopped the call before any network use. With messaging off, a check never touched the network and still
 * told people to check their internet.
 *
 * The provider checks that remain are the WhatsApp key + Phone Number ID check that runs when WhatsApp settings are
 * saved (the documented read-only business-profile request) and the template refresh. For both: kill switch ->
 * "Not checked - real messaging is switched off" (never a failure, never overwrites the last real result); a REAL
 * transport failure says which kind (DNS, timeout, certificate); an invalid key and a rejected request stay distinct.
 * No request leaves the process in these tests.
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
        // The WhatsApp field is left empty in these saves: it reuses the SMS key (one Fast2SMS account).
        SystemSetting::set('sms', 'fast2sms_api_key', 'unit-test-key-not-real', 'encrypted', $this->society->id);
    }

    protected function tearDown(): void
    {
        Fast2SmsAccount::$transportOverride = null;
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function saveWhatsApp(array $over = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), $over + [
            'whatsapp_enabled' => '1', 'whatsapp_phone_number_id' => '1209719742234591', 'whatsapp_country_code' => '91',
        ]);
    }

    public function test_with_messaging_off_saving_says_not_checked_and_never_blames_the_internet(): void
    {
        $this->assertFalse(\App\Support\ExternalMessaging::enabled());
        Fast2SmsAccount::$transportOverride = null;      // the real transport: the kill switch must stop it first

        $r = $this->saveWhatsApp()->assertRedirect('/settings?tab=whatsapp');

        $message = session('success');
        $this->assertStringContainsString('saved', $message);
        $this->assertStringContainsString('Not checked', $message);
        $this->assertStringContainsString('switched off', $message);
        $this->assertStringNotContainsString('internet', $message);
        $this->assertNull(ChannelStatus::get('whatsapp', $this->society->id), 'nothing was checked, so nothing is recorded');
        $this->assertSame('1209719742234591', SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $this->society->id), 'the settings are saved regardless');
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

            $this->saveWhatsApp();

            $status = ChannelStatus::get('whatsapp', $this->society->id);
            $this->assertFalse($status['ok'], $curl);
            $this->assertStringContainsString($expect, $status['summary'], $curl);
            $this->assertStringContainsString('did not accept', session('success'));
        }
    }

    public function test_an_invalid_key_and_a_provider_rejection_stay_distinct_from_a_network_failure(): void
    {
        config(['messaging.enabled' => true]);

        Fast2SmsAccount::$transportOverride = fn () => [['return' => false, 'status_code' => 401, 'message' => 'Invalid Authorization key'], 401];
        $this->saveWhatsApp();
        $status = ChannelStatus::get('whatsapp', $this->society->id);
        $this->assertStringContainsString('did not accept this API key', $status['summary']);
        $this->assertStringContainsString('Invalid Authorization key', $status['detail']);

        Fast2SmsAccount::$transportOverride = fn () => [['message' => 'Something else'], 500];
        $this->saveWhatsApp();
        $status = ChannelStatus::get('whatsapp', $this->society->id);
        $this->assertStringContainsString('could not complete the request', $status['summary']);
        $this->assertStringNotContainsString('internet', $status['summary']);
    }

    public function test_a_good_check_is_recorded_as_accepted_settings_not_as_delivery(): void
    {
        config(['messaging.enabled' => true]);
        $urls = [];
        Fast2SmsAccount::$transportOverride = function (string $method, string $url) use (&$urls) {
            $urls[] = [$method, $url];

            return [['data' => [['about' => 'Alpha Heights', 'vertical' => 'OTHER', 'messaging_product' => 'whatsapp']]], 200];
        };

        $this->saveWhatsApp();

        $status = ChannelStatus::get('whatsapp', $this->society->id);
        $this->assertTrue($status['ok']);
        $this->assertStringContainsString('accepted the API key and Phone Number ID', $status['summary']);
        $this->assertStringNotContainsStringIgnoringCase('deliver', $status['summary']);
        // exactly one read-only request: the documented business-profile GET for this Phone Number ID
        $this->assertCount(1, $urls);
        $this->assertSame('GET', $urls[0][0]);
        $this->assertStringContainsString('/dev/whatsapp/v26.0/1209719742234591/whatsapp_business_profile', $urls[0][1]);
    }

    public function test_no_key_anywhere_is_a_plain_setup_message_not_a_network_error(): void
    {
        SystemSetting::set('sms', 'fast2sms_api_key', '', 'encrypted', $this->society->id);

        $this->saveWhatsApp();

        $this->assertStringContainsString('Add the API key and the Phone Number ID', session('success'));
        $this->assertNull(ChannelStatus::get('whatsapp', $this->society->id));
    }

    public function test_a_switched_off_integration_is_saved_without_contacting_fast2sms(): void
    {
        config(['messaging.enabled' => true]);
        $called = false;
        Fast2SmsAccount::$transportOverride = function () use (&$called) { $called = true; return [[], 200]; };

        $this->saveWhatsApp(['whatsapp_enabled' => '0']);

        $this->assertFalse($called, 'no check while the integration is off');
        $this->assertStringContainsString('switched off', session('success'));
    }
}
