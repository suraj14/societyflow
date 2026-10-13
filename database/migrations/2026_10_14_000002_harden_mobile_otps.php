<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time codes are no longer stored as typed.
 *
 *   otp      was string(6) holding the code itself; now holds a keyed hash (64 hex characters), so a copy of the
 *            table does not contain a usable code
 *   purpose  'login' or 'password_reset': a code issued for one thing can no longer be spent on the other (they
 *            shared the table and the phone number, so a login code could reset the password)
 *
 * Codes that are still waiting were stored in the old form and cannot be checked against the new one. They live five
 * minutes at most, so they are marked used (the person asks for a new code) rather than rewritten. No row is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mobile_otps')) {
            return;
        }

        DB::table('mobile_otps')->where('is_used', false)->update(['is_used' => true]);

        Schema::table('mobile_otps', function (Blueprint $table) {
            $table->string('otp', 64)->change();
        });
        Schema::table('mobile_otps', function (Blueprint $table) {
            $table->string('purpose', 20)->default('login')->after('phone');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mobile_otps')) {
            return;
        }

        // Hashed codes cannot be turned back into digits, and a 64-character value does not fit string(6): retire what is
        // waiting, then shorten.
        DB::table('mobile_otps')->where('is_used', false)->update(['is_used' => true]);
        DB::table('mobile_otps')->update(['otp' => '0000']);

        Schema::table('mobile_otps', fn (Blueprint $t) => $t->dropColumn('purpose'));
        Schema::table('mobile_otps', fn (Blueprint $t) => $t->string('otp', 6)->change());
    }
};
