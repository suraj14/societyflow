<?php

use App\Support\DataIntegrityRepair;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two things, in the order they have to happen:
 *
 * 1. payments.payment_method on MySQL is still the original
 *    enum('cash','cheque','online','upi','card'). The rent screens have
 *    always offered "Bank Transfer" (value bank_transfer), which MySQL in
 *    strict mode rejects outright - the dev SQLite database already holds
 *    one such row. The ledger reconciliation below also needs an honest
 *    value ('unrecorded') for a payment whose method was never recorded.
 *    SQLite needs nothing: the 2026_01_29 column change rebuilt the table
 *    without the CHECK constraint (see payments' CREATE TABLE in the dev
 *    DB), so both values already store there.
 *
 * 2. The three data-integrity repairs (see App\Support\DataIntegrityRepair).
 *    Idempotent; running it again finds nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('payments')) {
            $methods = "'" . implode("','", \App\Models\Payment::METHODS) . "'";
            DB::statement("ALTER TABLE payments MODIFY payment_method ENUM({$methods}) NOT NULL DEFAULT 'online'");
        }

        if (Schema::hasTable('maintenance_bills') && Schema::hasTable('payments') && Schema::hasTable('activity_logs')) {
            DataIntegrityRepair::run(true);
        }
    }

    public function down(): void
    {
        // The reconciliation rows are the only thing worth undoing; they are
        // identifiable by their transaction_id. Cleared move-out dates and
        // resynced flat statuses are not restored (the forward state is the
        // correct one and the old values are in activity_logs).
        if (Schema::hasTable('payments')) {
            DB::table('payments')->where('transaction_id', 'like', DataIntegrityRepair::RECONCILIATION_PREFIX . '%')->delete();
        }
    }
};
