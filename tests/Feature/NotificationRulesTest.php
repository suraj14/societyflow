<?php

namespace Tests\Feature;

use App\Jobs\DispatchEventNotification;
use App\Support\NotificationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The per-event on/off rule store (`NotificationRules`), enforced centrally in
 * `DispatchEventNotification::handle()`. Opt-out by design (see that
 * class's docblock): every event/channel is enabled until a row says
 * otherwise, so introducing this table never silently stops a
 * notification that was already working.
 */
class NotificationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_event_with_no_rule_row_defaults_to_enabled(): void
    {
        $society = $this->makeSociety();

        $this->assertTrue(NotificationRules::isEnabled('notice_published', 'email', $society->id));
    }

    public function test_disabling_a_channel_actually_stops_the_job_from_sending(): void
    {
        $society = $this->makeSociety();
        NotificationRules::setEnabled('bill_generated', 'email', false, $society->id);

        $job = new DispatchEventNotification(
            'bill_generated',
            ['email' => 'owner@test.local', 'name' => 'Owner', 'role' => 'Owner'],
            ['user_name' => 'Owner', 'society_name' => $society->name],
            $society->id,
            'bill_generated:test:owner@test.local',
        );
        $job->handle();

        $this->assertDatabaseMissing('email_logs', ['notification_key' => 'bill_generated:test:owner@test.local']);
    }

    public function test_a_rule_disabled_for_one_society_does_not_affect_another(): void
    {
        $societyA = $this->makeSociety('Society A');
        $societyB = $this->makeSociety('Society B');
        NotificationRules::setEnabled('bill_generated', 'email', false, $societyA->id);

        $this->assertFalse(NotificationRules::isEnabled('bill_generated', 'email', $societyA->id));
        $this->assertTrue(NotificationRules::isEnabled('bill_generated', 'email', $societyB->id));
    }

    /*
     * The Communication Center screen (business groups, per-event rule
     * toggles, its own Test button) was removed on 2026-10-08 at the
     * product owner's request: managers configure Email / SMS / WhatsApp
     * once in Settings and never see individual events. The rule store
     * above is still enforced for rows saved earlier. The tests below pin
     * the replacement behaviour; the per-channel Settings flows are covered
     * by CommunicationSettingsTest.
     */

    public function test_the_old_communication_center_url_lands_on_settings(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        $this->actingAs($admin)->get(route('admin.communication-center.index'))
            ->assertRedirect('/settings?tab=email');
    }

    public function test_a_resident_cannot_reach_the_communication_center(): void
    {
        $society = $this->makeSociety();
        $resident = $this->makeUserWithRole($society, 'Tenant');

        $this->actingAs($resident)->get(route('admin.communication-center.index'))->assertForbidden();
    }

    public function test_settings_pages_show_no_technical_event_configuration(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');

        foreach (['email', 'sms', 'whatsapp'] as $tab) {
            $html = $this->actingAs($admin)->get("/settings?tab={$tab}")->assertOk()->getContent();

            foreach (['access_pass_revoked', 'notice_published', 'SendVisitorApprovedEmail', 'DispatchEventNotification', 'Communication Center', 'Rules &amp; Notifications', 'rule_'] as $technical) {
                $this->assertStringNotContainsString($technical, $html, "'{$technical}' is visible on Settings ({$tab}).");
            }
        }
    }

    public function test_a_rule_saved_before_the_screen_was_removed_is_still_honoured(): void
    {
        $society = $this->makeSociety();
        NotificationRules::setEnabled('access_pass_revoked', 'sms', false, $society->id);

        $this->assertFalse(NotificationRules::isEnabled('access_pass_revoked', 'sms', $society->id));
        $this->assertTrue(NotificationRules::isEnabled('access_pass_revoked', 'email', $society->id));
    }
}
