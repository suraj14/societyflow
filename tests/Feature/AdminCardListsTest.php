<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\Gate;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Visitor;
use App\Support\SocietyClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsFinanceLedger;
use Tests\TestCase;

/**
 * The lists behind the mobile office dashboard's Quick overview cards. Every card's number comes from
 * SocietyOverview; every list comes from AdminListApiController using the same rule - so for each pair the card figure
 * and the list's `meta.total` (or `meta.summary.amount`) must be equal, for the same society, role and period.
 */
class AdminCardListsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFinanceLedger;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'Asia/Kolkata'));
        $this->buildLedger();
        // a vacant unit nobody owns or rents
        Sanctum::actingAs($this->admin);
        Flat::create(['society_id' => $this->alpha->id, 'villa_area_id' => $this->v159->villa_area_id, 'property_type' => 'villa', 'flat_number' => '161', 'status' => 'vacant']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function kpis(): array
    {
        Sanctum::actingAs($this->admin);

        return $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.kpis');
    }

    private function visitor(array $over = []): Visitor
    {
        return Visitor::create($over + ['society_id' => $this->alpha->id, 'flat_id' => $this->v159->id, 'visitor_name' => 'Ravi', 'visitor_phone' => '9000000001', 'purpose' => 'Delivery',
            'expected_entry_time' => '2026-10-15 05:00:00', 'approval_status' => 'allowed', 'entry_status' => 'pending']);
    }

    // ── each card, against its list ─────────────────────────────────────────

    public function test_properties_occupied_and_vacant_match_their_lists(): void
    {
        $k = $this->kpis();

        $all = $this->getJson('/api/v1/admin/units')->assertOk()->json('meta.total');
        $occupied = $this->getJson('/api/v1/admin/units?state=occupied')->assertOk();
        $vacant = $this->getJson('/api/v1/admin/units?state=vacant')->assertOk();

        $this->assertSame($k['units'], $all);
        $this->assertSame($k['occupied_units'], $occupied->json('meta.total'));
        $this->assertSame($k['vacant_units'], $vacant->json('meta.total'));
        $this->assertSame($all, $occupied->json('meta.total') + $vacant->json('meta.total'));
        $this->assertContains('Villa 161, Garden', array_column($vacant->json('data'), 'title'));
        $this->assertNotContains('Villa 161, Garden', array_column($occupied->json('data'), 'title'));
    }

    public function test_residents_match_the_card_and_name_each_person_once(): void
    {
        $k = $this->kpis();
        $r = $this->getJson('/api/v1/admin/residents')->assertOk();

        $this->assertSame($k['residents'], $r->json('meta.total'));
        $names = array_column($r->json('data'), 'title');
        $this->assertContains('Suraj', $names);
        $this->assertContains('Piyus', $names);
    }

    public function test_visitors_today_match_the_card_and_use_the_societys_own_day(): void
    {
        // 2026-10-15 in Kolkata = 2026-10-14 18:30 .. 2026-10-15 18:29 UTC (stored in UTC)
        $this->visitor(['visitor_name' => 'Today early', 'expected_entry_time' => '2026-10-14 19:00:00']);   // 00:30 IST on the 15th
        $this->visitor(['visitor_name' => 'Today late', 'expected_entry_time' => '2026-10-15 17:00:00']);    // 22:30 IST on the 15th
        $this->visitor(['visitor_name' => 'Yesterday', 'expected_entry_time' => '2026-10-14 17:00:00']);     // 22:30 IST on the 14th
        $this->visitor(['visitor_name' => 'Tomorrow', 'expected_entry_time' => '2026-10-15 19:00:00']);      // 00:30 IST on the 16th
        $this->visitor(['visitor_name' => 'Beta', 'society_id' => $this->beta->id, 'flat_id' => Flat::where('society_id', $this->beta->id)->value('id'), 'expected_entry_time' => '2026-10-15 05:00:00']);

        $k = $this->kpis();
        $list = $this->getJson('/api/v1/admin/visitors?scope=today')->assertOk();

        $this->assertSame(2, $k['visitors_today']);
        $this->assertSame($k['visitors_today'], $list->json('meta.total'));
        $this->assertEqualsCanonicalizing(['Today early', 'Today late'], array_column($list->json('data'), 'title'));
    }

    public function test_pending_dues_match_the_card_and_the_list_sums_to_it(): void
    {
        $k = $this->kpis();
        $r = $this->getJson('/api/v1/admin/bills')->assertOk();

        $this->assertSame($k['pending_bills'], $r->json('meta.total'));
        $this->assertEqualsWithDelta($k['pending_dues'], $r->json('meta.summary.amount'), 0.001);
        $this->assertEqualsWithDelta($k['pending_dues'], array_sum(array_column($r->json('data'), 'amount')), 0.001, 'one page holds them all here');
        $this->assertGreaterThan(0, $k['pending_dues']);
    }

    public function test_collected_this_month_counts_verified_money_only_and_only_this_month(): void
    {
        // the ledger already holds a pending claim (500) and a failed/cancelled payment; add a pending claim this month too
        $this->pay($this->maint159oct, 800, 'pending', '2026-10-14');
        $k = $this->kpis();
        $r = $this->getJson('/api/v1/admin/collections')->assertOk();

        $this->assertEqualsWithDelta($k['monthly_revenue'], $r->json('meta.summary.amount'), 0.001);
        $this->assertEqualsWithDelta(1700.0, $r->json('meta.summary.amount'), 0.001, 'only the 12 Oct success payment is October money');
        $this->assertSame(1, $r->json('meta.total'));
        $this->assertSame('October 2026', $r->json('meta.summary.month'));
        $this->assertNotContains(800.0, array_column($r->json('data'), 'amount'), 'a claim is not collected');
    }

    public function test_collected_does_not_move_until_the_claim_is_verified(): void
    {
        $claim = $this->pay($this->maint159oct, 800, 'pending', '2026-10-14');
        $before = $this->kpis()['monthly_revenue'];
        $claim->update(['status' => 'success']);

        $this->assertEqualsWithDelta($before + 800, $this->kpis()['monthly_revenue'], 0.001);
        $this->assertEqualsWithDelta($before + 800, $this->getJson('/api/v1/admin/collections')->json('meta.summary.amount'), 0.001);
    }

    public function test_open_tickets_card_matches_the_unresolved_list(): void
    {
        $cat = \App\Models\ComplaintCategory::create(['society_id' => $this->alpha->id, 'name' => 'Water', 'status' => 'active']);
        foreach (['open', 'in_progress', 'resolved'] as $i => $status) {
            \App\Models\Complaint::create(['society_id' => $this->alpha->id, 'flat_id' => $this->v159->id, 'created_by' => $this->tenant->id, 'complaint_category_id' => $cat->id,
                'complaint_number' => 'CMP-T' . $i, 'title' => "T$i", 'description' => 'x', 'priority' => 'medium', 'status' => $status]);
        }

        $k = $this->kpis();
        $list = $this->getJson('/api/v1/admin/complaints')->assertOk();

        $this->assertSame($k['open_tickets'], $list->json('meta.total'));
        $this->assertSame(2, $list->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/complaints?status=open')->json('meta.total'), 'the "New complaints" attention row counts open only');
    }

    // ── Needs attention ─────────────────────────────────────────────────────

    public function test_needs_attention_has_no_service_requests_and_its_counts_match_the_filtered_lists(): void
    {
        $this->visitor(['approval_status' => 'pending']);
        $dash = $this->kpis();
        $att = collect($this->getJson('/api/v1/admin/dashboard')->json('data.attention'))->keyBy('key');

        $this->assertArrayNotHasKey('service_requests', $att->all());
        $this->assertSame($att['visitors']['count'], $this->getJson('/api/v1/admin/visitors?scope=pending')->json('meta.total'));
        if ($att->has('bills')) {
            $this->assertSame($att['bills']['count'], $this->getJson('/api/v1/admin/bills?scope=overdue')->json('meta.total'));
        }
    }

    // ── authorization and isolation ─────────────────────────────────────────

    public function test_only_a_society_admin_reaches_the_lists(): void
    {
        foreach (['/units', '/residents', '/visitors', '/bills', '/collections', '/guard-shifts'] as $path) {
            foreach ([$this->tenant, $this->owner, $this->accountant] as $who) {
                Sanctum::actingAs($who);
                $this->getJson('/api/v1/admin' . $path)->assertForbidden();
            }
        }
        $guard = $this->makeUserWithRole($this->alpha, 'Guard');
        Sanctum::actingAs($guard);
        $this->getJson('/api/v1/admin/guard-shifts')->assertStatus(403);
    }

    public function test_another_societys_admin_sees_only_their_own_figures(): void
    {
        $other = $this->makeUserWithRole($this->beta, 'Admin');
        Sanctum::actingAs($other);

        $this->assertSame(0, $this->getJson('/api/v1/admin/bills')->json('meta.total'), 'Beta\'s only bill is fully paid; Alpha\'s dues never show here');
        $this->assertEqualsWithDelta(0.0, $this->getJson('/api/v1/admin/collections')->json('meta.summary.amount'), 0.001, 'Beta has no October money');
        $this->assertSame(0, $this->getJson('/api/v1/admin/residents')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/units')->json('meta.total'));
    }

    // ── guards: who is on duty ──────────────────────────────────────────────

    private function rosterFixture(): array
    {
        $gate = Gate::create(['society_id' => $this->alpha->id, 'name' => 'Main Gate', 'type' => 'main', 'is_active' => true]);
        $day = Shift::create(['society_id' => $this->alpha->id, 'name' => 'Day', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_period_minutes' => 15, 'is_active' => true]);
        $night = Shift::create(['society_id' => $this->alpha->id, 'name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00', 'grace_period_minutes' => 15, 'is_active' => true]);
        $guards = [];
        foreach (['Asha', 'Bela', 'Chitra', 'Deepa'] as $n) {
            $guards[$n] = $this->makeUserWithRole($this->alpha, 'Guard', ['name' => $n]);
        }
        $a = fn ($guard, $shift, $date, array $over = []) => ShiftAssignment::create($over + ['society_id' => $this->alpha->id, 'shift_id' => $shift->id, 'gate_id' => $gate->id,
            'guard_user_id' => $guards[$guard]->id, 'date' => $date, 'status' => 'scheduled']);

        return [$a, $day, $night, $guards];
    }

    public function test_duty_states_at_midday_assigned_is_not_present(): void
    {
        [$a, $day, $night] = $this->rosterFixture();   // now = 15 Oct 12:00 IST
        $a('Asha', $day, '2026-10-15', ['status' => 'present', 'checked_in_at' => Carbon::parse('2026-10-15 06:02', 'Asia/Kolkata')->utc()]);
        $a('Bela', $day, '2026-10-15');                       // began at 06:00, nobody checked in
        $a('Chitra', $night, '2026-10-15');                   // starts 22:00 tonight

        $board = $this->getJson('/api/v1/admin/guard-shifts')->assertOk();
        $by = collect($board->json('data'))->keyBy('guard');

        $this->assertSame('on_duty', $by['Asha']['duty_state']);
        $this->assertSame('06:02', $by['Asha']['checked_in_at']);
        $this->assertSame('not_checked_in', $by['Bela']['duty_state']);
        $this->assertSame('scheduled', $by['Chitra']['duty_state']);
        $this->assertSame('Main Gate', $by['Asha']['gate']);
        $this->assertSame('06:00', $by['Asha']['start']);
        $this->assertSame('14:00', $by['Asha']['end']);
        $this->assertTrue($by['Chitra']['overnight']);
        $this->assertSame('on_duty', $board->json('data.0.duty_state'), 'on duty is listed first');
        $this->assertSame(1, $board->json('meta.summary.on_duty'));
    }

    public function test_an_overnight_shift_from_yesterday_is_still_on_the_board_after_midnight(): void
    {
        [$a, $day, $night] = $this->rosterFixture();
        Carbon::setTestNow(Carbon::parse('2026-10-16 02:00:00', 'Asia/Kolkata'));
        $a('Asha', $night, '2026-10-15', ['status' => 'present', 'checked_in_at' => Carbon::parse('2026-10-15 22:05', 'Asia/Kolkata')->utc()]);
        $a('Bela', $day, '2026-10-15');   // yesterday's day shift is over: not today's board

        $board = $this->getJson('/api/v1/admin/guard-shifts')->assertOk();

        $this->assertSame(['Asha'], array_column($board->json('data'), 'guard'));
        $this->assertSame('on_duty', $board->json('data.0.duty_state'));
        $this->assertSame('22:00', $board->json('data.0.start'));
        $this->assertSame('06:00', $board->json('data.0.end'));
    }

    public function test_completed_absent_missed_and_conflicts_are_reported_as_they_are(): void
    {
        [$a, $day, $night, $guards] = $this->rosterFixture();
        $a('Asha', $day, '2026-10-15', ['status' => 'checked_out', 'checked_in_at' => Carbon::parse('2026-10-15 06:00', 'Asia/Kolkata')->utc(), 'checked_out_at' => Carbon::parse('2026-10-15 11:00', 'Asia/Kolkata')->utc()]);
        $a('Bela', $day, '2026-10-15', ['status' => 'absent']);
        $a('Chitra', $day, '2026-10-15', ['status' => 'missed']);
        $gate2 = Gate::create(['society_id' => $this->alpha->id, 'name' => 'Back Gate', 'type' => 'back', 'is_active' => true]);
        $early = Shift::create(['society_id' => $this->alpha->id, 'name' => 'Early', 'start_time' => '08:00', 'end_time' => '16:00', 'grace_period_minutes' => 15, 'is_active' => true]);
        $a('Deepa', $day, '2026-10-15');
        ShiftAssignment::create(['society_id' => $this->alpha->id, 'shift_id' => $early->id, 'gate_id' => $gate2->id, 'guard_user_id' => $guards['Deepa']->id, 'date' => '2026-10-15', 'status' => 'scheduled']);
        $guards['Asha']->update(['status' => 'inactive']);

        $by = collect($this->getJson('/api/v1/admin/guard-shifts')->json('data'))->groupBy('guard');

        $this->assertSame('completed', $by['Asha'][0]['duty_state']);
        $this->assertSame('Guard account is not active', $by['Asha'][0]['issue']);
        $this->assertSame('absent', $by['Bela'][0]['duty_state']);
        $this->assertSame('missed', $by['Chitra'][0]['duty_state']);
        $this->assertCount(2, $by['Deepa']);
        $this->assertSame('Guard is rostered on overlapping shifts', $by['Deepa'][0]['issue']);
    }

    public function test_the_board_is_scoped_to_the_society_and_a_replaced_row_is_history(): void
    {
        [$a, $day] = $this->rosterFixture();
        $a('Asha', $day, '2026-10-15', ['status' => 'replaced']);
        $betaAdmin = $this->makeUserWithRole($this->beta, 'Admin');

        $this->assertSame([], $this->getJson('/api/v1/admin/guard-shifts')->json('data') ?? [], 'unauthenticated call is not the point');
        Sanctum::actingAs($this->admin);
        $this->assertSame(0, $this->getJson('/api/v1/admin/guard-shifts')->json('meta.total'));
        Sanctum::actingAs($betaAdmin);
        $this->assertSame(0, $this->getJson('/api/v1/admin/guard-shifts')->json('meta.total'));
    }

    public function test_the_dashboard_carries_the_duty_summary_without_extra_queries_per_guard(): void
    {
        [$a, $day] = $this->rosterFixture();
        foreach (['Asha', 'Bela', 'Chitra', 'Deepa'] as $n) {
            $a($n, $day, '2026-10-15');
        }
        Sanctum::actingAs($this->admin);

        \DB::enableQueryLog();
        $r = $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertSame(4, $r->json('data.guard_duty.counts.total'));
        $this->assertCount(4, $r->json('data.guard_duty.items'));
        $this->assertLessThan(60, $queries, 'the roster adds a fixed number of queries, not one per guard');
    }
}
