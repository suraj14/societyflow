<?php

namespace Tests\Feature;

use App\Events\PaymentReceived;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsFinanceLedger;
use Tests\TestCase;

/**
 * Separation of duties for money. Who may RECORD, VERIFY, CHANGE and REMOVE a payment, per role, on every write path
 * (web forms, JSON endpoints, API, mobile client).
 *
 *   record (counts at once)  Admin, Super Admin            (office money)
 *   claim (stays pending)    Tenant, Owner for their unit  (only an Admin verifies it)
 *   verify / reject          Admin, Super Admin; never the person who submitted the payment
 *   change / withdraw        Admin; a resident only their OWN claim and only while pending
 *   Accountant               read-only on payments, rent and utility bills (add/edit expenses is the documented
 *                            `manage_expenses` grant and is unchanged)
 *
 * Accountant used to be able to verify and reject claims, and any resident could rewrite the amount of - or delete - their
 * own VERIFIED payment through the generic "own record" check.
 */
class FinancePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;
    use BuildsFinanceLedger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildLedger();
    }

    private function claim(): Payment
    {
        $this->actingAs($this->tenant)->postJson(route('tenant.rent.payment'), ['rent_id' => $this->maint159oct->id, 'payment_date' => '2026-10-10', 'payment_amount' => 1000, 'payment_method' => 'cash'])
            ->assertOk();

        return Payment::where('maintenance_bill_id', $this->maint159oct->id)->where('amount', 1000)->sole();
    }

    private function edit(Payment $p, array $over = []): array
    {
        return $over + ['flat_id' => $p->flat_id, 'amount' => (float) $p->amount, 'payment_method' => 'cash', 'payment_date' => '2026-10-10', 'bill_type' => $p->bill_type ?: 'maintenance', 'due_date' => '2026-10-15'];
    }

    // ── tenant and owner submissions are claims ─────────────────────────────

    public function test_a_resident_submission_is_a_pending_claim_that_does_not_move_the_bill(): void
    {
        Event::fake([PaymentReceived::class]);
        $before = (float) $this->maint159oct->fresh()->balance_amount;

        $claim = $this->claim();

        $this->assertSame('pending', $claim->status);
        $this->assertEquals($before, (float) $this->maint159oct->fresh()->balance_amount);
        Event::assertNotDispatched(PaymentReceived::class);
    }

    // ── verification ────────────────────────────────────────────────────────

    public function test_only_an_admin_verifies_or_rejects_a_claim(): void
    {
        $claim = $this->claim();

        foreach ([$this->accountant, $this->tenant, $this->owner] as $who) {
            $this->actingAs($who)->post(route('payments.approve', $claim))->assertForbidden();
            $this->actingAs($who)->post(route('payments.reject', $claim))->assertForbidden();
        }
        $this->assertSame('pending', $claim->fresh()->status);
        $this->assertEquals(2700.0, (float) $this->maint159oct->fresh()->balance_amount);

        $this->actingAs($this->admin)->post(route('payments.approve', $claim))->assertSessionHas('success');
        $this->assertSame('success', $claim->fresh()->status);
        $this->assertEquals(1700.0, (float) $this->maint159oct->fresh()->balance_amount);
    }

    public function test_nobody_verifies_their_own_submission(): void
    {
        // an Admin who also rents a unit: their own claim needs somebody else
        $claim = $this->claim();
        $claim->update(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->post(route('payments.approve', $claim))->assertForbidden();
        $this->actingAs($this->admin)->post(route('payments.reject', $claim))->assertForbidden();
        $this->assertSame('pending', $claim->fresh()->status);

        $second = $this->makeUserWithRole($this->alpha, 'Admin');
        $this->actingAs($second)->post(route('payments.approve', $claim))->assertSessionHas('success');
        $this->assertSame('success', $claim->fresh()->status);
    }

    public function test_another_societys_admin_cannot_verify(): void
    {
        $claim = $this->claim();
        $other = $this->makeUserWithRole($this->beta, 'Admin');

        $this->actingAs($other)->post(route('payments.approve', $claim))->assertForbidden();
        $this->assertSame('pending', $claim->fresh()->status);
    }

    // ── changing and removing ───────────────────────────────────────────────

    public function test_a_resident_can_correct_and_withdraw_only_their_own_pending_claim(): void
    {
        $claim = $this->claim();

        $this->actingAs($this->tenant)->put(route('payments.update', $claim), $this->edit($claim, ['amount' => 900]))->assertRedirect(route('payments.index'));
        $this->assertEquals(900.0, (float) $claim->fresh()->amount);

        $this->actingAs($this->tenant)->put(route('payments.update', $claim), $this->edit($claim, ['amount' => 99999]))->assertSessionHasErrors('amount');
        $this->assertEquals(900.0, (float) $claim->fresh()->amount, 'more than the bill owes is refused');

        $this->actingAs($this->tenant)->delete(route('payments.destroy', $claim))->assertRedirect();
        $this->assertNull(Payment::find($claim->id));
    }

    public function test_a_resident_cannot_rewrite_or_delete_a_verified_payment(): void
    {
        $claim = $this->claim();
        $this->actingAs($this->admin)->post(route('payments.approve', $claim));
        $balance = (float) $this->maint159oct->fresh()->balance_amount;

        $this->actingAs($this->tenant)->put(route('payments.update', $claim), $this->edit($claim, ['amount' => 1]))->assertForbidden();
        $this->actingAs($this->tenant)->get(route('payments.edit', $claim))->assertForbidden();
        $this->actingAs($this->tenant)->delete(route('payments.destroy', $claim))->assertForbidden();

        $this->assertEquals(1000.0, (float) $claim->fresh()->amount);
        $this->assertSame('success', $claim->fresh()->status);
        $this->assertEquals($balance, (float) $this->maint159oct->fresh()->balance_amount);
    }

    public function test_a_resident_cannot_touch_someone_elses_payment_or_move_a_claim_to_another_unit(): void
    {
        $claim = $this->claim();
        $theirs = $this->pay($this->rent160, 0.01, 'pending', '2026-10-01', 'rent');

        $this->actingAs($this->tenant)->delete(route('payments.destroy', $theirs))->assertForbidden();
        $this->actingAs($this->tenant)->put(route('payments.update', $claim), $this->edit($claim, ['flat_id' => $this->v160->id]))
            ->assertSessionHasErrors('error');
        $this->assertSame($this->v159->id, $claim->fresh()->flat_id);
    }

    public function test_the_accountant_cannot_record_change_or_delete_a_payment(): void
    {
        $claim = $this->claim();
        $verified = Payment::where('maintenance_bill_id', $this->maint159sep->id)->where('status', 'success')->firstOrFail();

        $this->actingAs($this->accountant)->post(route('payments.store'), ['flat_id' => $this->v159->id, 'maintenance_bill_id' => $this->maint159oct->id, 'amount' => 100, 'payment_method' => 'cash', 'payment_date' => '2026-10-11'])
            ->assertSessionHasErrors('error');
        $this->actingAs($this->accountant)->put(route('payments.update', $claim), $this->edit($claim))->assertForbidden();
        $this->actingAs($this->accountant)->delete(route('payments.destroy', $verified))->assertForbidden();
        $this->actingAs($this->accountant)->delete(route('payments.destroy', $claim))->assertForbidden();

        $this->assertNotNull(Payment::find($verified->id));
        $this->assertSame(1, Payment::where('maintenance_bill_id', $this->maint159oct->id)->count(), 'nothing was recorded');
    }

    public function test_the_admin_can_still_correct_and_the_delete_leaves_an_audit_row(): void
    {
        $claim = $this->claim();
        $this->actingAs($this->admin)->post(route('payments.approve', $claim));

        $this->actingAs($this->admin)->delete(route('payments.destroy', $claim))->assertRedirect();

        $this->assertNull(Payment::find($claim->id));
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.deleted', 'model_id' => $claim->id]);
        $this->assertEquals(2700.0, (float) $this->maint159oct->fresh()->balance_amount, 'the bill is re-derived from the ledger');
    }

    public function test_the_buttons_follow_the_rules(): void
    {
        $claim = $this->claim();

        $editUrl = route('payments.edit', $claim, false);
        $this->actingAs($this->tenant)->get(route('payments.show', $claim))->assertOk()->assertSee($editUrl, false);
        $this->actingAs($this->admin)->get(route('payments.index'))->assertOk()->assertSee(route('payments.approve', $claim, false), false);
        $this->actingAs($this->accountant)->get(route('payments.index'))->assertOk()->assertDontSee(route('payments.approve', $claim, false), false);

        $this->actingAs($this->admin)->post(route('payments.approve', $claim));
        $this->actingAs($this->tenant)->get(route('payments.show', $claim))->assertOk()->assertDontSee($editUrl, false)->assertDontSee('Delete Payment');
        $this->actingAs($this->admin)->get(route('payments.show', $claim))->assertOk()->assertSee($editUrl, false);
    }

    // ── the other write paths ───────────────────────────────────────────────

    public function test_the_rent_and_utility_payment_endpoints_are_admin_only(): void
    {
        foreach (['Accountant' => $this->accountant, 'Tenant' => $this->tenant, 'Villa Owner' => $this->owner] as $name => $who) {
            $this->actingAs($who)->postJson(route('admin.rents.add-payment'), ['rent_id' => $this->maint159oct->id, 'payment_date' => '2026-10-11', 'payment_amount' => 100, 'payment_method' => 'cash'])
                ->assertForbidden();
            $this->actingAs($who)->post(route('admin.rents.store'), [])->assertForbidden();
            $this->actingAs($who)->delete(route('admin.rents.destroy', $this->maint159oct))->assertForbidden();
        }
        $this->assertSame(0, Payment::where('maintenance_bill_id', $this->maint159oct->id)->count());
    }

    public function test_the_api_has_no_payment_or_bill_write_route(): void
    {
        $writes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/') && preg_match('#(payment|bill|rent|receipt|refund)#i', $r->uri()))
            ->filter(fn ($r) => array_diff($r->methods(), ['GET', 'HEAD']));

        $this->assertSame([], $writes->map(fn ($r) => implode('|', $r->methods()) . ' ' . $r->uri())->values()->all(), 'mobile can read money but never write it');
    }

    public function test_expenses_stay_add_and_edit_for_the_accountant_delete_for_the_admin_only(): void
    {
        // documented: the seeder grants manage_expenses to Admin and Accountant; delete is Admin only
        $this->actingAs($this->accountant)->get(route('expenses.index'))->assertOk();
        $expense = \App\Models\Expense::firstOrFail();
        $this->actingAs($this->accountant)->delete(route('expenses.destroy', $expense))->assertForbidden();
        foreach ([$this->tenant, $this->owner] as $who) {
            $this->actingAs($who)->get(route('expenses.index'))->assertForbidden();
        }
    }
}
