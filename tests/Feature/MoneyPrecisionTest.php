<?php

namespace Tests\Feature;

use App\Models\MaintenanceBill;
use App\Models\Tenant;
use App\Models\UtilityBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0-4: do money columns keep their paise? SQLite is forgiving whatever a
 * column is declared as (numeric affinity keeps decimals), so a column that
 * silently truncated to whole rupees would only ever show up on MariaDB -
 * the engine production runs. Verified against a MariaDB built from the
 * migrations: every money column is DECIMAL(x,2). These tests keep it so.
 */
class MoneyPrecisionTest extends TestCase
{
    use RefreshDatabase;

    private function same(float $expected, mixed $actual, string $what): void
    {
        $this->assertEqualsWithDelta($expected, (float) $actual, 0.0001, "$what lost its decimals: stored {$expected}, read back " . var_export($actual, true));
    }

    public function test_bills_tenancies_and_utility_bills_keep_paise_on_a_round_trip(): void
    {
        $society = $this->makeSociety();
        $flat    = $this->makeFlat($society, $this->makeBuilding($society));

        // Up to just under the DECIMAL(10,2) ceiling (99,999,999.99) once the 0.99 penalty is added.
        $months = ['January', 'February', 'March', 'April'];

        foreach ([1234.56, 0.01, 99999.99, 98999999.00] as $i => $amount) {
            // total_amount and balance_amount are DERIVED on save from the
            // components (the bill ledger is canonical), so feed the inputs
            // and check the derived values keep their paise too.
            $bill = MaintenanceBill::create([
                'society_id' => $society->id, 'flat_id' => $flat->id, 'bill_number' => 'MB-' . uniqid(),
                // one bill per flat per month - a real unique constraint
                'month' => $months[$i], 'year' => 2026, 'bill_date' => '2026-01-01', 'due_date' => '2026-01-10',
                'maintenance_amount' => $amount, 'paid_amount' => 0.5, 'penalty_amount' => 0.99, 'status' => 'pending',
            ])->fresh();

            $this->same($amount, $bill->maintenance_amount, 'maintenance_bills.maintenance_amount');
            $this->same(0.5, $bill->paid_amount, 'maintenance_bills.paid_amount');
            $this->same(0.99, $bill->penalty_amount, 'maintenance_bills.penalty_amount');
            $this->same($amount + 0.99, $bill->total_amount, 'maintenance_bills.total_amount (derived)');
            $this->same($amount + 0.99 - 0.5, $bill->balance_amount, 'maintenance_bills.balance_amount (derived)');

            $tenant = Tenant::create([
                'society_id' => $society->id, 'flat_id' => $flat->id, 'name' => 'T', 'email' => uniqid() . '@test.local',
                'monthly_rent' => $amount, 'security_deposit' => $amount, 'status' => 'active',
            ])->fresh();
            $this->same($amount, $tenant->monthly_rent, 'tenants.monthly_rent');
            $this->same($amount, $tenant->security_deposit, 'tenants.security_deposit');

            $utility = UtilityBill::create([
                'society_id' => $society->id, 'flat_id' => $flat->id, 'bill_type' => 'Water', 'bill_amount' => $amount,
                'bill_date' => '2026-01-01', 'due_date' => '2026-01-10', 'status' => 'unpaid',
            ])->fresh();
            $this->same($amount, $utility->bill_amount, 'utility_bills.bill_amount');
        }
    }

    /**
     * MariaDB only: no money-looking column may be a whole-number decimal or
     * an integer. (On SQLite the column's declared type does not constrain
     * what is stored, so there is nothing to assert there.)
     */
    public function test_no_money_column_on_mariadb_is_declared_with_a_scale_below_two(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('Declared scale only matters on MariaDB; SQLite stores what it is given.');
        }

        // (*_by columns - paid_by, approved_by - are user references, not amounts, hence excluded below.)
        // Names that hold money. (Not "total": total_floors / total_slots are counters.)
        $money = 'amount|rent|deposit|charge|price|fee|salary|fine|penalty|balance|paid|cost';

        $tooCoarse = DB::select(
            "SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ct FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME REGEXP ?
               AND ((DATA_TYPE = 'decimal' AND NUMERIC_SCALE < 2)
                 OR DATA_TYPE IN ('int', 'bigint', 'smallint', 'mediumint'))
               AND COLUMN_NAME NOT REGEXP '_id$|_by$|^id$|count|number|days|months|years|minutes|hours|cycle|feedback'",
            [$money]
        );

        $this->assertSame(
            [],
            array_map(fn ($r) => "{$r->t}.{$r->c} ({$r->ct})", $tooCoarse),
            'money columns that would truncate paise'
        );
    }
}
