<?php

namespace Tests\Concerns;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Society;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VillaArea;

/**
 * The deterministic finance ledger shared by the reconciliation and the web/mobile parity tests (see
 * FinanceReconciliationTest for the figures).
 */
trait BuildsFinanceLedger
{
    private Society $alpha;
    private Society $beta;
    private Flat $v159;
    private Flat $v160;
    private User $admin;
    private User $accountant;
    private User $owner;
    private User $tenant;
    private MaintenanceBill $rent160;
    private MaintenanceBill $maint160;
    private MaintenanceBill $maint159sep;
    private MaintenanceBill $maint159oct;
    private int $seq = 0;

    /** Builds the ledger described on FinanceReconciliationTest. Call from setUp() after parent::setUp(). */
    protected function buildLedger(): void
    {
        $this->alpha = $this->makeSociety('Alpha');
        $this->beta = $this->makeSociety('Beta');
        $area = VillaArea::create(['society_id' => $this->alpha->id, 'name' => 'Garden', 'status' => 'active']);
        $this->v160 = Flat::create(['society_id' => $this->alpha->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '160', 'status' => 'occupied']);
        $this->v159 = Flat::create(['society_id' => $this->alpha->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '159', 'status' => 'on_rent']);

        $this->admin = $this->makeUserWithRole($this->alpha, 'Admin');
        $this->accountant = $this->makeUserWithRole($this->alpha, 'Accountant');

        $owner = Owner::create(['society_id' => $this->alpha->id, 'name' => 'Suraj', 'email' => 'suraj@t.local', 'phone' => '9000000160', 'status' => 'active', 'property_type' => 'villa', 'villa_area_id' => $area->id, 'villa_no' => '160']);
        $this->v160->update(['owner_id' => $owner->id]);
        $this->owner = $this->makeUserWithRole($this->alpha, 'Villa Owner', ['owner_id' => $owner->id]);

        $this->tenant = $this->makeUserWithRole($this->alpha, 'Tenant', ['name' => 'Piyus']);
        $t = Tenant::create(['society_id' => $this->alpha->id, 'flat_id' => $this->v159->id, 'user_id' => $this->tenant->id, 'name' => 'Piyus', 'email' => 'piyus@t.local', 'phone' => '9000000159', 'move_in_date' => '2026-09-22', 'monthly_rent' => 10000, 'status' => 'active']);
        $this->tenant->update(['tenant_id' => $t->id]);

        // bills
        $this->rent160 = $this->bill($this->v160, 'rent', '2026-09-22', 10000);
        $this->maint160 = $this->bill($this->v160, 'maintenance', '2026-09-01', 2700);
        $this->maint159sep = $this->bill($this->v159, 'maintenance', '2026-09-01', 2700);
        $this->maint159oct = $this->bill($this->v159, 'maintenance', '2026-10-01', 2700);

        // payments
        $this->pay($this->rent160, 10000, 'success', '2026-09-22', 'rent');
        $this->pay($this->maint159sep, 1000, 'success', '2026-09-10');
        $this->pay($this->maint159sep, 1700, 'success', '2026-10-12');
        $this->pay($this->maint159sep, 500, 'pending', '2026-09-12');
        $this->pay($this->maint159sep, 300, 'failed', '2026-09-13');
        $this->pay($this->maint159sep, 200, 'cancelled', '2026-09-14');

        // expenses
        $this->expense('Security', 5000, '2026-09-15');
        $this->expense('Security', 1000, '2026-09-16', ['status' => 'pending']);
        $this->expense('Common Electricity', 3500, '2026-09-03', ['scope' => 'property', 'flat_id' => $this->v160->id, 'paid_by' => 'owner']);

        // another society
        $betaFlat = Flat::create(['society_id' => $this->beta->id, 'property_type' => 'villa', 'flat_number' => '1', 'status' => 'occupied']);
        $betaBill = $this->bill($betaFlat, 'maintenance', '2026-09-01', 9999, $this->beta);
        $this->pay($betaBill, 9999, 'success', '2026-09-10');
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function bill(Flat $flat, string $type, string $date, float $amount, ?Society $society = null): MaintenanceBill
    {
        $d = \Carbon\Carbon::parse($date);

        return MaintenanceBill::create(['society_id' => ($society ?? $this->alpha)->id, 'flat_id' => $flat->id, 'month' => $d->format('F'), 'year' => $d->year, 'bill_type' => $type,
            'bill_date' => $date, 'due_date' => $d->copy()->addDays(14)->toDateString(), 'maintenance_amount' => $amount, 'paid_amount' => 0]);
    }

    private function pay(MaintenanceBill $bill, float $amount, string $status, string $date, string $billType = 'maintenance'): Payment
    {
        $this->seq++;

        return Payment::create(['society_id' => $bill->society_id, 'flat_id' => $bill->flat_id, 'maintenance_bill_id' => $bill->id, 'payment_id' => 'PAY-REC-' . $this->seq, 'amount' => $amount,
            'payment_method' => 'cash', 'status' => $status, 'payment_date' => $date, 'due_date' => $date, 'bill_type' => $billType, 'receipt_number' => $status === 'success' ? 'RCP-REC-' . $this->seq : null]);
    }

    private function expense(string $category, float $amount, string $date, array $over = []): Expense
    {
        return Expense::create($over + ['society_id' => $this->alpha->id, 'scope' => 'society', 'flat_id' => null, 'paid_by' => 'society', 'created_by' => $this->admin->id,
            'expense_category_id' => ExpenseCategory::firstOrCreate(['society_id' => $this->alpha->id, 'name' => $category], ['status' => 'active'])->id,
            'title' => $category . ' expense', 'amount' => $amount, 'expense_date' => $date, 'payment_method' => 'online', 'status' => 'approved']);
    }
}
