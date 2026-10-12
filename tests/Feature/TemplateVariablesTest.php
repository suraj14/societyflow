<?php

namespace Tests\Feature;

use App\Models\SmsTemplate;
use App\Services\Fast2SmsService;
use App\Support\TemplateVariables;
use App\Support\WhatsAppPurposes;
use Tests\TestCase;

/**
 * The one variable registry: what each SMS context can fill, how a body is checked, and how it is rendered.
 */
class TemplateVariablesTest extends TestCase
{
    public function test_the_sms_contexts_are_exactly_the_flows_that_really_send_sms(): void
    {
        $this->assertSame(['login_otp', 'notice', 'gate_pass', 'document'], array_keys(TemplateVariables::sms()));
        $this->assertSame(['login_otp' => 'Login OTP', 'notice' => 'Notice published', 'gate_pass' => 'Gate pass revoked', 'document' => 'Society document published'], TemplateVariables::smsLabels());
    }

    public function test_the_notification_variables_are_the_fields_the_whatsapp_events_already_supply(): void
    {
        $wa = WhatsAppPurposes::all();

        $this->assertSame($wa['notice']['fields'], TemplateVariables::sms()['notice']['variables']);
        $this->assertSame($wa['access_pass']['fields'], TemplateVariables::sms()['gate_pass']['variables']);
        $this->assertSame($wa['document']['fields'], TemplateVariables::sms()['document']['variables']);
        $this->assertSame(['otp', 'society_name'], array_keys(TemplateVariables::forSms('login_otp')));
        $this->assertSame([], TemplateVariables::forSms('new_bill'), 'no invented context');
    }

    public function test_every_variable_has_a_description_and_a_preview_value(): void
    {
        foreach (TemplateVariables::sms() as $purpose => $p) {
            foreach ($p['variables'] as $name => $description) {
                $this->assertNotSame('', trim($description), "{$purpose}.{$name} has a description");
                $this->assertNotSame('', TemplateVariables::sample($name), "{$purpose}.{$name} has a preview value");
            }
        }
        $this->assertSame('4821', TemplateVariables::sample('otp'));
    }

    public function test_placeholders_are_read_in_order_with_repeats(): void
    {
        $this->assertSame(['society_name', 'notice_title', 'society_name'], TemplateVariables::placeholders('{{society_name}}: {{ notice_title }} - {{society_name}}'));
        $this->assertSame([], TemplateVariables::placeholders('No variables here'));
    }

    public function test_a_valid_body_has_no_problems(): void
    {
        $this->assertSame([], TemplateVariables::problems('notice', 'Notice: {{notice_title}} from {{society_name}}'));
        $this->assertSame([], TemplateVariables::problems('notice', 'A notice with no variable at all'));
        $this->assertSame([], TemplateVariables::problems('login_otp', 'Your code is {{otp}}'));
    }

    public function test_problems_are_plain_sentences(): void
    {
        $unknown = TemplateVariables::problems('notice', 'Bill {{amount}} {{amount}} {{notice_title}}');
        $this->assertCount(1, $unknown);
        $this->assertStringContainsString('Not available for "Notice published": {{amount}}', $unknown[0]);
        $this->assertStringContainsString('You can use: {{resident_name}}', $unknown[0]);

        $this->assertStringContainsString('not written correctly', TemplateVariables::problems('notice', 'Broken {{notice_title}')[0]);
        $this->assertStringContainsString('must include {{otp}}', TemplateVariables::problems('login_otp', 'No code here')[0]);
        $this->assertSame(['Choose what this message is for.'], TemplateVariables::problems('nonsense', 'x'));
    }

    public function test_rendering_fills_each_placeholder_in_order_and_reports_what_is_missing(): void
    {
        $r = Fast2SmsService::render('Hi {{resident_name}}, {{notice_title}} - {{society_name}}', ['society_name' => 'Alpha', 'resident_name' => 'Asha', 'notice_title' => 'Water cut']);
        $this->assertSame('Hi Asha, Water cut - Alpha', $r['text']);
        $this->assertSame(['Asha', 'Water cut', 'Alpha'], $r['variables'], 'variables_values follow the order in the body, not the order given');
        $this->assertSame([], $r['missing']);

        $m = Fast2SmsService::render('{{resident_name}} {{notice_title}} {{society_name}}', ['resident_name' => 'Asha', 'notice_title' => '   ']);
        $this->assertSame(['notice_title', 'society_name'], $m['missing'], 'blank and absent values are both missing');
    }

    public function test_a_repeated_variable_fills_two_dlt_slots(): void
    {
        $r = Fast2SmsService::render('{{society_name}} / {{society_name}}', ['society_name' => 'Alpha']);

        $this->assertSame(['Alpha', 'Alpha'], $r['variables']);
        $this->assertSame(2, (new SmsTemplate(['body' => '{{society_name}} / {{society_name}}']))->variableCount());
    }

    public function test_a_pipe_inside_a_value_cannot_split_it(): void
    {
        $this->assertSame('Block A/B|12 Nov', Fast2SmsService::pipe(['Block A|B', '12 Nov']));
    }
}
