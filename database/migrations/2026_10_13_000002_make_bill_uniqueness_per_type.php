<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One bill per unit, month AND type.
 *
 * maintenance_bills was made unique on (society_id, flat_id, month, year) in the very first migration. The `bill_type`
 * column (maintenance | rent) was added later and the index was never widened, so a rent bill and a maintenance bill
 * for the same unit and month collided: with a maintenance bill on Villa 159 for September, the admin could not raise
 * September rent for its tenant (the insert hit the unique index and the form answered with a generic error), and the
 * tenant's My Rent stayed at zero.
 *
 * This keeps the uniqueness guarantee - still exactly one bill of each type per unit and month - and drops only the
 * part that wrongly treated rent and maintenance as the same bill. No row is read, changed or deleted. The wider index
 * is created BEFORE the old one is dropped, so there is no moment without a unique index, and MySQL always has an index
 * starting with society_id for its foreign key.
 */
return new class extends Migration
{
    private const NEW = 'maintenance_bills_unit_month_type_unique';
    private const OLD = 'maintenance_bills_society_id_flat_id_month_year_unique';

    public function up(): void
    {
        if (!Schema::hasTable('maintenance_bills') || !Schema::hasColumn('maintenance_bills', 'bill_type')) {
            return;
        }

        Schema::table('maintenance_bills', function (Blueprint $table) {
            $table->unique(['society_id', 'flat_id', 'month', 'year', 'bill_type'], self::NEW);
        });

        Schema::table('maintenance_bills', function (Blueprint $table) {
            $table->dropUnique(self::OLD);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_bills')) {
            return;
        }

        // Going back means one bill per unit and month whatever its type. If a unit now has a rent AND a maintenance
        // bill for the same month that is impossible without deleting a financial record - so refuse, and say why.
        $clash = DB::table('maintenance_bills')
            ->select('society_id', 'flat_id', 'month', 'year')
            ->groupBy('society_id', 'flat_id', 'month', 'year')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($clash) {
            throw new RuntimeException('Cannot roll back: some units have a rent bill and a maintenance bill for the same month. Restoring the old one-bill-per-month index would require deleting one of them.');
        }

        Schema::table('maintenance_bills', function (Blueprint $table) {
            $table->unique(['society_id', 'flat_id', 'month', 'year'], self::OLD);
        });

        Schema::table('maintenance_bills', function (Blueprint $table) {
            $table->dropUnique(self::NEW);
        });
    }
};
