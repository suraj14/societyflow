<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Society-wide staff (a common-area cleaner, the society's own electrician)
 * have no single flat, so their visits cannot become `visitors` rows the
 * way property-specific passes' do - `visitors.flat_id` is NOT NULL and
 * read unguarded (`$visitor->flat->building->name`, no `?->`) in enough
 * views, emails and the mobile API formatter that widening it safely would
 * mean auditing and null-guarding all of them, a much larger and riskier
 * change than this pass's actual ask. Tracking in/out directly on the pass
 * instead keeps that well-used table and everything that reads it
 * completely untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_passes', function (Blueprint $table) {
            $table->boolean('currently_inside')->default(false)->after('last_used_at');
            $table->timestamp('last_entry_at')->nullable()->after('currently_inside');
            $table->timestamp('last_exit_at')->nullable()->after('last_entry_at');
        });
    }

    public function down(): void
    {
        Schema::table('access_passes', function (Blueprint $table) {
            $table->dropColumn(['currently_inside', 'last_entry_at', 'last_exit_at']);
        });
    }
};
