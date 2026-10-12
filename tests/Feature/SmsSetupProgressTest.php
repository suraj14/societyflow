<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Support\ChannelStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings -> SMS / OTP: honest, distinct states. Saving a key never claims a connection; "Provider check passed"
 * appears only after a real recorded result, and while external messaging is off the page says so in one short line.
 * Another society never sees this society's state. A rejected save explains itself.
 */
class SmsSetupProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_key_is_not_shown_as_verified_and_the_kill_switch_is_explained(): void
    {
        $s = $this->makeSociety('Alpha');
        $admin = $this->makeUserWithRole($s, 'Admin');
        SystemSetting::set('sms', 'fast2sms_api_key', 'a-secret-key-value', 'encrypted', $s->id);
        SystemSetting::set('sms', 'fast2sms_route', 'otp', 'string', $s->id);
        SystemSetting::set('sms', 'fast2sms_otp_id', '6fd92db0ae', 'string', $s->id);

        $page = $this->actingAs($admin)->get('/settings?tab=sms')->assertOk();

        $page->assertSee('Real SMS delivery is switched off on this server')
            ->assertDontSee('Provider check passed')
            ->assertDontSee('not verified')
            ->assertDontSee('a-secret-key-value');
    }

    public function test_a_real_recorded_check_shows_as_a_provider_check(): void
    {
        config(['messaging.enabled' => true]);
        $s = $this->makeSociety('Alpha');
        $admin = $this->makeUserWithRole($s, 'Admin');
        SystemSetting::set('sms', 'fast2sms_api_key', 'k', 'encrypted', $s->id);
        SystemSetting::set('sms', 'fast2sms_route', 'otp', 'string', $s->id);
        SystemSetting::set('sms', 'fast2sms_otp_id', '6fd92db0ae', 'string', $s->id);
        ChannelStatus::record('sms', $s->id, true, 'Test SMS accepted for ******3210');

        $page = $this->actingAs($admin)->get('/settings?tab=sms')->assertOk();

        $page->assertSee('Provider check passed')->assertDontSee('Real SMS delivery is switched off');
        $page->assertDontSee('Delivered', false);
    }

    public function test_a_failed_test_shows_fast2smss_exact_words_on_the_page(): void
    {
        config(['messaging.enabled' => true]);
        $s = $this->makeSociety('Alpha');
        $admin = $this->makeUserWithRole($s, 'Admin');
        SystemSetting::set('sms', 'fast2sms_api_key', 'k', 'encrypted', $s->id);
        ChannelStatus::record('sms', $s->id, false, 'Test SMS failed', 'Invalid Authorization key');

        $this->actingAs($admin)->get('/settings?tab=sms')->assertOk()->assertSee('Test SMS failed')->assertSee('Invalid Authorization key');
    }

    public function test_another_societys_admin_does_not_see_this_societys_state(): void
    {
        $a = $this->makeSociety('Alpha');
        $b = $this->makeSociety('Beta');
        SystemSetting::set('sms', 'fast2sms_api_key', 'k', 'encrypted', $a->id);
        ChannelStatus::record('sms', $a->id, true, 'Test SMS accepted for ******3210');

        $page = $this->actingAs($this->makeUserWithRole($b, 'Admin'))->get('/settings?tab=sms')->assertOk();

        $page->assertDontSee('Provider check passed')->assertSee('SMS not configured');
    }

    public function test_a_rejected_save_shows_why_and_keeps_the_stored_values(): void
    {
        $s = $this->makeSociety('Alpha');
        $admin = $this->makeUserWithRole($s, 'Admin');
        SystemSetting::set('sms', 'fast2sms_sender_id', 'GOODID', 'string', $s->id);

        $this->actingAs($admin)->from('/settings?tab=sms')->post(route('settings.sms.update'), ['sms_provider' => 'fast2sms', 'fast2sms_sender_id' => str_repeat('X', 40)])
            ->assertRedirect('/settings?tab=sms')->assertSessionHasErrors('fast2sms_sender_id');

        // Found in the browser: the error was flashed but no settings tab rendered it.
        $this->actingAs($admin)->followingRedirects()->from('/settings?tab=sms')->post(route('settings.sms.update'), ['sms_provider' => 'fast2sms', 'fast2sms_sender_id' => str_repeat('X', 40)])
            ->assertSee('Your changes were not saved')->assertSee('role="alert"', false)->assertSee('Sender ID must be 3 to 6 letters');
        $this->assertSame('GOODID', SystemSetting::get('sms', 'fast2sms_sender_id', null, $s->id), 'a rejected save changes nothing');
    }
}
