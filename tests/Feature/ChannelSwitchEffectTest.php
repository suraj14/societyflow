<?php

namespace Tests\Feature;

use App\Events\NoticePublished;
use App\Models\Notice;
use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A channel switch saved in Settings must change what the next action does, per society, in both directions. External transports are test
 * doubles standing behind the global switch and allow-list (which are on here and unrestricted), so this proves application behaviour only -
 * not that Fast2SMS / WhatsApp would accept or deliver anything.
 */
class ChannelSwitchEffectTest extends TestCase
{
    use RefreshDatabase;

    private array $societies = [];
    private array $sms = [];
    private array $wa = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['messaging.enabled' => true, 'messaging.allowlist' => null]);
        foreach (['A' => '9811111111', 'B' => '9822222222'] as $key => $phone) {
            $society = $this->makeSociety("Society $key");
            $owner = $this->makeUserWithRole($society, 'Owner', ['phone' => $phone]);
            SystemSetting::set('sms', 'sms_enabled', true, 'boolean', $society->id);
            SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $society->id);
            SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $society->id);
            SystemSetting::set('sms', 'fast2sms_sender_id', 'SAIDIG', 'string', $society->id);
            SmsTemplate::create(['society_id' => $society->id, 'name' => 'notice', 'purpose' => 'notice', 'audience' => 'resident', 'dlt_message_id' => '228199', 'is_active' => true,
                'body' => 'SocietyFlow: New notice {{notice_title}} published by your society. Please check the SocietyFlow app for details. Thank you.']);
            SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $society->id);
            SystemSetting::set('whatsapp', 'whatsapp_api_key', 'WA-KEY-0123456789abcdefghij', 'encrypted', $society->id);
            SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $society->id);
            WhatsAppTemplate::create(['society_id' => $society->id, 'external_id' => "ext-$key", 'message_id' => '37332', 'name' => 'society_important_notice', 'status' => 'APPROVED', 'language' => 'en',
                'phone_number_id' => '1209719742234591', 'var_count' => 3, 'purpose' => 'notice', 'is_active' => true, 'source' => 'synced',
                'body_text' => 'Hello {{1}}, Important notice from {{2}}: {{3}}. Please check the SocietyFlow app for full details. Thank you.']);
            $this->societies[$key] = [$society, $owner];
        }
        SmsService::$transportOverride = function (string $url) { parse_str((string) parse_url($url, PHP_URL_QUERY), $q); $this->sms[] = $q['numbers'] ?? '?'; return [['return' => true, 'request_id' => 'r'], 200]; };
        WhatsAppService::$transportOverride = function (string $url) { parse_str((string) parse_url($url, PHP_URL_QUERY), $q); $this->wa[] = substr((string) ($q['numbers'] ?? '?'), -10); return [['return' => true, 'message' => ['sent'], 'request_id' => 'r'], 200]; };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function publish(string $key, string $title): void
    {
        [$society, $owner] = $this->societies[$key];
        $this->sms = [];
        $this->wa = [];
        \Illuminate\Support\Facades\Cache::flush();   // the duplicate window of an earlier identical send
        $notice = Notice::create(['society_id' => $society->id, 'created_by' => $owner->id, 'title' => $title, 'content' => 'x', 'type' => 'general', 'priority' => 'medium',
            'status' => 'published', 'publish_date' => now(), 'send_email' => false, 'send_sms' => true]);
        NoticePublished::dispatch($notice);
    }

    private function set(string $key, string $group, string $name, bool $value): void
    {
        SystemSetting::set($group, $name, $value, 'boolean', $this->societies[$key][0]->id);
    }

    public function test_sms_switch_off_sends_nothing_on_for_that_society_only_then_resumes_and_stops_again(): void
    {
        $this->publish('A', 'First');
        $this->assertSame(['9811111111'], $this->sms, 'ON: the correct society\'s owner only');

        $this->set('A', 'sms', 'notification_sms_enabled', false);
        $this->publish('A', 'Second');
        $this->assertSame([], $this->sms, 'OFF: no external request');
        $this->publish('B', 'Second B');
        $this->assertSame(['9822222222'], $this->sms, 'society B kept its own setting');

        $this->set('A', 'sms', 'notification_sms_enabled', true);
        $this->publish('A', 'Third');
        $this->assertSame(['9811111111'], $this->sms, 'ON again');

        $this->set('A', 'sms', 'sms_enabled', false);
        $this->publish('A', 'Fourth');
        $this->assertSame([], $this->sms, 'master SMS switch off stops it again');
    }

    public function test_whatsapp_switch_off_sends_nothing_on_for_that_society_only_then_resumes(): void
    {
        $this->publish('A', 'First');
        $this->assertSame(['9811111111'], $this->wa, 'ON: approved SocietyFlow notice template, owner of this society');

        $this->set('A', 'whatsapp', 'whatsapp_enabled', false);
        $this->publish('A', 'Second');
        $this->assertSame([], $this->wa, 'OFF: no request');
        $this->publish('B', 'Second B');
        $this->assertSame(['9822222222'], $this->wa);

        $this->set('A', 'whatsapp', 'whatsapp_enabled', true);
        $this->publish('A', 'Third');
        $this->assertSame(['9811111111'], $this->wa);
    }

    public function test_deactivating_the_template_stops_the_channel_without_falling_back_to_anything_else(): void
    {
        WhatsAppTemplate::where('society_id', $this->societies['A'][0]->id)->update(['is_active' => false]);
        SmsTemplate::where('society_id', $this->societies['A'][0]->id)->update(['is_active' => false]);

        $this->publish('A', 'No templates');

        $this->assertSame([], $this->wa, 'no active template: no WhatsApp, and no other product\'s template is used');
        $this->assertSame([], $this->sms, 'no active SMS template: no free-text fallback');
    }
}
