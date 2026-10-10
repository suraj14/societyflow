<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OTP and DLT must stay separate: distinct settings fields, distinct
 * endpoints, and testable independently of which route is currently saved.
 * Builds on Fast2SmsSettingsTest (the key/masking contract); this covers
 * what the latest product brief added on top of it.
 */
class Fast2SmsDltSeparationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'F2S-TESTKEY-0123456789ABCDEFGH';

    private function admin()
    {
        return $this->makeUserWithRole($this->makeSociety(), 'Admin');
    }

    private function save(\App\Models\User $admin, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->post(route('settings.sms.update'), array_merge([
            'sms_provider'         => 'fast2sms',
            'sms_enabled'          => '1',
            'fast2sms_api_key'     => self::KEY,
            'fast2sms_route'       => 'otp',
            'fast2sms_otp_id'      => '6fd92db0ae',
            'fast2sms_sender_id'   => 'DISP24',
            'fast2sms_template_id' => '169ec8e6c8',
            'fast2sms_entity_id'   => '1701234567890123456',
        ], $overrides));
    }

    private function fakeTransport(array $body, int $status, ?array &$sent = null): void
    {
        SmsService::$transportOverride = function (string $url, array $data, array $headers) use ($body, $status, &$sent) {
            $sent = ['url' => $url, 'data' => $data, 'headers' => $headers];
            return [$body, $status];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    public function test_the_entity_id_is_saved_and_shown_as_configuration_not_a_secret(): void
    {
        $admin = $this->admin();
        $this->save($admin);

        $raw = SystemSetting::where('group', 'sms')->where('key', 'fast2sms_entity_id')
            ->where('society_id', $admin->society_id)->value('value');
        $this->assertSame('1701234567890123456', $raw, 'the Entity ID is a registration record, not a secret - stored plain');

        $html = $this->actingAs($admin)->get('/settings?tab=sms')->assertOk()->getContent();
        $this->assertStringContainsString('1701234567890123456', $html);
    }

    public function test_entity_id_is_never_sent_to_the_fast2sms_api(): void
    {
        $admin = $this->admin();
        $this->save($admin);

        $this->fakeTransport(['return' => true], 200, $sentOtp);
        SmsService::sendOtp('9876543210', '1234', $admin->society_id);
        $this->assertArrayNotHasKey('entity_id', $sentOtp['data']);

        $this->save($admin, ['fast2sms_route' => 'dlt']);
        $this->fakeTransport(['return' => true], 200, $sentDlt);
        SmsService::sendOtp('9876543210', '1234', $admin->society_id);
        $this->assertArrayNotHasKey('entity_id', $sentDlt['data']);
    }

    public function test_test_otp_button_exercises_the_otp_endpoint_regardless_of_saved_route(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_route' => 'dlt']); // DLT is saved…

        $this->fakeTransport(['return' => true], 200, $sent);

        // …but "Test OTP" still hits the OTP endpoint.
        $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210', 'route' => 'otp'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('https://www.fast2sms.com/dev/otp/send', $sent['url']);
        $this->assertSame('6fd92db0ae', $sent['data']['otp_id']);
    }

    public function test_test_dlt_button_exercises_the_dlt_endpoint_regardless_of_saved_route(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_route' => 'otp']); // OTP is saved…

        $this->fakeTransport(['return' => true], 200, $sent);

        // …but "Test DLT SMS" still hits the DLT endpoint.
        $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210', 'route' => 'dlt'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('https://www.fast2sms.com/dev/bulkV2', $sent['url']);
        $this->assertSame('dlt', $sent['data']['route']);
        $this->assertSame('DISP24', $sent['data']['sender_id']);
        $this->assertSame('169ec8e6c8', $sent['data']['message']);
    }

    public function test_an_invalid_route_value_is_rejected(): void
    {
        $admin = $this->admin();
        $this->save($admin);

        $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210', 'route' => 'bogus'])
            ->assertStatus(422);
    }

    public function test_a_forced_route_test_with_no_key_reports_dev_mode_not_a_live_call(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['fast2sms_api_key' => '']);

        $this->fakeTransport(['return' => true], 200, $sent);

        $resp = $this->actingAs($admin)->postJson(route('settings.sms.test'), ['phone' => '9876543210', 'route' => 'dlt'])
            ->assertOk();

        $this->assertFalse($resp->json('success'));
        $this->assertNull($sent, 'no request may be sent without a key, even for a forced test');
    }

    public function test_the_three_notification_toggles_save_independently(): void
    {
        $admin = $this->admin();
        $this->save($admin, [
            'visitor_sms_enabled'      => '1',
            'access_pass_sms_enabled'  => '0',
            'notification_sms_enabled' => '1',
        ]);

        $this->assertTrue((bool) SystemSetting::get('sms', 'visitor_sms_enabled', null, $admin->society_id));
        $this->assertFalse((bool) SystemSetting::get('sms', 'access_pass_sms_enabled', null, $admin->society_id));
        $this->assertTrue((bool) SystemSetting::get('sms', 'notification_sms_enabled', null, $admin->society_id));
    }

    public function test_enabling_sms_otp_still_sets_the_flag_sendotp_actually_reads(): void
    {
        // The checkbox is still named sms_enabled in the form (SmsService's
        // real gate); this pins that the "Enable SMS OTP" relabel did not
        // quietly stop writing the key the OTP send path reads.
        $admin = $this->admin();
        $this->save($admin, ['sms_enabled' => '1']);

        $this->assertTrue((bool) SystemSetting::get('sms', 'sms_enabled', null, $admin->society_id));

        $this->fakeTransport(['return' => true], 200, $sent);
        SmsService::sendOtp('9876543210', '1234', $admin->society_id);
        $this->assertNotNull($sent, 'OTP must still actually send when enabled');
    }

    public function test_sms_notification_toggle_gates_the_dlt_notification_sender(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['visitor_sms_enabled' => '0']);

        $this->fakeTransport(['return' => true], 200, $sent);
        $result = SmsService::sendDltNotification('visitor', '9876543210', 'Your visitor has arrived.', $admin->society_id);

        $this->assertSame('dev_mode', $result['message']);
        $this->assertNull($sent, 'the toggle being off must stop the send, not just the UI');
    }

    public function test_sms_notification_actually_sends_when_its_toggle_is_on(): void
    {
        $admin = $this->admin();
        $this->save($admin, ['visitor_sms_enabled' => '1']);

        $this->fakeTransport(['return' => true], 200, $sent);
        $result = SmsService::sendDltNotification('visitor', '9876543210', 'Your visitor has arrived.', $admin->society_id);

        $this->assertTrue($result['success']);
        $this->assertSame('https://www.fast2sms.com/dev/bulkV2', $sent['url']);
        $this->assertSame('Your visitor has arrived.', $sent['data']['variables_values']);
    }
}
