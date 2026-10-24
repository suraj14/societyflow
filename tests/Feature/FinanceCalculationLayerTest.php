<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Facility;
use App\Models\FacilityBooking;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Society;
use App\Models\User;
use App\Models\VillaArea;
use App\Services\FinancialReportService;
use App\Support\Money;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The single calculation layer behind Payments, the Maintenance Report and the Financial Report.
 *
 * The scenario that started this is Royal Eden's: a ₹10,000 rent row with method "unrecorded" counted in "Total Collected";
 * a ₹1,700 October payment that settled a SEPTEMBER bill read as if it had paid October's; approved facility bookings with no
 * payment recorded; June 2025 expenses beside an October 2026 report. Each rule is asserted here with explicit figures.
 *
 * No gateway is involved: facility payments are recorded by the office (no real money moves), and nothing here can send a
 * real message or take a real payment.
 */
class FinanceCalculationLayerTest extends TestCase
{
    use RefreshDatabase;

    private Society $alpha;
    private Society $beta;
    private Flat $unitA;     // 101: owner Asha
    private Flat $unitB;     // 102: owner Bala
    private Flat $villa;     // villa 160: owner Suraj (rent)
    private User $admin;
    private User $accountant;
    private Facility $clubhouse;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = $this->makeSociety('Alpha');
        $this->beta = $this->makeSociety('Beta');
        $tower = $this->makeBuilding($this->alpha);
        $this->unitA = $this->makeFlat($this->alpha, $tower, '101');
        $this->unitB = $this->makeFlat($this->alpha, $tower, '102');
        $area = VillaArea::create(['society_id' => $this->alpha->id, 'name' => 'Garden', 'status' => 'active']);
        $this->villa = Flat::create(['society_id' => $this->alpha->id, 'villa_area_id' => $area->id, 'property_type' => 'villa', 'flat_number' => '160', 'status' => 'occupied']);

        foreach ([[$this->unitA, 'Asha Owner'], [$this->unitB, 'Bala Owner'], [$this->villa, 'Suraj Villa']] as [$flat, $name]) {
            $o = Owner::create(['society_id' => $this->alpha->id, 'name' => $name, 'email' => uniqid() . '@t.local', 'phone' => '9' . random_int(100000000, 999999999), 'status' => 'active']);
            $flat->update(['owner_id' => $o->id]);
        }

        $this->admin = $this->makeUserWithRole($this->alpha, 'Admin');
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_facilities', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('manage_facilities');
        $this->accountant = $this->makeUserWithRole($this->alpha, 'Accountant');
        $this->clubhouse = Facility::create(['society_id' => $this->alpha->id, 'name' => 'Clubhouse', 'type' => 'clubhouse', 'booking_charge' => 1500, 'status' => 'active',
            'capacity' => 100, 'opening_time' => '08:00', 'closing_time' => '22:00', 'advance_booking_days' => 60, 'max_booking_hours' => 8, 'requires_approval' => true]);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function bill(Flat $flat, string $date, float $amount, string $type = 'maintenance', ?Society $society = null): MaintenanceBill
    {
        $d = \Carbon\Carbon::parse($date);

        return MaintenanceBill::create(['society_id' => ($society ?? $this->alpha)->id, 'flat_id' => $flat->id, 'month' => $d->format('F'), 'year' => $d->year, 'bill_type' => $type,
            'bill_date' => $date, 'due_date' => $d->copy()->addDays(14)->toDateString(), 'maintenance_amount' => $amount, 'paid_amount' => 0]);
    }

    private function pay(MaintenanceBill $bill, float $amount, string $date, string $status = 'success', string $method = 'cash', ?string $type = null): Payment
    {
        $this->seq++;

        return Payment::create(['society_id' => $bill->society_id, 'flat_id' => $bill->flat_id, 'maintenance_bill_id' => $bill->id, 'payment_id' => 'PAY-FIN-' . $this->seq, 'amount' => $amount,
            'payment_method' => $method, 'status' => $status, 'payment_date' => $date, 'due_date' => $date, 'bill_type' => $type ?? $bill->bill_type,
            'receipt_number' => $status === 'success' && $method !== 'unrecorded' ? 'RCP-FIN-' . $this->seq : null]);
    }

    private function expense(string $category, float $amount, string $date, array $over = []): Expense
    {
        return Expense::create($over + ['society_id' => $this->alpha->id, 'scope' => 'society', 'flat_id' => null, 'paid_by' => 'society', 'created_by' => $this->admin->id,
            'expense_category_id' => ExpenseCategory::firstOrCreate(['society_id' => $this->alpha->id, 'name' => $category], ['status' => 'active'])->id,
            'title' => $category . ' expense', 'amount' => $amount, 'expense_date' => $date, 'payment_method' => 'online', 'status' => 'approved']);
    }

    private function booking(Flat $flat, string $date, string $status = 'approved', float $amount = 1500, ?Society $society = null, ?Facility $facility = null): FacilityBooking
    {
        $society ??= $this->alpha;

        return FacilityBooking::create(['society_id' => $society->id, 'facility_id' => ($facility ?? $this->clubhouse)->id, 'user_id' => $this->admin->id, 'flat_id' => $flat->id,
            'booking_date' => $date, 'start_time' => '10:00', 'end_time' => '12:00', 'purpose' => 'Party', 'expected_guests' => 10, 'booking_amount' => $amount, 'security_deposit' => 0,
            'payment_status' => 'pending', 'status' => $status]);
    }

    private function report(string $type, int $year, ?int $month = null, ?int $societyId = null): array
    {
        $period = $type === 'annual' ? ReportPeriod::annually($year) : ReportPeriod::monthly($year, $month);

        return app(FinancialReportService::class)->build($societyId ?? $this->alpha->id, $period);
    }

    private function october(): array
    {
        return $this->report('monthly', 2026, 10);
    }

    // ── bills are receivables, payments are cash ────────────────────────────

    public function test_unpaid_bills_are_receivables_not_cash(): void
    {
        foreach ([$this->unitA, $this->unitB, $this->villa] as $f) {
            $this->bill($f, '2026-10-01', 2700);
        }

        $r = $this->october();

        $this->assertEquals(8100, $r['overview']['maintenance_billed']);
        $this->assertEquals(0, $r['overview']['total_income'], 'nothing arrived');
        $this->assertEquals(8100, $r['billing']['outstanding']);
        $this->assertEquals(8100, $r['overview']['outstanding_dues']);
        $this->assertEquals(0, $r['overview']['net_position']);
    }

    public function test_partial_and_multiple_payments_reduce_what_is_owed_exactly_once(): void
    {
        $bill = $this->bill($this->unitA, '2026-10-01', 2700);

        $this->pay($bill, 1000, '2026-10-05');
        $r = $this->october();
        $this->assertEquals(1000, $r['overview']['total_income']);
        $this->assertEquals(1000, $r['billing']['collected']);
        $this->assertEquals(1700, $r['billing']['outstanding']);
        $this->assertEquals(1700, $r['overview']['outstanding_dues']);

        $this->pay($bill, 1700, '2026-10-20');
        $r = $this->october();
        $this->assertEquals(2700, $r['overview']['total_income'], 'two payments, each counted once');
        $this->assertEquals(2, $r['income']['count']);
        $this->assertEquals(0, $r['billing']['outstanding']);
        $this->assertEquals(0, $r['overview']['outstanding_dues']);
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_cash_received_for_an_earlier_bill_is_this_months_cash_but_does_not_pay_this_months_bills(): void
    {
        // the Royal Eden shape: Sept bill 2,700 paid 1,000 (10 Sep) + 1,700 (12 Oct); three October bills unpaid
        $sept = $this->bill($this->unitA, '2026-09-01', 2700);
        $this->pay($sept, 1000, '2026-09-10');
        $this->pay($sept, 1700, '2026-10-12');
        foreach ([$this->unitA, $this->unitB, $this->villa] as $f) {
            $this->bill($f, '2026-10-01', 2700);
        }

        $r = $this->october();

        $this->assertEquals(8100, $r['billing']['billed']);
        $this->assertEquals(0, $r['billing']['collected'], 'nothing has been paid against the October bills');
        $this->assertEquals(8100, $r['billing']['outstanding'], 'so ₹8,100 - not 6,400 - is still owed on them');
        $this->assertEquals(1700, $r['overview']['total_income'], 'but ₹1,700 of cash did arrive in October');
        $this->assertEquals(1700, $r['billing']['cash_in_period']['earlier'], 'and it settled the September bill');
        $this->assertEquals(0, $r['billing']['cash_in_period']['this_period']);
        $this->assertEquals(8100, $r['overview']['outstanding_dues'], 'the September bill is fully paid');
        $this->assertEquals(1700, $r['overview']['net_position']);
    }

    // ── owner rent ──────────────────────────────────────────────────────────

    public function test_owner_rent_is_never_society_income_expense_or_maintenance_outstanding(): void
    {
        $rent = $this->bill($this->villa, '2026-10-02', 10000, 'rent');
        $this->pay($rent, 10000, '2026-10-02', 'success', 'bank_transfer');
        $this->bill($this->villa, '2026-11-02', 10000, 'rent');   // next month's rent, unpaid
        $maint = $this->bill($this->unitA, '2026-10-01', 2700);
        $this->pay($maint, 2700, '2026-10-03');

        $r = $this->october();

        $this->assertEquals(2700, $r['overview']['total_income'], 'only the maintenance');
        $this->assertEquals(10000, $r['overview']['rent_collected'], 'rent is a memo: held for the owner');
        $this->assertEquals(0, $r['overview']['total_society_expenses']);
        $this->assertEquals(0, $r['overview']['outstanding_dues'], 'unpaid rent is not maintenance outstanding');
        $this->assertEquals(2700, $r['billing']['billed'], 'rent bills are not society billing');
        $this->assertSame('Suraj Villa', $r['rent_owners'][0]['owner']);
        $this->assertEquals(10000, $r['rent_owners'][0]['verified']);
        $this->assertSame('Villa 160', $r['rent_owners'][0]['property']);
    }

    public function test_rent_with_no_evidence_of_receipt_is_excluded_from_collections_and_the_record_is_kept(): void
    {
        $rent = $this->bill($this->villa, '2026-09-22', 10000, 'rent');
        $payment = $this->pay($rent, 10000, '2026-09-22', 'success', 'unrecorded');

        $r = $this->report('monthly', 2026, 9);

        $this->assertEquals(0, $r['overview']['total_income']);
        $this->assertEquals(0, $r['overview']['rent_collected'], 'not even as rent held for the owner: nothing proves it arrived');
        $this->assertEquals(10000, $r['excluded']['rent_unverified']);
        $this->assertEquals(10000, $r['rent_owners'][0]['unverified']);
        $this->assertStringContainsString('NO evidence', collect($r['excluded']['transactions'])->pluck('source')->implode('|'));

        // nothing was changed to make that so
        $payment->refresh();
        $this->assertSame('success', $payment->status);
        $this->assertSame('unrecorded', $payment->payment_method);
        $this->assertSame('paid', $rent->fresh()->status);
        $this->assertEquals(10000, $rent->fresh()->paid_amount);
    }

    public function test_a_maintenance_payment_with_no_evidence_is_not_cash_but_the_bill_still_shows_it_as_settled_and_says_so(): void
    {
        $bill = $this->bill($this->unitA, '2026-10-01', 2700);
        $this->pay($bill, 2700, '2026-10-04', 'success', 'unrecorded');

        $r = $this->october();

        $this->assertEquals(0, $r['overview']['total_income']);
        $this->assertEquals(2700, $r['excluded']['other_unverified']);
        $this->assertEquals(2700, $r['billing']['collected'], 'the bill\'s own ledger is untouched');
        $this->assertEquals(2700, $r['billing']['collected_without_evidence']);
        $this->assertEquals(0, $r['billing']['collected_with_evidence']);
        $this->assertEquals(0, $r['billing']['outstanding']);
    }

    public function test_pending_failed_and_cancelled_payments_are_listed_and_never_counted(): void
    {
        $bill = $this->bill($this->unitA, '2026-10-01', 2700);
        $this->pay($bill, 500, '2026-10-05', 'pending');
        $this->pay($bill, 300, '2026-10-06', 'failed');
        $this->pay($bill, 200, '2026-10-07', 'cancelled');

        $r = $this->october();

        $this->assertEquals(0, $r['overview']['total_income']);
        $this->assertEquals(2700, $r['billing']['outstanding']);
        $this->assertEquals(500, $r['excluded']['not_received']['pending']['amount']);
        $this->assertEquals(300, $r['excluded']['not_received']['failed']['amount']);
        $this->assertEquals(200, $r['excluded']['not_received']['cancelled']['amount']);
    }

    // ── the Payments page ───────────────────────────────────────────────────

    public function test_payments_total_collected_is_actual_society_cash_not_the_sum_of_every_row(): void
    {
        $rent = $this->bill($this->villa, '2026-09-22', 10000, 'rent');
        $this->pay($rent, 10000, '2026-09-22', 'success', 'unrecorded');
        $m1 = $this->bill($this->unitA, '2026-09-01', 2700);
        $this->pay($m1, 2700, '2026-09-10');
        $m2 = $this->bill($this->unitB, '2026-09-01', 2700);
        $this->pay($m2, 1000, '2026-09-10');
        $this->pay($m2, 1700, '2026-10-12', 'success', 'online');
        $this->pay($m2, 400, '2026-10-13', 'pending');
        $rent2 = $this->bill($this->villa, '2026-10-02', 8000, 'rent');
        $this->pay($rent2, 8000, '2026-10-02', 'success', 'cash');

        $stats = $this->actingAs($this->admin)->get(route('payments.index'))->assertOk()->viewData('stats');

        $this->assertEquals(5400, $stats['total_amount'], '2,700 + 1,000 + 1,700: not the 10,000 unrecorded rent, not the 8,000 owner rent, not the pending 400');
        $this->assertSame(3, $stats['total_count']);
        $this->assertEquals(8000, $stats['rent_amount'], 'owner rent with evidence: shown, not collected');
        $this->assertEquals(10000, $stats['no_evidence_amount']);
        $this->assertEquals(400, $stats['pending_amount']);
        $this->actingAs($this->admin)->get(route('payments.index'))->assertSee('owner rent')->assertSee('no evidence of receipt', false);
    }

    public function test_this_month_and_today_follow_the_payment_date_not_the_day_the_row_was_typed_in(): void
    {
        $bill = $this->bill($this->unitA, '2026-01-01', 2700);
        $p = $this->pay($bill, 2700, \App\Support\SocietyClock::today()->subMonths(3)->toDateString());   // paid three months ago, entered just now

        $stats = $this->actingAs($this->admin)->get(route('payments.index'))->viewData('stats');
        $this->assertEquals(2700, $stats['total_amount']);
        $this->assertEquals(0, $stats['this_month_amount'], 'created_at is today but the money arrived three months ago');
        $this->assertEquals(0, $stats['today_amount']);

        $p->update(['payment_date' => \App\Support\SocietyClock::today()->toDateString()]);
        $stats = $this->actingAs($this->admin)->get(route('payments.index'))->viewData('stats');
        $this->assertEquals(2700, $stats['this_month_amount']);
        $this->assertEquals(2700, $stats['today_amount']);
    }

    public function test_a_resident_sees_what_they_paid_including_their_rent_but_never_a_pending_row(): void
    {
        $tenant = $this->makeUserWithRole($this->alpha, 'Tenant');
        $t = \App\Models\Tenant::create(['society_id' => $this->alpha->id, 'flat_id' => $this->unitA->id, 'user_id' => $tenant->id, 'name' => 'T', 'email' => 't@t.local', 'phone' => '9000000101', 'move_in_date' => '2026-01-01', 'monthly_rent' => 10000, 'status' => 'active']);
        $tenant->update(['tenant_id' => $t->id]);
        $rent = $this->bill($this->unitA, '2026-10-01', 10000, 'rent');
        $this->pay($rent, 10000, '2026-10-02');
        $maint = $this->bill($this->unitA, '2026-10-01', 2700);
        $this->pay($maint, 2700, '2026-10-03');
        $this->pay($maint, 999, '2026-10-04', 'pending');

        $stats = $this->actingAs($tenant)->get(route('payments.index'))->assertOk()->viewData('stats');

        $this->assertEquals(12700, $stats['total_amount'], 'their own rent is theirs to see');
        $this->assertSame(2, $stats['total_count']);
    }

    // ── facility bookings ───────────────────────────────────────────────────

    public function test_an_approved_booking_with_no_payment_is_a_receivable_not_income(): void
    {
        $this->booking($this->unitA, '2026-10-24');
        $this->booking($this->unitB, '2026-10-25');
        $this->booking($this->unitA, '2026-10-26', 'rejected');
        $this->booking($this->unitA, '2026-10-27', 'cancelled');

        $r = $this->october();

        $this->assertEquals(0, $r['overview']['total_income']);
        $this->assertEquals(0, $r['overview']['facility_collected']);
        $this->assertEquals(3000, $r['overview']['facility_receivable'], 'two approved bookings; rejected and cancelled ones are owed nothing');
        $this->assertSame(2, $r['facility']['receivable']['count']);
    }

    public function test_a_recorded_facility_payment_is_income_once_even_if_recorded_again(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');

        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'upi', 'payment_reference' => 'UTR-884412', 'paid_on' => '2026-10-10'])->assertSessionHas('success');
        $again = $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'upi', 'payment_reference' => 'UTR-884412', 'paid_on' => '2026-10-10']);   // a repeated callback / double click
        $again->assertSessionHas('error');

        $b->refresh();
        $this->assertSame('paid', $b->payment_status);
        $this->assertEquals(1500, $b->paid_amount);
        $this->assertSame($this->admin->id, (int) $b->payment_recorded_by);
        $this->assertSame('2026-10-10', $b->paid_at->toDateString());

        $r = $this->october();
        $this->assertEquals(1500, $r['overview']['total_income']);
        $this->assertEquals(1500, $r['overview']['facility_collected']);
        $this->assertEquals(0, $r['overview']['facility_receivable'], 'no longer owed');
        $this->assertSame(1, $r['facility']['collected']['count'], 'one payment');
    }

    public function test_the_same_transaction_reference_cannot_be_recorded_for_two_bookings(): void
    {
        $a = $this->booking($this->unitA, '2026-10-24');
        $b = $this->booking($this->unitB, '2026-10-25');

        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $a), ['payment_method' => 'upi', 'payment_reference' => 'UTR-1'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'upi', 'payment_reference' => 'UTR-1'])->assertSessionHas('error');

        $this->assertSame('pending', $b->fresh()->payment_status);
        $this->assertEquals(1500, $this->october()['overview']['total_income']);
    }

    public function test_recording_a_payment_has_rules_a_reference_for_non_cash_an_approved_booking_a_charge_and_the_right_person(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');
        $pendingBooking = $this->booking($this->unitB, '2026-10-25', 'pending');
        $free = $this->booking($this->unitB, '2026-10-26', 'approved', 0);

        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'upi'])->assertSessionHasErrors('payment_reference');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'unrecorded', 'payment_reference' => 'x'])->assertSessionHasErrors('payment_method');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'cash', 'paid_on' => now()->addDays(3)->toDateString()])->assertSessionHasErrors('paid_on');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $pendingBooking), ['payment_method' => 'cash'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $free), ['payment_method' => 'cash'])->assertSessionHas('error');
        $this->assertSame('pending', $b->fresh()->payment_status);

        // a resident cannot record their own payment; another society's admin cannot touch it
        $resident = $this->makeResidentUser($this->alpha, $this->unitA);
        $this->actingAs($resident)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'cash'])->assertForbidden();
        $otherAdmin = $this->makeUserWithRole($this->beta, 'Admin');
        $otherAdmin->givePermissionTo('manage_facilities');   // the permission alone must not reach another society's booking
        $this->actingAs($otherAdmin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'cash'])->assertForbidden();
        $this->assertSame('pending', $b->fresh()->payment_status);

        // cash needs no reference
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'cash'])->assertSessionHas('success');
    }

    public function test_a_refund_is_a_second_dated_record_the_payment_stays_and_it_is_netted_in_the_month_it_is_paid_back(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'cash', 'paid_on' => '2026-09-28']);
        $this->assertSame('2026-09-28', $b->fresh()->paid_at->toDateString());

        // more than was received, and without a reason, are refused
        $this->actingAs($this->admin)->post(route('facility-bookings.record-refund', $b), ['refund_amount' => 2000, 'refund_reason' => 'cancelled'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-refund', $b), ['refund_amount' => 500])->assertSessionHasErrors('refund_reason');
        $this->assertSame('paid', $b->fresh()->payment_status);

        $this->actingAs($this->admin)->post(route('facility-bookings.record-refund', $b), ['refund_amount' => 1500, 'refund_reason' => 'Event cancelled'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-refund', $b), ['refund_amount' => 1500, 'refund_reason' => 'again'])->assertSessionHas('error');

        $b->refresh();
        $this->assertSame('refunded', $b->payment_status);
        $this->assertEquals(1500, $b->paid_amount, 'the payment record is kept');
        $this->assertEquals(1500, $b->refunded_amount);

        $sept = $this->report('monthly', 2026, 9);
        $oct = $this->october();
        $this->assertEquals(1500, $sept['overview']['facility_collected'], 'September received it');
        $this->assertEquals(-1500, $oct['overview']['facility_collected'], 'and paid it back in the month the refund was recorded');
        $this->assertEquals(0, $this->report('annual', 2026)['overview']['facility_collected'], 'over the year it nets to nothing');
    }

    public function test_a_paid_booking_is_never_deleted_so_income_cannot_vanish_from_the_books(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'cash']);

        $this->actingAs($this->admin)->delete(route('facility-bookings.destroy', $b))->assertSessionHas('error');

        $this->assertNotNull(FacilityBooking::find($b->id));
        $this->assertEquals(1500, $this->october()['overview']['facility_collected'] ?: $this->report('monthly', 2026, (int) now()->format('n'))['overview']['facility_collected']);
    }

    public function test_cancelling_a_paid_booking_keeps_the_income_until_a_refund_is_recorded(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');
        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'cash', 'paid_on' => '2026-10-02']);

        $this->actingAs($this->admin)->put(route('facility-bookings.update-status', $b), ['status' => 'cancelled', 'admin_notes' => 'host withdrew']);

        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertSame('paid', $b->fresh()->payment_status, 'cancelling does not move money');
        $this->assertEquals(1500, $this->october()['overview']['facility_collected'], 'the cash is still the society\'s until it is paid back');
    }

    public function test_duplicate_payment_callbacks_at_the_model_level_record_the_money_once(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');

        $first = $b->recordPayment($this->admin, 'online', 'GW-1');
        $stale = FacilityBooking::find($b->id)->setRawAttributes(array_merge($b->getAttributes(), ['payment_status' => 'pending']));   // a second callback holding an old copy
        $second = $stale->recordPayment($this->admin, 'online', 'GW-1');

        $this->assertTrue($first);
        $this->assertFalse($second);
        $this->assertSame(1, FacilityBooking::where('payment_status', 'paid')->count());
        $this->assertEquals(1500, FacilityBooking::sum('paid_amount'));
    }

    // ── payment vs cancellation race ────────────────────────────────────────

    public function test_a_booking_cancelled_after_the_earlier_check_cannot_receive_a_payment(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');
        $stale = FacilityBooking::find($b->id);                      // the request that already passed its "approved" check
        FacilityBooking::whereKey($b->id)->update(['status' => 'cancelled']);   // the cancellation lands in between

        $this->assertFalse($stale->recordPayment($this->admin, 'cash', null));

        $row = FacilityBooking::find($b->id);
        $this->assertSame('pending', $row->payment_status);
        $this->assertNull($row->paid_at);
        $this->assertEquals(0, $this->october()['overview']['facility_collected']);
        $this->assertStringContainsString('cancelled', $stale->paymentBlockReason());
    }

    public function test_the_controller_refuses_a_payment_for_a_booking_cancelled_underneath_it(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');
        FacilityBooking::whereKey($b->id)->update(['status' => 'cancelled']);

        $this->actingAs($this->admin)->post(route('facility-bookings.record-payment', $b), ['payment_method' => 'cash', 'paid_on' => '2026-10-02'])->assertSessionHas('error');

        $this->assertSame('pending', $b->fresh()->payment_status);
        $this->assertEquals(0, FacilityBooking::sum('paid_amount'));
    }

    public function test_payment_first_then_cancellation_keeps_the_money_and_a_repeat_payment_is_idempotent(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');
        $this->assertTrue($b->recordPayment($this->admin, 'cash', null));
        FacilityBooking::whereKey($b->id)->update(['status' => 'cancelled']);

        $this->assertFalse(FacilityBooking::find($b->id)->recordPayment($this->admin, 'cash', null));
        $this->assertSame('paid', $b->fresh()->payment_status);
        $this->assertEquals(1500, FacilityBooking::sum('paid_amount'));
    }

    public function test_a_repeated_refund_records_once_and_cannot_exceed_what_was_paid(): void
    {
        $b = $this->booking($this->unitA, '2026-10-24');
        $b->recordPayment($this->admin, 'cash', null);

        $this->assertFalse(FacilityBooking::find($b->id)->recordRefund($this->admin, 2000, 'too much'));
        $this->assertTrue(FacilityBooking::find($b->id)->recordRefund($this->admin, 1500, 'cancelled'));
        $this->assertFalse(FacilityBooking::find($b->id)->recordRefund($this->admin, 1500, 'again'));
        $this->assertEquals(1500, FacilityBooking::find($b->id)->refunded_amount);
    }

    // ── every dashboard and the mobile API use the same collection rules ────

    private function seedThisMonthsMoney(): void
    {
        $today = now()->toDateString();
        $bill = $this->bill($this->unitA, $today, 5000);
        $this->pay($bill, 1000, $today);                                              // counts
        $this->pay($this->bill($this->unitB, $today, 900, 'rent'), 500, $today, 'success', 'cash');   // owner rent: never society income
        $villaBill = $this->bill($this->villa, $today, 900);
        $this->pay($villaBill, 300, $today, 'success', 'unrecorded');                                 // no evidence of receipt
        $this->pay($villaBill, 200, $today, 'pending');
        $this->pay($villaBill, 100, $today, 'failed');
        // typed in this month, but the money arrived last month: belongs to last month
        $old = $this->pay($this->bill($this->unitB, now()->subMonths(2)->toDateString(), 700), 400, now()->subMonthNoOverflow()->startOfMonth()->toDateString());
        // a verified bill-less payment (gas) is real income
        Payment::create(['society_id' => $this->alpha->id, 'flat_id' => $this->unitA->id, 'payment_id' => 'PAY-GAS-1', 'amount' => 150, 'payment_method' => 'online', 'status' => 'success',
            'payment_date' => $today, 'due_date' => $today, 'bill_type' => 'maintenance', 'receipt_number' => 'RCP-GAS-1', 'transaction_id' => 'TXN-GAS-1']);
        // another society's money
        $other = $this->bill($this->unitA, $today, 777, 'maintenance', $this->beta);
        $this->pay($other, 777, $today);
        $this->booking($this->unitA, $today)->recordPayment($this->admin, 'cash', null);   // facility 1500
    }

    public function test_every_collection_figure_agrees_and_follows_the_rules(): void
    {
        $this->seedThisMonthsMoney();
        $expected = 1000 + 150 + 1500;

        [$s, $e] = FinancialReportService::currentMonthBounds($this->alpha->id);
        $this->assertEquals($expected, FinancialReportService::societyCollections($this->alpha->id, $s, $e)['amount']);
        $this->assertEquals(777, FinancialReportService::societyCollections($this->beta->id, $s, $e)['amount'], 'society scoped');

        \Laravel\Sanctum\Sanctum::actingAs($this->admin);
        $this->assertEqualsWithDelta($expected, $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.kpis.monthly_revenue'), 0.001);
        $c = $this->getJson('/api/v1/admin/collections')->assertOk();
        $this->assertEqualsWithDelta($expected, $c->json('meta.summary.amount'), 0.001);
        $this->assertEqualsWithDelta(1150, $c->json('meta.summary.payments_amount'), 0.001);
        $this->assertEqualsWithDelta(1500, $c->json('meta.summary.facility_amount'), 0.001);
        $listed = collect($c->json('data'))->count();
        $this->assertSame(2, $listed, 'the list shows exactly the counted payments');

        $stats = $this->actingAs($this->accountant, 'web')->get('/dashboard')->assertOk()->viewData('stats');
        $this->assertEquals($expected, $stats['monthly_collection']);
        $this->assertEquals($expected + 400, $stats['total_collected'], 'all-time adds last month\'s verified 400');

        $super = $this->makeUserWithRole($this->alpha, 'Super Admin');
        $super->update(['society_id' => null]);
        $r = $this->actingAs($super, 'web')->get(route('super-admin.societies.show', $this->alpha))->assertOk();
        $this->assertEquals($expected, $r->viewData('stats')['monthly_revenue']);
    }

    public function test_the_payment_date_not_the_typing_date_decides_the_month(): void
    {
        $bill = $this->bill($this->unitA, '2026-09-05', 1000);
        $p = $this->pay($bill, 1000, '2026-09-20');
        DB::table('payments')->where('id', $p->id)->update(['created_at' => '2026-10-10 09:00:00']);

        $this->assertEquals(1000, FinancialReportService::societyCollections($this->alpha->id, '2026-09-01', '2026-09-30')['amount']);
        $this->assertEquals(0, FinancialReportService::societyCollections($this->alpha->id, '2026-10-01', '2026-10-31')['amount']);
    }
    // ── expenses ────────────────────────────────────────────────────────────

    public function test_expenses_appear_only_in_the_period_of_their_own_date(): void
    {
        $this->expense('Security', 120000, '2025-06-07');
        $this->expense('Electricity', 18000, '2025-06-05');
        $this->expense('Security', 5000, '2026-10-01');
        $this->expense('Security', 1000, '2026-10-02', ['status' => 'pending']);
        $this->expense('Security', 2000, '2026-10-03', ['status' => 'rejected']);
        $this->expense('Gardening', 3500, '2026-10-04', ['scope' => 'property', 'flat_id' => $this->villa->id, 'paid_by' => 'owner']);

        $oct = $this->october();
        $this->assertEquals(5000, $oct['overview']['total_society_expenses'], 'June 2025 is not October 2026; pending, rejected and property expenses are not society expenses');
        $this->assertSame(1, $oct['expenses']['count']);

        $june = $this->report('monthly', 2025, 6);
        $this->assertEquals(138000, $june['overview']['total_society_expenses']);
        $this->assertSame(['Security', 'Electricity'], array_column($june['expenses']['rows'], 'category'));
        $this->assertEquals(87.0, $june['expenses']['rows'][0]['percent']);
        $this->assertEquals(-138000, $june['overview']['net_position']);
        $this->assertSame(2, count($june['expenses']['transactions']));
    }

    // ── isolation ───────────────────────────────────────────────────────────

    public function test_another_societys_money_never_reaches_this_report(): void
    {
        $bFlat = Flat::create(['society_id' => $this->beta->id, 'property_type' => 'villa', 'flat_number' => '1', 'status' => 'occupied']);
        $bBill = $this->bill($bFlat, '2026-10-01', 9999, 'maintenance', $this->beta);
        $this->pay($bBill, 9999, '2026-10-02');
        $fac = Facility::create(['society_id' => $this->beta->id, 'name' => 'Hall', 'type' => 'clubhouse', 'booking_charge' => 700, 'status' => 'active', 'capacity' => 10, 'opening_time' => '08:00', 'closing_time' => '22:00', 'advance_booking_days' => 30, 'max_booking_hours' => 4, 'requires_approval' => true]);
        $bBooking = $this->booking($bFlat, '2026-10-12', 'approved', 700, $this->beta, $fac);
        $bBooking->recordPayment($this->admin, 'cash', null);
        Expense::create(['society_id' => $this->beta->id, 'scope' => 'society', 'paid_by' => 'society', 'created_by' => $this->admin->id,
            'expense_category_id' => ExpenseCategory::create(['society_id' => $this->beta->id, 'name' => 'Security', 'status' => 'active'])->id, 'title' => 'x', 'amount' => 4444, 'expense_date' => '2026-10-05', 'payment_method' => 'online', 'status' => 'approved']);

        $r = $this->october();

        $this->assertEquals(0, $r['overview']['total_income']);
        $this->assertEquals(0, $r['overview']['total_society_expenses']);
        $this->assertEquals(0, $r['overview']['outstanding_dues']);
        $this->assertEquals(0, $r['overview']['facility_receivable']);
        $beta = $this->report('monthly', 2026, 10, $this->beta->id);
        $this->assertEquals(9999 + 700, $beta['overview']['total_income'], 'their own payment and their own facility income, and only theirs');
        $this->assertEquals(700, $beta['overview']['facility_collected']);
        $this->assertEquals(0, $this->actingAs($this->admin)->get(route('payments.index'))->viewData('stats')['total_amount']);
    }

    // ── reconciliation: months and the year ─────────────────────────────────

    public function test_the_twelve_months_add_up_to_the_annual_report_to_the_paisa(): void
    {
        $this->bill($this->villa, '2026-03-02', 10000, 'rent');
        $rent = MaintenanceBill::where('bill_type', 'rent')->first();
        $this->pay($rent, 10000, '2026-03-02', 'success', 'cash');                                     // owner rent: in no total
        $this->pay($this->bill($this->villa, '2026-04-02', 8000, 'rent'), 8000, '2026-04-02', 'success', 'unrecorded');   // no evidence
        foreach ([1, 3, 5, 8, 10, 12] as $m) {
            $bill = $this->bill($this->unitA, sprintf('2026-%02d-01', $m), 2700.35);
            $this->pay($bill, 1000.10, sprintf('2026-%02d-05', $m));
            $this->pay($bill, 700.20, sprintf('2026-%02d-25', $m));                                    // paise on purpose
            $this->expense('Security', 1234.56, sprintf('2026-%02d-15', $m));
        }
        $this->expense('Security', 99.99, '2025-12-31');                                                // last year
        $this->expense('Security', 99.99, '2027-01-01');                                                // next year
        $b = $this->booking($this->unitB, '2026-06-10');
        $b->recordPayment($this->admin, 'cash', null, \Carbon\Carbon::parse('2026-06-10'));
        $b->recordRefund($this->admin, 500.50, 'partial');
        DB::table('facility_bookings')->where('id', $b->id)->update(['refunded_at' => '2026-07-01 10:00:00']);

        $annual = $this->report('annual', 2026);

        $sum = fn (string $k) => Money::sum(array_column($annual['months'], $k));
        $this->assertCount(12, $annual['months']);
        $this->assertSame($annual['overview']['total_income'], $sum('income'), 'collections');
        $this->assertSame($annual['overview']['total_society_expenses'], $sum('expenses'), 'expenses');
        $this->assertSame($annual['overview']['net_position'], $sum('net'), 'net cash movement');
        $this->assertSame($annual['overview']['maintenance_billed'], $sum('billed'), 'billed');
        $this->assertSame(Money::sum(array_fill(0, 6, 1700.30)) + 1500.00 - 500.50, $annual['overview']['total_income']);
        $this->assertSame(Money::sum(array_fill(0, 6, 1234.56)), $annual['overview']['total_society_expenses'], 'neither 31 Dec 2025 nor 1 Jan 2027');

        // and every month in the table is exactly what that month's own report says
        foreach ($annual['months'] as $row) {
            $monthly = $this->report('monthly', 2026, $row['month']);
            $this->assertSame($monthly['overview']['total_income'], $row['income'], $row['label']);
            $this->assertSame($monthly['overview']['total_society_expenses'], $row['expenses'], $row['label']);
        }
    }

    public function test_money_is_added_in_whole_paise_so_nothing_drifts(): void
    {
        $this->assertSame(0.3, Money::sum([0.1, 0.2]));
        $this->assertSame(99.99, Money::sum([33.33, 33.33, 33.33]));
        $this->assertSame(0.3, Money::diff(0.5, 0.2));
        $this->assertTrue(Money::same('2700.00', 2700));

        $bill = $this->bill($this->unitA, '2026-10-01', 0.30);
        $this->pay($bill, 0.10, '2026-10-02');
        $this->pay($bill, 0.20, '2026-10-03');
        $r = $this->october();
        $this->assertSame(0.3, $r['overview']['total_income']);
        $this->assertSame(0.0, $r['billing']['outstanding']);
    }

    // ── the screens ─────────────────────────────────────────────────────────

    public function test_the_financial_report_page_explains_itself_and_opens_each_total_into_its_transactions(): void
    {
        $bill = $this->bill($this->unitA, '2026-10-01', 2700);
        $this->pay($bill, 2700, '2026-10-03', 'success', 'upi');
        $rent = $this->bill($this->villa, '2026-10-02', 10000, 'rent');
        $this->pay($rent, 10000, '2026-10-02', 'success', 'unrecorded');
        $this->expense('Security', 5000, '2026-10-05');
        $this->booking($this->unitB, '2026-10-24');

        $this->actingAs($this->admin)->get(route('reports.financial', ['report_type' => 'monthly', 'month' => 10, 'year' => 2026]))->assertOk()
            ->assertSee('How to read this page')
            ->assertSee('Actual Collections')->assertSee('Net Cash Movement')->assertSee('Facility Payments Awaited')->assertSee('Held for Owners')
            ->assertSee('Open the 1 transactions behind this total')->assertSee('Open the 1 expenses behind this total')
            ->assertSee('Excluded from society income')->assertSee('NO evidence of receipt', false)
            ->assertSee('₹2,700.00');
        $this->actingAs($this->accountant)->get(route('reports.financial', ['report_type' => 'monthly', 'month' => 10, 'year' => 2026]))->assertOk()->assertSee('Actual Collections');
        $this->actingAs($this->admin)->get(route('reports.financial', ['report_type' => 'annually', 'year' => 2026]))->assertOk()->assertSee('month by month')->assertSee('add up to the annual figures');
    }

    public function test_the_pdf_renders_the_same_new_sections(): void
    {
        $this->pay($this->bill($this->unitA, '2026-10-01', 2700), 2700, '2026-10-03');
        $this->booking($this->unitB, '2026-10-24');

        $response = $this->actingAs($this->admin)->post(route('reports.download-financial'), ['report_type' => 'monthly', 'month' => 10, 'year' => 2026]);

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_maintenance_report_separates_what_was_billed_from_what_arrived_and_where_it_went(): void
    {
        $sept = $this->bill($this->unitA, '2026-09-01', 2700);
        $this->pay($sept, 1000, '2026-09-10');
        $this->pay($sept, 1700, '2026-10-12', 'success', 'online');
        foreach ([$this->unitA, $this->unitB, $this->villa] as $f) {
            $this->bill($f, '2026-10-01', 2700);
        }
        $rent = $this->bill($this->villa, '2026-10-02', 10000, 'rent');
        $this->pay($rent, 10000, '2026-10-02', 'success', 'unrecorded');

        $page = $this->actingAs($this->admin)->get(route('reports.maintenance', ['report_type' => 'monthly', 'month' => 10, 'year' => 2026]))->assertOk();
        $data = $page->viewData('data');

        $this->assertEquals(8100, $data['billed']);
        $this->assertEquals(8100, $data['outstanding'], 'outstanding on the October bills');
        $this->assertEquals(1700, $data['total_maintenance'], 'cash received in October');
        $this->assertEquals(0, $data['collected_on_these_bills']);
        $this->assertEquals(1700, $data['cash_against_earlier_bills']);
        $this->assertEquals(8100, $data['outstanding_all_time']);
        $page->assertSee('Received in October 2026')->assertSee('earlier bills');
    }

    public function test_deleting_a_payment_leaves_an_audit_entry_that_can_rebuild_the_row_and_the_bill_is_honest_again(): void
    {
        $rent = $this->bill($this->villa, '2026-09-22', 10000, 'rent');
        $payment = $this->pay($rent, 10000, '2026-09-22', 'success', 'unrecorded');
        $payment->update(['transaction_id' => 'RECON-BILL-' . $rent->id]);

        $this->actingAs($this->admin)->delete(route('payments.destroy', $payment))->assertRedirect();

        $log = \App\Models\ActivityLog::where('action', 'payment.deleted')->where('model_id', $payment->id)->firstOrFail();
        $values = is_array($log->new_values) ? $log->new_values : json_decode($log->new_values, true);
        $this->assertEquals(10000, $values['amount']);
        $this->assertSame('unrecorded', $values['payment_method']);
        $this->assertSame('2026-09-22', $values['payment_date']);
        $this->assertSame('RECON-BILL-' . $rent->id, $values['transaction_id']);
        $this->assertSame('rent', $values['bill_type']);
        $this->assertSame($this->villa->id, $values['flat_id']);

        $rent->refresh();
        $this->assertEquals(0, $rent->paid_amount, 'the bill no longer claims money that has no payment behind it');
        $this->assertContains($rent->status, ['pending', 'overdue'], 'it is owed again, not "paid"');
    }

    // ── the read-only reconciliation ────────────────────────────────────────

    public function test_the_reconciliation_command_changes_nothing_and_names_the_records_that_need_a_decision(): void
    {
        $rent = $this->bill($this->villa, '2026-09-22', 10000, 'rent');
        $this->pay($rent, 10000, '2026-09-22', 'success', 'unrecorded');
        $b = $this->booking($this->unitA, '2026-10-24');
        $this->expense('Security', 120000, '2025-06-07', ['created_at' => '2026-10-02 08:00:00']);

        $before = [Payment::count(), MaintenanceBill::count(), Expense::count(), FacilityBooking::count(), Payment::max('updated_at'), MaintenanceBill::max('updated_at')];

        $this->artisan('finance:reconcile', ['--society' => $this->alpha->id])
            ->expectsOutputToContain('READ-ONLY')
            ->expectsOutputToContain('success payments with method "unrecorded"')
            ->expectsOutputToContain('approved / completed facility bookings with a charge and NO payment recorded')
            ->expectsOutputToContain('expenses entered more than 90 days after their expense date')
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();

        $this->assertSame($before, [Payment::count(), MaintenanceBill::count(), Expense::count(), FacilityBooking::count(), Payment::max('updated_at'), MaintenanceBill::max('updated_at')]);
        $this->assertSame('pending', $b->fresh()->payment_status);
    }
}
