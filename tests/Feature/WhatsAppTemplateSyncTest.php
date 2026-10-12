<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `WhatsAppTemplateSyncService` calls the real, documented Fast2SMS
 * endpoint (docs.fast2sms.com/reference/get-all-templates) - **not
 * verified against a live account**, no WhatsApp Business Account ID or
 * API key is available in this environment. `$transportOverride` (the
 * same test seam `WhatsAppService`/`SmsService` already use) stands in
 * for the real HTTP call, returning a response shaped exactly like that
 * documented endpoint.
 */
class WhatsAppTemplateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        WhatsAppTemplateSyncService::$transportOverride = null;
        parent::tearDown();
    }

    private function configureWaba($society): void
    {
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'TEST-KEY', 'encrypted', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_waba_id', '1029384756', 'string', $society->id);
    }

    public function test_sync_populates_templates_from_the_documented_response_shape(): void
    {
        $society = $this->makeSociety();
        $this->configureWaba($society);

        WhatsAppTemplateSyncService::$transportOverride = fn () => [[
            'data' => [
                ['id' => 'tpl_1', 'name' => 'order_update_v2', 'status' => 'APPROVED', 'language' => 'en_US', 'category' => 'UTILITY'],
                ['id' => 'tpl_2', 'name' => 'otp_auth', 'status' => 'PENDING', 'language' => 'en_US', 'category' => 'AUTHENTICATION'],
            ],
        ], 200];

        $result = WhatsAppTemplateSyncService::sync($society->id);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['count']);
        $this->assertDatabaseHas('whatsapp_templates', ['external_id' => 'tpl_1', 'status' => 'APPROVED', 'society_id' => $society->id]);
        $this->assertDatabaseHas('whatsapp_templates', ['external_id' => 'tpl_2', 'status' => 'PENDING']);
    }

    public function test_sync_through_the_account_client_stores_message_id_variables_and_body_and_filters_by_number(): void
    {
        config(['messaging.enabled' => true]);
        $society = $this->makeSociety();
        $this->configureWaba($society);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $society->id);

        $urls = [];
        \App\Services\Fast2SmsAccount::$transportOverride = function (string $method, string $url) use (&$urls) {
            $urls[] = [$method, $url];

            return [['success' => true, 'data' => [[
                'waba_id' => 'w1', 'phone_number_id' => '1209719742234591',
                'templates' => [
                    ['message_id' => 8340, 'template_id' => '2012345678901234', 'template_name' => 'visitor_arrived', 'phone_number_id' => '1209719742234591', 'category' => 'UTILITY', 'status' => 'APPROVED', 'language' => 'en', 'var_count' => 2,
                     'components' => [['type' => 'HEADER', 'text' => 'ignored'], ['type' => 'BODY', 'text' => 'Hi {{1}}, {{2}} is at the gate']]],
                    // var_count absent: counted from the body instead of showing 0
                    ['message_id' => 8341, 'template_id' => '2012345678905555', 'template_name' => 'bill_due', 'phone_number_id' => '1209719742234591', 'category' => 'UTILITY', 'status' => 'APPROVED', 'language' => 'en',
                     'components' => [['type' => 'BODY', 'text' => 'Bill {{1}} due {{2}} amount {{3}}']]],
                ],
            ]]], 200];
        };

        try {
            $result = WhatsAppTemplateSyncService::sync($society->id);
        } finally {
            \App\Services\Fast2SmsAccount::$transportOverride = null;
        }

        $this->assertTrue($result['success']);
        $this->assertSame('GET', $urls[0][0]);
        $this->assertStringContainsString('/dev/dlt_manager/whatsapp?type=template&phone_number_id=1209719742234591', $urls[0][1]);
        $this->assertDatabaseHas('whatsapp_templates', ['society_id' => $society->id, 'name' => 'visitor_arrived', 'message_id' => '8340', 'external_id' => '2012345678901234', 'var_count' => 2, 'body_text' => 'Hi {{1}}, {{2}} is at the gate', 'category' => 'UTILITY', 'language' => 'en', 'status' => 'APPROVED']);
        $this->assertDatabaseHas('whatsapp_templates', ['name' => 'bill_due', 'message_id' => '8341', 'var_count' => 3]);
    }

    public function test_friendly_name_defaults_to_a_humanized_technical_name(): void
    {
        $society = $this->makeSociety();
        $this->configureWaba($society);

        WhatsAppTemplateSyncService::$transportOverride = fn () => [[
            'data' => [['id' => 'tpl_1', 'name' => 'order_update_v2', 'status' => 'APPROVED']],
        ], 200];

        WhatsAppTemplateSyncService::sync($society->id);

        $template = WhatsAppTemplate::where('external_id', 'tpl_1')->first();
        $this->assertSame('Order Update V2', $template->displayName());
    }

    public function test_re_syncing_never_overwrites_an_admins_own_friendly_name(): void
    {
        $society = $this->makeSociety();
        $this->configureWaba($society);
        $response = fn () => [['data' => [['id' => 'tpl_1', 'name' => 'order_update_v2', 'status' => 'APPROVED']]], 200];
        WhatsAppTemplateSyncService::$transportOverride = $response;

        WhatsAppTemplateSyncService::sync($society->id);
        WhatsAppTemplate::where('external_id', 'tpl_1')->update(['friendly_name' => 'Order Confirmation']);

        WhatsAppTemplateSyncService::sync($society->id); // re-sync, same remote data

        $template = WhatsAppTemplate::where('external_id', 'tpl_1')->first();
        $this->assertSame('Order Confirmation', $template->displayName());
    }

    public function test_sync_fails_honestly_when_the_api_key_is_not_configured(): void
    {
        // The sync now uses Fast2SMS's lookup that needs only the API key
        // (no WhatsApp Business Account ID), so the key is the precondition.
        $society = $this->makeSociety();
        $called = false;
        WhatsAppTemplateSyncService::$transportOverride = function () use (&$called) { $called = true; return [[], 500]; };

        $result = WhatsAppTemplateSyncService::sync($society->id);

        $this->assertFalse($result['success']);
        $this->assertFalse($called, 'no provider request without a key');
        $this->assertDatabaseCount('whatsapp_templates', 0);
    }

    public function test_the_technical_external_id_never_appears_on_the_settings_page(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $this->configureWaba($society);

        WhatsAppTemplateSyncService::$transportOverride = fn () => [[
            'data' => [['id' => 'tpl_super_secret_meta_id_123', 'name' => 'order_update_v2', 'status' => 'APPROVED']],
        ], 200];
        WhatsAppTemplateSyncService::sync($society->id);

        $html = $this->actingAs($admin)->get('/settings?tab=whatsapp')->assertOk()->getContent();

        $this->assertStringNotContainsString('tpl_super_secret_meta_id_123', $html);
        $this->assertStringContainsString('Order Update V2', $html);
    }

    public function test_using_a_template_is_scoped_to_the_acting_admins_society(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        $adminB = $this->makeUserWithRole($societyB, 'Admin');

        $template = WhatsAppTemplate::create([
            'society_id' => $societyA->id,
            'external_id' => 'tpl_1',
            'name' => 'order_update_v2',
            'status' => 'APPROVED',
            'var_count' => 1,
        ]);

        $this->actingAs($adminB)
            ->postJson(route('settings.whatsapp.assign'), ['purpose' => 'payment', 'template_id' => $template->id, 'variables' => ['amount']])
            ->assertOk()->assertJson(['success' => false]);

        $this->assertNull(SystemSetting::where('society_id', $societyB->id)->where('key', 'whatsapp_payment_template_name')->value('value'));
    }
}
