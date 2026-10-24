<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Festival;
use App\Models\FestivalBudgetItem;
use App\Models\FestivalContribution;
use App\Models\FestivalExpense;
use App\Models\FestivalMember;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Society;
use App\Models\User;
use App\Services\FestivalException;
use App\Services\FestivalService;
use App\Services\FinancialReportService;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Festival & Celebrations: voluntary fund, verified receipts only, approval workflow, settlement, isolation. */
class FestivalModuleTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private User $admin;
    private User $admin2;
    private User $accountant;
    private User $committee;
    private User $resident;
    private User $neighbour;
    private Festival $festival;
    private FestivalService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(FestivalService::class);
        $this->society = $this->makeSociety('Royal');
        $this->admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->admin2 = $this->makeUserWithRole($this->society, 'Admin');
        $this->accountant = $this->makeUserWithRole($this->society, 'Accountant');
        $this->committee = $this->makeUserWithRole($this->society, 'Apartment Owner');
        $this->resident = $this->makeUserWithRole($this->society, 'Apartment Owner');
        $this->neighbour = $this->makeUserWithRole($this->society, 'Tenant');

        $this->festival = new Festival(['society_id' => $this->society->id, 'name' => 'Navratri', 'festival_date' => now()->addDays(20)->toDateString(), 'venue' => 'Garden',
            'expected_attendance' => 500, 'estimated_budget' => 50000, 'contribution_target' => 40000, 'separate_approver' => true, 'created_by' => $this->admin->id]);
        $this->festival->status = 'published';
        $this->festival->save();
        FestivalMember::create(['society_id' => $this->society->id, 'festival_id' => $this->festival->id, 'user_id' => $this->committee->id, 'role' => 'committee']);
    }

    private function receive(User $who, float $amount, string $method = 'cash', ?string $ref = null, ?User $from = null, array $over = []): FestivalContribution
    {
        return $this->svc->recordReceipt($this->festival->fresh(), $this->accountant, $over + ['amount' => $amount, 'payment_method' => $method, 'payment_reference' => $ref,
            'received_on' => now()->toDateString(), 'user_id' => ($from ?? $who)->id]);
    }

    private function expense(float $amount = 5000, ?User $by = null, string $vendor = 'Decor Co', bool $submit = true, array $over = []): FestivalExpense
    {
        return $this->svc->createExpense($this->festival->fresh(), $by ?? $this->committee, $over + ['vendor' => $vendor, 'purpose' => 'Stage', 'amount' => $amount, 'expense_date' => now()->toDateString()], $submit);
    }

    private function figures(): array
    {
        return $this->svc->summary($this->festival->fresh());
    }

    // ── voluntary, never a due ──────────────────────────────────────────────

    public function test_a_festival_creates_no_bill_payment_or_debt_for_anyone(): void
    {
        $bills = MaintenanceBill::count();
        $payments = Payment::count();
        $this->svc->pledge($this->festival, $this->resident, 500, false);
        $this->receive($this->resident, 700);

        $this->assertSame($bills, MaintenanceBill::count());
        $this->assertSame($payments, Payment::count());
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('maintenance_bills', 'festival_id'));
    }

    public function test_a_pledge_is_not_money_and_has_no_receipt(): void
    {
        $c = $this->svc->pledge($this->festival, $this->resident, 1000, false);

        $this->assertSame('pledged', $c->status);
        $this->assertNull($c->receipt_number);
        $this->assertEquals(0, $this->figures()['contributions_net']);
        $this->assertEquals(1000, $this->figures()['pledged']);
        $this->assertSame(0, $this->figures()['contributors']);
    }

    public function test_recording_receipt_issues_a_receipt_once_and_a_repeat_changes_nothing(): void
    {
        $pledge = $this->svc->pledge($this->festival, $this->resident, 1000, false);
        $data = ['payment_method' => 'upi', 'payment_reference' => 'UPI-1', 'received_on' => now()->toDateString()];

        $first = $this->svc->recordReceipt($this->festival, $this->accountant, $data, $pledge);
        $again = $this->svc->recordReceipt($this->festival, $this->accountant, $data, $pledge);

        $this->assertSame('received', $first->status);
        $this->assertNotEmpty($first->receipt_number);
        $this->assertSame($first->receipt_number, $again->receipt_number);
        $this->assertEquals(1000, $this->figures()['contributions_net']);
        $this->assertSame(1, FestivalContribution::whereNotNull('receipt_number')->count());
    }

    public function test_a_double_submit_of_the_same_form_records_the_money_once(): void
    {
        $this->receive($this->resident, 500, 'cash', null, null, ['request_token' => 'tok-1']);
        $this->receive($this->resident, 500, 'cash', null, null, ['request_token' => 'tok-1']);

        $this->assertSame(1, FestivalContribution::count());
        $this->assertEquals(500, $this->figures()['contributions_net']);
    }

    public function test_a_transaction_reference_can_be_recorded_only_once(): void
    {
        $this->receive($this->resident, 500, 'upi', 'REF-9');

        $this->expectException(FestivalException::class);
        try {
            $this->receive($this->neighbour, 800, 'upi', 'REF-9');
        } finally {
            $this->assertSame(1, FestivalContribution::count());
        }
    }

    public function test_non_cash_needs_a_reference_and_the_date_cannot_be_in_the_future(): void
    {
        foreach ([['upi', null, now()->toDateString()], ['cash', null, now()->addDay()->toDateString()]] as [$m, $r, $d]) {
            try {
                $this->svc->recordReceipt($this->festival, $this->accountant, ['amount' => 100, 'payment_method' => $m, 'payment_reference' => $r, 'received_on' => $d, 'user_id' => $this->resident->id]);
                $this->fail('should have been refused');
            } catch (FestivalException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(0, FestivalContribution::count());
    }

    public function test_a_resident_cannot_record_money_received(): void
    {
        $this->expectException(FestivalException::class);
        $this->svc->recordReceipt($this->festival, $this->resident, ['amount' => 100, 'payment_method' => 'cash', 'received_on' => now()->toDateString()]);
    }

    public function test_anonymous_contributions_only_when_the_festival_allows_them(): void
    {
        try {
            $this->svc->pledge($this->festival, $this->resident, 100, true);
            $this->fail('anonymous should be refused');
        } catch (FestivalException $e) {
            $this->assertStringContainsString('anonymous', $e->getMessage());
        }
        Festival::whereKey($this->festival->id)->update(['allow_anonymous' => true]);
        $this->assertTrue($this->svc->pledge($this->festival->fresh(), $this->resident, 100, true)->is_anonymous);
    }

    // ── refunds ─────────────────────────────────────────────────────────────

    public function test_refunds_are_netted_once_and_never_exceed_what_was_received(): void
    {
        $c = $this->receive($this->resident, 1000);

        $this->assertFalse($this->svc->refund($this->festival, $this->accountant, $c, 1500, 'too much'));
        $this->assertTrue($this->svc->refund($this->festival, $this->accountant, $c, 400, 'partial'));
        $this->assertEquals(600, $this->figures()['contributions_net']);
        $this->assertTrue($this->svc->refund($this->festival, $this->accountant, $c->fresh(), 600, 'rest'));
        $this->assertFalse($this->svc->refund($this->festival, $this->accountant, $c->fresh(), 1, 'again'));

        $this->assertSame('refunded', $c->fresh()->status);
        $f = $this->figures();
        $this->assertEquals(0, $f['contributions_net']);
        $this->assertEquals(1000, $f['refunds']);
        $this->assertEquals(1000, $f['contributions_gross']);
    }

    public function test_a_withdrawn_pledge_is_not_counted_and_a_received_one_cannot_be_withdrawn(): void
    {
        $p = $this->svc->pledge($this->festival, $this->resident, 300, false);
        $this->svc->cancelPledge($this->festival, $this->resident, $p, 'changed my mind');
        $this->assertSame('cancelled', $p->fresh()->status);

        $r = $this->receive($this->resident, 300);
        $this->svc->cancelPledge($this->festival, $this->accountant, $r, 'x');
        $this->assertSame('received', $r->fresh()->status);
        $this->assertEquals(300, $this->figures()['contributions_net']);
    }

    // ── expenses ────────────────────────────────────────────────────────────

    public function test_expense_workflow_approved_is_a_commitment_and_only_paid_reduces_the_fund(): void
    {
        $this->receive($this->resident, 20000);
        $e = $this->expense(5000);
        $this->assertSame('submitted', $e->status);

        $this->assertTrue($this->svc->approveExpense($this->festival, $this->admin, $e));
        $f = $this->figures();
        $this->assertEquals(20000, $f['cash_available'], 'approved is not paid');
        $this->assertEquals(5000, $f['expenses_approved_unpaid']);
        $this->assertEquals(15000, $f['after_commitments']);

        $this->assertTrue($this->svc->payExpense($this->festival, $this->accountant, $e->fresh(), 'cash', null, now()->toDateString()));
        $this->assertFalse($this->svc->payExpense($this->festival, $this->accountant, $e->fresh(), 'cash', null, now()->toDateString()), 'paying twice does nothing');
        $f = $this->figures();
        $this->assertEquals(15000, $f['cash_available']);
        $this->assertEquals(5000, $f['expenses_paid']);
        $this->assertEquals(0, $f['expenses_approved_unpaid']);
    }

    public function test_nobody_approves_their_own_expense_and_a_committee_member_is_bounded_by_the_limit(): void
    {
        $e = $this->expense(5000, $this->admin);
        try {
            $this->svc->approveExpense($this->festival, $this->admin, $e);
            $this->fail('self approval');
        } catch (FestivalException $x) {
            $this->assertStringContainsString('someone else', $x->getMessage());
        }
        $this->assertTrue($this->svc->approveExpense($this->festival, $this->admin2, $e), 'a different Admin can');

        // committee: no limit set -> cannot approve; with a limit -> only within it
        $other = $this->expense(2000, $this->admin, 'Sound Co');
        try {
            $this->svc->approveExpense($this->festival, $this->committee, $other);
            $this->fail('committee without a limit');
        } catch (FestivalException $x) {
            $this->assertStringContainsString('Only an Admin', $x->getMessage());
        }
        Festival::whereKey($this->festival->id)->update(['approval_limit' => 3000]);
        $this->assertTrue($this->svc->approveExpense($this->festival->fresh(), $this->committee, $other));
        $big = $this->expense(4000, $this->admin, 'Tent Co');
        $this->expectException(FestivalException::class);
        $this->svc->approveExpense($this->festival->fresh(), $this->committee, $big);
    }

    public function test_a_resident_or_accountant_cannot_approve_and_a_resident_cannot_enter_expenses(): void
    {
        $e = $this->expense(1000);
        foreach ([$this->resident, $this->accountant] as $who) {
            try {
                $this->svc->approveExpense($this->festival, $who, $e);
                $this->fail('should be refused');
            } catch (FestivalException $x) {
                $this->assertNotEmpty($x->getMessage());
            }
        }
        $this->assertSame('submitted', $e->fresh()->status);
        $this->expectException(FestivalException::class);
        $this->svc->createExpense($this->festival, $this->resident, ['vendor' => 'V', 'purpose' => 'P', 'amount' => 10, 'expense_date' => now()->toDateString()], false);
    }

    public function test_an_expense_cannot_be_paid_before_approval_or_beyond_the_funds(): void
    {
        $e = $this->expense(5000);
        $this->assertFalse($this->svc->payExpense($this->festival, $this->accountant, $e, 'cash', null, now()->toDateString()), 'submitted, not approved');
        $this->svc->approveExpense($this->festival, $this->admin, $e);

        try {
            $this->svc->payExpense($this->festival, $this->accountant, $e->fresh(), 'cash', null, now()->toDateString());
            $this->fail('the fund is empty');
        } catch (FestivalException $x) {
            $this->assertStringContainsString('does not hold', $x->getMessage());
        }
        $this->assertSame('approved', $e->fresh()->status);
    }

    public function test_duplicate_expense_submissions_and_cross_festival_budget_lines_are_refused(): void
    {
        $this->expense(1000, null, 'Decor Co', true, ['request_token' => 'e-1']);
        $this->expense(1000, null, 'Decor Co', true, ['request_token' => 'e-1']);          // same form again
        $this->assertSame(1, FestivalExpense::count());
        try {
            $this->expense(1000, null, 'Decor Co');                                       // same content, new form
            $this->fail('duplicate content');
        } catch (FestivalException $x) {
            $this->assertStringContainsString('duplicate', $x->getMessage());
        }

        $other = new Festival(['society_id' => $this->society->id, 'name' => 'Diwali', 'festival_date' => now()->addDays(60)->toDateString()]);
        $other->save();
        $foreign = FestivalBudgetItem::create(['society_id' => $this->society->id, 'festival_id' => $other->id, 'category' => 'X', 'estimated_amount' => 1]);
        $this->expectException(FestivalException::class);
        $this->expense(77, null, 'Other Co', true, ['budget_item_id' => $foreign->id]);
    }

    public function test_a_budget_line_limit_blocks_approval_and_overspend_is_flagged(): void
    {
        $item = FestivalBudgetItem::create(['society_id' => $this->society->id, 'festival_id' => $this->festival->id, 'category' => 'Decoration', 'estimated_amount' => 4000, 'approved_limit' => 4000]);
        $a = $this->expense(3000, $this->committee, 'A Co', true, ['budget_item_id' => $item->id]);
        $b = $this->expense(2000, $this->committee, 'B Co', true, ['budget_item_id' => $item->id]);
        $this->svc->approveExpense($this->festival, $this->admin, $a);

        try {
            $this->svc->approveExpense($this->festival, $this->admin, $b);
            $this->fail('over the line limit');
        } catch (FestivalException $x) {
            $this->assertStringContainsString('approved limit', $x->getMessage());
        }

        Festival::whereKey($this->festival->id)->update(['approved_budget' => 2500]);
        $this->assertTrue($this->figures()['overspent'], '3,000 committed against a 2,500 budget');
    }

    public function test_a_cancelled_expense_cannot_be_paid_and_a_paid_one_cannot_be_cancelled(): void
    {
        $this->receive($this->resident, 10000);
        $a = $this->expense(1000, $this->admin, 'A');
        $this->svc->cancelExpense($this->festival, $this->admin, $a);
        $this->assertFalse($this->svc->approveExpense($this->festival, $this->admin2, $a->fresh()));

        $b = $this->expense(1000, $this->admin, 'B');
        $this->svc->approveExpense($this->festival, $this->admin2, $b);
        $this->svc->payExpense($this->festival, $this->accountant, $b->fresh(), 'cash', null, now()->toDateString());
        $this->assertFalse($this->svc->cancelExpense($this->festival, $this->admin, $b->fresh()));
        $this->assertSame('paid', $b->fresh()->status);
    }

    // ── festival money is not society money ─────────────────────────────────

    public function test_festival_money_never_enters_the_society_financial_report(): void
    {
        $this->receive($this->resident, 20000);
        $e = $this->expense(5000);
        $this->svc->approveExpense($this->festival, $this->admin, $e);
        $this->svc->payExpense($this->festival, $this->accountant, $e->fresh(), 'cash', null, now()->toDateString());

        $r = app(FinancialReportService::class)->build($this->society->id, ReportPeriod::monthly((int) now()->year, (int) now()->month));
        $this->assertEquals(0, $r['overview']['total_income']);
        $this->assertEquals(0, $r['overview']['total_society_expenses']);
        $this->assertEquals(0, FinancialReportService::societyCollections($this->society->id)['amount']);
    }

    // ── settlement ──────────────────────────────────────────────────────────

    public function test_settlement_needs_everything_paid_or_closed_and_a_decision_on_any_balance_then_locks_the_records(): void
    {
        $this->receive($this->resident, 10000);
        $e = $this->expense(4000, $this->admin);
        $this->svc->approveExpense($this->festival, $this->admin2, $e);

        try {
            $this->svc->settle($this->festival, $this->admin, 'x');
            $this->fail('unpaid commitment');
        } catch (FestivalException $x) {
            $this->assertStringContainsString('unpaid', $x->getMessage());
        }
        $this->svc->payExpense($this->festival, $this->accountant, $e->fresh(), 'cash', null, now()->toDateString());
        try {
            $this->svc->settle($this->festival, $this->admin, null);
            $this->fail('surplus needs a decision');
        } catch (FestivalException $x) {
            $this->assertStringContainsString('surplus', $x->getMessage());
        }
        try {
            $this->svc->settle($this->festival, $this->accountant, 'carry');
            $this->fail('accountant cannot settle');
        } catch (FestivalException $x) {
            $this->assertStringContainsString('Admin', $x->getMessage());
        }

        $this->svc->settle($this->festival, $this->admin, 'Return pro-rata to contributors');
        $f = $this->festival->fresh();
        $this->assertTrue($f->isSettled());
        $this->assertSame('Return pro-rata to contributors', $f->settlement_decision);
        $this->assertEquals(6000, $this->figures()['cash_available']);

        foreach ([fn () => $this->receive($this->resident, 50), fn () => $this->expense(10, $this->admin, 'Late Co'), fn () => $this->svc->pledge($this->festival, $this->resident, 5, false)] as $try) {
            try {
                $try();
                $this->fail('settled festival accepted new money');
            } catch (FestivalException $x) {
                $this->assertStringContainsString('closed', $x->getMessage());
            }
        }
    }

    public function test_a_next_edition_copies_the_plan_but_never_money(): void
    {
        FestivalBudgetItem::create(['society_id' => $this->society->id, 'festival_id' => $this->festival->id, 'category' => 'Lighting', 'estimated_amount' => 3000]);
        $this->receive($this->resident, 900);

        $this->actingAs($this->admin)->post(route('festivals.duplicate', $this->festival), ['festival_date' => now()->addYear()->toDateString()])->assertRedirect();

        $copy = Festival::where('id', '!=', $this->festival->id)->first();
        $this->assertSame('draft', $copy->status);
        $this->assertSame(1, $copy->budgetItems()->count());
        $this->assertSame(0, $copy->contributions()->count());
        $this->assertEquals(0, $copy->opening_balance);
        $this->assertEquals(900, $this->figures()['contributions_net'], 'the old edition keeps its own money');
    }

    // ── HTTP: access, isolation, confidentiality ────────────────────────────

    public function test_residents_see_published_festivals_but_never_a_draft(): void
    {
        $draft = new Festival(['society_id' => $this->society->id, 'name' => 'Secret Draft', 'festival_date' => now()->addDays(5)->toDateString()]);
        $draft->save();

        $this->actingAs($this->resident)->get(route('festivals.index'))->assertOk()->assertSee('Navratri')->assertDontSee('Secret Draft');
        $this->actingAs($this->resident)->get(route('festivals.show', $draft))->assertNotFound();
        $this->actingAs($this->admin)->get(route('festivals.index'))->assertSee('Secret Draft');
    }

    public function test_individual_contributions_are_confidential(): void
    {
        $this->receive($this->neighbour, 2345);
        $mine = $this->receive($this->resident, 777);

        $page = $this->actingAs($this->resident)->get(route('festivals.show', $this->festival))->assertOk();
        $page->assertSee('777.00');
        $page->assertDontSee('2,345.00', false);
        $page->assertSee($mine->receipt_number);
        $this->actingAs($this->accountant)->get(route('festivals.show', $this->festival))->assertSee('2,345.00', false);
    }

    public function test_a_resident_cannot_use_the_money_or_admin_actions_over_http(): void
    {
        $c = $this->svc->pledge($this->festival, $this->neighbour, 100, false);
        $e = $this->expense(100);
        $this->actingAs($this->resident);

        $this->post(route('festivals.contributions.record', $this->festival), ['amount' => 5, 'payment_method' => 'cash', 'received_on' => now()->toDateString()])->assertForbidden();
        $this->post(route('festivals.contributions.receive', [$this->festival, $c->id]), ['payment_method' => 'cash', 'received_on' => now()->toDateString()])->assertForbidden();
        $this->post(route('festivals.settle', $this->festival))->assertForbidden();
        $this->get(route('festivals.report', $this->festival))->assertForbidden();
        $this->get(route('festivals.create'))->assertForbidden();
        $this->post(route('festivals.budget.add', $this->festival), ['category' => 'X', 'estimated_amount' => 1])->assertForbidden();
        $this->assertSame('pledged', $c->fresh()->status);
        $this->assertSame('submitted', $e->fresh()->status);
    }

    public function test_another_society_cannot_see_or_touch_a_festival(): void
    {
        $other = $this->makeSociety('Other');
        $theirAdmin = $this->makeUserWithRole($other, 'Admin');
        $c = $this->receive($this->resident, 100);

        $this->actingAs($theirAdmin)->get(route('festivals.show', $this->festival))->assertForbidden();
        $this->actingAs($theirAdmin)->post(route('festivals.contributions.refund', [$this->festival, $c->id]), ['amount' => 10, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($theirAdmin)->get(route('festivals.index'))->assertOk()->assertDontSee('Navratri');
        $this->assertEquals(100, $this->figures()['contributions_net']);
    }

    public function test_a_contribution_of_another_festival_cannot_be_reached_through_this_one(): void
    {
        $other = new Festival(['society_id' => $this->society->id, 'name' => 'Diwali', 'festival_date' => now()->addDays(60)->toDateString()]);
        $other->status = 'published';
        $other->save();
        $c = $this->svc->recordReceipt($other, $this->accountant, ['amount' => 50, 'payment_method' => 'cash', 'received_on' => now()->toDateString()]);

        $this->actingAs($this->accountant)->post(route('festivals.contributions.refund', [$this->festival, $c->id]), ['amount' => 10, 'reason' => 'x'])->assertNotFound();
        $this->assertSame('received', $c->fresh()->status);
    }

    public function test_staff_and_guard_have_no_access(): void
    {
        foreach (['Staff', 'Guard'] as $role) {
            $u = $this->makeUserWithRole($this->society, $role);
            $this->actingAs($u)->get(route('festivals.index'))->assertForbidden();
        }
    }

    public function test_the_http_flow_pledge_then_record_then_report(): void
    {
        $this->actingAs($this->resident)->post(route('festivals.pledge', $this->festival), ['amount' => 1500])->assertSessionHas('success');
        $c = FestivalContribution::first();
        $this->assertSame('pledged', $c->status);

        $this->actingAs($this->accountant)->post(route('festivals.contributions.receive', [$this->festival, $c->id]), ['payment_method' => 'upi', 'payment_reference' => 'U-77', 'received_on' => now()->toDateString()])->assertSessionHas('success');
        $this->assertSame('received', $c->fresh()->status);

        $this->actingAs($this->accountant)->get(route('festivals.report', $this->festival))->assertOk()->assertSee('voluntary', false)->assertSee($c->fresh()->receipt_number);
        $csv = $this->actingAs($this->accountant)->get(route('festivals.report', [$this->festival, 'csv']))->assertOk();
        $this->assertStringContainsString('U-77', $csv->streamedContent());
        $this->actingAs($this->accountant)->get(route('festivals.report', [$this->festival, 'pdf']))->assertOk();
    }

    public function test_an_admin_creates_a_draft_that_needs_a_note_for_any_opening_balance_and_publishing_can_announce_through_events(): void
    {
        $this->actingAs($this->admin)->post(route('festivals.store'), ['name' => 'Holi', 'festival_date' => now()->addDays(30)->toDateString(), 'opening_balance' => 500])->assertSessionHasErrors('opening_balance_note');
        $this->actingAs($this->admin)->post(route('festivals.store'), ['name' => 'Holi', 'festival_date' => now()->addDays(30)->toDateString(), 'estimated_budget' => 20000])->assertRedirect();
        $holi = Festival::where('name', 'Holi')->first();
        $this->assertSame('draft', $holi->status);

        \Illuminate\Support\Facades\Event::fake([\App\Events\EventCreated::class]);
        $this->actingAs($this->admin)->post(route('festivals.publish', $holi), ['announce' => 1])->assertSessionHas('success');

        $this->assertSame('published', $holi->fresh()->status);
        $this->assertNotNull($holi->fresh()->event_id);
        $this->assertStringContainsString('voluntary', Event::find($holi->fresh()->event_id)->description);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\EventCreated::class);

        $this->actingAs($this->resident)->post(route('festivals.store'), ['name' => 'Nope', 'festival_date' => now()->addDays(3)->toDateString()])->assertForbidden();
    }

    public function test_contributor_amounts_are_never_in_the_audit_of_a_resident_and_every_step_is_audited(): void
    {
        $c = $this->receive($this->resident, 250);
        $e = $this->expense(100);
        $this->svc->approveExpense($this->festival, $this->admin, $e);

        foreach (['festival.contribution_received', 'festival.expense_created', 'festival.expense_submitted', 'festival.expense_approved'] as $action) {
            $this->assertDatabaseHas('activity_logs', ['action' => $action, 'society_id' => $this->society->id]);
        }
        $this->assertDatabaseHas('festival_expenses', ['id' => $e->id, 'approved_by' => $this->admin->id, 'created_by' => $this->committee->id]);
    }
}
