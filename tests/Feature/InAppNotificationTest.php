<?php

namespace Tests\Feature;

use App\Events\BillGenerated;
use App\Events\FacilityBookingStatusUpdated;
use App\Events\NoticePublished;
use App\Events\PaymentReceived;
use App\Events\TicketUpdated;
use App\Events\VisitorApproved;
use App\Jobs\DispatchEventNotification;
use App\Models\Complaint;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\MaintenanceBill;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Visitor;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;
use App\Support\InAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The header bell: each user sees only their own entries, and each business
 * event puts an entry in exactly the bells of the people its email reaches.
 * Email jobs are faked here (covered elsewhere); WhatsApp is exercised for real
 * through its transport seam.
 */
class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([DispatchEventNotification::class]);
    }

    protected function tearDown(): void
    {
        WhatsAppService::$transportOverride = null;
        parent::tearDown();
    }

    private function titles(User $user): array
    {
        return $user->fresh()->notifications()->pluck('data')->map(fn ($d) => $d['title'])->all();
    }

    // ── The bell itself ─────────────────────────────────────────────────────

    public function test_feed_shows_only_the_users_own_notifications_with_unread_count(): void
    {
        $s = $this->makeSociety();
        $me = $this->makeUserWithRole($s, 'Tenant');
        $other = $this->makeUserWithRole($s, 'Tenant');
        InAppNotifier::send([$me], 'notice', 'Mine one', 'body', '/notices');
        InAppNotifier::send([$me], 'notice', 'Mine two', 'body');
        InAppNotifier::send([$other], 'notice', 'Not mine', 'body');

        $feed = $this->actingAs($me)->getJson(route('notifications.feed'))->assertOk();

        $this->assertSame(2, $feed->json('unread_count'));
        $this->assertEqualsCanonicalizing(['Mine one', 'Mine two'], array_column($feed->json('items'), 'title'));
    }

    public function test_marking_read_works_only_on_your_own_notifications(): void
    {
        $s = $this->makeSociety();
        $me = $this->makeUserWithRole($s, 'Tenant');
        $other = $this->makeUserWithRole($s, 'Tenant');
        InAppNotifier::send([$me], 'notice', 'Mine', 'body', url('/notices'));
        InAppNotifier::send([$other], 'notice', 'Theirs', 'body');
        $mine = $me->notifications()->first();
        $theirs = $other->notifications()->first();

        $this->actingAs($me)->postJson(route('notifications.read', $theirs->id))->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);

        $this->actingAs($me)->postJson(route('notifications.read', $mine->id))->assertOk()->assertJson(['unread_count' => 0]);
        $this->assertNotNull($mine->fresh()->read_at);

        // Non-JSON click follows the link inside the app.
        InAppNotifier::send([$me], 'notice', 'Second', 'body', url('/notices'));
        $this->actingAs($me)->post(route('notifications.read', $me->unreadNotifications()->first()->id))->assertRedirect(url('/notices'));
    }

    public function test_mark_all_read_touches_only_the_current_user(): void
    {
        $s = $this->makeSociety();
        $me = $this->makeUserWithRole($s, 'Tenant');
        $other = $this->makeUserWithRole($s, 'Tenant');
        InAppNotifier::send([$me, $other], 'notice', 'Shared notice', 'body');

        $this->actingAs($me)->postJson(route('notifications.read-all'))->assertOk();

        $this->assertSame(0, $me->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_view_all_page_and_header_bell_render(): void
    {
        $s = $this->makeSociety();
        $me = $this->makeUserWithRole($s, 'Admin');
        InAppNotifier::send([$me], 'payment', 'Payment received', "\u{20B9}500.00 received.");

        $this->actingAs($me)->get(route('notifications.index'))->assertOk()->assertSee('Payment received')->assertSee('Mark all as read');
        $this->actingAs($me)->get(route('dashboard'))->assertOk()->assertSee('aria-label="Notifications"', false);
    }

    public function test_guard_bell_is_reachable_and_scoped(): void
    {
        $s = $this->makeSociety();
        $guard = $this->makeUserWithRole($s, 'Guard');
        InAppNotifier::send([$guard], 'document', 'New society document', 'Gate SOP v2');

        $this->actingAs($guard)->getJson(route('notifications.feed'))->assertOk()->assertJson(['unread_count' => 1]);
        $this->actingAs($guard)->get(route('guard.dashboard'))->assertOk()->assertSee('aria-label="Notifications"', false);
    }

    public function test_api_lists_and_marks_the_callers_notifications(): void
    {
        $s = $this->makeSociety();
        $me = $this->makeUserWithRole($s, 'Tenant');
        $other = $this->makeUserWithRole($s, 'Tenant');
        InAppNotifier::send([$me], 'bill', 'New bill', "A bill of \u{20B9}2,000.00 has been generated.");
        InAppNotifier::send([$other], 'bill', 'Their bill', 'x');
        Sanctum::actingAs($me);

        $list = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertSame(['New bill'], array_column($list->json('data'), 'title'));
        $this->assertSame(1, $list->json('meta.unread_count'));

        $this->postJson('/api/v1/notifications/' . $other->notifications()->first()->id . '/read')->assertNotFound();
        $this->postJson('/api/v1/notifications/' . $me->notifications()->first()->id . '/read')->assertOk()->assertJsonPath('data.unread_count', 0);
    }

    // ── Business events reach exactly the right bells ──────────────────────

    public function test_notice_reaches_its_society_and_audience_only(): void
    {
        $a = $this->makeSociety('A');
        $b = $this->makeSociety('B');
        $tenantA = $this->makeUserWithRole($a, 'Tenant');
        $ownerA = $this->makeUserWithRole($a, 'Villa Owner');
        $adminA = $this->makeUserWithRole($a, 'Admin');
        $staffA = $this->makeUserWithRole($a, 'Staff');
        $tenantB = $this->makeUserWithRole($b, 'Tenant');

        $notice = (new Notice())->forceFill(['id' => 41, 'society_id' => $a->id, 'title' => 'Water cut on Sunday', 'target_audience' => null]);
        event(new NoticePublished($notice));

        foreach ([$tenantA, $ownerA, $adminA] as $u) {
            $this->assertSame(['New notice'], $this->titles($u));
        }
        $this->assertSame([], $this->titles($staffA));
        $this->assertSame([], $this->titles($tenantB), 'another society never sees it');

        $tenantOnly = (new Notice())->forceFill(['id' => 42, 'society_id' => $a->id, 'title' => 'Tenants meeting', 'target_audience' => ['Tenant']]);
        event(new NoticePublished($tenantOnly));
        $this->assertCount(2, $this->titles($tenantA));
        $this->assertCount(1, $this->titles($ownerA), 'audience-targeted notice skips other roles');
    }

    public function test_visitor_complaint_payment_and_booking_go_to_their_own_resident(): void
    {
        $s = $this->makeSociety();
        $resident = $this->makeUserWithRole($s, 'Tenant');
        $neighbour = $this->makeUserWithRole($s, 'Tenant');
        $admin = $this->makeUserWithRole($s, 'Admin');

        event(new VisitorApproved((new Visitor())->forceFill(['id' => 7, 'society_id' => $s->id, 'created_by' => $resident->id, 'visitor_name' => 'Ramesh', 'expected_entry_time' => now()])));
        event(new TicketUpdated((new Complaint())->forceFill(['id' => 8, 'society_id' => $s->id, 'created_by' => $resident->id, 'complaint_number' => 'CMP0008', 'title' => 'Leak', 'status' => 'in_progress'])));
        event(new PaymentReceived((new Payment())->forceFill(['id' => 9, 'society_id' => $s->id, 'user_id' => $resident->id, 'amount' => 1500, 'payment_date' => now()])));
        $booking = (new FacilityBooking())->forceFill(['id' => 10, 'society_id' => $s->id, 'user_id' => $resident->id, 'status' => 'approved', 'booking_date' => now()]);
        $booking->setRelation('user', $resident);
        $booking->setRelation('facility', (new Facility())->forceFill(['name' => 'Clubhouse']));
        event(new FacilityBookingStatusUpdated($booking, 'pending'));

        $this->assertEqualsCanonicalizing(['Visitor approved', 'Complaint updated', 'Payment received', 'Booking Approved'], $this->titles($resident));
        $this->assertSame([], $this->titles($neighbour), "a neighbour never gets another resident's private updates");
        $this->assertSame(['Payment received'], $this->titles($admin), 'admins see payments come in, not private visitor/complaint updates');
    }

    public function test_bill_reaches_only_the_residents_of_that_unit(): void
    {
        $s = $this->makeSociety();
        $building = $this->makeBuilding($s);
        $flat159 = $this->makeFlat($s, $building, '159');
        $flat160 = $this->makeFlat($s, $building, '160');
        $r159 = $this->makeResidentUser($s, $flat159);
        $r160 = $this->makeResidentUser($s, $flat160);
        $this->makeRole('Tenant');
        $r159->assignRole('Tenant');
        $r160->assignRole('Tenant');

        event(new BillGenerated((new MaintenanceBill())->forceFill(['id' => 11, 'society_id' => $s->id, 'flat_id' => $flat159->id, 'total_amount' => 2500, 'due_date' => now()->addDays(10), 'bill_number' => 'MB0011'])));

        $this->assertSame(['New bill'], $this->titles($r159));
        $this->assertSame([], $this->titles($r160), 'Villa 160 never sees Villa 159\'s bill');
    }

    public function test_a_business_event_sends_whatsapp_with_the_assigned_template_and_mapping(): void
    {
        $s = $this->makeSociety('Royal Eden');
        $payer = $this->makeUserWithRole($s, 'Tenant', ['phone' => '9876543210']);
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $s->id);
        SystemSetting::set('whatsapp', 'whatsapp_api_key', 'KEY-123', 'encrypted', $s->id);
        SystemSetting::set('whatsapp', 'whatsapp_phone_number_id', '7001', 'string', $s->id);
        WhatsAppTemplate::create(['society_id' => $s->id, 'external_id' => 'm1', 'name' => 'payment_completed', 'status' => 'APPROVED', 'language' => 'en', 'var_count' => 1, 'body_text' => 'amount: {{1}}']);
        SystemSetting::set('whatsapp', 'whatsapp_payment_template_name', 'payment_completed', 'string', $s->id);
        SystemSetting::set('whatsapp', 'whatsapp_payment_variables', ['amount'], 'json', $s->id);

        $sent = [];
        WhatsAppService::$transportOverride = function ($url, $data) use (&$sent) { $sent[] = $data; return [['messages' => [['id' => 'wamid.x']]], 200]; };

        event(new PaymentReceived((new Payment())->forceFill(['id' => 12, 'society_id' => $s->id, 'user_id' => $payer->id, 'amount' => 1200, 'payment_date' => now()])));

        $this->assertCount(1, $sent, 'one WhatsApp, to the payer');
        $this->assertSame('919876543210', $sent[0]['to']);
        $this->assertSame('payment_completed', $sent[0]['template']['name']);
        $this->assertSame(["\u{20B9}1,200.00"], array_column($sent[0]['template']['components'][0]['parameters'], 'text'));
        $this->assertDatabaseHas('email_logs', ['society_id' => $s->id, 'channel' => 'whatsapp', 'trigger_action' => 'payment', 'status' => 'sent']);
    }

    public function test_no_whatsapp_is_attempted_for_a_type_without_a_template(): void
    {
        $s = $this->makeSociety();
        $payer = $this->makeUserWithRole($s, 'Tenant', ['phone' => '9876543210']);
        SystemSetting::set('whatsapp', 'whatsapp_enabled', true, 'boolean', $s->id);
        $called = false;
        WhatsAppService::$transportOverride = function () use (&$called) { $called = true; return [[], 200]; };

        event(new PaymentReceived((new Payment())->forceFill(['id' => 13, 'society_id' => $s->id, 'user_id' => $payer->id, 'amount' => 10])));

        $this->assertFalse($called);
        $this->assertDatabaseMissing('email_logs', ['channel' => 'whatsapp']);
        $this->assertSame(['Payment received'], $this->titles($payer), 'the bell still gets it');
    }
}
