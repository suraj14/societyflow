<?php

namespace Tests\Feature;

use App\Events\PaymentReceived;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\WhatsAppService;
use App\Support\ChannelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Fast2SMS WhatsApp contract as the real account showed it: two WhatsApp numbers on one API key, a saved Phone Number ID
 * with two digits transposed (so the provider answers 404 "WABA or Phone Number not found"), and templates that belong to
 * one specific number. Covers every HTTP outcome on the verify and send paths, the Test WhatsApp endpoint, settings
 * rules, isolation and independence of the other channels. The provider is always a captured fake.
 */
class WhatsAppProviderContractTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'wa-key-0123456789abcdefghijKLMNOP';
    private const BOOKMIZO_ID = '1209719742234591';   // the number the society_* templates belong to
    private const OTHER_ID = '941982805667233';       // a second number on the same key
    private const TYPO_ID = '1209719722434591';       // what was saved: BOOKMIZO_ID with two digits swapped

    private $society;
    private $admin;
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Cache::flush();
        $this->society = $this->makeSociety('Royal Eden');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->set('whatsapp_enabled', true, 'boolean');
        $this->set('whatsapp_api_key', self::KEY, 'encrypted');
        $this->set('whatsapp_phone_number_id', self::TYPO_ID);
    }

    protected function tearDown(): void
    {
        Fast2SmsAccount::$transportOverride = null;
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function set(string $key, $value, string $type = 'string'): void
    {
        SystemSetting::set('whatsapp', $key, $value, $type, $this->society->id);
    }

    private function numbers(): array
    {
        return ['success' => true, 'data' => [
            ['waba_id' => '1368222962124471', 'phone_number_id' => self::BOOKMIZO_ID, 'number' => '919999995118', 'verified_name' => 'Bookmizo', 'name_status' => 'Unknown', 'quality_rating' => 'GREEN', 'connection_status' => 'CONNECTED', 'platform_type' => 'CLOUD_API'],
            ['waba_id' => '765390135991223', 'phone_number_id' => self::OTHER_ID, 'number' => '919999998793', 'verified_name' => 'Sainiweb Solutions', 'name_status' => 'Unknown', 'quality_rating' => 'UNKNOWN', 'connection_status' => 'CONNECTED', 'platform_type' => 'CLOUD_API'],
        ]];
    }

    /** Fake account lookups: the profile answer and the number list; every request recorded. */
    private function provider(array $profile, int $profileCode = 200, ?array $numbers = null): void
    {
        $numbers ??= $this->numbers();
        Fast2SmsAccount::$transportOverride = function (string $method, string $url) use ($profile, $profileCode, $numbers) {
            $this->calls[] = [$method, parse_url($url, PHP_URL_PATH), parse_url($url, PHP_URL_QUERY)];

            return str_contains($url, 'whatsapp_business_profile') ? [$profile, $profileCode] : [$numbers, 200];
        };
    }

    private function template(array $over = []): WhatsAppTemplate
    {
        return WhatsAppTemplate::create($over + ['society_id' => $this->society->id, 'external_id' => 'e-' . uniqid(), 'message_id' => '37330', 'phone_number_id' => self::BOOKMIZO_ID,
            'name' => 'society_bill_generated', 'status' => 'APPROVED', 'language' => 'en', 'var_count' => 4, 'purpose' => 'bill', 'is_active' => true, 'source' => 'synced',
            'body_text' => 'Hello {{1}}, bill for {{2}} is Rs. {{3}}. Pay by {{4}}.']);
    }

    private function bill(): array
    {
        return ['resident_name' => 'Asha', 'bill_period' => 'October 2026', 'amount_number' => '2,700.00', 'due_date' => '20 Oct 2026'];
    }

    // ── verification: the real account ──────────────────────────────────────

    public function test_numbers_come_from_the_documented_type_number_listing(): void
    {
        $this->provider(['data' => [['about' => 'x']]]);

        $r = Fast2SmsAccount::whatsappNumbers(self::KEY);

        $this->assertTrue($r['ok']);
        $this->assertSame('/dev/dlt_manager/whatsapp', $this->calls[0][1]);
        $this->assertStringContainsString('type=number', (string) $this->calls[0][2]);
        $this->assertSame([self::BOOKMIZO_ID, self::OTHER_ID], array_column($r['numbers'], 'phone_number_id'));
        $this->assertSame('5118', $r['numbers'][0]['number_tail'], 'only the last four digits are kept');
        $this->assertStringNotContainsString('919999995118', json_encode($r));
    }

    public function test_a_transposed_phone_number_id_gets_the_404_the_user_saw_and_the_real_list_to_pick_from(): void
    {
        $this->provider(['message' => 'WABA or Phone Number not found'], 404);

        $r = WhatsAppService::verify($this->society->id);

        $this->assertTrue($r['local']['ok'], 'shaped like an id: only the provider can tell it is wrong');
        $this->assertFalse($r['provider']['ok']);
        $this->assertStringContainsString('does not know this Phone Number ID', $r['provider']['message']);
        $this->assertStringContainsString('HTTP 404', (string) $r['provider']['detail']);
        $this->assertStringContainsString(self::BOOKMIZO_ID, $r['provider']['message'], 'the way out is named right there');
        $this->assertFalse($r['account']['ok']);
        $this->assertStringContainsString('check each digit', $r['account']['message']);
        $this->assertStringContainsString('Bookmizo', $r['account']['message']);
        $this->assertStringContainsString('Sainiweb Solutions', $r['account']['message']);
        $this->assertFalse($r['verified'], 'nothing is called verified or connected');
        $this->assertCount(2, $r['numbers']);
    }

    public function test_the_correct_id_verifies_and_names_which_number_it_is(): void
    {
        $this->set('whatsapp_phone_number_id', self::BOOKMIZO_ID);
        $this->provider(['data' => [['about' => 'x']]]);

        $r = WhatsAppService::verify($this->society->id);

        $this->assertTrue($r['verified']);
        $this->assertStringContainsString('Bookmizo', $r['account']['message']);
        $this->assertStringContainsString('5118', $r['account']['message']);
    }

    public function test_every_http_outcome_of_the_profile_check_has_a_clear_failure(): void
    {
        $cases = [401 => 'did not accept', 403 => 'did not accept', 400 => 'refused the request', 404 => 'does not know this Phone Number ID',
            429 => 'rate-limiting', 500 => 'problem on its side', 503 => 'problem on its side'];

        foreach ($cases as $code => $needle) {
            $this->provider(['message' => 'provider words'], $code);
            $r = WhatsAppService::checkConnection($this->society->id);

            $this->assertFalse($r['ok'], "HTTP $code is a failure");
            $this->assertStringContainsString($needle, $r['message'], "HTTP $code");
            $this->assertStringNotContainsString(self::KEY, json_encode($r));
        }
    }

    public function test_a_timeout_and_a_malformed_answer_are_failures_not_successes(): void
    {
        Fast2SmsAccount::$transportOverride = fn () => [['transport_error' => 'Operation timed out after 20001 milliseconds'], 0];
        $timeout = WhatsAppService::checkConnection($this->society->id);
        $this->assertFalse($timeout['ok']);
        $this->assertStringContainsString('did not answer in time', $timeout['message']);

        Fast2SmsAccount::$transportOverride = fn () => [['message' => 'Unreadable response', 'raw' => '<html>502</html>'], 200];
        $malformed = WhatsAppService::checkConnection($this->society->id);
        $this->assertFalse($malformed['ok'], 'an HTTP 200 without the documented data is not a pass');
    }

    public function test_the_ownership_stage_never_passes_on_the_shape_of_an_id(): void
    {
        $this->set('whatsapp_phone_number_id', '555000111222333');   // a perfectly shaped id the account does not have
        $this->provider(['data' => [['about' => 'x']]]);

        $r = WhatsAppService::verify($this->society->id);

        $this->assertTrue($r['local']['ok']);
        $this->assertFalse($r['account']['ok']);
        $this->assertFalse($r['verified']);
    }

    public function test_a_failed_lookup_never_makes_the_ownership_stage_pass(): void
    {
        Fast2SmsAccount::$transportOverride = fn () => [['message' => 'Invalid Authorization key'], 401];

        $r = WhatsAppService::verify($this->society->id);

        $this->assertFalse($r['provider']['ok']);
        $this->assertFalse($r['account']['ok']);
        $this->assertSame([], $r['numbers']);
        $this->assertFalse($r['verified']);
    }

    // ── sending: the template's own number, and every outcome ───────────────

    private function capture(array $answer = null, int $code = 200): void
    {
        WhatsAppService::$transportOverride = function (string $url) use ($answer, $code) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->calls[] = ['path' => parse_url($url, PHP_URL_PATH), 'q' => $q];

            return [$answer ?? ['status' => true, 'message' => 'Message sent successfully', 'request_id' => 'req-77'], $code];
        };
    }

    public function test_a_template_is_sent_from_its_own_number_even_when_the_saved_id_is_mistyped(): void
    {
        $this->template();
        $this->capture();

        $r = WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id);

        $this->assertTrue($r['success']);
        $this->assertSame('req-77', $r['request_id'], 'the provider request id is kept');
        $this->assertSame('/dev/whatsapp', $this->calls[0]['path']);
        $this->assertSame(self::BOOKMIZO_ID, $this->calls[0]['q']['phone_number_id'], 'the number the template belongs to, not the mistyped setting');
        $this->assertSame('37330', $this->calls[0]['q']['message_id']);
        $this->assertSame('Asha|October 2026|2,700.00|20 Oct 2026', $this->calls[0]['q']['variables_values']);
        $this->assertSame('919876543210', $this->calls[0]['q']['numbers']);
    }

    public function test_a_hand_typed_template_falls_back_to_the_saved_number(): void
    {
        $this->template(['phone_number_id' => null, 'source' => 'manual', 'status' => 'LOCAL']);
        $this->set('whatsapp_phone_number_id', self::OTHER_ID);
        $this->capture();

        WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id);

        $this->assertSame(self::OTHER_ID, $this->calls[0]['q']['phone_number_id']);
    }

    public function test_every_http_outcome_of_a_send_is_a_failure_with_advice_and_is_not_retried(): void
    {
        $this->template();
        $cases = [400 => 'refused the request', 404 => 'does not know this Phone Number ID', 401 => 'did not accept the API key', 403 => 'did not accept the API key', 429 => 'rate-limiting', 500 => 'problem on its side', 503 => 'problem on its side'];

        foreach ($cases as $code => $needle) {
            $this->calls = [];
            Cache::flush();   // so the duplicate guard does not mask the next case
            $this->capture(['status' => false, 'message' => 'WABA or Phone Number not found'], $code);

            $r = WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id);

            $this->assertFalse($r['success'], "HTTP $code");
            $this->assertStringContainsString($needle, $r['message'], "HTTP $code");
            $this->assertCount(1, $this->calls, "HTTP $code is never retried");
        }
        $this->assertDatabaseMissing('email_logs', ['channel' => 'whatsapp', 'status' => 'sent']);
        $this->assertDatabaseHas('email_logs', ['channel' => 'whatsapp', 'trigger_action' => 'bill', 'status' => 'failed']);
    }

    public function test_an_unreachable_provider_and_an_http_200_that_is_not_an_acceptance_are_failures(): void
    {
        $this->template();
        WhatsAppService::$transportOverride = fn () => [['transport_error' => 'Could not resolve host'], 0];
        $down = WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id);
        $this->assertFalse($down['success']);
        $this->assertStringContainsString('Could not reach Fast2SMS', $down['message']);

        Cache::flush();
        $this->capture(['message' => 'Unreadable response', 'raw' => '<html>'], 200);   // HTTP 200 but no `status: true`
        $malformed = WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id);
        $this->assertFalse($malformed['success'], 'an HTTP 200 alone is not acceptance');
    }

    public function test_missing_key_or_phone_number_id_sends_nothing(): void
    {
        $this->template(['phone_number_id' => null, 'source' => 'manual', 'status' => 'LOCAL']);
        $this->capture();

        $this->set('whatsapp_phone_number_id', '');
        $this->assertSame('dev_mode', WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id)['message']);

        $this->set('whatsapp_phone_number_id', self::OTHER_ID);
        $this->set('whatsapp_api_key', '', 'encrypted');
        Cache::flush();
        $this->assertSame('dev_mode', WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id)['message']);

        $this->assertSame([], $this->calls, 'nothing was sent');
    }

    public function test_the_integration_toggle_and_the_server_kill_switch_stop_every_send(): void
    {
        $this->template();
        $this->capture();
        $this->set('whatsapp_enabled', false, 'boolean');
        WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id);
        $this->assertSame([], $this->calls, 'toggle off');

        // toggle on, but the real client runs and the kill switch (off in the test environment) stops it before any request
        $this->set('whatsapp_enabled', true, 'boolean');
        WhatsAppService::$transportOverride = null;
        Cache::flush();
        $r = WhatsAppService::sendNotification('bill', '9876543210', $this->bill(), $this->society->id);
        $this->assertTrue($r['skipped'] ?? false);
        Http::assertNothingSent();
    }

    // ── Test WhatsApp ───────────────────────────────────────────────────────

    private function test(array $over = [], $as = null)
    {
        return ($as ? $this->actingAs($as) : $this->actingAs($this->admin))->postJson(route('settings.whatsapp.test-template'), $over + ['phone' => '9876543210', 'template_id' => $this->template()->id, 'variables' => 'Asha|Oct|10|20 Oct']);
    }

    public function test_a_test_send_calls_the_provider_once_with_the_templates_number_and_does_not_claim_delivery(): void
    {
        $this->capture();

        $r = $this->test()->assertOk()->assertJsonPath('success', true);

        $this->assertCount(1, $this->calls);
        $this->assertSame(self::BOOKMIZO_ID, $this->calls[0]['q']['phone_number_id']);
        $this->assertStringContainsString('not a delivery confirmation', $r->json('message'));
        $this->assertStringNotContainsString(self::KEY, $r->getContent());
        $this->assertStringContainsString('3210', $r->json('message'));
        $this->assertStringNotContainsString('9876543210', $r->json('message'), 'only the last four digits are shown');
    }

    public function test_a_double_submitted_test_reaches_the_provider_once(): void
    {
        $this->capture();
        $template = $this->template();

        $first = $this->test(['template_id' => $template->id]);
        $second = $this->test(['template_id' => $template->id]);

        $first->assertJsonPath('success', true);
        $second->assertJsonPath('success', false);
        $this->assertStringContainsString('just submitted', $second->json('message'));
        $this->assertCount(1, $this->calls);
    }

    public function test_a_failed_test_send_does_not_block_a_retry_but_an_accepted_one_does(): void
    {
        $template = $this->template();

        $this->capture(['message' => 'WABA or Phone Number not found'], 404);
        $this->test(['template_id' => $template->id])->assertJsonPath('success', false);

        $this->capture();
        $this->test(['template_id' => $template->id])->assertJsonPath('success', true);          // the retry goes through
        $this->test(['template_id' => $template->id])->assertJsonPath('success', false);         // an identical resend after acceptance does not
        $this->assertCount(2, $this->calls);
    }

    public function test_a_test_with_a_bad_recipient_or_values_sends_nothing_and_does_not_lock_the_next_try(): void
    {
        $this->capture();
        $template = $this->template();

        foreach (['abc12345678', '12345', '+91 98765 4321 0987 65'] as $bad) {
            $this->test(['phone' => $bad, 'template_id' => $template->id])->assertStatus(422)->assertJsonValidationErrors('phone');
        }
        $this->test(['variables' => 'Asha|Oct', 'template_id' => $template->id])->assertJsonPath('success', false);   // 2 of 4 values
        $this->assertSame([], $this->calls);

        $this->test(['template_id' => $template->id])->assertJsonPath('success', true);   // a corrected try is not blocked
        $this->assertCount(1, $this->calls);
    }

    public function test_a_test_with_a_pending_or_bookmizo_template_is_refused_before_any_request(): void
    {
        $this->capture();

        $pending = $this->template(['status' => 'PENDING', 'name' => 'society_x_pending']);
        $this->test(['template_id' => $pending->id])->assertJsonPath('success', false);

        $bookmizo = $this->template(['name' => 'bookmizo_refund_full', 'purpose' => 'visitor']);
        $r = $this->test(['template_id' => $bookmizo->id, 'phone' => '9876543211']);
        $r->assertJsonPath('success', false);

        $this->assertSame([], $this->calls);
    }

    public function test_another_societys_template_cannot_be_used_and_only_admins_may_test(): void
    {
        $this->capture();
        $other = $this->makeSociety('Beta');
        $foreign = WhatsAppTemplate::create(['society_id' => $other->id, 'external_id' => 'f1', 'message_id' => '1', 'name' => 'society_bill_generated', 'status' => 'APPROVED', 'var_count' => 0, 'source' => 'synced']);

        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.test-template'), ['phone' => '9876543210', 'template_id' => $foreign->id])->assertJsonPath('success', false);

        foreach (['Tenant', 'Staff', 'Guard', 'Accountant'] as $role) {
            $this->actingAs($this->makeUserWithRole($this->society, $role))->postJson(route('settings.whatsapp.test-template'), ['phone' => '9876543210', 'template_id' => $this->template()->id])->assertForbidden();
        }
        $this->assertSame([], $this->calls);
    }

    // ── settings ────────────────────────────────────────────────────────────

    public function test_whatsapp_is_not_switched_on_without_a_key_and_a_phone_number_id_but_the_rest_is_saved(): void
    {
        $this->set('whatsapp_enabled', false, 'boolean');
        $this->set('whatsapp_phone_number_id', '');
        $this->set('whatsapp_api_key', '', 'encrypted');

        $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), ['whatsapp_enabled' => 1, 'whatsapp_country_code' => '971'])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'not switched on'));

        $this->assertFalse(WhatsAppService::isEnabled($this->society->id));
        $this->assertSame('971', SystemSetting::get('whatsapp', 'whatsapp_country_code', null, $this->society->id), 'other fields still saved');
    }

    public function test_settings_are_per_society_and_only_admins_can_change_them(): void
    {
        $other = $this->makeSociety('Beta');
        $otherAdmin = $this->makeUserWithRole($other, 'Admin');
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', self::OTHER_ID, 'string', $other->id);
        $this->provider(['data' => [['about' => 'x']]]);

        $this->actingAs($otherAdmin)->post(route('settings.whatsapp.update'), ['whatsapp_phone_number_id' => '777000111222333', 'whatsapp_api_key' => 'beta-key-0123456789abcdefghij']);
        $this->assertSame(self::TYPO_ID, SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $this->society->id), 'society A untouched');
        $this->assertSame(self::KEY, WhatsAppService::apiKey($this->society->id));

        foreach (['Tenant', 'Staff', 'Guard', 'Accountant'] as $role) {
            $this->actingAs($this->makeUserWithRole($this->society, $role))->post(route('settings.whatsapp.update'), ['whatsapp_phone_number_id' => '999000111222333'])->assertForbidden();
        }
        $this->assertSame(self::TYPO_ID, SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $this->society->id));
    }

    public function test_the_page_never_contains_the_api_key_and_counts_only_notifications_that_can_really_send(): void
    {
        // a BookMizo template switched on for a resident notification: counted nowhere as "in use", called out instead
        $this->template(['name' => 'bookmizo_refund_full', 'purpose' => 'visitor']);

        $html = $this->actingAs($this->admin)->get('/settings?tab=whatsapp')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::KEY, $html);
        $this->assertStringContainsString('(0 of 8 now)', $html);
        $this->assertStringContainsString('cannot be sent', $html);
        $this->assertStringContainsString('Fixing', $html);
    }

    public function test_the_last_success_survives_a_later_failure_and_both_are_shown(): void
    {
        ChannelStatus::record('whatsapp', $this->society->id, true, 'Fast2SMS accepted a test message');
        ChannelStatus::record('whatsapp', $this->society->id, false, 'Verification failed', 'HTTP 404: WABA or Phone Number not found');

        $status = ChannelStatus::get('whatsapp', $this->society->id);
        $this->assertFalse($status['ok']);
        $this->assertNotNull($status['last_ok_at']);

        $html = $this->actingAs($this->admin)->get('/settings?tab=whatsapp')->getContent();
        $this->assertStringContainsString('Last successful provider request', $html);
        $this->assertStringContainsString('Fast2SMS accepted a test message', $html);
        $this->assertStringContainsString('HTTP 404: WABA or Phone Number not found', $html);
    }

    // ── other channels keep working when WhatsApp fails ─────────────────────

    public function test_a_failing_whatsapp_send_does_not_stop_the_in_app_notification(): void
    {
        $this->template(['purpose' => 'payment', 'name' => 'society_payment_received', 'var_count' => 1, 'body_text' => 'Paid {{1}}']);
        $resident = $this->makeUserWithRole($this->society, 'Tenant', ['phone' => '9876543210']);
        WhatsAppService::$transportOverride = fn () => throw new \RuntimeException('provider exploded');

        event(new PaymentReceived((new Payment())->forceFill(['id' => 9, 'society_id' => $this->society->id, 'user_id' => $resident->id, 'amount' => 1200, 'payment_date' => now()])));

        $titles = $resident->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all();
        $this->assertContains('Payment received', $titles, 'the bell entry exists even though WhatsApp blew up');
    }
}
