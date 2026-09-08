<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The villa owner visitor form has always collected a visitor email, but
 * there was no column to put it in, so the value was silently discarded
 * (and, because it was passed to Visitor::create via mass assignment
 * alongside several other non-existent columns, the insert failed outright).
 *
 * Purely additive nullable column: no existing row or query changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('visitors', 'visitor_email')) {
            return;
        }

        Schema::table('visitors', function (Blueprint $table) {
            $table->string('visitor_email')->nullable()->after('visitor_phone');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('visitors', 'visitor_email')) {
            return;
        }

        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn('visitor_email');
        });
    }
};
