<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Society;
use App\Services\FinancialReportService;
use App\Support\ReportPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 1. An annual report does not read as if the whole year has passed: future months are marked, and records dated after today are
 *    flagged (not hidden, not moved), while the months still add up to the year.
 * 2. Parking: the application itself never adds a parking charge to a bill; a bill's parts always add up to its total.
 * 3. The payments CSV export copes with commas, quotes, line breaks, Unicode and currency, and neutralises spreadsheet formulas.
 */
class ReportPeriodAndExportHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Society $society;
    private Flat $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->society = $this->makeSociety('Royal');
        $this->flat = $this->makeFlat($this->society, $this->makeBuilding($this->society), '101');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function receipt(string $date, float $amount, string $status = 'success', string $method = 'cash', string $type = 'maintenance'): Payment
    {
        static $n = 0;
        $n++;

        return Payment::create(['society_id' => $this->society->id, 'flat_id' => $this->flat->id, 'payment_id' => 'PAY-RP-' . $n, 'amount' => $amount, 'payment_method' => $method,
            'status' => $status, 'payment_date' => $date, 'due_date' => $date, 'bill_type' => $type, 'receipt_number' => $status === 'success' && $method !== 'unrecorded' ? 'RCP-RP-' . $n : null]);
    }

    private function expense(string $date, float $amount, string $status = 'approved'): Expense
    {
        return Expense::create(['society_id' => $this->society->id, 'scope' => 'society', 'flat_id' => null, 'paid_by' => 'society', 'created_by' => $this->makeUserWithRole($this->society, 'Admin')->id,
            'expense_category_id' => ExpenseCategory::firstOrCreate(['society_id' => $this->society->id, 'name' => 'Security'], ['status' => 'active'])->id,
            'title' => 'Security', 'amount' => $amount, 'expense_date' => $date, 'payment_method' => 'online', 'status' => $status]);
    }

    // ── 1. future months ────────────────────────────────────────────────────

    public function test_the_annual_table_marks_months_that_have_not_happened_and_still_adds_up(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');
        $this->receipt('2026-09-10', 1000);
        $this->receipt('2026-10-05', 500);
        $this->expense('2026-10-06', 200);

        $r = app(FinancialReportService::class)->build($this->society->id, ReportPeriod::annually(2026));
        $byMonth = collect($r['months'])->keyBy('month');

        $this->assertSame('2026-10-17', $r['as_of']);
        $this->assertFalse($byMonth[9]['future']);
        $this->assertFalse($byMonth[10]['future']);
        $this->assertTrue($byMonth[10]['current'], 'October is still running');
        $this->assertFalse($byMonth[9]['current']);
        foreach ([11, 12] as $m) {
            $this->assertTrue($byMonth[$m]['future'], "month $m has not begun");
            $this->assertFalse($byMonth[$m]['has_data']);
        }
        $this->assertTrue($byMonth[9]['has_data']);
        $this->assertEquals(1500, collect($r['months'])->sum('income'));
        $this->assertEquals($r['overview']['total_income'], collect($r['months'])->sum('income'));
        $this->assertEquals($r['overview']['total_society_expenses'], collect($r['months'])->sum('expenses'));
    }

    public function test_records_dated_after_today_are_flagged_not_hidden_and_not_moved(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');
        $this->receipt('2026-10-10', 1000);                          // already happened
        $this->receipt('2026-10-25', 300);                           // a receipt cannot be dated in the future
        $this->receipt('2026-10-26', 999, 'pending');                // not counted at all, so not flagged
        $this->receipt('2026-10-27', 777, 'success', 'unrecorded');  // no evidence, not counted, not flagged
        $this->expense('2026-10-28', 120);
        $this->expense('2026-10-29', 55, 'rejected');                // not counted, not flagged

        $r = app(FinancialReportService::class)->build($this->society->id, ReportPeriod::monthly(2026, 10));

        $this->assertSame(1, $r['future_dated']['payments']['count']);
        $this->assertEquals(300, $r['future_dated']['payments']['amount']);
        $this->assertSame(1, $r['future_dated']['expenses']['count']);
        $this->assertEquals(120, $r['future_dated']['expenses']['amount']);
        $this->assertEquals(1300, $r['overview']['total_income'], 'history is not rewritten: the dated record stays in its own period');
        $this->assertEquals(0, app(FinancialReportService::class)->build($this->society->id, ReportPeriod::monthly(2026, 9))['future_dated']['payments']['count'], 'a past month has none');
    }

    public function test_the_report_page_says_which_months_are_future_and_names_the_dated_records(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');
        $this->receipt('2026-10-10', 1000);
        $this->receipt('2026-10-25', 300);
        $admin = $this->makeUserWithRole($this->society, 'Admin');

        $annual = $this->actingAs($admin)->get(route('reports.financial', ['report_type' => 'annually', 'year' => 2026]))->assertOk();
        $annual->assertSee('future - no data yet', false)->assertSee('in progress', false);

        $month = $this->actingAs($admin)->get(route('reports.financial', ['report_type' => 'monthly', 'year' => 2026, 'month' => 10]))->assertOk();
        $month->assertSee('Dated after today', false)->assertSee('17 Oct 2026', false);
    }

    // ── 2. parking ──────────────────────────────────────────────────────────

    public function test_a_rent_bill_created_by_the_application_never_carries_a_parking_charge(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $tenantUser = $this->makeUserWithRole($this->society, 'Tenant');
        $tenant = \App\Models\Tenant::create(['society_id' => $this->society->id, 'flat_id' => $this->flat->id, 'user_id' => $tenantUser->id, 'name' => 'T', 'email' => $tenantUser->email,
            'phone' => '9000000071', 'move_in_date' => now(), 'status' => 'active', 'monthly_rent' => 9000]);
        $this->flat->update(['has_parking' => true, 'parking_slots' => 2]);        // the unit has parking; that alone must not become a fee

        $this->actingAs($admin)->post(route('admin.rents.store'), ['property_type' => 'apartment', 'unit_id' => $this->flat->id, 'tenant_id' => $tenant->id, 'rent_amount' => 9000,
            'bill_date' => '2026-10-01', 'due_date' => '2026-10-10', 'status' => 'pending']);

        $bill = MaintenanceBill::where('flat_id', $this->flat->id)->firstOrFail();
        $this->assertEquals(0, $bill->parking_charges);
        $this->assertEquals(9000, $bill->total_amount);
    }

    public function test_a_bills_parts_always_add_up_to_its_total_including_parking_when_it_is_really_charged(): void
    {
        $with = MaintenanceBill::create(['society_id' => $this->society->id, 'flat_id' => $this->flat->id, 'month' => 'October', 'year' => 2026, 'bill_type' => 'maintenance',
            'bill_date' => '2026-10-01', 'due_date' => '2026-10-10', 'maintenance_amount' => 2500, 'water_charges' => 150, 'parking_charges' => 50, 'paid_amount' => 0]);
        $without = MaintenanceBill::create(['society_id' => $this->society->id, 'flat_id' => $this->flat->id, 'month' => 'November', 'year' => 2026, 'bill_type' => 'maintenance',
            'bill_date' => '2026-11-01', 'due_date' => '2026-11-10', 'maintenance_amount' => 2500, 'water_charges' => 150, 'paid_amount' => 0]);

        $this->assertEquals(2700, $with->fresh()->total_amount);
        $this->assertEquals(2650, $without->fresh()->total_amount, 'no parking charge, none added');
        $this->assertEquals(0, $without->fresh()->parking_charges);
    }

    public function test_no_application_code_writes_a_parking_charge_the_only_source_was_the_demo_seeder(): void
    {
        $writers = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() === 'php' && preg_match("/['\"]parking_charges['\"]\s*=>\s*(?:\\$|[1-9])/", file_get_contents($file->getPathname()))) {
                $writers[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        // MaintenanceBill lists the column in $fillable / $casts (no value assigned); nothing in app/ assigns a non-zero parking charge
        $this->assertSame([], $writers, 'a new place that sets a parking charge needs an owner decision and a configured fee, not a hard-coded amount');
    }

    // ── 3. payments CSV export ──────────────────────────────────────────────

    public function test_the_csv_export_keeps_commas_quotes_line_breaks_unicode_and_exact_amounts_and_neutralises_formulas(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->receipt('2026-10-02', 1234567.5, 'success', 'online');
        $p = $this->receipt('2026-10-03', 10);
        $p->forceFill(['notes' => "Paid by \"R. Rao\", flat 101,\nsecond line — नवरात्रि ₹"])->save();
        foreach (['=1+1', '+SUM(A1)', '-2+3', '@cmd', "\t=HYPERLINK(\"x\")"] as $i => $evil) {
            $this->receipt('2026-10-0' . (4 + $i), 1)->forceFill(['notes' => $evil])->save();
        }

        $csv = $this->actingAs($admin)->get(route('payments.export'))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\R(?=(?:[^"]*"[^"]*")*[^"]*$)/', trim($csv)));
        $header = array_shift($rows);
        $notes = array_column($rows, array_search('Notes', $header, true));
        $amounts = array_column($rows, array_search('Amount', $header, true));

        $this->assertContains('1234567.50', $amounts, 'amounts are plain decimals with two places, no thousands separator or currency symbol');
        $this->assertContains("Paid by \"R. Rao\", flat 101,\nsecond line — नवरात्रि ₹", $notes, 'commas, quotes, a line break and Unicode survive a round trip');
        foreach ($notes as $cell) {
            $this->assertDoesNotMatchRegularExpression('/^[=+\-@\t\r]/', (string) $cell, "a formula cell must not start with a trigger character: $cell");
        }
        $this->assertContains("'=1+1", $notes);
        $this->assertContains("'@cmd", $notes);
        $this->assertSame(1 + 1 + 5, count($rows));
    }

    public function test_the_export_respects_the_status_and_date_filters_and_refuses_bad_ones(): void
    {
        $admin = $this->makeUserWithRole($this->society, 'Admin');
        $this->receipt('2026-09-10', 1);
        $this->receipt('2026-10-10', 2);
        $this->receipt('2026-10-11', 3, 'failed');

        $oct = $this->actingAs($admin)->get(route('payments.export', ['from' => '2026-10-01', 'to' => '2026-10-31', 'status' => 'success']))->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-10-10', $oct);
        $this->assertStringNotContainsString('2026-09-10', $oct);
        $this->assertStringNotContainsString('2026-10-11', $oct, 'a failed payment is filtered out when asked');

        $this->actingAs($admin)->get(route('payments.export', ['status' => 'paid']))->assertSessionHasErrors('status');
        $this->actingAs($admin)->get(route('payments.export', ['from' => '2026-10-31', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
    }

    public function test_the_pdf_view_carries_the_same_future_labels_and_dated_after_today_note(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');
        $this->receipt('2026-10-10', 1000);
        $this->receipt('2026-10-25', 300);
        $report = app(FinancialReportService::class)->build($this->society->id, ReportPeriod::annually(2026));

        $html = view('reports.financial-pdf', ['report' => $report, 'society' => $this->society, 'periodLabel' => '2026', 'period' => ReportPeriod::annually(2026), 'generatedAt' => now()])->render();

        $this->assertStringContainsString('future - no data yet', $html);
        $this->assertStringContainsString('(in progress)', $html);
        $this->assertStringContainsString('Dated after today (17 Oct 2026)', $html);
        $this->assertStringContainsString('₹1,300.00', $html, 'the dated receipt stays in its own period and in the total, exactly like the page');
    }
}