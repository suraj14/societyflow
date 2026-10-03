<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions, both reusing the existing visitor entry/exit machinery
 * rather than building a parallel one:
 *
 *  - access_pass_id: set when a gate scan resolved to a recurring-staff or
 *    temporary-service AccessPass. The visit itself (check-in, "Visitors
 *    Inside", check-out, history) is an ordinary visitors row from here on.
 *  - qr_token: lets a *pre-approved guest* (brief Part 8 - "reuse existing
 *    visitor invitation system") be looked up by an opaque token at the
 *    gate, the same lookup-not-decode pattern as access_passes.token,
 *    without a second pass table for something the visitors table already
 *    models (approval_status = allowed, entry_status = pending).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->foreignId('access_pass_id')->nullable()->after('host_user_id')
                ->constrained('access_passes')->onDelete('set null');
            $table->string('qr_token', 64)->nullable()->unique()->after('access_pass_id');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_pass_id');
            $table->dropColumn('qr_token');
        });
    }
};
