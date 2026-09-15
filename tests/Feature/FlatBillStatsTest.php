<?php

namespace Tests\Feature;

use App\Models\MaintenanceBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The flat detail page sums real columns.
 *
 * It summed `amount`, which maintenance_bills does not have - it carries
 * total_amount / paid_amount / balance_amount. On MySQL that is a hard 500
 * (Unknown column 'amount'); on SQLite it passed silently, because Laravel
 * quotes identifiers with double quotes there and SQLite reads an unknown
 * double-quoted name as a string literal, so sum("amount") summed the text
 * 'amount' and returned 0.
 *
 * That is why these assertions check the figures rather than the status
 * code: a 200 proves nothing when the wrong column quietly yields zero.
 */
class FlatBillStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_flat_page_reports_real_bill_totals(): void
    {
        $society  = $this->makeSociety('Billing Society');
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '301');
        $admin    = $this->makeUserWithRole($society, 'Admin');

        // One bill per flat per month is enforced by a unique index, so each
        // fixture bill needs its own month.
        $month = 0;

        $bill = function (string $status, float $total, float $paid, float $balance) use ($society, $flat, &$month) {
            $month++;

            return MaintenanceBill::create([
                'society_id'         => $society->id,
                'flat_id'            => $flat->id,
                'bill_number'        => 'MB-' . uniqid(),
                'month'              => $month,
                'year'               => 2026,
                'bill_date'          => now()->toDateString(),
                'due_date'           => now()->addDays(10)->toDateString(),
                'maintenance_amount' => $total,
                'total_amount'       => $total,
                'paid_amount'        => $paid,
                'balance_amount'     => $balance,
                'status'             => $status,
            ]);
        };

        $bill('paid', 1000, 1000, 0);
        $bill('paid', 500, 500, 0);
        $bill('pending', 800, 0, 800);

        $stats = $this->actingAs($admin)
            ->get("/flats/{$flat->id}")
            ->assertOk()
            ->viewData('stats');

        $this->assertSame(3, $stats['total_bills']);
        $this->assertSame(2, $stats['paid_bills']);
        $this->assertSame(1, $stats['pending_bills']);

        // The figures that were silently zero before.
        $this->assertEquals(1500, $stats['total_paid'], 'paid bills should total 1500');
        $this->assertEquals(800, $stats['total_pending'], 'pending balance should be 800');
    }

    /** With no bills the page still renders and reports zeros honestly. */
    public function test_a_flat_with_no_bills_reports_zero(): void
    {
        $society  = $this->makeSociety('Empty Billing');
        $building = $this->makeBuilding($society);
        $flat     = $this->makeFlat($society, $building, '302');
        $admin    = $this->makeUserWithRole($society, 'Admin');

        $stats = $this->actingAs($admin)
            ->get("/flats/{$flat->id}")
            ->assertOk()
            ->viewData('stats');

        $this->assertSame(0, $stats['total_bills']);
        $this->assertEquals(0, $stats['total_paid']);
        $this->assertEquals(0, $stats['total_pending']);
    }
}
