<?php

namespace Tests\Feature;

use App\Events\PaymentReceived;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsFinanceLedger;
use Tests\TestCase;

/**
 * Who can make a bill "paid".
 *
 * Found in the audit: POST /payments created a `success` payment for ANY signed-in resident of the unit, and the ledger
 * then marked their bill paid - no money, no verification. Now only the office (Admin, Super Admin) records
 * received money; a resident's record is a pending claim that changes nothing until an admin verifies it. The same
 * route also accepted ₹0, more than the bill owes, and the same payment twice in a row; and a verified payment never told
 * its payer. Ledger and figures: see FinanceReconciliationTest.
 */
class ResidentPaymentSubmissionTest extends TestCase
{
    use BuildsFinanceLedger;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildLedger();
        Mail::fake();
        Queue::fake();
    }

    private function record(\App\Models\User $as, MaintenanceBill $bill, float $amount, array $over = [])
    {
        return $this->actingAs($as)->post(route('payments.store'), $over + [
            'flat_id' => $bill->flat_id, 'maintenance_bill_id' => $bill->id, 'amount' => $amount, 'payment_method' => 'cash', 'payment_date' => '2026-10-12',
        ]);
    }

    private function claims(MaintenanceBill $bill)
    {
        return Payment::where('maintenance_bill_id', $bill->id)->where('amount', 2700)->where('payment_date', '>=', '2026-10-12');
    }

    // ── a resident's record is a claim ──────────────────────────────────────

    public function test_a_resident_recording_a_payment_makes_a_pending_claim_and_the_bill_stays_unpaid(): void
    {
        Event::fake([PaymentReceived::class]);

        $this->record($this->tenant, $this->maint159oct, 2700)->assertRedirect(route('payments.index'))->assertSessionHas('success', fn ($m) => str_contains($m, 'once the office has verified'));

        $claim = $this->claims($this->maint159oct)->sole();
        $this->assertSame('pending', $claim->status);
        $this->assertSame($this->tenant->id, (int) $claim->user_id);
        $fresh = $this->maint159oct->fresh();
        $this->assertContains($fresh->status, ['pending', 'overdue'], 'nothing has been paid');
        $this->assertEquals(0.0, (float) $fresh->paid_amount);
        $this->assertEquals(2700.0, (float) $fresh->balance_amount);
        Event::assertNotDispatched(PaymentReceived::class);
    }

    public function test_the_owner_of_the_unit_is_treated_the_same(): void
    {
        $this->record($this->owner, $this->maint160, 2700)->assertSessionHasNoErrors();

        $this->assertSame('pending', Payment::where('maintenance_bill_id', $this->maint160->id)->latest('id')->first()->status);
        $this->assertEquals(2700.0, (float) $this->maint160->fresh()->balance_amount);
    }

    public function test_verifying_the_claim_is_what_pays_the_bill_and_tells_the_payer(): void
    {
        $this->record($this->tenant, $this->maint159oct, 2700);
        $claim = $this->claims($this->maint159oct)->sole();
        Event::fake([PaymentReceived::class]);

        $this->actingAs($this->admin)->post(route('payments.approve', $claim))->assertSessionHas('success');

        $this->assertSame('success', $claim->fresh()->status);
        $this->assertSame('paid', $this->maint159oct->fresh()->status);
        $this->assertEquals(0.0, (float) $this->maint159oct->fresh()->balance_amount);
        Event::assertDispatchedTimes(PaymentReceived::class, 1);
    }

    public function test_the_office_records_received_money_straight_away(): void
    {
        Event::fake([PaymentReceived::class]);

        $this->record($this->admin, $this->maint159oct, 1000)->assertSessionHas('success', 'Payment recorded successfully!');

        $p = Payment::where('maintenance_bill_id', $this->maint159oct->id)->where('amount', 1000)->sole();
        $this->assertSame('success', $p->status);
        $this->assertNull($p->user_id, 'the office\'s record is not the office\'s own payment');
        $this->assertEquals(1700.0, (float) $this->maint159oct->fresh()->balance_amount);
        Event::assertDispatchedTimes(PaymentReceived::class, 1);
    }

    public function test_the_accountant_neither_records_nor_verifies_payments(): void
    {
        // separation of duties (FinancePermissionMatrixTest): the form is for residents' own units and the Admin, and only an Admin verifies
        $this->record($this->accountant, $this->maint159oct, 1000)->assertSessionHasErrors('error');
        $this->assertSame(0, Payment::where('maintenance_bill_id', $this->maint159oct->id)->where('amount', 1000)->count());

        $this->record($this->tenant, $this->maint159oct, 1000);
        $claim = Payment::where('maintenance_bill_id', $this->maint159oct->id)->where('amount', 1000)->sole();
        $this->actingAs($this->accountant)->post(route('payments.approve', $claim))->assertForbidden();
        $this->assertSame('pending', $claim->fresh()->status);
    }
    // ── what the route refuses ──────────────────────────────────────────────

    public function test_more_than_the_bill_owes_is_refused_for_everyone(): void
    {
        foreach ([$this->tenant, $this->admin] as $who) {
            $this->record($who, $this->maint159oct, 5000)->assertSessionHasErrors('amount');
            $this->assertStringContainsString('more than the bill still owes', session('errors')->first('amount'));
        }
        $this->assertSame(0, Payment::where('maintenance_bill_id', $this->maint159oct->id)->count());
    }

    public function test_a_bill_that_is_already_paid_takes_no_more_money(): void
    {
        $this->record($this->admin, $this->maint159sep, 100)->assertSessionHasErrors('amount');

        $this->assertStringContainsString('already fully paid', session('errors')->first('amount'));
    }

    public function test_zero_and_negative_amounts_are_refused(): void
    {
        $this->record($this->admin, $this->maint159oct, 0)->assertSessionHasErrors('amount');
        $this->record($this->admin, $this->maint159oct, -50)->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::where('maintenance_bill_id', $this->maint159oct->id)->count());
    }

    public function test_the_same_payment_twice_in_a_row_is_recorded_once(): void
    {
        $this->record($this->tenant, $this->maint159oct, 1000);
        $second = $this->record($this->tenant, $this->maint159oct, 1000);

        $second->assertRedirect(route('payments.index'))->assertSessionHas('info', fn ($m) => str_contains($m, 'not added a second time'));
        $this->assertSame(1, Payment::where('maintenance_bill_id', $this->maint159oct->id)->where('amount', 1000)->count());
    }

    public function test_a_resident_cannot_record_against_a_unit_or_society_that_is_not_theirs(): void
    {
        $this->record($this->tenant, $this->maint160, 100)->assertSessionHasErrors('error');
        $this->assertStringContainsString('your own property', session('errors')->first('error'));

        $betaFlat = \App\Models\Flat::where('society_id', $this->beta->id)->first();
        $betaBill = MaintenanceBill::where('society_id', $this->beta->id)->first();
        $this->actingAs($this->tenant)->post(route('payments.store'), ['flat_id' => $betaFlat->id, 'maintenance_bill_id' => $betaBill->id, 'amount' => 100, 'payment_method' => 'cash', 'payment_date' => '2026-10-12'])->assertForbidden();

        $this->assertSame(0, Payment::where('maintenance_bill_id', $this->maint160->id)->where('amount', 100)->count());
        $this->assertSame(1, Payment::where('maintenance_bill_id', $betaBill->id)->count(), 'Beta\'s ledger is untouched');
    }

    public function test_two_claims_for_the_same_money_cannot_both_be_verified(): void
    {
        $this->record($this->tenant, $this->maint159oct, 2700);
        // a second claim by another route (e.g. the rent screen) for the same amount, past the 10-minute repeat guard
        $first = $this->claims($this->maint159oct)->sole();
        $duplicate = Payment::create(['society_id' => $this->alpha->id, 'flat_id' => $this->v159->id, 'maintenance_bill_id' => $this->maint159oct->id, 'user_id' => $this->tenant->id,
            'payment_id' => 'PAY-DUP-1', 'amount' => 2700, 'payment_method' => 'upi', 'status' => 'pending', 'payment_date' => '2026-10-12', 'due_date' => '2026-10-15', 'bill_type' => 'maintenance']);

        $this->actingAs($this->admin)->post(route('payments.approve', $first))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('payments.approve', $duplicate))->assertSessionHas('error', fn ($m) => str_contains($m, 'more than the bill still owes'));

        $this->assertSame('pending', $duplicate->fresh()->status);
        $this->assertEquals(0.0, (float) $this->maint159oct->fresh()->balance_amount, 'never below zero');
    }

    // ── receipts ────────────────────────────────────────────────────────────

    public function test_the_unit_tenant_can_open_the_receipt_of_a_payment_the_office_recorded_for_his_unit(): void
    {
        $recorded = Payment::where('flat_id', $this->v159->id)->where('status', 'success')->whereNull('user_id')->first()
            ?? Payment::where('flat_id', $this->v159->id)->where('status', 'success')->first();

        $this->actingAs($this->tenant)->get(route('payments.receipt', $recorded))->assertOk();
        $this->actingAs($this->accountant)->get(route('payments.receipt', $recorded))->assertOk();
        $this->actingAs($this->admin)->get(route('payments.receipt', $recorded))->assertOk();
    }

    public function test_nobody_opens_a_receipt_for_another_unit_or_society(): void
    {
        $villa160 = Payment::where('flat_id', $this->v160->id)->where('status', 'success')->first();
        $beta = Payment::where('society_id', $this->beta->id)->first();

        $this->actingAs($this->tenant)->get(route('payments.receipt', $villa160))->assertForbidden();
        $this->actingAs($this->owner)->get(route('payments.receipt', $villa160))->assertOk();
        $this->actingAs($this->admin)->get(route('payments.receipt', $beta))->assertForbidden();
        $this->actingAs($this->tenant)->get(route('payments.receipt', $beta))->assertForbidden();
    }

    public function test_there_is_no_receipt_for_a_claim_or_a_declined_payment(): void
    {
        $pending = Payment::where('flat_id', $this->v159->id)->where('status', 'pending')->first();
        $cancelled = Payment::where('flat_id', $this->v159->id)->where('status', 'cancelled')->first();

        foreach ([$pending, $cancelled] as $p) {
            $this->actingAs($this->tenant)->get(route('payments.receipt', $p))->assertRedirect()->assertSessionHas('error', fn ($m) => str_contains($m, 'once the payment has been verified'));
            $this->actingAs($this->admin)->get(route('payments.receipt', $p))->assertRedirect();
        }
    }

    // ── who is told about an office-recorded payment ────────────────────────

    private function payments(\App\Models\User $u)
    {
        return $u->fresh()->notifications()->get()->filter(fn ($n) => ($n->data['category'] ?? null) === 'payment');
    }

    public function test_an_office_recorded_maintenance_payment_tells_the_owner_not_the_admin_who_typed_it(): void
    {
        $this->record($this->admin, $this->maint160, 500);

        $this->assertCount(1, $this->payments($this->owner), 'the owner of Villa 160 is told');
        $this->assertStringContainsString('We received your payment', $this->payments($this->owner)->first()->data['body']);
        $adminMessages = $this->payments($this->admin)->pluck('data.body')->all();
        $this->assertNotEmpty($adminMessages, 'the office is told too');
        $this->assertStringNotContainsString('We received your payment', implode(' ', $adminMessages), 'but never as if it had paid');
        $this->assertCount(0, $this->payments($this->tenant), 'the tenant of another unit hears nothing');
    }

    public function test_an_office_recorded_rent_payment_tells_the_tenant(): void
    {
        $rent159 = $this->bill($this->v159, 'rent', '2026-11-05', 10000);

        $this->record($this->admin, $rent159, 10000, ['payment_date' => '2026-11-06']);

        $this->assertCount(1, $this->payments($this->tenant));
        $this->assertCount(0, $this->payments($this->owner));
    }

    // ── the villa owner's bill screens ──────────────────────────────────────

    public function test_the_villa_owners_pay_and_receipt_links_go_somewhere(): void
    {
        $index = $this->actingAs($this->owner)->get(route('villa-owner.bills'))->assertOk()->getContent();
        $this->assertStringNotContainsString('href="#"', $index);
        $this->assertStringContainsString(route('payments.create'), $index);

        $unpaid = $this->actingAs($this->owner)->get(route('villa-owner.bills.show', $this->maint160))->assertOk()->getContent();
        $this->assertStringContainsString(route('payments.create'), $unpaid);
        $this->assertStringNotContainsString('Download Receipt', $unpaid, 'nothing is paid yet');

        $paid = $this->actingAs($this->owner)->get(route('villa-owner.bills.show', $this->rent160))->assertOk()->getContent();
        $receipt = Payment::where('maintenance_bill_id', $this->rent160->id)->where('status', 'success')->first();
        $this->assertStringContainsString(route('payments.receipt', $receipt), $paid);
        $this->assertStringContainsString('Download Receipt', $paid);
    }
}
