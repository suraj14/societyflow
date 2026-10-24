<?php

namespace Tests\Feature;

use App\Models\SmsTemplate;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SmsService;
use App\Support\BusinessNotifications;
use App\Support\DltTemplates;
use App\Support\SmsAudience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SMS audiences resolved on the server (eligibility, duplicates, society isolation), the registered DLT texts (wording, variable
 * order, slot types), and the two new event senders (complaint status, bill) - all through a mock transport, never a provider.
 */
class SmsAudienceAndDltTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $other;
    private array $requests = [];
    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal');
        $this->other = $this->makeSociety('Other');
        SmsService::$transportOverride = function (string $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->requests[] = $q;

            return [['return' => true, 'request_id' => 'rq' . count($this->requests), 'message' => ['SMS sent successfully.']], 200];
        };
    }

    protected function tearDown(): void
    {
        SmsService::$transportOverride = null;
        parent::tearDown();
    }

    private function person($society, string $role, array $over = []): User
    {
        $this->n++;

        return $this->makeUserWithRole($society, $role, $over + ['phone' => '98000' . str_pad((string) $this->n, 5, '0', STR_PAD_LEFT)]);
    }

    private function phones(string $audience, $society = null): array
    {
        return SmsAudience::resolve($audience, ($society ?? $this->society)->id)->pluck('id')->sort()->values()->all();
    }

    // ── audiences ───────────────────────────────────────────────────────────

    public function test_each_audience_resolves_to_exactly_its_eligible_people(): void
    {
        $owner = $this->person($this->society, 'Apartment Owner');
        $villa = $this->person($this->society, 'Villa Owner');
        $tenant = $this->person($this->society, 'Tenant');
        $guard = $this->person($this->society, 'Guard');
        $admin = $this->person($this->society, 'Admin');
        $staff = $this->person($this->society, 'Staff');
        $member = $this->person($this->society, 'Apartment Owner', ['committee_designation' => 'Secretary']);
        $accountant = $this->person($this->society, 'Accountant');

        $ids = fn (...$u) => collect($u)->pluck('id')->sort()->values()->all();
        $this->assertSame($ids($owner, $villa, $tenant, $member), $this->phones('resident'));
        $this->assertSame($this->phones('resident'), $this->phones('all_residents'));
        $this->assertSame($ids($guard), $this->phones('guard'));
        $this->assertSame($ids($admin), $this->phones('admin'));
        $this->assertSame($ids($staff), $this->phones('staff'));
        $this->assertSame($ids($member), $this->phones('committee'));
        $this->assertSame($ids($admin, $guard, $staff, $member), $this->phones('all_team'));
        $this->assertSame($ids($owner, $villa, $tenant, $member, $admin, $guard, $staff), $this->phones('everyone'));
        $this->assertNotContains($accountant->id, $this->phones('everyone'), 'an Accountant is not one of the supported audiences');
    }

    public function test_ineligible_people_and_other_societies_are_never_included(): void
    {
        $ok = $this->person($this->society, 'Tenant');
        $inactive = $this->person($this->society, 'Tenant');
        $inactive->update(['status' => 'inactive']);
        $noPhone = $this->person($this->society, 'Tenant', ['phone' => null]);
        $badPhone = $this->person($this->society, 'Tenant', ['phone' => '12345']);
        $stranger = $this->person($this->other, 'Tenant');
        $super = $this->person($this->society, 'Super Admin');
        $super->update(['society_id' => $this->society->id]);                       // even one attached to this society
        $blankDesignation = $this->person($this->society, 'Guard', ['committee_designation' => '   ']);

        $everyone = $this->phones('everyone');
        $this->assertContains($ok->id, $everyone);
        foreach ([$inactive, $noPhone, $badPhone, $stranger, $super] as $never) {
            $this->assertNotContains($never->id, $everyone);
        }
        $this->assertNotContains($blankDesignation->id, $this->phones('committee'), 'a blank designation is not membership');
        $this->assertContains($blankDesignation->id, $this->phones('guard'));
        $this->assertSame([$stranger->id], $this->phones('all_residents', $this->other));
    }

    public function test_a_person_with_two_roles_or_a_shared_number_is_counted_once(): void
    {
        $both = $this->person($this->society, 'Apartment Owner');
        $this->makeRole('Tenant');
        $both->assignRole('Tenant');
        $admin = $this->person($this->society, 'Admin', ['committee_designation' => 'President']);
        $twinA = $this->person($this->society, 'Tenant', ['phone' => '9811111111']);
        $twinB = $this->person($this->society, 'Villa Owner', ['phone' => '+91 98111 11111']);   // same number, written differently

        $all = SmsAudience::resolve('everyone', $this->society->id);
        $this->assertSame(1, $all->where('id', $both->id)->count());
        $this->assertSame(1, $all->where('id', $admin->id)->count(), 'Admin + committee is one person');
        $this->assertSame(1, $all->filter(fn ($u) => in_array($u->id, [$twinA->id, $twinB->id]))->count(), 'one phone, one SMS');
        $this->assertSame(count($all), count(array_unique($all->map(fn ($u) => SmsService::normalizePhone($u->phone))->all())));
    }

    public function test_the_preview_counts_and_explains_and_blocks_an_empty_audience(): void
    {
        $this->person($this->society, 'Tenant');
        $this->person($this->society, 'Tenant', ['phone' => null]);

        $p = SmsAudience::preview('all_residents', $this->society->id);
        $this->assertSame(1, $p['count']);
        $this->assertSame(1, $p['excluded_no_phone']);
        $this->assertTrue($p['can_send']);
        $this->assertStringNotContainsString('98000', implode(' ', $p['sample']), 'numbers are masked in the preview');

        $empty = SmsAudience::preview('guard', $this->society->id);
        $this->assertFalse($empty['can_send']);
        $this->assertStringContainsString('Nobody', $empty['reason']);
        $this->assertFalse(SmsAudience::preview('nonsense', $this->society->id)['can_send']);
        $this->assertSame(['all_residents', 'all_team', 'everyone'], SmsAudience::BULK);
    }

    public function test_the_audience_endpoint_is_society_scoped_and_rejects_made_up_audiences(): void
    {
        $admin = $this->person($this->society, 'Admin');
        $this->person($this->other, 'Tenant');
        $this->person($this->society, 'Tenant');

        $r = $this->actingAs($admin)->getJson(route('admin.message-templates.sms.audience-preview', ['audience' => 'all_residents']))->assertOk();
        $this->assertSame(1, $r->json('count'), 'only this society');
        $this->assertTrue($r->json('bulk'));
        $this->actingAs($admin)->getJson(route('admin.message-templates.sms.audience-preview', ['audience' => 'user_ids:1,2']))->assertStatus(422);
        $resident = $this->person($this->society, 'Tenant');
        $this->actingAs($resident)->getJson(route('admin.message-templates.sms.audience-preview', ['audience' => 'everyone']))->assertForbidden();
    }

    // ── the registered DLT texts ────────────────────────────────────────────

    public function test_the_registered_texts_and_ids_are_as_taken_from_the_telecom_screens_and_marked_unverified(): void
    {
        $all = DltTemplates::all();
        $this->assertSame('1277179196367432151', $all['complaint']['dlt_template_id']);
        $this->assertSame('1277179196734446136', $all['bill']['dlt_template_id']);
        $this->assertSame('1277179196313898498', $all['notice']['dlt_template_id']);
        $this->assertSame('1277179196034433976', $all['login_otp']['dlt_template_id']);
        foreach ($all as $entry) {
            $this->assertFalse($entry['verified'], 'screenshot-derived, not checked against the provider account');
            $this->assertGreaterThanOrEqual(15, strlen($entry['dlt_template_id']), 'a DLT Template ID is not a Fast2SMS Message ID');
        }
        $this->assertStringNotContainsString('4821', $all['login_otp']['content'], 'no sample OTP is ever stored');
        $this->assertSame('SocietyFlow: Complaint {{complaint_ref}} status updated to {{complaint_status}}. Please check the SocietyFlow app for details.', DltTemplates::asPlaceholders($all['complaint']));
    }

    public function test_a_template_with_a_message_id_must_match_the_registered_wording_and_variable_order(): void
    {
        $good = 'SocietyFlow: Complaint {{complaint_ref}} status updated to {{complaint_status}}. Please check the SocietyFlow app for details.';
        $this->assertSame([], DltTemplates::bodyProblems('complaint', $good));
        $this->assertSame([], DltTemplates::bodyProblems('complaint', "SocietyFlow:  Complaint {{complaint_ref}} status updated to  {{complaint_status}}.\nPlease check the SocietyFlow app for details."));
        $this->assertNotEmpty(DltTemplates::bodyProblems('complaint', 'Your complaint {{complaint_ref}} is now {{complaint_status}}'), 'different wording');
        $swapped = 'SocietyFlow: Complaint {{complaint_status}} status updated to {{complaint_ref}}. Please check the SocietyFlow app for details.';
        $this->assertStringContainsString('order', implode(' ', DltTemplates::bodyProblems('complaint', $swapped)));
        $this->assertSame([], DltTemplates::bodyProblems('login_otp', 'anything {{otp}}'), 'OTP is reference only and never rejected here');
        $this->assertSame([], DltTemplates::bodyProblems('document', 'free wording'), 'no registered text, nothing to compare');
    }

    public function test_a_body_that_differs_from_the_registered_text_is_saved_with_a_warning_not_refused(): void
    {
        $admin = $this->person($this->society, 'Admin');
        $base = ['name' => 'Complaint SMS', 'purpose' => 'complaint', 'audience' => 'resident', 'status' => 'active'];

        // the reference text is unverified, so a society's own approved wording is never blocked - but it is flagged
        $r = $this->actingAs($admin)->post(route('admin.message-templates.sms.store'), $base + ['body' => 'Complaint {{complaint_ref}} is {{complaint_status}}', 'dlt_message_id' => '228126']);
        $r->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->assertStringContainsString('registered with the telecom', session('warning'));
        $this->assertSame('228126', SmsTemplate::first()->dlt_message_id, 'the Message ID is stored on its own, apart from the long DLT Template ID');

        SmsTemplate::query()->delete();
        $this->flushSession();   // the test client keeps one session, so the first save's flash would still be there
        $ok = 'SocietyFlow: Complaint {{complaint_ref}} status updated to {{complaint_status}}. Please check the SocietyFlow app for details.';
        $this->actingAs($admin)->post(route('admin.message-templates.sms.store'), $base + ['body' => $ok, 'dlt_message_id' => '228126'])->assertSessionHasNoErrors()->assertSessionMissing('warning');
    }
    public function test_the_value_checks_reject_what_a_slot_cannot_carry(): void
    {
        $this->assertSame([], DltTemplates::valueProblems('complaint', ['complaint_ref' => 'CMP006', 'complaint_status' => 'In Progress']));
        $this->assertNotEmpty(DltTemplates::valueProblems('complaint', ['complaint_ref' => 'CMP006']), 'missing');
        $this->assertNotEmpty(DltTemplates::valueProblems('complaint', ['complaint_ref' => str_repeat('A', 31), 'complaint_status' => 'Open']), 'too long');
        $this->assertNotEmpty(DltTemplates::valueProblems('complaint', ['complaint_ref' => 'नवरात्रि', 'complaint_status' => 'Open']), 'not alphanumeric');
        $this->assertSame([], DltTemplates::valueProblems('bill', ['bill_label' => 'Maintenance October 2026', 'amount_plain' => '2500']));
        $this->assertNotEmpty(DltTemplates::valueProblems('bill', ['bill_label' => 'Maintenance October 2026', 'amount_plain' => '2,500.50']), 'a number slot is digits only');
    }

    // ── the event senders ───────────────────────────────────────────────────

    private function enableSms(): void
    {
        foreach (['sms_enabled' => true, 'notification_sms_enabled' => true] as $k => $v) {
            SystemSetting::set('sms', $k, $v, 'boolean', $this->society->id);
        }
        SystemSetting::set('sms', 'fast2sms_api_key', 'TEST-KEY-1234567890', 'encrypted', $this->society->id);
        SystemSetting::set('sms', 'fast2sms_sender_id', 'SAIDIG', 'string', $this->society->id);
    }

    private function template(string $purpose, string $body, ?string $messageId = '228126'): SmsTemplate
    {
        return SmsTemplate::create(['society_id' => $this->society->id, 'name' => $purpose, 'purpose' => $purpose, 'audience' => 'resident', 'body' => $body, 'dlt_message_id' => $messageId, 'is_active' => true]);
    }

    private const COMPLAINT_BODY = 'SocietyFlow: Complaint {{complaint_ref}} status updated to {{complaint_status}}. Please check the SocietyFlow app for details.';
    private const BILL_BODY = 'SocietyFlow: Your {{bill_label}} bill is Rs. {{amount_plain}}. Please check the SocietyFlow app for due date and payment details. Thank you.';

    private function complaint(User $creator, string $status = 'in_progress'): object
    {
        return (object) ['id' => 9, 'society_id' => $this->society->id, 'created_by' => $creator->id, 'status' => $status, 'complaint_number' => 'CMP102', 'title' => 'Leak', 'resolution_notes' => null];
    }

    public function test_a_complaint_status_change_texts_the_resident_with_the_ref_then_the_status_in_the_registered_order(): void
    {
        $this->enableSms();
        $this->template('complaint', self::COMPLAINT_BODY);
        $resident = $this->person($this->society, 'Tenant');

        BusinessNotifications::complaintUpdated($this->complaint($resident));

        $this->assertCount(1, $this->requests);
        $this->assertSame('228126', $this->requests[0]['message']);
        $this->assertSame('CMP102|In Progress', $this->requests[0]['variables_values'], 'ref first, status second');
        $this->assertSame(SmsService::normalizePhone($resident->phone), $this->requests[0]['numbers']);
    }

    public function test_no_template_or_no_message_id_means_no_sms_and_never_a_quick_sms(): void
    {
        $this->enableSms();
        $resident = $this->person($this->society, 'Tenant');

        BusinessNotifications::complaintUpdated($this->complaint($resident));                 // no template at all
        $this->template('complaint', self::COMPLAINT_BODY, null);                              // a template, but no approved Message ID
        BusinessNotifications::complaintUpdated($this->complaint($resident));

        $this->assertSame([], $this->requests);
    }

    public function test_a_value_the_slot_cannot_carry_is_skipped_and_logged_not_sent(): void
    {
        $this->enableSms();
        $this->template('complaint', self::COMPLAINT_BODY);
        $resident = $this->person($this->society, 'Tenant');
        $c = $this->complaint($resident);
        $c->complaint_number = 'नवरात्रि-1';

        BusinessNotifications::complaintUpdated($c);

        $this->assertSame([], $this->requests);
        $this->assertDatabaseHas('email_logs', ['channel' => 'sms', 'society_id' => $this->society->id, 'trigger_action' => 'notification', 'status' => 'failed']);
    }

    public function test_the_channel_switches_the_kill_switch_and_the_event_rule_all_stop_it(): void
    {
        $this->enableSms();
        $this->template('complaint', self::COMPLAINT_BODY);
        $resident = $this->person($this->society, 'Tenant');

        SystemSetting::set('sms', 'notification_sms_enabled', false, 'boolean', $this->society->id);
        BusinessNotifications::complaintUpdated($this->complaint($resident));
        $this->assertSame([], $this->requests, 'notification SMS switched off');

        SystemSetting::set('sms', 'notification_sms_enabled', true, 'boolean', $this->society->id);
        SystemSetting::set('notification_rules', 'ticket_updated:sms', false, 'boolean', $this->society->id);
        BusinessNotifications::complaintUpdated($this->complaint($resident));
        $this->assertSame([], $this->requests, 'event rule off');
        SystemSetting::where('key', 'ticket_updated:sms')->delete();

        BusinessNotifications::complaintUpdated($this->complaint($resident));
        $this->assertCount(1, $this->requests, 'back to normal');
    }

    public function test_a_bill_texts_only_the_residents_of_that_unit_with_the_bill_and_a_whole_rupee_amount(): void
    {
        $this->enableSms();
        $this->template('bill', self::BILL_BODY);
        $flat = $this->makeFlat($this->society, $this->makeBuilding($this->society), '101');
        $otherFlat = $this->makeFlat($this->society, $this->makeBuilding($this->society, 'Tower B'), '201');
        $this->makeRole('Tenant');
        $mine = $this->makeResidentUser($this->society, $flat, ['phone' => '9822222222']);
        $theirs = $this->makeResidentUser($this->society, $otherFlat, ['phone' => '9833333333']);
        $mine->assignRole('Tenant');
        $theirs->assignRole('Tenant');

        $bill = (object) ['id' => 5, 'society_id' => $this->society->id, 'flat_id' => $flat->id, 'total_amount' => 2500, 'due_date' => '2026-10-22', 'month' => 'October', 'year' => 2026, 'bill_number' => 'MB-1', 'bill_type' => 'maintenance'];
        BusinessNotifications::billGenerated($bill);

        $this->assertCount(1, $this->requests);
        $this->assertSame('9822222222', $this->requests[0]['numbers']);
        $this->assertSame('Maintenance October 2026|2500', $this->requests[0]['variables_values']);

        $this->requests = [];
        $paise = clone $bill;
        $paise->total_amount = 2500.5;
        BusinessNotifications::billGenerated($paise);
        $this->assertSame([], $this->requests, 'a slot that takes digits only is never sent paise');
    }

    public function test_a_repeat_of_the_same_event_does_not_text_twice(): void
    {
        $this->enableSms();
        $this->template('complaint', self::COMPLAINT_BODY);
        $resident = $this->person($this->society, 'Tenant');

        BusinessNotifications::complaintUpdated($this->complaint($resident));
        BusinessNotifications::complaintUpdated($this->complaint($resident));

        $this->assertCount(1, $this->requests);
    }
}
