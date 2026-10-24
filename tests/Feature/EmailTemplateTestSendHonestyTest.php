<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The template "send test" button must not log or report a send that the kill switch suppressed. */
class EmailTemplateTestSendHonestyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_template_test_button_reports_not_sent_and_writes_no_sent_row_when_messaging_is_off(): void
    {
        config(['messaging.enabled' => false]);
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $t = EmailTemplate::create(['society_id' => $society->id, 'name' => 'Notice', 'slug' => 'notice', 'subject' => 'S', 'body' => 'B', 'type' => 'system', 'status' => 'active', 'trigger_event' => 'notice_published']);

        $r = $this->actingAs($admin)->postJson(route('admin.email-templates.test', $t), ['test_email' => 'someone@example.invalid']);

        $r->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('Not sent', (string) $r->json('message'));
        $this->assertSame(0, EmailLog::where('trigger_action', 'test_email')->count());
    }

    public function test_another_societys_template_cannot_be_used(): void
    {
        config(['messaging.enabled' => false]);
        $a = $this->makeSociety('A');
        $b = $this->makeSociety('B');
        $admin = $this->makeUserWithRole($a, 'Admin');
        $t = EmailTemplate::create(['society_id' => $b->id, 'name' => 'N', 'slug' => 'n', 'subject' => 'S', 'body' => 'B', 'type' => 'system', 'status' => 'active', 'trigger_event' => 'notice_published']);

        $this->actingAs($admin)->postJson(route('admin.email-templates.test', $t), ['test_email' => 'x@example.invalid'])->assertForbidden();
    }
}
