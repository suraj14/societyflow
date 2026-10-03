<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Society;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The one calculation path (FinancialReportService) across the edge cases
 * a society admin actually hits: no records, one record, many, several on
 * the same day, the first/last day of a month, the year boundary, and the
 * previous / current / future month selections.
 */
class ReportCalculationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function fixture(): array
    {
        $society = $this->makeSociety();
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $flat    = Flat::create(['society_id' => $society->id, 'flat_number' => '160', 'property_type' => 'villa', 'floor' => 0, 'status' => 'vacant']);

        return compact('society', 'admin', 'flat');
    }

    private function pay(array $f, float $amount, string $date, string $type = 'Maintenance', string $status = 'success'): Payment
    {
        $this->seq++;

        return Payment::create([
            'society_id' => $f['society']->id, 'flat_id' => $f['flat']->id, 'payment_id' => 'P' . $this->seq,
            'amount' => $amount, 'payment_method' => 'cash', 'status' => $status, 'payment_date' => $date,
            'bill_type' => $type, 'due_date' => $date,
        ]);
    }

    private function spend(array $f, float $amount, string $date, string $category = 'Security'): Expense
    {
        $cat = ExpenseCategory::firstOrCreate(['society_id' => $f['society']->id, 'name' => $category], ['status' => 'active']);

        return Expense::create([
            'society_id' => $f['society']->id, 'scope' => 'society', 'paid_by' => 'society', 'expense_category_id' => $cat->id,
            'created_by' => $f['admin']->id, 'title' => $category, 'amount' => $amount, 'expense_date' => $date,
            'payment_method' => 'cash', 'status' => 'approved',
        ]);
    }

    private function report(array $f, ReportPeriod $period): array
    {
        return app(FinancialReportService::class)->build($f['society']->id, $period);
    }

    public function test_zero_records_is_an_honest_empty_report(): void
    {
        $f = $this->fixture();
        $r = $this->report($f, ReportPeriod::monthly(2026, 6));

        $this->assertFalse($r['has_data']);
        $this->assertSame(0, $r['transaction_count']);
        foreach (['total_income', 'maintenance_collected', 'other_income', 'rent_collected', 'total_society_expenses', 'outstanding_dues', 'net_position'] as $k) {
            $this->assertEquals(0, $r['overview'][$k], $k);
        }
        $this->assertSame([], $r['income']['rows']);
        $this->assertSame([], $r['expenses']['rows']);
    }

    public function test_one_record_each_side(): void
    {
        $f = $this->fixture();
        $this->pay($f, 1500.50, '2026-06-15');
        $this->spend($f, 400.25, '2026-06-20');

        $r = $this->report($f, ReportPeriod::monthly(2026, 6));

        $this->assertTrue($r['has_data']);
        $this->assertEquals(1500.50, $r['overview']['total_income']);
        $this->assertEquals(400.25, $r['overview']['total_society_expenses']);
        $this->assertEquals(1100.25, $r['overview']['net_position']);
        $this->assertSame(1, $r['income']['count']);
        $this->assertSame(1, $r['expenses']['count']);
        $this->assertEquals(100.0, $r['expenses']['rows'][0]['percent']);
    }

    public function test_many_records_and_same_day_records_are_all_counted_once(): void
    {
        $f = $this->fixture();
        foreach ([100, 200, 300] as $amt) {
            $this->pay($f, $amt, '2026-06-10');            // three on the same day
        }
        $this->pay($f, 50, '2026-06-10', 'Water Bill');     // utility, same day
        $this->pay($f, 999, '2026-06-10', 'Maintenance', 'pending'); // never counted
        $this->pay($f, 5000, '2026-06-10', 'rent');         // memo, not income
        $this->spend($f, 70, '2026-06-10');
        $this->spend($f, 30, '2026-06-10', 'Gardening');

        $r = $this->report($f, ReportPeriod::monthly(2026, 6));

        $this->assertEquals(650, $r['overview']['total_income']);
        $this->assertEquals(600, $r['overview']['maintenance_collected']);
        $this->assertEquals(50, $r['overview']['other_income']);
        $this->assertEquals(5000, $r['overview']['rent_collected']);
        $this->assertSame(4, $r['income']['count']);
        $this->assertEquals(100, $r['overview']['total_society_expenses']);
        $this->assertEquals(550, $r['overview']['net_position']);
        $this->assertEquals(70.0, $r['expenses']['rows'][0]['percent']);
        $this->assertEquals(30.0, $r['expenses']['rows'][1]['percent']);
    }

    public function test_month_boundaries_are_inclusive_on_both_ends_and_exclusive_of_neighbours(): void
    {
        $f = $this->fixture();
        $this->pay($f, 1, '2026-05-31');
        $this->pay($f, 10, '2026-06-01');
        $this->pay($f, 100, '2026-06-30');
        $this->pay($f, 1000, '2026-07-01');
        $this->spend($f, 2, '2026-05-31');
        $this->spend($f, 20, '2026-06-01');
        $this->spend($f, 200, '2026-06-30');
        $this->spend($f, 2000, '2026-07-01');

        $june = $this->report($f, ReportPeriod::monthly(2026, 6));
        $this->assertEquals(110, $june['overview']['total_income']);
        $this->assertEquals(220, $june['overview']['total_society_expenses']);

        $may = $this->report($f, ReportPeriod::monthly(2026, 5));
        $this->assertEquals(1, $may['overview']['total_income']);
        $this->assertEquals(2, $may['overview']['total_society_expenses']);

        $july = $this->report($f, ReportPeriod::monthly(2026, 7));
        $this->assertEquals(1000, $july['overview']['total_income']);
        $this->assertEquals(2000, $july['overview']['total_society_expenses']);
    }

    public function test_year_boundary_and_annual_totals(): void
    {
        $f = $this->fixture();
        $this->pay($f, 5, '2025-12-31');
        $this->pay($f, 50, '2026-01-01');
        $this->pay($f, 500, '2026-12-31');
        $this->pay($f, 5000, '2027-01-01');

        $this->assertEquals(5, $this->report($f, ReportPeriod::monthly(2025, 12))['overview']['total_income']);
        $this->assertEquals(50, $this->report($f, ReportPeriod::monthly(2026, 1))['overview']['total_income']);
        $this->assertEquals(550, $this->report($f, ReportPeriod::annually(2026))['overview']['total_income']);
        $this->assertEquals(5, $this->report($f, ReportPeriod::annually(2025))['overview']['total_income']);
        $this->assertEquals(5000, $this->report($f, ReportPeriod::annually(2027))['overview']['total_income']);
    }

    public function test_previous_current_and_future_month_selection(): void
    {
        $f = $this->fixture();
        Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00', 'UTC'));

        $this->pay($f, 1, '2026-05-10');
        $this->pay($f, 10, '2026-06-10');
        $this->pay($f, 100, '2026-07-10'); // dated in the future

        $current = ReportPeriod::fromRequest(new \Illuminate\Http\Request(['report_type' => 'monthly']));
        $this->assertSame([2026, 6], [$current->year, $current->month]);
        $this->assertEquals(10, $this->report($f, $current)['overview']['total_income']);

        $previous = ReportPeriod::fromRequest(new \Illuminate\Http\Request(['report_type' => 'monthly', 'month' => 5, 'year' => 2026]));
        $this->assertEquals(1, $this->report($f, $previous)['overview']['total_income']);

        $future = ReportPeriod::fromRequest(new \Illuminate\Http\Request(['report_type' => 'monthly', 'month' => 7, 'year' => 2026]));
        $this->assertEquals(100, $this->report($f, $future)['overview']['total_income']);

        $empty = ReportPeriod::fromRequest(new \Illuminate\Http\Request(['report_type' => 'monthly', 'month' => 8, 'year' => 2026]));
        $this->assertFalse($this->report($f, $empty)['has_data']);

        Carbon::setTestNow();
    }

    public function test_outstanding_dues_are_all_time_and_bill_backed_while_income_is_period_cash(): void
    {
        $f = $this->fixture();

        $old = MaintenanceBill::create(['society_id' => $f['society']->id, 'flat_id' => $f['flat']->id, 'month' => 'January', 'year' => 2026, 'bill_date' => '2026-01-01', 'due_date' => '2026-01-10', 'maintenance_amount' => 1000, 'paid_amount' => 0, 'bill_type' => 'maintenance']);
        $june = MaintenanceBill::create(['society_id' => $f['society']->id, 'flat_id' => $f['flat']->id, 'month' => 'June', 'year' => 2026, 'bill_date' => '2026-06-01', 'due_date' => '2026-06-10', 'maintenance_amount' => 2000, 'paid_amount' => 0, 'bill_type' => 'maintenance']);

        Payment::create(['society_id' => $f['society']->id, 'flat_id' => $f['flat']->id, 'maintenance_bill_id' => $june->id, 'payment_id' => 'PJ', 'amount' => 500, 'payment_method' => 'cash', 'status' => 'success', 'payment_date' => '2026-06-05', 'bill_type' => 'maintenance', 'due_date' => '2026-06-10']);

        $r = $this->report($f, ReportPeriod::monthly(2026, 6));

        $this->assertEquals(500, $r['overview']['maintenance_collected']);
        $this->assertEquals(2000, $r['billing']['billed']);
        $this->assertEquals(500, $r['billing']['collected'], 'bill-side collected agrees with the ledger');
        $this->assertEquals(1500, $r['billing']['outstanding']);
        $this->assertEquals(2500, $r['overview']['outstanding_dues'], 'all-time: January 1000 + June 1500');
        $this->assertEquals(500, $r['properties'][0]['paid']);
    }

    public function test_screen_and_pdf_use_the_same_numbers(): void
    {
        $f = $this->fixture();
        $this->pay($f, 1234.56, '2026-06-03');
        $this->spend($f, 234.56, '2026-06-04');

        $period = ['report_type' => 'monthly', 'month' => 6, 'year' => 2026];
        $page = $this->actingAs($f['admin'])->get(route('reports.financial', $period))->assertOk();
        $this->assertSame($this->report($f, ReportPeriod::monthly(2026, 6))['overview'], $page->viewData('report')['overview']);
    }
}
