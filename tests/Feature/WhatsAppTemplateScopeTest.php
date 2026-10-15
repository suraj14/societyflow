<?php

namespace Tests\Feature;

use App\Events\BillGenerated;
use App\Events\NoticePublished;
use App\Events\PaymentReceived;
use App\Events\TicketUpdated;
use App\Events\VisitorApproved;
use App\Models\Complaint;
use App\Models\MaintenanceBill;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Visitor;
use App\Models\WhatsAppTemplate;
use App\Services\Fast2SmsAccount;
use App\Services\WhatsAppService;
use App\Support\WhatsAppTemplateScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Message Templates -> WhatsApp lists every SocietyFlow template in five categories whatever its provider status,
 * keeps another product's (BookMizo) templates out, never sends a pending / rejected / BookMizo one, sends the right
 * template with the right values for each real notification event, and "Sync from Fast2SMS" adds new templates without
 * touching what is saved. Every provider call here is a captured fake; nothing leaves the process.
 */
class WhatsAppTemplateScopeTest extends TestCase
{
    use RefreshDatabase;

    private $society;
    private $admin;
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->society = $this->makeSociety('Alpha');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        WhatsAppService::$transportOverride = function (string $url, array $data = []) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $this->calls[] = ['url' => $url, 'query' => $q, 'data' => $data];

            return [['return' => true, 'request_id' => 'r1', 'message' => ['Whatsapp Message sent successfully.']], 200];
        };
        $this->wa('whatsapp_enabled', true, 'boolean');
        $this->wa('whatsapp_api_key', 'wa-key-0123456789abcdefghij', 'encrypted');
        $this->wa('whatsapp_phone_number_id', '1209719742234591');
    }

    protected function tearDown(): void
    {
        WhatsAppService::$transportOverride = null;
        Fast2SmsAccount::$transportOverride = null;
        parent::tearDown();
    }

    private function wa(string $key, $value, string $type = 'string'): void
    {
        SystemSetting::set('whatsapp', $key, $value, $type, $this->society->id);
    }

    /** A template row as the provider sync stores it. */
    private function synced(string $name, string $status = 'APPROVED', array $over = []): WhatsAppTemplate
    {
        static $n = 0;

        return WhatsAppTemplate::create($over + [
            'society_id' => $this->society->id, 'external_id' => 'ext-' . (++$n), 'name' => $name, 'status' => $status, 'language' => 'en',
            'category' => 'UTILITY', 'var_count' => 0, 'body_text' => null, 'is_active' => false, 'source' => 'synced',
        ]);
    }

    private function tab(array $query = []): string
    {
        return $this->actingAs($this->admin)->get(route('admin.message-templates.index', ['channel' => 'whatsapp'] + $query))->assertOk()->getContent();
    }

    // ── listing ─────────────────────────────────────────────────────────────

    public function test_every_societyflow_template_is_listed_in_its_category_whatever_its_status(): void
    {
        $this->synced('society_visitor_approved', 'APPROVED', ['purpose' => 'visitor', 'is_active' => true, 'var_count' => 3, 'body_text' => 'Hi {{1}}, {{2}} {{3}}', 'message_id' => '101']);
        $this->synced('society_visitor_arrived', 'PENDING');
        $this->synced('society_bill_generated', 'APPROVED');
        $this->synced('society_complaint_update', 'REJECTED');
        $this->synced('society_important_notice', 'APPROVED');
        $this->synced('society_event_announcement', 'PAUSED');
        $this->synced('society_login_otp', 'APPROVED');

        $html = $this->tab();

        foreach (['society_visitor_approved', 'society_visitor_arrived', 'society_bill_generated', 'society_complaint_update', 'society_important_notice', 'society_event_announcement', 'society_login_otp'] as $name) {
            $this->assertStringContainsString($name, $html, "$name is listed");
        }
        foreach (WhatsAppTemplateScope::categories() as $label) {
            $this->assertStringContainsString(e($label), $html, "category heading $label");
        }
    }

    public function test_provider_status_and_in_app_status_are_shown_separately_and_accurately(): void
    {
        $approvedActive = $this->synced('society_visitor_approved', 'APPROVED', ['purpose' => 'visitor', 'is_active' => true]);
        $pendingInactive = $this->synced('society_visitor_arrived', 'PENDING');
        $approvedInactive = $this->synced('society_bill_generated', 'APPROVED');
        $rejected = $this->synced('society_complaint_update', 'REJECTED');

        $html = $this->tab();
        $card = function (WhatsAppTemplate $t) use ($html) {
            preg_match('/<article[^>]*data-template-id="' . $t->id . '".*?<\/article>/s', $html, $m);

            return $m[0] ?? '';
        };

        $this->assertStringContainsString('Provider: Approved', $card($approvedActive));
        $this->assertStringContainsString('In app: Active', $card($approvedActive));
        $this->assertStringContainsString('Provider: Pending review', $card($pendingInactive));
        $this->assertStringContainsString('In app: Inactive', $card($pendingInactive));
        $this->assertStringContainsString('Provider: Approved', $card($approvedInactive));
        $this->assertStringContainsString('In app: Inactive', $card($approvedInactive));
        $this->assertStringContainsString('Provider: Rejected', $card($rejected));
        $this->assertStringContainsString('never sent', $card($rejected));
    }

    public function test_the_category_provider_status_and_in_app_filters_and_search_work_together(): void
    {
        $this->synced('society_visitor_approved', 'APPROVED', ['purpose' => 'visitor', 'is_active' => true]);
        $this->synced('society_visitor_arrived', 'PENDING');
        $this->synced('society_bill_generated', 'APPROVED');

        $visitors = $this->tab(['category' => WhatsAppTemplateScope::VISITORS]);
        $this->assertStringContainsString('society_visitor_arrived', $visitors);
        $this->assertStringNotContainsString('society_bill_generated', $visitors);

        $pending = $this->tab(['provider' => 'pending']);
        $this->assertStringContainsString('society_visitor_arrived', $pending);
        $this->assertStringNotContainsString('society_visitor_approved', $pending);

        $inactive = $this->tab(['status' => 'inactive']);
        $this->assertStringNotContainsString('data-template-id="' . WhatsAppTemplate::where('name', 'society_visitor_approved')->value('id') . '"', $inactive);
        $this->assertStringContainsString('society_bill_generated', $inactive);

        $this->assertStringContainsString('society_bill_generated', $this->tab(['q' => 'bill_gen']));
        $this->assertStringNotContainsString('society_visitor_arrived', $this->tab(['q' => 'bill_gen']));
    }

    public function test_bookmizo_templates_are_kept_out_of_the_list_but_reachable_and_never_deleted(): void
    {
        $bookmizo = ['bookmizo_event_slot_reserved', 'bookmizo_booking_reminder_v2', 'bookmizo_staff_new_booking', 'bookmizo_refund_full', 'bookmizo_payment_received_full', 'booking_confirmation', 'booking_confirmation_bookmizo'];
        foreach ($bookmizo as $name) {
            $this->synced($name, $name === 'booking_confirmation_bookmizo' ? 'REJECTED' : 'APPROVED');
        }
        $this->synced('society_visitor_approved');

        $html = $this->tab();
        foreach ($bookmizo as $name) {
            $this->assertStringNotContainsString($name, $html, "$name stays out of the SocietyFlow list");
        }
        $this->assertStringContainsString('society_visitor_approved', $html);
        $this->assertStringContainsString(count($bookmizo) . ' other template(s)', $html);

        $other = $this->tab(['scope' => 'other']);
        foreach ($bookmizo as $name) {
            $this->assertStringContainsString($name, $other);
        }
        $this->assertSame(count($bookmizo) + 1, WhatsAppTemplate::count(), 'nothing was deleted');
    }

    public function test_a_prefix_alone_decides_nothing(): void
    {
        // looks like ours, is a booking template
        $this->synced('society_booking_reminder');
        // no society prefix, is ours
        $this->synced('visitor_arrived_v2');
        // a hand-typed template is the admin's own, whatever it is called
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.store'), [
            'name' => 'Pass note', 'purpose' => 'access_pass', 'template_name' => 'refund_style_name', 'language' => 'en', 'status' => 'inactive', 'body_text' => 'Hi {{1}}',
        ])->assertSessionHasNoErrors();

        $html = $this->tab();
        $this->assertStringNotContainsString('society_booking_reminder', $html);
        $this->assertStringContainsString('visitor_arrived_v2', $html);
        $this->assertStringContainsString('refund_style_name', $html);
    }

    public function test_an_unrecognisable_template_stays_visible_under_not_sorted_yet(): void
    {
        $this->synced('promo_diwali_offer');

        $html = $this->tab();
        $this->assertStringContainsString('promo_diwali_offer', $html, 'a custom template of the society\'s own is never hidden');
        $this->assertStringContainsString('Not sorted yet', $html);
        $this->assertStringNotContainsString('promo_diwali_offer', $this->tab(['scope' => 'other']), 'only BookMizo templates go under Other templates');
    }

    public function test_existing_template_data_is_untouched_by_listing_and_by_a_failed_or_blocked_activation(): void
    {
        $t = $this->synced('society_visitor_arrived', 'PENDING', ['message_id' => '777', 'var_count' => 2, 'body_text' => 'Hello {{1}}, {{2}} is here', 'language' => 'en_GB', 'friendly_name' => 'Arrived', 'purpose' => 'visitor']);
        $before = $t->fresh()->only(['id', 'name', 'friendly_name', 'body_text', 'language', 'message_id', 'var_count', 'status', 'purpose', 'is_active']);

        $this->tab();
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.toggle', $t))->assertSessionHas('error');

        $this->assertSame($before, $t->fresh()->only(array_keys($before)));
    }

    public function test_the_misrouted_bookmizo_templates_in_the_development_data_are_reported_not_hidden(): void
    {
        // the real shape found in the dev database: BookMizo templates switched on for resident notifications
        $this->synced('bookmizo_staff_booking_reschedule', 'APPROVED', ['purpose' => 'access_pass', 'is_active' => true]);
        $this->synced('bookmizo_refund_full', 'APPROVED', ['purpose' => 'visitor', 'is_active' => true]);

        $html = $this->tab();

        $this->assertStringContainsString('BookMizo template(s) are switched on for resident notifications', $html);
        $this->assertStringContainsString('Gate pass updates', $html);
        $this->assertStringContainsString('Visitor approved', $html);
        $this->assertStringContainsString('scope=other', $html);
    }

    // ── nothing unapproved or foreign is ever sent ───────────────────────────

    private function approveVisitor(): void
    {
        $this->resident = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Asha', 'phone' => '9876543210']);
        $this->visitorEvent = fn () => event(new VisitorApproved((new Visitor())->forceFill([
            'id' => 7, 'society_id' => $this->society->id, 'created_by' => $this->resident->id, 'visitor_name' => 'Ravi', 'expected_entry_time' => '2030-11-12 10:00:00',
        ])));
    }

    private $resident;
    private $visitorEvent;

    public function test_a_pending_or_rejected_template_is_never_sent_even_if_it_is_switched_on(): void
    {
        $this->approveVisitor();
        foreach (['PENDING', 'REJECTED', 'PAUSED', 'DISABLED'] as $status) {
            WhatsAppTemplate::query()->delete();
            $this->synced('society_visitor_approved', $status, ['purpose' => 'visitor', 'is_active' => true, 'message_id' => '101', 'var_count' => 3, 'body_text' => 'Hi {{1}}, {{2}} {{3}}']);

            ($this->visitorEvent)();
        }

        $this->assertSame([], $this->calls, 'no provider request for an unapproved template');
        $this->assertDatabaseMissing('email_logs', ['channel' => 'whatsapp', 'status' => 'sent']);
    }

    public function test_a_bookmizo_template_attached_to_a_resident_notification_is_never_sent(): void
    {
        $this->approveVisitor();
        $this->synced('bookmizo_refund_full', 'APPROVED', ['purpose' => 'visitor', 'is_active' => true, 'message_id' => '55']);

        ($this->visitorEvent)();

        $this->assertSame([], $this->calls);
    }

    public function test_an_unapproved_template_cannot_be_switched_on_or_saved_active(): void
    {
        $pending = $this->synced('society_visitor_arrived', 'PENDING', ['purpose' => 'visitor', 'var_count' => 1, 'body_text' => 'Hi {{1}}']);

        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.toggle', $pending))->assertSessionHas('error');
        $this->assertFalse($pending->fresh()->is_active);

        $this->actingAs($this->admin)->put(route('admin.message-templates.whatsapp.update', $pending), [
            'name' => 'Arrived', 'purpose' => 'visitor', 'template_name' => 'society_visitor_arrived', 'language' => 'en', 'status' => 'active', 'body_text' => 'Hi {{1}}',
            'loaded' => (string) $pending->fresh()->updated_at->timestamp,
        ])->assertSessionHasErrors('status');
        $this->assertFalse($pending->fresh()->is_active);
    }

    public function test_the_test_button_path_also_refuses_an_unapproved_template(): void
    {
        $pending = $this->synced('society_visitor_arrived', 'PENDING', ['message_id' => '9', 'var_count' => 1, 'body_text' => 'Hi {{1}}']);

        $r = WhatsAppService::sendTestTemplate($pending, '9876543210', $this->society->id, ['Asha']);

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('not approved', $r['message']);
        $this->assertSame([], $this->calls);
    }

    // ── the right template and values for each real event ────────────────────

    private function attach(string $purpose, string $name, string $messageId, int $vars, string $body): WhatsAppTemplate
    {
        return $this->synced($name, 'APPROVED', ['purpose' => $purpose, 'is_active' => true, 'message_id' => $messageId, 'var_count' => $vars, 'body_text' => $body]);
    }

    private function lastValues(): array
    {
        $last = end($this->calls);

        return [$last['query']['message_id'] ?? null, explode('|', $last['query']['variables_values'] ?? '')];
    }

    public function test_each_real_event_uses_its_own_template_with_the_values_in_placeholder_order(): void
    {
        $this->attach('visitor', 'society_visitor_approved', '101', 3, 'Hi {{1}}, {{2}} arrives {{3}}');
        $this->attach('complaint', 'society_complaint_update', '102', 4, '{{1}}: {{2}} {{3}} is {{4}}');
        $this->attach('bill', 'society_bill_generated', '103', 4, '{{1}} bill {{2}} due {{3}} ref {{4}}');
        $this->attach('payment', 'society_payment_received', '104', 4, '{{1}} paid {{2}} on {{3}} receipt {{4}}');
        $this->attach('notice', 'society_important_notice', '105', 2, '{{1}}: {{2}}');

        $resident = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Asha', 'phone' => '9876543210']);

        event(new VisitorApproved((new Visitor())->forceFill(['id' => 7, 'society_id' => $this->society->id, 'created_by' => $resident->id, 'visitor_name' => 'Ravi', 'expected_entry_time' => '2030-11-12 10:00:00'])));
        $this->assertSame(['101', ['Asha', 'Ravi', '12 Nov, 10:00 AM']], $this->lastValues());

        event(new TicketUpdated((new Complaint())->forceFill(['id' => 8, 'society_id' => $this->society->id, 'created_by' => $resident->id, 'complaint_number' => 'CMP0008', 'title' => 'Leak', 'status' => 'in_progress'])));
        $this->assertSame(['102', ['Asha', 'CMP0008', 'Leak', 'In Progress']], $this->lastValues());

        event(new PaymentReceived((new Payment())->forceFill(['id' => 9, 'society_id' => $this->society->id, 'user_id' => $resident->id, 'amount' => 1200, 'payment_date' => '2030-11-12', 'receipt_number' => 'RCP9'])));
        $this->assertSame(['104', ["\u{20B9}1,200.00", 'Asha', '12 Nov 2030', 'RCP9']], $this->lastValues());

        event(new NoticePublished((new Notice())->forceFill(['id' => 10, 'society_id' => $this->society->id, 'title' => 'Water cut', 'target_audience' => null])));
        $this->assertSame(['105', ['Asha', 'Water cut']], $this->lastValues());

        $building = $this->makeBuilding($this->society);
        $flat = $this->makeFlat($this->society, $building, '159');
        $owner = $this->makeResidentUser($this->society, $flat, ['name' => 'Meera', 'phone' => '9876500001']);
        $this->makeRole('Tenant');
        $owner->assignRole('Tenant');
        event(new BillGenerated((new MaintenanceBill())->forceFill(['id' => 11, 'society_id' => $this->society->id, 'flat_id' => $flat->id, 'total_amount' => 2500, 'due_date' => '2030-12-01', 'bill_number' => 'MB0011'])));
        $this->assertSame(['103', ['Meera', "\u{20B9}2,500.00", '01 Dec 2030', 'MB0011']], $this->lastValues());

        $this->assertCount(5, $this->calls, 'one request per event, each to its own template');
    }

    /** The message exactly as the recipient would read it: the saved body with each {{n}} replaced by the value sent for it. */
    private function rendered(string $body): string
    {
        $values = explode('|', end($this->calls)['query']['variables_values'] ?? '');

        return preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $values[(int) $m[1] - 1] ?? '??', $body);
    }

    public function test_the_real_approved_societyflow_templates_read_correctly_when_filled_from_real_events(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        // the approved wording, verbatim from the provider (dev database rows society_*), with the provider's variable counts
        $visitor = $this->attach('visitor', 'society_visitor_approved', '37327', 4, "Hello {{1}},\n\nYour visitor {{2}} has been approved for entry at {{3}}.\nPlease follow society security guidelines. Contact security if needed: {{4}}. Thank you.");
        $complaint = $this->attach('complaint', 'society_complaint_update', '37331', 5, "Hello {{1}},\n\nYour complaint #{{2}} status is now {{3}}. Update: {{4}}. Regards, {{5}}. Please check SocietyFlow for details. Thank you.");
        $bill = $this->attach('bill', 'society_bill_generated', '37330', 4, "Hello {{1}},\n\nYour society bill for {{2}} is Rs. {{3}}. Please pay by {{4}}. Open SocietyFlow for details. Thank you.");
        $notice = $this->attach('notice', 'society_important_notice', '37332', 3, "Hello {{1}},\n\nImportant notice from {{2}}: {{3}}. Please check the SocietyFlow app for full details. Thank you.");
        $event = $this->attach('event', 'society_event_announcement', '37335', 5, "Hello {{1}},\n\n{{2}} invites you to {{3}} on {{4}} at {{5}}. Please check the SocietyFlow app for event details. We hope to see you there.");
        $this->society->forceFill(['phone' => '9998887776'])->save();
        $resident = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Asha', 'phone' => '9876543210']);

        event(new VisitorApproved((new Visitor())->forceFill(['id' => 7, 'society_id' => $this->society->id, 'created_by' => $resident->id, 'visitor_name' => 'Ravi', 'expected_entry_time' => '2030-11-12 10:00:00'])));
        $this->assertSame('37327', $this->lastValues()[0]);
        $this->assertSame("Hello Asha,\n\nYour visitor Ravi has been approved for entry at 12 Nov, 10:00 AM.\nPlease follow society security guidelines. Contact security if needed: 9998887776. Thank you.", $this->rendered($visitor->body_text));

        event(new TicketUpdated((new Complaint())->forceFill(['id' => 8, 'society_id' => $this->society->id, 'created_by' => $resident->id, 'complaint_number' => 'CMP0008', 'title' => 'Leak', 'status' => 'in_progress', 'resolution_notes' => 'Plumber booked'])));
        $this->assertSame("Hello Asha,\n\nYour complaint #CMP0008 status is now In Progress. Update: Plumber booked. Regards, Alpha. Please check SocietyFlow for details. Thank you.", $this->rendered($complaint->body_text));

        event(new NoticePublished((new Notice())->forceFill(['id' => 10, 'society_id' => $this->society->id, 'title' => 'Water cut', 'target_audience' => null])));
        $this->assertSame("Hello Asha,\n\nImportant notice from Alpha: Water cut. Please check the SocietyFlow app for full details. Thank you.", $this->rendered($notice->body_text));

        $building = $this->makeBuilding($this->society);
        $flat = $this->makeFlat($this->society, $building, '159');
        $owner = $this->makeResidentUser($this->society, $flat, ['name' => 'Meera', 'phone' => '9876500001']);
        $this->makeRole('Tenant');
        $owner->assignRole('Tenant');
        event(new BillGenerated((new MaintenanceBill())->forceFill(['id' => 11, 'society_id' => $this->society->id, 'flat_id' => $flat->id, 'month' => 12, 'year' => 2030, 'total_amount' => 2500, 'due_date' => '2030-12-20', 'bill_number' => 'MB0011'])));
        $this->assertSame("Hello Meera,\n\nYour society bill for December 2030 is Rs. 2,500.00. Please pay by 20 Dec 2030. Open SocietyFlow for details. Thank you.", $this->rendered($bill->body_text));

        $ownerUser = $this->makeUserWithRole($this->society, 'Owner', ['name' => 'Suraj', 'phone' => '9000000011']);
        $this->actingAs($this->admin)->post(route('events.store'), [
            'event_name' => 'Diwali get-together', 'location' => 'Clubhouse', 'description' => 'Lights', 'start_date' => '2030-10-30', 'start_time' => '18:00',
            'end_date' => '2030-10-30', 'end_time' => '21:00', 'status' => 'pending', 'is_role_based' => 0,
        ])->assertRedirect(route('events.index'));
        $this->assertSame('37335', $this->lastValues()[0]);
        $this->assertSame("Hello Suraj,\n\nAlpha invites you to Diwali get-together on 30 Oct 2030 at 06:00 PM. Please check the SocietyFlow app for event details. We hope to see you there.", $this->rendered($event->body_text));
    }

    public function test_a_value_the_event_cannot_supply_stops_the_send_instead_of_printing_a_placeholder(): void
    {
        $this->attach('visitor', 'society_visitor_approved', '37327', 4, 'Hello {{1}}, visitor {{2}} at {{3}}. Contact security if needed: {{4}}.');
        $this->society->forceFill(['phone' => null])->save();   // no phone saved, so {{4}} has no real value
        $this->approveVisitor();

        ($this->visitorEvent)();

        $this->assertSame([], $this->calls, 'not sent with a made-up or blank contact number');
    }

    public function test_the_card_shows_what_fills_each_variable_in_the_templates_own_order(): void
    {
        $this->attach('notice', 'society_important_notice', '37332', 3, "Hello {{1}},\n\nImportant notice from {{2}}: {{3}}. Thank you.");

        $html = $this->tab();

        $this->assertStringContainsString('{{1}} Resident name · {{2}} Society name · {{3}} Notice title', $html);
        $this->assertStringContainsString('Example:', $html);
        $this->assertStringContainsString('Hello Asha Rao,', $html);
        $this->assertStringContainsString('Important notice from', $html);
    }

    public function test_an_older_setting_that_names_a_bookmizo_template_is_not_sent(): void
    {
        // the shape in the dev database: a resident notification still pointed at a BookMizo display name by an old setting
        $this->wa('whatsapp_payment_template_name', 'Bookmizo Booking Confirm Full');
        $resident = $this->makeUserWithRole($this->society, 'Tenant', ['name' => 'Asha', 'phone' => '9876543210']);

        event(new PaymentReceived((new Payment())->forceFill(['id' => 9, 'society_id' => $this->society->id, 'user_id' => $resident->id, 'amount' => 1200, 'payment_date' => '2030-11-12'])));

        $this->assertSame([], $this->calls);
        $r = WhatsAppService::sendNotification('payment', '9876543210', ['amount' => '1', 'resident_name' => 'A', 'payment_date' => 'x', 'receipt_ref' => 'r', 'society_name' => 'S'], $this->society->id);
        $this->assertSame('blocked_template', $r['status']);
        $this->assertStringContainsString('BookMizo', $r['message']);
    }

    public function test_a_template_the_admin_just_saved_in_the_ui_reaches_the_real_notification_path(): void
    {
        // no older settings key anywhere: the saved, active record alone must be enough
        $this->approveVisitor();
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.store'), [
            'name' => 'Visitor approved', 'purpose' => 'visitor', 'template_name' => 'society_visitor_approved', 'message_id' => '101', 'language' => 'en',
            'status' => 'active', 'body_text' => 'Hi {{1}}, {{2}} arrives {{3}}',
        ])->assertSessionHasNoErrors();

        ($this->visitorEvent)();

        $this->assertCount(1, $this->calls);
        $this->assertSame(['101', ['Asha', 'Ravi', '12 Nov, 10:00 AM']], $this->lastValues());
    }

    public function test_the_parameter_count_must_match_what_the_event_can_supply_and_the_saved_text(): void
    {
        $this->approveVisitor();
        // six variables, but a visitor notification supplies five values at most
        $t = $this->attach('visitor', 'society_visitor_approved', '101', 6, '{{1}} {{2}} {{3}} {{4}} {{5}} {{6}}');
        ($this->visitorEvent)();
        $this->assertSame([], $this->calls, 'too many variables: not sent');

        // text says two variables, record says three
        $t->forceFill(['var_count' => 3, 'body_text' => 'Hi {{1}}, {{2}}'])->save();
        ($this->visitorEvent)();
        $this->assertSame([], $this->calls, 'text and variable count disagree: not sent');

        // consistent again: sent
        $t->forceFill(['var_count' => 2])->save();
        ($this->visitorEvent)();
        $this->assertCount(1, $this->calls);
        $this->assertSame(['Asha', 'Ravi'], explode('|', $this->calls[0]['query']['variables_values']));
    }

    // ── sync from Fast2SMS ──────────────────────────────────────────────────

    private function provider(array $templates): void
    {
        Fast2SmsAccount::$transportOverride = function (string $method, string $url) use ($templates) {
            $this->calls[] = ['sync' => $method . ' ' . parse_url($url, PHP_URL_PATH)];

            return [['success' => true, 'data' => [['phone_number_id' => '1209719742234591', 'templates' => $templates]]], 200];
        };
    }

    private function remote(string $name, string $status, array $over = []): array
    {
        return $over + [
            'template_id' => 'rid-' . $name, 'message_id' => '9' . strlen($name), 'template_name' => $name, 'language' => 'en', 'status' => $status,
            'category' => 'UTILITY', 'var_count' => 1, 'phone_number_id' => '1209719742234591', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']],
        ];
    }

    public function test_the_next_sync_adds_a_newly_approved_templates_inactive_and_visible(): void
    {
        $this->synced('society_visitor_approved', 'APPROVED', ['external_id' => 'rid-society_visitor_approved']);
        $this->provider([$this->remote('society_visitor_approved', 'APPROVED'), $this->remote('society_visitor_arrived', 'APPROVED'), $this->remote('bookmizo_booking_confirm_v2', 'APPROVED')]);

        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'))->assertRedirect()->assertSessionHas('success');

        $new = WhatsAppTemplate::where('name', 'society_visitor_arrived')->firstOrFail();
        $this->assertSame('APPROVED', $new->status);
        $this->assertSame('synced', $new->source);
        $this->assertFalse($new->is_active, 'discovered, not switched on');
        $this->assertSame('visitor_arrival', $new->purpose, 'an arrival template is attached to the gate-request notification (never the approval one), switched off');
        $this->assertSame('Hi {{1}}', $new->body_text);
        $this->assertSame(1, $new->var_count);
        $this->assertStringContainsString('society_visitor_arrived', $this->tab());
        $this->assertStringNotContainsString('bookmizo_booking_confirm_v2', $this->tab(), 'another product\'s template is stored but not listed');
        $this->assertSame('GET /dev/dlt_manager/whatsapp', $this->calls[0]['sync'], 'a read-only request, nothing sent to anyone');
        $this->assertCount(1, $this->calls);
    }

    public function test_sync_keeps_everything_saved_and_deletes_nothing(): void
    {
        $mine = $this->synced('society_bill_generated', 'APPROVED', [
            'external_id' => 'rid-society_bill_generated', 'friendly_name' => 'My bill note', 'purpose' => 'bill', 'is_active' => true, 'language' => 'en_GB',
            'message_id' => '5555', 'var_count' => 2, 'body_text' => 'Dear {{1}}, bill {{2}}', 'variable_map' => ['resident_name', 'bill_ref'],
        ]);
        $gone = $this->synced('society_old_notice', 'APPROVED');
        $before = $mine->fresh()->only(['id', 'friendly_name', 'purpose', 'is_active', 'language', 'message_id', 'var_count', 'body_text', 'variable_map', 'name']);

        $this->provider([$this->remote('society_bill_generated', 'APPROVED', ['message_id' => '1', 'language' => 'en', 'var_count' => 1, 'components' => [['type' => 'BODY', 'text' => 'Different {{1}}']]])]);
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'))->assertSessionHas('success');

        $this->assertSame($before, $mine->fresh()->only(array_keys($before)), 'id, label, type, switch, language, Message ID, text and variables are all kept');
        $this->assertNotNull(WhatsAppTemplate::find($gone->id), 'a template the provider no longer lists is kept');
        $this->assertSame(2, WhatsAppTemplate::count());
    }

    public function test_sync_refreshes_status_fills_empty_fields_and_warns_about_a_switched_on_template_that_lost_approval(): void
    {
        $empty = $this->synced('society_complaint_update', 'PENDING', ['external_id' => 'rid-society_complaint_update']);
        $live = $this->synced('society_visitor_approved', 'APPROVED', ['external_id' => 'rid-society_visitor_approved', 'purpose' => 'visitor', 'is_active' => true, 'body_text' => 'Hi {{1}}', 'var_count' => 1, 'message_id' => '7']);

        $this->provider([$this->remote('society_complaint_update', 'APPROVED', ['message_id' => '88', 'var_count' => 2, 'components' => [['type' => 'BODY', 'text' => '{{1}} {{2}}']]]), $this->remote('society_visitor_approved', 'PAUSED')]);
        $response = $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'));

        $empty = $empty->fresh();
        $this->assertSame('APPROVED', $empty->status);
        $this->assertSame('88', $empty->message_id);
        $this->assertSame('{{1}} {{2}}', $empty->body_text);
        $this->assertSame(2, $empty->var_count);
        $this->assertSame('PAUSED', $live->fresh()->status);
        $this->assertTrue($live->fresh()->is_active, 'the switch is the admin\'s; the sender refuses it instead');
        $response->assertSessionHas('sync_warnings', fn ($warnings) => str_contains(implode(' ', $warnings), 'now PAUSED'));
    }

    public function test_a_hand_typed_template_found_at_the_provider_keeps_its_data_and_gains_the_provider_status(): void
    {
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.store'), [
            'name' => 'Mine', 'purpose' => 'notice', 'template_name' => 'society_important_notice', 'language' => 'en', 'status' => 'inactive', 'body_text' => 'My own wording {{1}}',
        ])->assertSessionHasNoErrors();
        $mine = WhatsAppTemplate::firstOrFail();

        $this->provider([$this->remote('society_important_notice', 'APPROVED', ['message_id' => '66'])]);
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'))->assertSessionHas('success');

        $mine = $mine->fresh();
        $this->assertSame(1, WhatsAppTemplate::count(), 'matched, not duplicated');
        $this->assertSame('Mine', $mine->friendly_name);
        $this->assertSame('My own wording {{1}}', $mine->body_text);
        $this->assertSame('APPROVED', $mine->status);
        $this->assertSame('66', $mine->message_id);
        $this->assertSame('manual', $mine->source);
    }

    public function test_sync_with_messaging_switched_off_contacts_nobody_and_says_so(): void
    {
        // no fake: the real client runs, and the server kill switch (off in the test environment) stops it before any request
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'))->assertSessionHas('info');

        Http::assertNothingSent();
        $this->assertSame(0, WhatsAppTemplate::count());
    }

    public function test_sync_needs_an_api_key_and_a_provider_failure_changes_nothing(): void
    {
        $this->synced('society_visitor_approved');
        SystemSetting::where('key', 'whatsapp_api_key')->delete();
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'))->assertSessionHas('error');

        $this->wa('whatsapp_api_key', 'wa-key-0123456789abcdefghij', 'encrypted');
        Fast2SmsAccount::$transportOverride = fn () => [['success' => false, 'message' => 'Invalid Authorization key'], 401];
        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'))->assertSessionHas('error');

        $this->assertSame(1, WhatsAppTemplate::count());
        $this->assertSame('APPROVED', WhatsAppTemplate::first()->status);
    }

    public function test_only_an_admin_can_sync_and_it_stays_inside_their_society(): void
    {
        $this->provider([$this->remote('society_visitor_arrived', 'APPROVED')]);

        foreach (['Tenant', 'Staff', 'Guard', 'Accountant'] as $role) {
            $this->actingAs($this->makeUserWithRole($this->society, $role))->post(route('admin.message-templates.whatsapp.sync'))->assertForbidden();
        }
        $this->assertSame(0, WhatsAppTemplate::count());

        $this->actingAs($this->admin)->post(route('admin.message-templates.whatsapp.sync'))->assertSessionHas('success');
        $this->assertSame([$this->society->id], WhatsAppTemplate::pluck('society_id')->unique()->values()->all());
    }

    public function test_the_page_does_not_claim_an_automatic_sync_exists(): void
    {
        $html = $this->tab();

        $this->assertStringContainsString('Sync from Fast2SMS', $html);
        $this->assertStringContainsString('there is no automatic sync', $html);
    }
}
