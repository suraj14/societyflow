<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Flat;
use App\Models\MaintenanceBill;
use App\Models\Payment;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * The financial report must answer "how is the society doing, and where is
 * its money going?" - and it must never let one property's own expense
 * leak into society expense totals, never show a period other than the one
 * selected, and never compute the PDF from anything but the web figures.
 */
class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private int $paymentSeq = 0;

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function villa(Society $society, string $number, string $name = ''): Flat
    {
        return Flat::create([
            'society_id'    => $society->id,
            'flat_number'   => $number,
            'villa_name'    => $name ?: null,
            'property_type' => 'villa',
            'floor'         => 0,
            'status'        => 'occupied',
        ]);
    }

    private function category(Society $society, string $name): ExpenseCategory
    {
        return ExpenseCategory::firstOrCreate(
            ['society_id' => $society->id, 'name' => $name],
            ['status' => 'active']
        );
    }

    private function expense(Society $society, User $by, string $category, float $amount, string $date, array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'society_id'          => $society->id,
            'scope'               => 'society',
            'flat_id'             => null,
            'paid_by'             => 'society',
            'expense_category_id' => $this->category($society, $category)->id,
            'created_by'          => $by->id,
            'title'               => $category . ' expense',
            'amount'              => $amount,
            'expense_date'        => $date,
            'payment_method'      => 'online',
            'status'              => 'approved',
        ], $overrides));
    }

    private function payment(Society $society, ?Flat $flat, float $amount, string $date, string $billType = 'Maintenance', string $status = 'success', ?int $billId = null): Payment
    {
        $this->paymentSeq++;

        return Payment::create([
            'society_id'          => $society->id,
            'flat_id'             => $flat?->id,
            'maintenance_bill_id' => $billId,
            'payment_id'          => 'PAY-' . $society->id . '-' . $this->paymentSeq,
            'amount'              => $amount,
            'payment_method'      => 'online',
            'status'              => $status,
            'payment_date'        => $date,
            'bill_type'           => $billType,
            'due_date'            => $date,
            'receipt_number'      => 'RCP-' . $this->paymentSeq,
        ]);
    }

    private function bill(Society $society, Flat $flat, string $date, float $amount, float $paid = 0, string $type = 'maintenance'): MaintenanceBill
    {
        $d = \Carbon\Carbon::parse($date);

        return MaintenanceBill::create([
            'society_id'         => $society->id,
            'flat_id'            => $flat->id,
            'month'              => $d->format('F'),
            'year'               => $d->year,
            'bill_date'          => $date,
            'due_date'           => $d->copy()->addDays(15)->toDateString(),
            'maintenance_amount' => $amount,
            'paid_amount'        => $paid,
            'bill_type'          => $type,
        ]);
    }

    /** Urban Heights with Villa 160 (owner: Suraj) and a neighbour, Villa 159. */
    private function urbanHeights(): array
    {
        $society = $this->makeSociety('Urban Heights');
        $admin   = $this->makeUserWithRole($society, 'Admin');
        $villa160 = $this->villa($society, '160', 'Suraj Villa');
        $villa159 = $this->villa($society, '159');

        return compact('society', 'admin', 'villa160', 'villa159');
    }

    private function june2025(): array
    {
        return ['report_type' => 'monthly', 'month' => 6, 'year' => 2025];
    }

    // ------------------------------------------------------------------
    // The Villa 160 scenario (brief §21)
    // ------------------------------------------------------------------

    public function test_society_expenses_are_broken_down_by_category_and_villa_160_personal_electricity_is_excluded(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160] = $this->urbanHeights();

        // Villa 160's own electricity - a property expense.
        $this->expense($society, $admin, 'Common Electricity', 3500, '2025-06-03', [
            'scope'   => 'property',
            'flat_id' => $villa160->id,
            'paid_by' => 'owner',
            'title'   => 'Villa 160 individual electricity',
        ]);

        // Society/common expenses.
        $this->expense($society, $admin, 'Common Electricity', 18000, '2025-06-05', ['title' => 'Common road lighting electricity']);
        $this->expense($society, $admin, 'Security', 120000, '2025-06-07', ['title' => 'Security agency']);
        $this->expense($society, $admin, 'Gardening', 20000, '2025-06-12', ['title' => 'Gardening']);
        $this->expense($society, $admin, 'Common Water', 15000, '2025-06-20', ['title' => 'Common water tanker']);

        $response = $this->actingAs($admin)->get(route('reports.financial', $this->june2025()));
        $response->assertOk();

        $report = $response->viewData('report');
        $this->assertSame('society', $report['mode']);
        $this->assertSame('June 2025', $report['period']['label']);

        $byCategory = collect($report['expenses']['rows'])->keyBy('category');

        $this->assertEquals(18000, $byCategory['Common Electricity']['amount'], 'Villa 160 electricity must not inflate Common Electricity');
        $this->assertEquals(120000, $byCategory['Security']['amount']);
        $this->assertEquals(20000, $byCategory['Gardening']['amount']);
        $this->assertEquals(15000, $byCategory['Common Water']['amount']);
        $this->assertEquals(173000, $report['expenses']['total']);
        $this->assertEquals(173000, $report['overview']['total_society_expenses']);
        $this->assertSame(4, $report['expenses']['count']);

        // The personal expense is not part of society finances at all: no
        // section, no card, no mention.
        $this->assertArrayNotHasKey('property_expenses', $report);

        // Percentages add up to the whole.
        $this->assertEqualsWithDelta(100, collect($report['expenses']['rows'])->sum('percent'), 0.3);

        $response->assertSee('173,000.00');
        $response->assertDontSee('Villa 160 individual electricity');
        $response->assertDontSee('Property Expenses');
        $response->assertDontSee('not society expense', false);
        $response->assertDontSee('Not society expense');
        $response->assertSee('Society Expenses');
    }

    public function test_mixed_income_and_expenses_produce_correct_totals_with_no_double_counting(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160, 'villa159' => $villa159] = $this->urbanHeights();

        $bill = $this->bill($society, $villa160, '2025-06-01', 5000, 5000);

        $this->payment($society, $villa160, 5000, '2025-06-04', 'Maintenance', 'success', $bill->id);
        $this->payment($society, $villa159, 1200, '2025-06-08', 'Water Bill');
        $this->payment($society, $villa159, 8000, '2025-06-09', 'rent');            // memo only
        $this->payment($society, $villa159, 9999, '2025-06-10', 'Maintenance', 'pending'); // not income
        $this->payment($society, $villa159, 4000, '2025-07-01', 'Maintenance');      // next month

        $this->expense($society, $admin, 'Security', 2000, '2025-06-15');
        $this->expense($society, $admin, 'Repairs', 700, '2025-06-16', [
            'scope' => 'property', 'flat_id' => $villa159->id, 'paid_by' => 'tenant',
        ]);

        $report = $this->actingAs($admin)->get(route('reports.financial', $this->june2025()))->viewData('report');

        $o = $report['overview'];
        $this->assertEquals(6200, $o['total_income']);
        $this->assertEquals(5000, $o['maintenance_collected']);
        $this->assertEquals(1200, $o['other_income']);
        $this->assertEquals(8000, $o['rent_collected']);
        $this->assertEquals(2000, $o['total_society_expenses']);
        $this->assertEquals(4200, $o['net_position']);

        // Summary totals equal the underlying rows.
        $this->assertEquals($o['total_income'], collect($report['income']['rows'])->sum('amount'));
        $this->assertEquals($o['total_society_expenses'], collect($report['expenses']['rows'])->sum('amount'));

        // Every successful non-rent payment is counted exactly once.
        $this->assertSame(2, $report['income']['count']);

        // Billing section reflects the bill issued in June.
        $this->assertEquals(5000, $report['billing']['billed']);
        $this->assertEquals(5000, $report['billing']['collected']);
        $this->assertEquals(0, $report['billing']['outstanding']);
        $this->assertSame('Villa 160', $report['properties'][0]['property']);
    }

    // ------------------------------------------------------------------
    // Period handling (brief §15, §16)
    // ------------------------------------------------------------------

    public function test_monthly_filter_includes_only_the_selected_month(): void
    {
        ['society' => $society, 'admin' => $admin] = $this->urbanHeights();

        $this->expense($society, $admin, 'Security', 100, '2025-06-30');
        $this->expense($society, $admin, 'Security', 200, '2025-07-01');
        $this->expense($society, $admin, 'Security', 400, '2025-05-31');

        $report = $this->actingAs($admin)->get(route('reports.financial', $this->june2025()))->viewData('report');

        $this->assertEquals(100, $report['expenses']['total']);
        $this->assertSame('2025-06-01', $report['period']['start']);
        $this->assertSame('2025-06-30', $report['period']['end']);
    }

    public function test_annual_filter_includes_the_whole_year_and_nothing_else(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160] = $this->urbanHeights();

        $this->expense($society, $admin, 'Security', 100, '2025-01-01');
        $this->expense($society, $admin, 'Security', 200, '2025-12-31');
        $this->expense($society, $admin, 'Security', 400, '2024-12-31');
        $this->expense($society, $admin, 'Security', 800, '2026-01-01');
        $this->payment($society, $villa160, 50, '2025-03-03');
        $this->payment($society, $villa160, 70, '2026-03-03');

        $response = $this->actingAs($admin)->get(route('reports.financial', ['report_type' => 'annually', 'year' => 2025]));
        $response->assertOk();
        $report = $response->viewData('report');

        $this->assertEquals(300, $report['expenses']['total']);
        $this->assertEquals(50, $report['income']['total']);
        $this->assertSame('Year 2025', $report['period']['label']);
        $response->assertSee('Year 2025');
    }

    public function test_selected_june_2025_never_displays_the_current_month(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160] = $this->urbanHeights();

        // Data only exists in the current month - it must not bleed into June 2025.
        $this->expense($society, $admin, 'Security', 5000, now()->toDateString());
        $this->payment($society, $villa160, 999, now()->toDateString());

        $response = $this->actingAs($admin)->get(route('reports.financial', $this->june2025()));
        $response->assertOk();

        $report = $response->viewData('report');
        $this->assertSame('June 2025', $report['period']['label']);
        $this->assertFalse($report['has_data']);
        $this->assertEquals(0, $report['overview']['total_income']);
        $this->assertEquals(0, $report['overview']['total_society_expenses']);

        $response->assertSee('Showing data for:', false);
        $response->assertSee('June 2025');
        $response->assertSee('No financial data available for June 2025.');
        $response->assertSee('No transactions recorded for this period.');
        $response->assertDontSee(now()->format('F Y'));
        $response->assertDontSee('5,000.00');
    }

    public function test_invalid_period_is_rejected_rather_than_replaced_with_today(): void
    {
        ['admin' => $admin] = $this->urbanHeights();

        $this->actingAs($admin)
            ->get(route('reports.financial', ['report_type' => 'monthly', 'month' => 13, 'year' => 2025]))
            ->assertRedirect(route('reports.financial'))
            ->assertSessionHasErrors('month');

        $this->actingAs($admin)
            ->get(route('reports.financial', ['report_type' => 'weekly', 'year' => 2025]))
            ->assertSessionHasErrors('report_type');

        $this->actingAs($admin)
            ->post(route('reports.download-financial'), ['report_type' => 'monthly', 'month' => 0, 'year' => 2025])
            ->assertSessionHasErrors('month');
    }

    public function test_empty_period_shows_zero_totals_and_empty_state(): void
    {
        ['admin' => $admin] = $this->urbanHeights();

        $response = $this->actingAs($admin)->get(route('reports.financial', ['report_type' => 'annually', 'year' => 2023]));
        $response->assertOk();
        $response->assertSee('No financial data available for Year 2023.');
        $response->assertSee('0.00');
        $this->assertFalse($response->viewData('report')['has_data']);
    }

    // ------------------------------------------------------------------
    // PDF (brief §25)
    // ------------------------------------------------------------------

    public function test_pdf_is_built_from_exactly_the_same_report_data_as_the_page(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160] = $this->urbanHeights();

        $this->expense($society, $admin, 'Security', 120000, '2025-06-07');
        $this->expense($society, $admin, 'Gardening', 20000, '2025-06-12');
        $this->expense($society, $admin, 'Common Electricity', 3500, '2025-06-03', ['scope' => 'property', 'flat_id' => $villa160->id]);
        $this->payment($society, $villa160, 5000, '2025-06-04');

        $web =$this->actingAs($admin)->get(route('reports.financial', $this->june2025()))->viewData('report');

        $captured = null;
        // The facade's loadView() is typed to return the wrapper, so the
        // stand-in has to be one.
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')->once()->with('Financial-Report-June-2025.pdf')
            ->andReturn(response('pdf-bytes', 200, ['Content-Type' => 'application/pdf']));

        \PDF::shouldReceive('loadView')->once()
            ->withArgs(function ($view, $data) use (&$captured) {
                $captured = $data;
                return $view === 'reports.financial-pdf';
            })
            ->andReturn($pdf);

        $this->actingAs($admin)->post(route('reports.download-financial'), $this->june2025())->assertOk();

        $this->assertNotNull($captured);
        $this->assertSame('June 2025', $captured['periodLabel']);
        $this->assertEquals($web, $captured['report'], 'PDF and web page must render the same report array');
        $this->assertEquals(140000, $captured['report']['expenses']['total']);
        $this->assertArrayNotHasKey('property_expenses', $captured['report']);
    }

    public function test_pdf_actually_renders_with_dompdf(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160] = $this->urbanHeights();

        $this->expense($society, $admin, 'Security', 120000, '2025-06-07');
        $this->expense($society, $admin, 'Common Electricity', 3500, '2025-06-03', ['scope' => 'property', 'flat_id' => $villa160->id]);
        $this->bill($society, $villa160, '2025-06-01', 5000, 2000);

        $response = $this->actingAs($admin)->post(route('reports.download-financial'), $this->june2025());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Financial-Report-June-2025.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // ------------------------------------------------------------------
    // Isolation and roles (brief §20, §23)
    // ------------------------------------------------------------------

    public function test_another_societys_expenses_and_payments_never_appear(): void
    {
        ['society' => $society, 'admin' => $admin] = $this->urbanHeights();
        $other      = $this->makeSociety('Elsewhere');
        $otherAdmin = $this->makeUserWithRole($other, 'Admin');
        $otherFlat  = $this->villa($other, '1');

        $this->expense($other, $otherAdmin, 'Security', 99999, '2025-06-07');
        $this->payment($other, $otherFlat, 88888, '2025-06-07');

        $report = $this->actingAs($admin)->get(route('reports.financial', $this->june2025()))->viewData('report');

        $this->assertFalse($report['has_data']);
        $this->assertEquals(0, $report['expenses']['total']);
        $this->assertEquals(0, $report['income']['total']);
    }

    public function test_owner_sees_only_their_own_property_and_no_society_expense_management(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160, 'villa159' => $villa159] = $this->urbanHeights();

        $suraj = $this->makeResidentUser($society, $villa160, ['name' => 'Suraj']);
        $this->makeRole('Owner');
        $suraj->assignRole('Owner');

        $this->expense($society, $admin, 'Security', 120000, '2025-06-07');
        $this->expense($society, $admin, 'Common Electricity', 3500, '2025-06-03', ['scope' => 'property', 'flat_id' => $villa160->id, 'title' => 'Villa 160 electricity']);
        $this->expense($society, $admin, 'Plumbing', 1200, '2025-06-03', ['scope' => 'property', 'flat_id' => $villa159->id, 'title' => 'Villa 159 private plumbing']);
        $this->bill($society, $villa160, '2025-06-01', 5000, 5000);
        $this->bill($society, $villa159, '2025-06-01', 7000, 0);
        $this->payment($society, $villa160, 5000, '2025-06-04');
        $this->payment($society, $villa159, 7000, '2025-06-04');

        $response = $this->actingAs($suraj)->get(route('reports.financial', $this->june2025()));
        $response->assertOk();

        $report = $response->viewData('report');
        $this->assertSame('property', $report['mode']);
        $this->assertEquals(5000, $report['overview']['total_billed']);
        $this->assertEquals(5000, $report['overview']['total_paid']);
        $this->assertArrayNotHasKey('expenses', $report, 'resident view must carry no society expense section');
        $this->assertArrayNotHasKey('property_expenses', $report);

        $response->assertDontSee('Villa 160 electricity');
        $response->assertDontSee('Villa 159 private plumbing');
        $response->assertDontSee('Property Expenses');
        $response->assertDontSee('7,000.00');
        $response->assertDontSee('Total Society Expenses');
        $response->assertDontSee('120,000.00');
    }

    public function test_accountant_sees_the_society_wide_report(): void
    {
        ['society' => $society, 'admin' => $admin] = $this->urbanHeights();
        $accountant = $this->makeUserWithRole($society, 'Accountant');

        $this->expense($society, $admin, 'Housekeeping', 45000, '2025-06-07');

        $response = $this->actingAs($accountant)->get(route('reports.financial', $this->june2025()));
        $response->assertOk();
        $this->assertSame('society', $response->viewData('report')['mode']);
        $response->assertSee('Housekeeping')->assertSee('45,000.00');
    }

    public function test_guard_and_staff_cannot_open_the_financial_report(): void
    {
        ['society' => $society] = $this->urbanHeights();

        foreach (['Guard', 'Staff'] as $role) {
            $user = $this->makeUserWithRole($society, $role);
            $this->actingAs($user)->get(route('reports.financial', $this->june2025()))->assertForbidden();
            $this->actingAs($user)->post(route('reports.download-financial'), $this->june2025())->assertForbidden();
        }
    }

    public function test_dashboard_snapshot_counts_only_society_expenses_this_month(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160] = $this->urbanHeights();

        $this->expense($society, $admin, 'Security', 1000, now()->toDateString());
        $this->expense($society, $admin, 'Repairs', 250, now()->toDateString(), ['scope' => 'property', 'flat_id' => $villa160->id]);
        $this->expense($society, $admin, 'Security', 9000, now()->subMonthNoOverflow()->startOfMonth()->toDateString());
        $this->payment($society, $villa160, 4000, now()->toDateString());

        $stats = $this->actingAs($admin)->get('/dashboard')->viewData('stats');

        $this->assertEquals(1000, $stats['monthly_expenses']);
        $this->assertEquals(3000, $stats['monthly_net']);
    }

    public function test_individual_property_bills_and_payments_never_inflate_society_expenses(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160] = $this->urbanHeights();

        $this->expense($society, $admin, 'Security', 120000, '2025-06-07');

        // Villa 160's own electricity is billed to and paid by the resident.
        // That is a collection (income), never a society expense.
        \App\Models\UtilityBill::create([
            'society_id' => $society->id, 'flat_id' => $villa160->id, 'bill_type' => 'Electricity',
            'bill_amount' => 3500, 'bill_date' => '2025-06-03', 'due_date' => '2025-06-20', 'status' => 'paid',
        ]);
        $this->payment($society, $villa160, 3500, '2025-06-04', 'Electricity');

        $report = $this->actingAs($admin)->get(route('reports.financial', $this->june2025()))->viewData('report');

        $this->assertEquals(120000, $report['expenses']['total']);
        $this->assertSame(1, $report['expenses']['count']);
        $this->assertEquals(3500, $report['income']['total']);
        $this->assertEquals(3500 - 120000, $report['overview']['net_position']);
    }

    public function test_period_with_income_but_no_society_expenses_says_society_common_expenses(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160] = $this->urbanHeights();

        $this->payment($society, $villa160, 5000, '2025-06-04');

        $response = $this->actingAs($admin)->get(route('reports.financial', $this->june2025()));
        $response->assertOk();
        $response->assertSee('No society/common expenses recorded for this period.');
        $response->assertDontSee('No expenses recorded yet');
    }

    // ------------------------------------------------------------------
    // Maintenance report stays separate and honest
    // ------------------------------------------------------------------

    public function test_maintenance_report_counts_only_successful_maintenance_payments_in_the_period(): void
    {
        ['society' => $society, 'admin' => $admin, 'villa160' => $villa160, 'villa159' => $villa159] = $this->urbanHeights();

        $bill = $this->bill($society, $villa160, '2025-06-01', 5000, 3000);
        // maintenance_bills is unique on (society, flat, month, year), so a
        // rent bill for the same month has to sit on a different unit.
        $rent = $this->bill($society, $villa159, '2025-06-01', 8000, 8000, 'rent');

        $this->payment($society, $villa160, 3000, '2025-06-04', 'Maintenance', 'success', $bill->id);
        $this->payment($society, $villa160, 2000, '2025-06-05', 'Maintenance', 'pending', $bill->id);
        $this->payment($society, $villa159, 8000, '2025-06-05', 'rent', 'success', $rent->id);
        $this->payment($society, $villa160, 500, '2025-07-05', 'Maintenance', 'success', $bill->id);
        // Ad-hoc maintenance payment with no bill link (the payment form
        // allows this) - it is still maintenance collected.
        $this->payment($society, $villa159, 1500, '2025-06-06', 'Maintenance');
        // Ad-hoc water payment is not maintenance.
        $this->payment($society, $villa159, 700, '2025-06-06', 'Water Bill');

        $response = $this->actingAs($admin)->get(route('reports.maintenance', $this->june2025()));
        $response->assertOk();

        $data = $response->viewData('data');
        $this->assertEquals(4500, $data['total_maintenance']);
        $this->assertSame(2, $data['count']);
        $this->assertEquals(5000, $data['billed']);
        // The bill's paid amount is derived from its success payments
        // (3000 in June + 500 in July = 3500; the 2000 pending one does not
        // count), so 1500 is outstanding. This used to expect 2000 because
        // the fixture wrote paid_amount = 3000 directly and the July payment
        // linked to the bill was never reflected on it - the exact
        // bill/ledger disagreement the ledger-derived rule removes.
        $this->assertEquals(1500, $data['outstanding']);
        $response->assertSee('Track maintenance billing and collections for the selected period.');
        $response->assertSee('June 2025');
    }
}
