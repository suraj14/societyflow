<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One deterministic ledger, every Finance & Reports screen, explicit expected totals - and the same totals recomputed
 * straight from the tables, so a screen can never drift from the records it is built on.
 *
 * Ledger (society "Alpha", all amounts in rupees):
 *   Villa 160 (owner Suraj)      rent Sep 10,000 .... paid by one success payment 10,000 on 22 Sep
 *                                maintenance Sep 2,700 ... unpaid
 *   Villa 159 (tenant Piyus)     maintenance Sep 2,700 .. success 1,000 (10 Sep) + success 1,700 (12 Oct)
 *                                                          + pending 500, failed 300, cancelled 200 (none of these count)
 *                                maintenance Oct 2,700 .. unpaid
 *   Society expenses Sep         Security 5,000 approved; 1,000 still pending; Villa 160's own electricity 3,500 (a
 *                                property expense, never a society one)
 *   Society "Beta"               a 9,999 maintenance payment that must never appear anywhere in Alpha
 *
 * Cash basis: income = status `success` payments dated in the period; rent is a memo line, not society income.
 * Refunds: the application has no resident-payment refund flow - statuses are pending | success | failed | cancelled,
 * and only `success` ever counts.
 */
class FinanceReconciliationTest extends TestCase
{
    use RefreshDatabase;

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

    protected function setUp(): void
    {
        parent::setUp();
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

    private function financial(User $as, array $query)
    {
        return $this->actingAs($as)->get(route('reports.financial', $query))->assertOk();
    }

    private function maintenance(User $as, array $query)
    {
        return $this->actingAs($as)->get(route('reports.maintenance', $query))->assertOk();
    }

    private const SEPTEMBER = ['report_type' => 'monthly', 'month' => 9, 'year' => 2026];
    private const OCTOBER = ['report_type' => 'monthly', 'month' => 10, 'year' => 2026];

    // ── the ledger itself ───────────────────────────────────────────────────

    public function test_only_success_payments_settle_a_bill(): void
    {
        $this->assertEquals(2700.0, (float) $this->maint159sep->fresh()->paid_amount, '1,000 + 1,700; the pending, failed and cancelled rows never count');
        $this->assertEquals(0.0, (float) $this->maint159sep->fresh()->balance_amount);
        $this->assertSame('paid', $this->maint159sep->fresh()->status);
        $this->assertEquals(10000.0, (float) $this->rent160->fresh()->paid_amount);
        $this->assertSame('paid', $this->rent160->fresh()->status);
        $this->assertEquals(0.0, (float) $this->maint160->fresh()->paid_amount);
        $this->assertEquals(2700.0, (float) $this->maint160->fresh()->balance_amount);
    }

    // ── Financial Report: society-wide (Admin and Accountant) ───────────────

    public function test_the_september_financial_report_matches_the_ledger_to_the_rupee(): void
    {
        foreach ([$this->admin, $this->accountant] as $as) {
            $o = $this->financial($as, self::SEPTEMBER)->viewData('report')['overview'];

            $this->assertEquals(1000.0, $o['maintenance_collected'], 'only the 10 Sep success payment is a September maintenance collection');
            $this->assertEquals(1000.0, $o['total_income'], 'rent is a memo line, not society income');
            $this->assertEquals(10000.0, $o['rent_collected']);
            $this->assertEquals(5000.0, $o['total_society_expenses'], 'approved society expenses only: not the pending 1,000, not Villa 160\'s own 3,500');
            $this->assertEquals(-4000.0, $o['net_position']);
            $this->assertEquals(5400.0, $o['outstanding_dues'], 'all-time unpaid maintenance: 160 Sep 2,700 + 159 Oct 2,700; the fully paid 159 Sep is not owed');
        }
    }

    public function test_the_october_report_counts_the_12_oct_payment_in_october_only(): void
    {
        $o = $this->financial($this->admin, self::OCTOBER)->viewData('report')['overview'];

        $this->assertEquals(1700.0, $o['maintenance_collected']);
        $this->assertEquals(0.0, $o['rent_collected']);
        $this->assertEquals(0.0, $o['total_society_expenses']);
        $this->assertEquals(5400.0, $o['outstanding_dues'], 'dues are as of now, whatever period is shown');
    }

    public function test_the_report_totals_equal_the_same_sums_taken_straight_from_the_tables(): void
    {
        $o = $this->financial($this->admin, self::SEPTEMBER)->viewData('report')['overview'];

        $collected = (float) Payment::where('society_id', $this->alpha->id)->where('status', 'success')->where('bill_type', 'maintenance')->whereBetween('payment_date', ['2026-09-01', '2026-09-30 23:59:59'])->sum('amount');
        $rent = (float) Payment::where('society_id', $this->alpha->id)->where('status', 'success')->where('bill_type', 'rent')->whereBetween('payment_date', ['2026-09-01', '2026-09-30 23:59:59'])->sum('amount');
        $expenses = (float) Expense::where('society_id', $this->alpha->id)->where('scope', 'society')->where('status', 'approved')->whereBetween('expense_date', ['2026-09-01', '2026-09-30'])->sum('amount');
        $dues = (float) MaintenanceBill::where('society_id', $this->alpha->id)->where('bill_type', 'maintenance')->whereIn('status', ['pending', 'partial', 'overdue'])->sum('balance_amount');

        $this->assertEquals($collected, $o['maintenance_collected']);
        $this->assertEquals($rent, $o['rent_collected']);
        $this->assertEquals($expenses, $o['total_society_expenses']);
        $this->assertEquals($dues, $o['outstanding_dues']);
    }

    public function test_another_societys_money_never_appears_in_alphas_reports(): void
    {
        $sep = $this->financial($this->admin, self::SEPTEMBER)->viewData('report');

        $this->assertEquals(1000.0, $sep['overview']['maintenance_collected'], 'Beta\'s 9,999 is not in Alpha\'s collections');
        $this->assertEquals(5400.0, $sep['overview']['outstanding_dues']);
        $this->assertStringNotContainsString('9,999', $this->financial($this->admin, self::SEPTEMBER)->getContent());
    }

    // ── Financial Report: a resident sees only their own units ──────────────

    public function test_the_tenant_report_covers_only_the_unit_he_rents(): void
    {
        $r = $this->financial($this->tenant, self::SEPTEMBER)->viewData('report');

        $this->assertSame('property', $r['mode']);
        $this->assertEquals(1000.0, $r['overview']['total_paid'], 'his Sep success payment; not the 10,000 paid on Villa 160, not the pending/failed/cancelled rows');
        $this->assertEquals(2700.0, $r['overview']['total_billed']);
        $this->assertEquals(0.0, $r['overview']['period_due']);
        $this->assertEquals(2700.0, $r['overview']['outstanding_dues'], 'the unpaid October bill');
        $this->assertArrayNotHasKey('expenses', $r, 'a resident never sees the society\'s expense management');
    }

    public function test_the_owner_report_covers_only_the_villa_he_owns_including_its_rent(): void
    {
        $r = $this->financial($this->owner, self::SEPTEMBER)->viewData('report');

        $this->assertEquals(10000.0, $r['overview']['total_paid'], 'the rent collected on his villa');
        $this->assertEquals(12700.0, $r['overview']['total_billed'], 'rent 10,000 + maintenance 2,700');
        $this->assertEquals(2700.0, $r['overview']['period_due']);
        $this->assertEquals(2700.0, $r['overview']['outstanding_dues']);
    }

    // ── Maintenance Report ──────────────────────────────────────────────────

    public function test_the_maintenance_report_counts_maintenance_only_and_each_payment_once(): void
    {
        $d = $this->maintenance($this->admin, self::SEPTEMBER)->viewData('data');

        $this->assertEquals(1000.0, $d['total_maintenance'], 'the rent payment is not maintenance; the 12 Oct payment is not September');
        $this->assertSame(1, $d['count']);
        $this->assertEquals(5400.0, $d['billed'], 'two September maintenance bills: 2,700 + 2,700');
        $this->assertEquals(2700.0, $d['outstanding'], 'Villa 160\'s unpaid September bill');

        $oct = $this->maintenance($this->admin, self::OCTOBER)->viewData('data');
        $this->assertEquals(1700.0, $oct['total_maintenance']);
        $this->assertSame(1, $oct['count']);
    }

    public function test_residents_see_their_own_maintenance_only(): void
    {
        $tenant = $this->maintenance($this->tenant, self::SEPTEMBER)->viewData('data');
        $this->assertEquals(1000.0, $tenant['total_maintenance']);
        $this->assertEquals(2700.0, $tenant['billed']);
        $this->assertEquals(0.0, $tenant['outstanding']);

        $owner = $this->maintenance($this->owner, self::SEPTEMBER)->viewData('data');
        $this->assertEquals(0.0, $owner['total_maintenance'], 'the only payment on his villa is rent');
        $this->assertEquals(2700.0, $owner['billed']);
        $this->assertEquals(2700.0, $owner['outstanding']);
    }

    // ── Rent Management (admin) and Bills ───────────────────────────────────

    public function test_rent_management_lists_the_rent_bill_with_its_ledger_figures_and_nothing_else(): void
    {
        $records = $this->actingAs($this->admin)->get(route('admin.rents.index'))->assertOk()->viewData('rentRecords');

        $this->assertSame([$this->rent160->id], $records->pluck('id')->all(), 'rent bills only: no maintenance, no other society');
        $row = $records->first();
        $this->assertSame($this->v160->id, (int) $row->flat_id, 'the ₹10,000 September rent is Villa 160\'s');
        $this->assertEquals(10000.0, (float) $row->total_amount);
        $this->assertEquals(10000.0, (float) $row->paid_amount);
        $this->assertSame('paid', $row->status);
        $this->assertEquals(10000.0, (float) $row->payments->where('status', 'success')->sum('amount'));
    }

    public function test_the_tenants_rent_page_is_for_his_own_unit_and_says_so_when_it_has_no_rent_bill(): void
    {
        $r = $this->actingAs($this->tenant)->get(route('tenant.rent'))->assertOk();

        $this->assertSame($this->v159->id, $r->viewData('flat')->id);
        $this->assertCount(0, $r->viewData('rentRecords'));
        $this->assertEquals(0.0, (float) $r->viewData('totalRent'));
        $r->assertSee('No rent records found');
        // the admin figure for Villa 160 was never copied here, and was not changed by looking
        $this->assertEquals(10000.0, (float) $this->rent160->fresh()->paid_amount);
    }

    public function test_the_bills_listing_is_scoped_by_role(): void
    {
        $ids = fn (User $u) => $this->actingAs($u)->get(route('payments.index'))->assertOk()->viewData('payments')->pluck('id')->all();
        $all = Payment::where('society_id', $this->alpha->id)->pluck('id')->all();

        $this->assertEqualsCanonicalizing($all, $ids($this->admin), 'the office sees every Alpha payment, none of Beta\'s');
        $this->assertEqualsCanonicalizing($all, $ids($this->accountant));
        $tenantIds = $ids($this->tenant);
        $this->assertNotEmpty($tenantIds);
        $this->assertEmpty(array_intersect($tenantIds, Payment::where('flat_id', $this->v160->id)->pluck('id')->all()), 'a tenant never sees the payments of another unit');
    }

    // ── no double counting, paise, empty periods ────────────────────────────

    public function test_amounts_with_paise_add_up_exactly(): void
    {
        $bill = $this->bill($this->v160, 'maintenance', '2026-08-01', 30.30);
        $this->pay($bill, 10.10, 'success', '2026-08-03');
        $this->pay($bill, 20.20, 'success', '2026-08-04');

        $d = $this->maintenance($this->admin, ['report_type' => 'monthly', 'month' => 8, 'year' => 2026])->viewData('data');
        $o = $this->financial($this->admin, ['report_type' => 'monthly', 'month' => 8, 'year' => 2026])->viewData('report')['overview'];

        $this->assertEqualsWithDelta(30.30, $d['total_maintenance'], 0.0001);
        $this->assertEqualsWithDelta(30.30, $o['maintenance_collected'], 0.0001);
        $this->assertEqualsWithDelta(30.30, (float) $bill->fresh()->paid_amount, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $bill->fresh()->balance_amount, 0.0001);
    }

    public function test_a_period_with_no_records_is_an_honest_empty_report(): void
    {
        $r = $this->financial($this->admin, ['report_type' => 'monthly', 'month' => 1, 'year' => 2026])->viewData('report');

        $this->assertFalse($r['has_data']);
        $this->assertEquals(0.0, $r['overview']['total_income']);
        $this->assertEquals(0.0, $r['overview']['total_society_expenses']);
    }

    // ── who may open which finance screen ───────────────────────────────────

    /** @dataProvider accessMatrix */
    public function test_finance_screens_answer_each_role_as_designed(string $role, array $expected): void
    {
        $user = match ($role) {
            'Admin' => $this->admin, 'Accountant' => $this->accountant, 'Villa Owner' => $this->owner, 'Tenant' => $this->tenant,
            default => $this->makeUserWithRole($this->alpha, $role),
        };

        foreach ($expected as $route => $status) {
            $this->assertSame($status, $this->actingAs($user)->get(route($route))->getStatusCode(), "{$role} -> {$route}");
        }
    }

    public static function accessMatrix(): array
    {
        $pages = ['admin.rents.index', 'payments.index', 'expenses.index', 'reports.maintenance', 'reports.financial', 'utility-bills.index', 'tenant.rent'];

        return [
            'Admin'      => ['Admin',      array_combine($pages, [200, 200, 200, 200, 200, 200, 403])],
            'Accountant' => ['Accountant', array_combine($pages, [200, 200, 200, 403, 200, 200, 403])],
            'Villa Owner' => ['Villa Owner', array_combine($pages, [403, 200, 403, 200, 200, 200, 403])],
            'Tenant'     => ['Tenant',     array_combine($pages, [403, 200, 403, 200, 200, 200, 200])],
            'Staff'      => ['Staff',      array_combine($pages, [403, 403, 403, 403, 403, 403, 403])],
            'Guard'      => ['Guard',      array_combine($pages, [403, 403, 403, 403, 403, 403, 403])],
        ];
    }

    public function test_the_accountant_is_read_only_on_rent(): void
    {
        $this->actingAs($this->accountant)->get(route('admin.rents.create'))->assertForbidden();
        $this->actingAs($this->accountant)->post(route('admin.rents.store'), [])->assertForbidden();
    }
}
