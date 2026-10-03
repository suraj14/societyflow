<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The expenses table has existed since the original schema but nothing wrote
 * to it, and it could not say *whose* expense a row was. The financial
 * report needs one distinction above all others: a society/common expense
 * (security agency, common-area electricity, gardening) versus an expense
 * that belongs to one property (Villa 160's own electricity). Only the
 * first kind may ever count towards "Total Society Expenses".
 *
 * Three columns, all plain strings validated in ExpenseController (no CHECK
 * constraint, so widening the allowed set later is a code change, not a
 * SQLite table rebuild):
 *
 *  - scope:   'society' (no property - the default) | 'property'
 *  - flat_id: required when scope = property, must be inside the same
 *             society; null for a society-wide expense
 *  - paid_by: who bears the cost - 'society' | 'owner' | 'tenant' |
 *             'property'. Deliberately not hard-coded to "owner" for a
 *             property expense: for a rented unit that depends on the
 *             agreement, so it is recorded per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('scope', 20)->default('society')->after('society_id');
            $table->foreignId('flat_id')->nullable()->after('scope')
                ->constrained('flats')->onDelete('set null');
            $table->string('paid_by', 20)->default('society')->after('flat_id');

            $table->index(['society_id', 'scope', 'expense_date'], 'expenses_society_scope_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_society_scope_date_idx');
            $table->dropConstrainedForeignId('flat_id');
            $table->dropColumn(['scope', 'paid_by']);
        });
    }
};
