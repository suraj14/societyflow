<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\VillaArea;
use App\Support\DataIntegrityRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The payments ledger is the single source of what a bill has collected.
 * paid_amount / balance_amount / status / paid_date are derived from the
 * bill's `success` payments (MaintenanceBill::syncFromLedger, triggered by
 * the Payment model), never set by hand and never by a status dropdown.
 *
 * Found in the dev database: 20 bills marked paid with no payment row
 * behind them (rent screens accepted "paid" and wrote paid_amount; the
 * seeder did the same), and a tenant's unverified payment counted as paid.
 */
class BillLedgerConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $society = $this->makeSociety();
        $area    = VillaArea::create(['society_id' => $society->id, 'name' => 'Area', 'status' => 'active']);
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $villa   = Flat::create(['society_id' => $society->id, 'property_type' => 'villa', 'villa_area_id' => $area->id, 'flat_number' => '159', 'floor' => 0, 'status' => 'vacant']);
        $tenantUser = $this->makeUserWithRole($society, 'Tenant');
        $tenant  = Tenant::create([
            'society_id' => $society->id, 'flat_id' => $villa->id, 'user_id' => $tenantUser->id, 'name' => 'Piyus',
            'email' => $tenantUser->email, 'phone' => '9000000061', 'move_in_date' => now(), 'status' => 'active', 'monthly_rent' => 10000,
        ]);
        $tenantUser->update(['tenant_id' => $tenant->id]);

        return compact('society', 'area', 'admin', 'villa', 'tenantUser', 'tenant');
    }

    /** One bill per (flat, month, year) - the table is unique on that. */
    private function bill(array $f, float $amount = 10000, string $type = 'rent', string $month = 'September'): MaintenanceBill
    {
        $m = \Carbon\Carbon::parse("1 {$month} 2026");

        return MaintenanceBill::create([
            'society_id' => $f['society']->id, 'flat_id' => $f['villa']->id,
            'month' => $month, 'year' => 2026, 'bill_date' => $m->toDateString(), 'due_date' => $m->copy()->addDays(9)->toDateString(),
            'maintenance_amount' => $amount, 'paid_amount' => 0, 'bill_type' => $type,
        ]);
    }

    private function pay(MaintenanceBill $bill, float $amount, string $status = 'success', array $extra = []): Payment
    {
        return Payment::create(array_merge([
            'society_id' => $bill->society_id, 'flat_id' => $bill->flat_id, 'maintenance_bill_id' => $bill->id,
            'amount' => $amount, 'payment_method' => 'cash', 'status' => $status, 'payment_date' => '2026-09-05',
            'bill_type' => 'rent', 'due_date' => '2026-09-10',
        ], $extra));
    }

    public function test_bill_is_derived_from_its_success_payments_on_create_update_and_delete(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f);
        $this->assertContains($bill->status, ['pending', 'overdue'], 'unpaid either way');

        $p1 = $this->pay($bill, 4000);
        $bill->refresh();
        $this->assertEquals(4000, $bill->paid_amount);
        $this->assertEquals(6000, $bill->balance_amount);
        $this->assertSame('partial', $bill->status);
        $this->assertNull($bill->paid_date);

        $p2 = $this->pay($bill, 6000, 'success', ['payment_date' => '2026-09-08']);
        $bill->refresh();
        $this->assertEquals(10000, $bill->paid_amount);
        $this->assertEquals(0, $bill->balance_amount);
        $this->assertSame('paid', $bill->status);
        $this->assertSame('2026-09-08', $bill->paid_date->toDateString());

        $p2->delete();
        $bill->refresh();
        $this->assertEquals(4000, $bill->paid_amount);
        $this->assertSame('partial', $bill->status);
        $this->assertNull($bill->paid_date);

        $p1->update(['amount' => 10000]);
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_pending_failed_and_cancelled_payments_do_not_count(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f);

        $this->pay($bill, 10000, 'pending');
        $this->pay($bill, 10000, 'failed');
        $this->pay($bill, 10000, 'cancelled');

        $bill->refresh();
        $this->assertEquals(0, $bill->paid_amount);
        $this->assertSame('overdue', $bill->status, 'due 2026-09-10 and unpaid');
    }

    public function test_a_status_write_cannot_mark_a_bill_paid(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f);

        $bill->update(['status' => 'paid']);
        $bill->refresh();

        $this->assertEquals(0, $bill->paid_amount);
        $this->assertNotSame('paid', $bill->status);
    }

    public function test_rent_created_as_paid_must_carry_payment_details_and_writes_the_ledger(): void
    {
        $f = $this->fixture();
        $base = [
            'property_type' => 'villa', 'unit_id' => $f['villa']->id, 'tenant_id' => $f['tenant']->id,
            'rent_amount' => 10000, 'bill_date' => '2026-09-01', 'due_date' => '2026-09-10', 'status' => 'paid',
        ];

        // "Paid" with no payment date/method is rejected, not silently stored.
        $this->actingAs($f['admin'])->post(route('admin.rents.store'), $base)
            ->assertSessionHasErrors(['payment_date', 'payment_method']);
        $this->assertSame(0, MaintenanceBill::count());

        $this->actingAs($f['admin'])->post(route('admin.rents.store'), $base + ['payment_date' => '2026-09-03', 'payment_method' => 'bank_transfer'])
            ->assertRedirect(route('admin.rents.index'));

        $bill = MaintenanceBill::sole();
        $this->assertSame('rent', $bill->bill_type);
        $this->assertSame('paid', $bill->status);
        $this->assertEquals(10000, $bill->paid_amount);
        $this->assertSame('2026-09-03', $bill->paid_date->toDateString());

        $payment = $bill->payments()->sole();
        $this->assertSame('success', $payment->status, "'completed' is not a value the column accepts");
        $this->assertSame('bank_transfer', $payment->payment_method);
        $this->assertTrue($bill->ledgerAgrees());
    }

    public function test_rent_edit_cannot_flip_status_to_paid(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f);

        $this->actingAs($f['admin'])->put(route('admin.rents.update', $bill), [
            'flat_id' => $f['villa']->id, 'rent_amount' => 10000, 'bill_date' => '2026-09-01', 'due_date' => '2026-09-10', 'status' => 'paid',
        ])->assertRedirect(route('admin.rents.index'));

        $bill->refresh();
        $this->assertEquals(0, $bill->paid_amount);
        $this->assertNotSame('paid', $bill->status);
        $this->assertSame(0, $bill->payments()->count());
    }

    public function test_admin_add_payment_is_idempotent_and_cannot_overpay(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f);
        $payload = ['rent_id' => $bill->id, 'payment_date' => '2026-09-05', 'payment_amount' => 10000, 'payment_method' => 'upi', 'idempotency_key' => 'abc-123'];

        $this->actingAs($f['admin'])->postJson(route('admin.rents.add-payment'), $payload)->assertOk()->assertJson(['success' => true]);
        // Double click / browser retry with the same token.
        $this->actingAs($f['admin'])->postJson(route('admin.rents.add-payment'), $payload)->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, $bill->payments()->count());
        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertEquals(10000, $bill->paid_amount);

        // A further payment with a new token exceeds the balance.
        $this->actingAs($f['admin'])->postJson(route('admin.rents.add-payment'), array_merge($payload, ['idempotency_key' => 'def-456']))
            ->assertStatus(422);
        $this->assertSame(1, $bill->payments()->count());
    }

    public function test_tenant_submission_is_pending_until_an_admin_verifies_it(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f);

        // Tenant\RentController reads the tenant's unit via $user->resident.
        \App\Models\Resident::create([
            'society_id' => $f['society']->id, 'flat_id' => $f['villa']->id, 'user_id' => $f['tenantUser']->id,
            'name' => 'Piyus', 'email' => $f['tenantUser']->email, 'phone' => '9000000061', 'type' => 'tenant',
            'move_in_date' => now()->toDateString(), 'status' => 'active',
        ]);

        $payload = ['rent_id' => $bill->id, 'payment_date' => '2026-09-05', 'payment_amount' => 10000, 'payment_method' => 'upi', 'idempotency_key' => 'tenant-1'];
        $this->actingAs($f['tenantUser'])->postJson(route('tenant.rent.payment'), $payload)->assertOk()->assertJson(['success' => true]);
        $this->actingAs($f['tenantUser'])->postJson(route('tenant.rent.payment'), $payload)->assertOk()->assertJson(['success' => true]);

        $payment = $bill->payments()->sole();
        $this->assertSame('pending', $payment->status);

        $bill->refresh();
        $this->assertEquals(0, $bill->paid_amount, 'an unverified claim is not money received');
        $this->assertNotSame('paid', $bill->status);

        // A tenant cannot verify their own payment.
        $this->actingAs($f['tenantUser'])->post(route('payments.approve', $payment))->assertForbidden();

        $this->actingAs($f['admin'])->post(route('payments.approve', $payment))->assertRedirect();
        $this->assertSame('success', $payment->fresh()->status);
        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertEquals(10000, $bill->paid_amount);
        $this->assertDatabaseHas('activity_logs', ['action' => 'payment.approved', 'model_id' => $payment->id]);
    }

    public function test_rejected_tenant_submission_leaves_the_bill_unpaid(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f);
        $payment = $this->pay($bill, 10000, 'pending', ['user_id' => $f['tenantUser']->id]);

        $this->actingAs($f['admin'])->post(route('payments.reject', $payment))->assertRedirect();
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertEquals(0, $bill->fresh()->paid_amount);

        // Cannot approve after rejecting.
        $this->actingAs($f['admin'])->post(route('payments.approve', $payment))->assertRedirect();
        $this->assertSame('cancelled', $payment->fresh()->status);
    }

    public function test_another_societys_admin_cannot_verify_the_payment(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f);
        $payment = $this->pay($bill, 10000, 'pending');

        $otherAdmin = $this->makeUserWithRole($this->makeSociety('Other'), 'Admin');
        $this->actingAs($otherAdmin)->post(route('payments.approve', $payment))->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_legacy_paid_bills_are_reconciled_into_the_ledger_once(): void
    {
        $f = $this->fixture();

        // The dev database's shape: paid_amount written directly, no rows.
        $seeded = $this->bill($f, 6000);
        \DB::table('maintenance_bills')->where('id', $seeded->id)->update(['paid_amount' => 6000, 'balance_amount' => 0, 'status' => 'paid', 'paid_date' => '2025-09-09']);

        $partial = $this->bill($f, 90000, 'maintenance', 'January');
        \DB::table('maintenance_bills')->where('id', $partial->id)->update(['paid_amount' => 54000, 'balance_amount' => 36000, 'status' => 'partial', 'bill_number' => 'RENT-1-5-202601-1', 'updated_at' => '2026-02-02 11:28:59']);

        $backed = $this->bill($f, 3000, 'rent', 'March');
        $this->pay($backed, 3000);

        $report = DataIntegrityRepair::reconcileBillLedger(false);
        $this->assertSame(2, $report['bills_reconciled']);
        $this->assertEquals(60000, $report['amount_reconciled']);
        $this->assertSame(1, Payment::count(), 'report mode writes nothing');

        $result = DataIntegrityRepair::run(true);
        $this->assertSame(2, $result['bills_reconciled']);
        $this->assertSame(1, $result['bills_relabelled']);

        $recon = Payment::where('maintenance_bill_id', $seeded->id)->sole();
        $this->assertSame('success', $recon->status);
        $this->assertSame('unrecorded', $recon->payment_method);
        $this->assertSame('2025-09-09', $recon->payment_date->toDateString(), 'the bill\'s own paid date');
        $this->assertSame(DataIntegrityRepair::RECONCILIATION_PREFIX . $seeded->id, $recon->transaction_id);
        $this->assertStringContainsString('never recorded', $recon->notes);

        $recon2 = Payment::where('maintenance_bill_id', $partial->id)->sole();
        $this->assertEquals(54000, $recon2->amount);
        $this->assertSame('2026-02-02', $recon2->payment_date->toDateString(), 'no paid date: when "paid" was recorded');
        $this->assertSame('rent', $recon2->bill_type);
        $this->assertSame('rent', $partial->fresh()->bill_type, 'RENT- numbered bill relabelled');

        // Bills still say what they said; the ledger now agrees.
        foreach ([$seeded, $partial, $backed] as $bill) {
            $bill->refresh();
            $this->assertTrue($bill->ledgerAgrees(), $bill->bill_number);
        }
        $this->assertSame('paid', $seeded->status);
        $this->assertSame('partial', $partial->status);
        $this->assertEquals(36000, $partial->balance_amount);

        $this->assertSame(0, DataIntegrityRepair::run(true)['bills_reconciled'], 'idempotent');
        $this->assertSame(3, Payment::count());
        $this->assertSame(0, DataIntegrityRepair::unbackedBills()->count());
    }

    public function test_financial_report_income_and_billing_read_the_same_ledger(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f, 5000, 'maintenance');
        $this->pay($bill, 5000, 'success', ['bill_type' => 'maintenance']);
        $this->pay($bill, 999, 'pending', ['bill_type' => 'maintenance']); // must not appear anywhere

        $period = \App\Support\ReportPeriod::fromRequest(new \Illuminate\Http\Request(['report_type' => 'monthly', 'month' => 9, 'year' => 2026]));
        $report = app(\App\Services\FinancialReportService::class)->build($f['society']->id, $period);

        $this->assertEquals(5000, $report['overview']['maintenance_collected']);
        $this->assertEquals(5000, $report['billing']['collected']);
        $this->assertEquals(0, $report['billing']['outstanding']);
        $this->assertEquals(0, $report['overview']['outstanding_dues']);
    }

    public function test_audit_command_reports_then_fixes(): void
    {
        $f = $this->fixture();
        $bill = $this->bill($f, 6000);
        \DB::table('maintenance_bills')->where('id', $bill->id)->update(['paid_amount' => 6000, 'balance_amount' => 0, 'status' => 'paid']);

        $this->artisan('data:integrity-audit')->assertExitCode(1);
        $this->assertSame(0, Payment::count());

        $this->artisan('data:integrity-audit', ['--fix' => true])->assertExitCode(0);
        $this->assertSame(1, Payment::count());

        $this->artisan('data:integrity-audit')->assertExitCode(0);
    }
}
