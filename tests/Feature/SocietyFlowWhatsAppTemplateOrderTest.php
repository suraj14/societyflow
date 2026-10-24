<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use App\Support\WhatsAppTemplateMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The six SocietyFlow WhatsApp templates exactly as they are saved for Royal Eden (wording copied from the synced records): with NO variable
 * map saved, each {{n}} must still be filled with the value its own wording asks for ("Important notice from {{2}}: {{3}}" is the society
 * and then the title, not the other way round). Nothing is sent: the transport is a mock.
 */
class SocietyFlowWhatsAppTemplateOrderTest extends TestCase
{
    use RefreshDatabase;

    /** purpose => [template name, saved body, expected field for {{1}}, {{2}}, ...] */
    private const TEMPLATES = [
        'event' => ['society_event_announcement', 'Hello {{1}}, {{2}} invites you to {{3}} on {{4}} at {{5}}. Please check the SocietyFlow app for event details. We hope to see you there.',
            ['resident_name', 'society_name', 'event_name', 'event_day', 'event_time']],
        'notice' => ['society_important_notice', 'Hello {{1}}, Important notice from {{2}}: {{3}}. Please check the SocietyFlow app for full details. Thank you.',
            ['resident_name', 'society_name', 'notice_title']],
        'complaint' => ['society_complaint_update', 'Hello {{1}}, Your complaint #{{2}} status is now {{3}}. Update: {{4}}. Regards, {{5}}. Please check SocietyFlow for details. Thank you.',
            ['resident_name', 'complaint_ref', 'complaint_status', 'complaint_update', 'society_name']],
        'bill' => ['society_bill_generated', 'Hello {{1}}, Your society bill for {{2}} is Rs. {{3}}. Please pay by {{4}}. Open SocietyFlow for details. Thank you.',
            ['resident_name', 'bill_period', 'amount_number', 'due_date']],
        'visitor_arrival' => ['society_visitor_arrived', 'Hello {{1}}, Your visitor {{2}} has arrived at {{3}}. Please contact the security gate to confirm entry. Thank you.',
            ['resident_name', 'visitor_name', 'visit_date']],
        'visitor' => ['society_visitor_approved', 'Hello {{1}}, Your visitor {{2}} has been approved for entry at {{3}}. Please follow society security guidelines. Contact security if needed: {{4}}. Thank you.',
            ['resident_name', 'visitor_name', 'visit_date', 'society_phone']],
    ];

    public function test_each_societyflow_template_maps_its_variables_by_its_own_wording_when_nothing_is_saved(): void
    {
        foreach (self::TEMPLATES as $purpose => [$name, $body, $expected]) {
            $count = count(WhatsAppTemplate::placeholderNumbers($body));
            $this->assertSame($expected, WhatsAppTemplateMapping::suggest($purpose, $body, $count), "$name ($purpose)");
        }
    }

    public function test_a_template_without_a_saved_map_still_sends_the_values_in_the_order_its_wording_asks_for(): void
    {
        $society = $this->makeSociety('Royal Eden');
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'WA-KEY-0123456789abcdefghij', 'encrypted', $society->id);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '1209719742234591', 'string', $society->id);
        [$name, $body] = self::TEMPLATES['notice'];
        WhatsAppTemplate::create(['society_id' => $society->id, 'external_id' => '1088260420753201', 'message_id' => '37332', 'name' => $name, 'status' => 'APPROVED', 'language' => 'en',
            'phone_number_id' => '1209719742234591', 'var_count' => 3, 'body_text' => $body, 'purpose' => 'notice', 'is_active' => true, 'source' => 'synced']);
        $sent = [];
        WhatsAppService::$transportOverride = function (string $url) use (&$sent) { parse_str((string) parse_url($url, PHP_URL_QUERY), $q); $sent[] = $q; return [['return' => true, 'message' => ['sent'], 'request_id' => 'r1'], 200]; };

        try {
            $r = WhatsAppService::sendNotification('notice', '9887313518', ['resident_name' => 'Asha Rao', 'notice_title' => 'Water supply off 2-4 PM', 'society_name' => 'Royal Eden'], $society->id);
        } finally {
            WhatsAppService::$transportOverride = null;
        }

        $this->assertTrue($r['success']);
        $this->assertCount(1, $sent);
        $this->assertSame('37332', $sent[0]['message_id']);
        $this->assertSame('1209719742234591', $sent[0]['phone_number_id']);
        $this->assertSame('Asha Rao|Royal Eden|Water supply off 2-4 PM', $sent[0]['variables_values'], '{{1}} resident, {{2}} society, {{3}} title - the order the wording asks for');
        $this->assertSame('919887313518', $sent[0]['numbers']);
    }
}
