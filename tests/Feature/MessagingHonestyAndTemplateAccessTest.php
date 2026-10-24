<?php

namespace Tests\Feature;

use App\Events\NoticePublished;
use App\Jobs\DispatchEventNotification;
use App\Listeners\SendNoticePublishedEmail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\User;
use App\Support\MailDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * 1. The delivery log tells the truth: "sent" only when a real mail server took the message; "simulated" when it only went to the log
 *    file or the kill switch is off.
 * 2. Notice e-mails go to active, valid, de-duplicated recipients of the notice's own society - and not only to the publisher.
 * 3. A society Admin can read (not change) shared system templates and customise a copy; isolation between societies holds.
 * 4. Payments export: society-wide roles only, scoped, formula-safe.
 */
class MessagingHonestyAndTemplateAccessTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. honest status ────────────────────────────────────────────────────

    public function test_the_status_is_sent_only_when_a_real_mail_server_took_it_blocked_or_simulated_otherwise(): void
    {
        $cases = [
            // enabled, transport, expected
            [false, 'log', MailDelivery::BLOCKED],         // the kill switch is off: blocked, whatever the mailer
            [false, 'smtp', MailDelivery::BLOCKED],        // cancelled by the kill switch at the last moment
            [true,  'log', MailDelivery::SIMULATED],       // delivery is on but the mailer is the log file: simulated
            [true,  'smtp', MailDelivery::SENT],
            [false, 'array', MailDelivery::SENT],          // the in-memory capture the tests use; never a real mailer
        ];
        foreach ($cases as [$enabled, $transport, $expected]) {
            config(['messaging.enabled' => $enabled, 'mail.default' => 'probe', 'mail.mailers.probe' => ['transport' => $transport]]);
            $this->assertSame($expected, MailDelivery::statusAfterSend(), "enabled=" . var_export($enabled, true) . " transport=$transport");
        }
    }

    public function test_an_event_email_with_the_kill_switch_off_is_logged_as_blocked_not_sent(): void
    {
        $society = $this->makeSociety();
        config(['messaging.enabled' => false, 'mail.default' => 'log']);

        (new DispatchEventNotification('notice_published', ['email' => 'resident@test.local', 'name' => 'R'], ['notice_title' => 'T', 'notice_content' => 'B', 'view_url' => '#'], $society->id, 'notice_published:77:resident@test.local'))->handle();

        $row = EmailLog::where('notification_key', 'notice_published:77:resident@test.local')->first();
        $this->assertNotNull($row);
        $this->assertSame('blocked', $row->status);
        $this->assertStringContainsString('EXTERNAL_MESSAGING_ENABLED', (string) $row->error_message, 'the reason is stored, so it is never mistaken for a provider fault');
        $this->assertNull($row->sent_at, 'nothing was sent, so there is no send time');
        $this->assertSame(0, EmailLog::where('status', 'sent')->count());
    }

    public function test_a_blocked_row_does_not_stop_the_real_send_once_messaging_is_enabled(): void
    {
        $society = $this->makeSociety();
        $key = 'notice_published:78:resident@test.local';
        $job = fn () => new DispatchEventNotification('notice_published', ['email' => 'resident@test.local', 'name' => 'R'], ['notice_title' => 'T', 'notice_content' => 'B', 'view_url' => '#'], $society->id, $key);

        config(['messaging.enabled' => false, 'mail.default' => 'log']);
        $job()->handle();
        config(['messaging.enabled' => false, 'mail.default' => 'array']);   // the capture transport stands in for a real mailer here
        $job()->handle();

        $this->assertSame(['blocked', 'sent'], EmailLog::where('notification_key', $key)->orderBy('id')->pluck('status')->all());
    }

    // ── 2. notice recipients ────────────────────────────────────────────────

    public function test_notice_emails_go_to_active_valid_unique_people_of_that_society_and_not_just_the_publisher(): void
    {
        Bus::fake([DispatchEventNotification::class]);
        $society = $this->makeSociety('Royal');
        $other = $this->makeSociety('Other');
        $publisher = $this->makeUserWithRole($society, 'Admin');
        $owner = $this->makeUserWithRole($society, 'Apartment Owner');
        $tenant = $this->makeUserWithRole($society, 'Tenant');
        $inactive = $this->makeUserWithRole($society, 'Tenant');
        $inactive->update(['status' => 'inactive']);
        $noEmail = $this->makeUserWithRole($society, 'Tenant');
        User::whereKey($noEmail->id)->update(['email' => 'not-an-email']);
        $twin = $this->makeUserWithRole($society, 'Villa Owner');
        // same address, different case - only possible where the unique index is case-sensitive (SQLite); MySQL's collation makes it impossible, so there the twin simply stays distinct
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            User::whereKey($twin->id)->update(['email' => strtoupper($owner->email)]);
        }
        $stranger = $this->makeUserWithRole($other, 'Apartment Owner');
        $staff = $this->makeUserWithRole($society, 'Staff');

        $notice = (new Notice())->forceFill(['id' => 501, 'society_id' => $society->id, 'title' => 'Water cut', 'content' => 'Tomorrow', 'target_audience' => null, 'created_by' => $publisher->id]);
        (new SendNoticePublishedEmail())->handle(new NoticePublished($notice));

        $sent = [];
        Bus::assertDispatched(DispatchEventNotification::class, function ($job) use (&$sent) {
            $sent[] = strtolower($job->recipient['email']);

            return true;
        });
        $this->assertContains(strtolower($owner->email), $sent);
        $this->assertContains(strtolower($tenant->email), $sent);
        $this->assertContains(strtolower($publisher->email), $sent, 'the Admin is one recipient among the others');
        $this->assertGreaterThan(1, count($sent), 'the publisher is not the only recipient');
        $this->assertSame(count($sent), count(array_unique($sent)), 'no address twice');
        foreach ([$inactive->email, 'not-an-email', $stranger->email, $staff->email] as $never) {
            $this->assertNotContains(strtolower($never), $sent);
        }
    }

    // ── 3. template access ──────────────────────────────────────────────────

    private function sharedTemplate(): EmailTemplate
    {
        return EmailTemplate::create(['society_id' => null, 'name' => 'Access Pass Revoked', 'slug' => 'access-pass-revoked-x', 'subject' => 'S {{user_name}}', 'body' => 'B',
            'type' => 'system', 'status' => 'active', 'trigger_event' => 'access_pass_revoked_x']);
    }

    public function test_an_admin_can_read_a_shared_template_but_not_change_or_delete_it(): void
    {
        $society = $this->makeSociety();
        $admin = $this->makeUserWithRole($society, 'Admin');
        $t = $this->sharedTemplate();

        $this->actingAs($admin)->get(route('admin.email-templates.show', $t))->assertOk()->assertSee('Customise for my society');
        $this->actingAs($admin)->get(route('admin.email-templates.edit', $t))->assertRedirect(route('admin.email-templates.show', $t));
        $this->actingAs($admin)->put(route('admin.email-templates.update', $t), ['name' => 'Hacked', 'subject' => 'x', 'body' => 'x', 'status' => 'active'])->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.email-templates.destroy', $t))->assertForbidden();
        $this->assertSame('Access Pass Revoked', $t->fresh()->name);
    }

    public function test_customising_makes_a_society_copy_that_wins_for_that_society_only(): void
    {
        $a = $this->makeSociety('A');
        $b = $this->makeSociety('B');
        $adminA = $this->makeUserWithRole($a, 'Admin');
        $t = $this->sharedTemplate();

        $this->actingAs($adminA)->post(route('admin.email-templates.customise', $t))->assertRedirect();
        $this->actingAs($adminA)->post(route('admin.email-templates.customise', $t));      // pressed twice

        $copies = EmailTemplate::where('society_id', $a->id)->where('trigger_event', 'access_pass_revoked_x')->get();
        $this->assertCount(1, $copies);
        $this->assertSame($copies->first()->id, EmailTemplate::getTemplateForEvent('access_pass_revoked_x', $a->id)->id);
        $this->assertSame($t->id, EmailTemplate::getTemplateForEvent('access_pass_revoked_x', $b->id)->id, 'another society still uses the shared one');

        $this->actingAs($adminA)->put(route('admin.email-templates.update', $copies->first()), ['name' => 'Mine', 'subject' => 'S', 'body' => 'B', 'status' => 'active'])->assertRedirect();
        $this->assertSame('Mine', $copies->first()->fresh()->name);
        $this->assertSame('Access Pass Revoked', $t->fresh()->name);
    }

    public function test_another_societys_template_is_still_forbidden_and_a_super_admin_can_edit_a_shared_one(): void
    {
        $a = $this->makeSociety('A');
        $b = $this->makeSociety('B');
        $adminA = $this->makeUserWithRole($a, 'Admin');
        $theirs = EmailTemplate::create(['society_id' => $b->id, 'name' => 'Theirs', 'slug' => 'theirs', 'subject' => 'S', 'body' => 'B', 'type' => 'custom', 'status' => 'active', 'trigger_event' => 'x']);

        $this->actingAs($adminA)->get(route('admin.email-templates.show', $theirs))->assertForbidden();
        $this->actingAs($adminA)->post(route('admin.email-templates.customise', $theirs))->assertForbidden();   // not theirs to read, so nothing is copied
        $this->assertSame(0, EmailTemplate::where('society_id', $a->id)->count());

        $super = $this->makeUserWithRole($a, 'Super Admin');
        $super->update(['society_id' => null]);
        $shared = $this->sharedTemplate();
        $this->actingAs($super)->put(route('admin.email-templates.update', $shared), ['name' => 'Global edit', 'subject' => 'S', 'body' => 'B', 'status' => 'active'])->assertRedirect();
        $this->assertSame('Global edit', $shared->fresh()->name);
    }

    // ── 4. payments export ──────────────────────────────────────────────────

    public function test_the_payments_export_is_scoped_formula_safe_and_for_society_wide_roles_only(): void
    {
        $a = $this->makeSociety('A');
        $b = $this->makeSociety('B');
        $flat = $this->makeFlat($a, $this->makeBuilding($a), '101');
        $bFlat = $this->makeFlat($b, $this->makeBuilding($b), '201');
        $admin = $this->makeUserWithRole($a, 'Admin');
        $accountant = $this->makeUserWithRole($a, 'Accountant');
        $resident = $this->makeResidentUser($a, $flat);
        Payment::create(['society_id' => $a->id, 'flat_id' => $flat->id, 'payment_id' => 'PAY-A-1', 'amount' => 1250.5, 'payment_method' => 'cash', 'status' => 'success',
            'payment_date' => '2026-10-02', 'due_date' => '2026-10-02', 'bill_type' => 'maintenance', 'receipt_number' => 'RCP-1', 'notes' => '=HYPERLINK("http://evil")']);
        Payment::create(['society_id' => $a->id, 'flat_id' => $flat->id, 'payment_id' => 'PAY-A-2', 'amount' => 10, 'payment_method' => 'cash', 'status' => 'pending',
            'payment_date' => '2026-10-03', 'due_date' => '2026-10-03', 'bill_type' => 'maintenance']);
        Payment::create(['society_id' => $b->id, 'flat_id' => $bFlat->id, 'payment_id' => 'PAY-B-1', 'amount' => 999, 'payment_method' => 'cash', 'status' => 'success',
            'payment_date' => '2026-10-02', 'due_date' => '2026-10-02', 'bill_type' => 'maintenance']);

        $csv = $this->actingAs($admin)->get(route('payments.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('PAY-A-1', $csv);
        $this->assertStringContainsString('PAY-A-2', $csv, 'every record is exported with its status');
        $this->assertStringNotContainsString('PAY-B-1', $csv, 'another society is never exported');
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'a formula is neutralised');
        $this->assertStringContainsString('1250.50', $csv);

        $only = $this->actingAs($accountant)->get(route('payments.export', ['status' => 'pending']))->assertOk()->streamedContent();
        $this->assertStringContainsString('PAY-A-2', $only);
        $this->assertStringNotContainsString('PAY-A-1', $only);

        $this->actingAs($resident)->get(route('payments.export'))->assertForbidden();
    }
}
