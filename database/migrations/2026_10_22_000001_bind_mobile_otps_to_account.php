<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A password-reset code is bound to the ACCOUNT it was issued for, not just a phone number: a number saved on several accounts (even in
 * several societies) must not let a code requested for one account reset another. Nullable on purpose - existing rows and every login code
 * stay user-less (login still resolves its account after the number is proven). No data is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_otps') && !Schema::hasColumn('mobile_otps', 'user_id')) {
            Schema::table('mobile_otps', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('purpose');
                $table->index(['phone', 'purpose', 'user_id'], 'mobile_otps_phone_purpose_user_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobile_otps') && Schema::hasColumn('mobile_otps', 'user_id')) {
            Schema::table('mobile_otps', function (Blueprint $table) {
                $table->dropIndex('mobile_otps_phone_purpose_user_index');
                $table->dropColumn('user_id');
            });
        }
    }
};
