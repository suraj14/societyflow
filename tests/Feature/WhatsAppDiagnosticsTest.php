<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use App\Support\WhatsAppProviderError;
use App\Support\WhatsAppTemplateMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The WhatsApp integration end to end, against faked provider answers only: the `(#100) Tried accessing nonexistent field`
 * error (Meta's error inside an HTTP 200, caused by saving the Business Account ID as the Phone Number ID), the three
 * separate "Verify configuration" stages, redaction that keeps field names, settings validation, a sync that is idempotent
 * and attaches only clearly-named approved templates (never activating one), and provider send errors explained.
 * Nothing leaves the process.
 */
class WhatsAppDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'wa-key-0123456789abcdefghijKLMNOP';
    private const PNID = '1209719742234591';   // a Phone Number ID
    private const WABA = '1368222962124471';   // the Business Account ID: a different, equally long number

    private $society;
    private $admin;
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->set('whatsapp_enabled', true, 'boolean');
        $this->set('whatsapp_api_key', self::KEY, 'encrypted');
        $this->set('whatsapp_phone_number_id', self::PNID);
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

    /** The fake provider: profile and template-list requests answered from the given bodies, every request recorded. */
    private function provider(?array $profile = null, ?array $account = null, int $profileCode = 200): void
    {
        $profile ??= ['data' => [['about' => 'Hello', 'messaging_product' => 'whatsapp']]];
        $account ??= ['success' => true, 'data' => [['waba_id' => self::WABA, 'phone_number_id' => self::PNID, 'templates' => []]]];
        Fast2SmsAccount::$transportOverride = function (string $method, string $url) use ($profile, $account, $profileCode) {
            $this->calls[] = [$method, parse_url($url, PHP_URL_PATH), parse_url($url, PHP_URL_QUERY)];

            return str_contains($url, 'whatsapp_business_profile') ? [$profile, $profileCode] : [$account, 200];
        };
    }

    private function meta100(): array
    {
        return ['error' => ['message' => '(#100) Tried accessing nonexistent field (whatsapp_business_profile) on node type (WhatsAppBusinessAccount)', 'type' => 'OAuthException', 'code' => 100]];
    }

    // ── the (#100) error ────────────────────────────────────────────────────

    public function test_the_profile_check_calls_the_documented_endpoint_with_the_saved_ids_and_the_key_only_in_the_header(): void
    {
        $this->provider();

        $r = WhatsAppService::checkConnection($this->society->id);

        $this->assertTrue($r['ok']);
        [$method, $path, $query] = $this->calls[0];
        $this->assertSame('GET', $method);
        $this->assertSame('/dev/whatsapp/v26.0/' . self::PNID . '/whatsapp_business_profile', $path);
        $this->assertStringContainsString('fields=about', (string) $query);
        $this->assertStringNotContainsString(self::KEY, (string) $query, 'the key never travels in the URL');
    }

    public function test_a_meta_error_inside_http_200_is_a_failure_with_the_fix_not_a_generic_line(): void
    {
        $this->provider($this->meta100());

        $r = WhatsAppService::checkConnection($this->society->id);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Phone Number ID', $r['message']);
        $this->assertStringContainsString('WABA', $r['message']);
        $this->assertStringNotContainsString('could not complete the request', $r['message']);
        // the provider's own words stay as detail, with the endpoint name intact (it is not a secret)
        $this->assertStringContainsString('whatsapp_business_profile', (string) $r['detail']);
        $this->assertStringNotContainsString(self::KEY, json_encode($r));
    }

    public function test_redaction_hides_keys_and_tokens_but_not_field_names(): void
    {
        $text = 'bad (whatsapp_business_profile) token ' . str_repeat('A1', 20) . ' key ' . self::KEY . ' authorization: Bearer abc';

        $safe = SmsService::safeProviderMessage($text, self::KEY);

        $this->assertStringContainsString('whatsapp_business_profile', $safe);
        $this->assertStringNotContainsString(self::KEY, $safe);
        $this->assertStringNotContainsString(str_repeat('A1', 20), $safe);
        $this->assertStringNotContainsString('abc', $safe);
    }

    public function test_provider_error_codes_are_explained_in_plain_language(): void
    {
        $this->assertStringContainsString('different number of values', WhatsAppProviderError::explain('(#132000) Number of parameters does not match the expected number of params'));
        $this->assertStringContainsString('name and language', WhatsAppProviderError::explain('Template name does not exist', 132001));
        $this->assertStringContainsString('API key', WhatsAppProviderError::explain('(#190) Invalid OAuth access token'));
        $this->assertStringContainsString('24 hours', WhatsAppProviderError::explain('(#131047) Re-engagement message'));
        $this->assertStringContainsString('rate-limiting', WhatsAppProviderError::explain('(#130429) Rate limit hit'));
        $this->assertNull(WhatsAppProviderError::explain('something unknown'));
    }

    public function test_a_send_rejected_for_the_wrong_parameter_count_inside_http_200_is_a_failure_that_says_so(): void
    {
        WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => 'e1', 'message_id' => '37330', 'name' => 'society_bill_generated', 'status' => 'APPROVED', 'language' => 'en',
            'var_count' => 4, 'body_text' => 'Hello {{1}}, bill for {{2}} is Rs. {{3}}. Pay by {{4}}.', 'purpose' => 'bill', 'is_active' => true, 'source' => 'synced']);
        WhatsAppService::$transportOverride = function (string $url) {
            $this->calls[] = [$url];

            return [['error' => ['message' => '(#132000) Number of parameters does not match the expected number of params', 'code' => 132000]], 200];
        };

        $r = WhatsAppService::sendNotification('bill', '9876543210', ['resident_name' => 'Asha', 'bill_period' => 'October 2026', 'amount_number' => '2,700.00', 'due_date' => '20 Oct 2026'], $this->society->id);

        $this->assertFalse($r['success'], 'an HTTP 200 carrying an error is not a success');
        $this->assertStringContainsString('different number of values', $r['message']);
        $this->assertDatabaseHas('email_logs', ['channel' => 'whatsapp', 'trigger_action' => 'bill', 'status' => 'failed']);
        $this->assertDatabaseMissing('email_logs', ['channel' => 'whatsapp', 'status' => 'sent']);
    }

    public function test_the_same_notification_retried_is_sent_once(): void
    {
        WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => 'e1', 'message_id' => '37332', 'name' => 'society_important_notice', 'status' => 'APPROVED', 'language' => 'en',
            'var_count' => 3, 'body_text' => 'Hello {{1}}, notice from {{2}}: {{3}}.', 'purpose' => 'notice', 'is_active' => true, 'source' => 'synced']);
        WhatsAppService::$transportOverride = function (string $url) {
            $this->calls[] = [$url];

            return [['status' => true, 'request_id' => 'r'], 200];
        };
        $values = ['resident_name' => 'Asha', 'notice_title' => 'Water cut', 'society_name' => 'Alpha'];

        $first = WhatsAppService::sendNotification('notice', '9876543210', $values, $this->society->id);
        $retry = WhatsAppService::sendNotification('notice', '9876543210', $values, $this->society->id);

        $this->assertTrue($first['success']);
        $this->assertSame('skipped_duplicate', $retry['status']);
        $this->assertCount(1, $this->calls);
    }

    // ── Verify configuration: three separate stages ─────────────────────────

    public function test_verification_passes_only_when_all_three_stages_pass_and_sends_no_message(): void
    {
        $this->provider();

        $r = WhatsAppService::verify($this->society->id);

        $this->assertTrue($r['local']['ok']);
        $this->assertTrue($r['provider']['ok']);
        $this->assertTrue($r['account']['ok']);
        $this->assertTrue($r['verified']);
        $this->assertSame(['GET', 'GET'], array_column($this->calls, 0), 'two read-only lookups, nothing posted');
        Http::assertNothingSent();
    }

    public function test_the_business_account_id_saved_as_the_phone_number_id_is_diagnosed_and_names_the_right_one(): void
    {
        // the real shape of the problem: society 6 saved the WABA id in the Phone Number ID field
        $this->set('whatsapp_phone_number_id', self::WABA);
        $this->provider($this->meta100());

        $r = WhatsAppService::verify($this->society->id);

        $this->assertTrue($r['local']['ok'], 'shaped like an id, so only the provider can tell');
        $this->assertFalse($r['provider']['ok']);
        $this->assertFalse($r['account']['ok']);
        $this->assertStringContainsString('Business Account (WABA) ID', $r['account']['message']);
        $this->assertStringContainsString(self::PNID, $r['account']['message'], 'the account\'s real Phone Number ID is named');
        $this->assertFalse($r['verified']);
    }

    public function test_local_problems_stop_before_any_provider_call(): void
    {
        $this->provider();
        $this->set('whatsapp_waba_id', self::PNID);   // same as the phone number id

        $r = WhatsAppService::verify($this->society->id);

        $this->assertFalse($r['local']['ok']);
        $this->assertStringContainsString('same as the Business Account', $r['local']['message']);
        $this->assertNull($r['provider']['ok']);
        $this->assertNull($r['account']['ok']);
        $this->assertSame([], $this->calls);

        $this->set('whatsapp_waba_id', '');
        $this->set('whatsapp_phone_number_id', '9876543210');
        $this->assertStringContainsString('mobile number', WhatsAppService::verify($this->society->id)['local']['message']);
        $this->set('whatsapp_api_key', '', 'encrypted');
        $this->assertStringContainsString('API key', WhatsAppService::verify($this->society->id)['local']['message']);
        $this->assertSame([], $this->calls);
    }

    public function test_an_id_that_is_none_of_the_accounts_numbers_is_reported_not_accepted(): void
    {
        $this->set('whatsapp_phone_number_id', '5550001112223334');
        $this->provider();   // the profile answers fine, the account lists a different number

        $r = WhatsAppService::verify($this->society->id);

        $this->assertFalse($r['account']['ok']);
        $this->assertStringContainsString(self::PNID, $r['account']['message']);
        $this->assertFalse($r['verified'], 'the profile answering is not enough');
    }

    public function test_with_the_server_kill_switch_off_the_provider_stages_are_not_checked_rather_than_passed(): void
    {
        // no fake: the real client runs and the kill switch (off in the test environment) stops it before any request
        $r = WhatsAppService::verify($this->society->id);

        $this->assertTrue($r['local']['ok']);
        $this->assertNull($r['provider']['ok']);
        $this->assertNull($r['account']['ok']);
        $this->assertFalse($r['verified']);
        Http::assertNothingSent();
    }

    public function test_the_verify_endpoint_is_admin_only_and_reports_json(): void
    {
        $this->provider();

        foreach (['Tenant', 'Staff', 'Guard', 'Accountant'] as $role) {
            $this->actingAs($this->makeUserWithRole($this->society, $role))->postJson(route('settings.whatsapp.verify'))->assertForbidden();
        }
        $this->assertSame([], $this->calls);

        $this->actingAs($this->admin)->postJson(route('settings.whatsapp.verify'))->assertOk()
            ->assertJsonPath('verified', true)->assertJsonPath('delivery', 'not tested: verification sends no message');
        $this->assertStringNotContainsString(self::KEY, $this->actingAs($this->admin)->postJson(route('settings.whatsapp.verify'))->getContent());
    }

    // ── settings ────────────────────────────────────────────────────────────

    public function test_saving_the_business_account_id_as_the_phone_number_id_is_refused_and_the_old_value_is_kept(): void
    {
        $this->set('whatsapp_waba_id', self::WABA);
        $this->provider();

        $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), ['whatsapp_enabled' => 1, 'whatsapp_phone_number_id' => self::WABA])
            ->assertSessionHasErrors('whatsapp_phone_number_id');

        $this->assertSame(self::PNID, SystemSetting::get('whatsapp', 'whatsapp_phone_number_id', null, $this->society->id));
        $this->assertSame([], $this->calls, 'a rejected save contacts nobody');
    }

    public function test_saving_keeps_the_masked_key_stores_a_new_one_encrypted_and_sends_no_message(): void
    {
        $this->provider();
        WhatsAppService::$transportOverride = function () { $this->calls[] = ['SEND']; return [[], 200]; };

        $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), ['whatsapp_enabled' => 1, 'whatsapp_api_key' => '', 'whatsapp_phone_number_id' => self::PNID, 'whatsapp_country_code' => '91'])
            ->assertSessionHasNoErrors();
        $this->assertSame(self::KEY, WhatsAppService::apiKey($this->society->id), 'an empty key field keeps the saved key');

        $this->actingAs($this->admin)->post(route('settings.whatsapp.update'), ['whatsapp_enabled' => 1, 'whatsapp_api_key' => 'new-key-0123456789abcdefghijZZ', 'whatsapp_phone_number_id' => self::PNID]);
        $this->assertSame('new-key-0123456789abcdefghijZZ', WhatsAppService::apiKey($this->society->id));
        $raw = (string) \Illuminate\Support\Facades\DB::table('system_settings')->where('key', 'whatsapp_api_key')->where('society_id', $this->society->id)->value('value');
        $this->assertStringNotContainsString('new-key-0123456789', $raw, 'stored encrypted');

        $this->assertNotContains(['SEND'], $this->calls, 'saving never sends a message');
    }

    public function test_the_settings_page_does_not_say_connected_without_a_provider_success_and_offers_verification(): void
    {
        $html = $this->actingAs($this->admin)->get('/settings?tab=whatsapp')->assertOk()->getContent();

        $this->assertStringContainsString('Verify configuration', $html);
        $this->assertStringContainsString('sends no message', $html);
        $this->assertStringNotContainsString('Connected', $html);
        $this->assertStringContainsString('Business Account (WABA) ID', $html);
    }

    // ── template sync ───────────────────────────────────────────────────────

    private function remote(string $name, string $status = 'APPROVED', array $over = []): array
    {
        return $over + ['template_id' => 'rid-' . $name, 'message_id' => (string) (30000 + strlen($name)), 'template_name' => $name, 'language' => 'en', 'status' => $status,
            'category' => 'UTILITY', 'var_count' => 1, 'phone_number_id' => self::PNID, 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']]];
    }

    private function listing(array $templates, string $pnid = self::PNID): void
    {
        Fast2SmsAccount::$transportOverride = function (string $method, string $url) use ($templates, $pnid) {
            $this->calls[] = [$method, parse_url($url, PHP_URL_PATH), parse_url($url, PHP_URL_QUERY)];

            return [['success' => true, 'data' => [['waba_id' => self::WABA, 'phone_number_id' => $pnid, 'templates' => $templates]]], 200];
        };
    }

    private function sync()
    {
        return $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'));
    }

    public function test_sync_is_idempotent_and_reports_added_updated_unchanged(): void
    {
        $this->listing([$this->remote('society_bill_generated'), $this->remote('society_visitor_arrived'), $this->remote('bookmizo_booking_confirm_v2')]);

        $this->sync()->assertSessionHas('success', fn ($m) => str_contains($m, '3 added, 0 updated, 0 unchanged'));
        $ids = WhatsAppTemplate::orderBy('id')->pluck('id', 'name')->all();

        $this->sync()->assertSessionHas('success', fn ($m) => str_contains($m, '0 added, 0 updated, 3 unchanged'));
        $this->assertSame($ids, WhatsAppTemplate::orderBy('id')->pluck('id', 'name')->all(), 'no duplicates, same ids');
        $this->assertSame(3, WhatsAppTemplate::count());
    }

    public function test_sync_attaches_only_approved_clearly_named_templates_and_never_switches_one_on(): void
    {
        $this->listing([
            $this->remote('society_bill_generated', 'APPROVED', ['var_count' => 4, 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}} bill for {{2}} Rs. {{3}} by {{4}}']]]),
            $this->remote('society_complaint_update', 'PENDING', ['var_count' => 5, 'components' => [['type' => 'BODY', 'text' => '{{1}} {{2}} {{3}} {{4}} {{5}}']]]),
            $this->remote('society_visitor_arrived', 'APPROVED', ['var_count' => 3, 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}} {{2}} {{3}}']]]),
            $this->remote('society_visitor_approved', 'APPROVED', ['var_count' => 4, 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}} {{2}} {{3}} {{4}}']]]),
            $this->remote('bookmizo_refund_full', 'APPROVED'),
            $this->remote('promo_diwali_offer', 'APPROVED'),
        ]);

        $this->sync()->assertSessionHas('success', fn ($m) => str_contains($m, '2 approved template(s) were attached'));

        $byName = WhatsAppTemplate::pluck('purpose', 'name')->all();
        $this->assertSame('bill', $byName['society_bill_generated']);
        $this->assertSame('visitor', $byName['society_visitor_approved']);
        $this->assertNull($byName['society_complaint_update'], 'pending: never attached');
        $this->assertNull($byName['society_visitor_arrived'], 'no sender for an arrival: not attached to the approval notification');
        $this->assertNull($byName['bookmizo_refund_full']);
        $this->assertNull($byName['promo_diwali_offer']);
        $this->assertSame(0, WhatsAppTemplate::where('is_active', true)->count(), 'nothing is switched on by a sync');
    }

    public function test_sync_never_overrides_an_existing_assignment_or_label_and_a_second_sync_leaves_it(): void
    {
        $mine = WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => 'rid-society_bill_generated', 'name' => 'society_bill_generated', 'friendly_name' => 'My bill', 'purpose' => 'payment',
            'status' => 'APPROVED', 'language' => 'en', 'var_count' => 1, 'body_text' => 'Hello {{1}}', 'is_active' => true, 'source' => 'synced']);
        $this->listing([$this->remote('society_bill_generated')]);

        $this->sync();
        $this->sync();

        $mine = $mine->fresh();
        $this->assertSame('payment', $mine->purpose, 'the administrator\'s assignment stands');
        $this->assertSame('My bill', $mine->friendly_name);
        $this->assertTrue($mine->is_active);
    }

    public function test_sync_with_a_wrong_saved_phone_number_id_still_lists_the_templates_and_says_what_is_wrong(): void
    {
        $this->set('whatsapp_phone_number_id', self::WABA);   // the wrong id
        $this->listing([$this->remote('society_bill_generated')]);

        $response = $this->sync()->assertSessionHas('success');

        $this->assertSame(1, WhatsAppTemplate::count(), 'the list belongs to the account, not to the saved id');
        $response->assertSessionHas('sync_warnings', fn ($w) => str_contains(implode(' ', $w), 'not a WhatsApp number of this account') && str_contains(implode(' ', $w), self::PNID));
    }

    public function test_with_a_valid_saved_id_another_numbers_templates_are_skipped_and_counted(): void
    {
        Fast2SmsAccount::$transportOverride = function () {
            return [['success' => true, 'data' => [
                ['waba_id' => self::WABA, 'phone_number_id' => self::PNID, 'templates' => [$this->remote('society_bill_generated')]],
                ['waba_id' => self::WABA, 'phone_number_id' => '777000111222333', 'templates' => [$this->remote('other_number_template', 'APPROVED', ['phone_number_id' => '777000111222333'])]],
            ]], 200];
        };

        $this->sync()->assertSessionHas('success', fn ($m) => str_contains($m, '1 added') && str_contains($m, '1 skipped'));

        $this->assertSame(['society_bill_generated'], WhatsAppTemplate::pluck('name')->all());
    }

    public function test_a_failed_sync_changes_nothing_and_is_not_called_a_success(): void
    {
        WhatsAppTemplate::create(['society_id' => $this->society->id, 'external_id' => 'x', 'name' => 'society_bill_generated', 'status' => 'APPROVED', 'source' => 'synced']);
        Fast2SmsAccount::$transportOverride = fn () => [$this->meta100(), 200];

        $this->sync()->assertSessionHas('error')->assertSessionMissing('success');

        $this->assertSame(1, WhatsAppTemplate::count());
        $this->assertSame('APPROVED', WhatsAppTemplate::first()->status);
    }

    public function test_the_assigned_template_shows_its_notification_and_activation_survives_a_reload(): void
    {
        $this->listing([$this->remote('society_bill_generated', 'APPROVED', ['var_count' => 4, 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}} bill for {{2}} Rs. {{3}} by {{4}}']]])]);
        $this->sync();
        $t = WhatsAppTemplate::firstOrFail();

        $before = $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->getContent();
        $this->assertStringContainsString('New bill', $before, 'the notification it is attached to');
        $this->assertStringNotContainsString('Not assigned', $before);
        $this->assertStringContainsString('In app: Inactive', $before);

        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.toggle', $t))->assertSessionHas('success');

        $after = $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp']))->getContent();
        $this->assertTrue($t->fresh()->is_active);
        $this->assertStringContainsString('In app: Active', $after);
        $this->assertStringContainsString('{{1}} Resident name · {{2}} Bill month · {{3}} Amount (number only, no symbol) · {{4}} Due date', $after);
    }

    public function test_the_purpose_suggestion_is_conservative(): void
    {
        $this->assertSame('bill', WhatsAppTemplateMapping::suggestPurpose('society_bill_generated', 'a {{1}} b {{2}} c {{3}} d {{4}}'));
        $this->assertSame('notice', WhatsAppTemplateMapping::suggestPurpose('society_important_notice', '{{1}} {{2}} {{3}}'));
        $this->assertSame('event', WhatsAppTemplateMapping::suggestPurpose('society_event_announcement', '{{1}}'));
        $this->assertNull(WhatsAppTemplateMapping::suggestPurpose('society_visitor_arrived'));
        $this->assertNull(WhatsAppTemplateMapping::suggestPurpose('payment_completed'), 'a generic name says nothing about whose it is');
        $this->assertNull(WhatsAppTemplateMapping::suggestPurpose('bookmizo_booking_confirm_full'));
        $this->assertNull(WhatsAppTemplateMapping::suggestPurpose('society_visitor_approved', '{{1}} {{2}} {{3}} {{4}} {{5}} {{6}}'), 'more variables than the notification can fill');
    }
}
