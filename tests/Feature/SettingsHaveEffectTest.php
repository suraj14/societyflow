<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\SystemSetting;
use App\Services\DynamicEmailService;
use App\Support\RecipientEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Settings that used to be saved and ignored now change behaviour - per society, and reversibly. */
class SettingsHaveEffectTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $password): void
    {
        $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    public function test_the_login_attempt_limit_of_the_society_locks_the_form_sooner_and_leaves_other_societies_alone(): void
    {
        $a = $this->makeSociety('Strict');
        $b = $this->makeSociety('Default');
        $ua = $this->makeUserWithRole($a, 'Owner', ['password' => bcrypt('Right-pass-1!x')]);
        $ub = $this->makeUserWithRole($b, 'Owner', ['password' => bcrypt('Right-pass-1!x')]);
        SystemSetting::set('security', 'login_attempt_limit', 3, 'integer', $a->id);

        for ($i = 0; $i < 3; $i++) {
            $this->attempt($ua->email, 'wrong');
            $this->attempt($ub->email, 'wrong');
        }

        $this->post('/login', ['email' => $ua->email, 'password' => 'Right-pass-1!x'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $ub->email, 'password' => 'Right-pass-1!x']);
        $this->assertAuthenticatedAs($ub);   // 3 failures are under the platform's 5
    }

    public function test_an_unset_or_out_of_range_limit_falls_back_to_the_platform_default(): void
    {
        $a = $this->makeSociety('Odd');
        $u = $this->makeUserWithRole($a, 'Owner', ['password' => bcrypt('Right-pass-1!x')]);
        SystemSetting::set('security', 'login_attempt_limit', 1, 'integer', $a->id);   // below the 3 minimum

        $this->attempt($u->email, 'wrong');
        $this->attempt($u->email, 'wrong');

        $this->post('/login', ['email' => $u->email, 'password' => 'Right-pass-1!x']);
        $this->assertAuthenticatedAs($u);
    }

    public function test_the_email_master_switch_stops_society_mail_for_that_society_only_and_not_account_mail(): void
    {
        Mail::fake();
        $a = $this->makeSociety('Quiet');
        $b = $this->makeSociety('Loud');
        $ra = $this->makeUserWithRole($a, 'Tenant');
        $rb = $this->makeUserWithRole($b, 'Tenant');
        SystemSetting::set('app', 'enable_email_notifications', false, 'boolean', $a->id);

        $this->assertStringContainsString('switched off', (string) RecipientEligibility::emailSkipReason($ra->email, $a->id, 'notice_published'));
        $this->assertNull(RecipientEligibility::emailSkipReason($ra->email, $a->id, 'password_reset'), 'account mail is exempt');
        $this->assertNull(RecipientEligibility::emailSkipReason($rb->email, $b->id, 'notice_published'), 'another society is unaffected');

        $vars = ['notice_title' => 'T', 'notice_content' => 'C', 'view_url' => '#'];
        DynamicEmailService::sendByEvent('notice_published', [['email' => $ra->email, 'name' => 'A', 'role' => 'Tenant']], $vars, $a->id);
        DynamicEmailService::sendByEvent('notice_published', [['email' => $rb->email, 'name' => 'B', 'role' => 'Tenant']], $vars, $b->id);

        $skipped = EmailLog::where('recipient_email', $ra->email)->first();
        $this->assertTrue($skipped->isSkipped());
        $this->assertSame(1, EmailLog::where('recipient_email', $rb->email)->where('status', 'sent')->count());
        $this->assertSame(0, EmailLog::where('recipient_email', $ra->email)->where('status', 'sent')->count());
    }

    public function test_switching_the_master_back_on_sends_again(): void
    {
        Mail::fake();
        $a = $this->makeSociety('Toggle');
        $r = $this->makeUserWithRole($a, 'Tenant');
        $vars = ['notice_title' => 'T', 'notice_content' => 'C', 'view_url' => '#'];

        SystemSetting::set('app', 'enable_email_notifications', false, 'boolean', $a->id);
        DynamicEmailService::sendByEvent('notice_published', [['email' => $r->email, 'name' => 'R', 'role' => 'Tenant']], $vars, $a->id, 'k-off');
        SystemSetting::set('app', 'enable_email_notifications', true, 'boolean', $a->id);
        DynamicEmailService::sendByEvent('notice_published', [['email' => $r->email, 'name' => 'R', 'role' => 'Tenant']], $vars, $a->id, 'k-on');

        $this->assertSame(1, EmailLog::where('recipient_email', $r->email)->where('status', 'sent')->count());
    }
}
