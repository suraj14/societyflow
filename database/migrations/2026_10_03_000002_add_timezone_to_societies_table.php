<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-society timezone (IANA name). Storage stays in app.timezone; this is
 * the calendar a society's "today", month boundaries, overdue checks and
 * access-pass hours are read on - see App\Support\SocietyClock. Nullable so
 * an unset row falls back to config('app.society_timezone') (Asia/Kolkata),
 * which is also the value every existing row gets here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('societies', 'timezone')) {
            return;
        }

        Schema::table('societies', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('country');
        });

        // A society that already chose a timezone in Settings → App Settings
        // (system_settings app.timezone) keeps it; everyone else gets IST.
        if (Schema::hasTable('system_settings') && Schema::hasColumn('system_settings', 'society_id')) {
            $chosen = \Illuminate\Support\Facades\DB::table('system_settings')
                ->where('group', 'app')->where('key', 'timezone')->whereNotNull('society_id')
                ->pluck('value', 'society_id');

            foreach ($chosen as $societyId => $tz) {
                $tz = trim((string) $tz, "\" \t\n");
                if (in_array($tz, timezone_identifiers_list(), true)) {
                    \Illuminate\Support\Facades\DB::table('societies')->whereKey($societyId)->update(['timezone' => $tz]);
                }
            }
        }

        \Illuminate\Support\Facades\DB::table('societies')->whereNull('timezone')->update(['timezone' => 'Asia/Kolkata']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('societies', 'timezone')) {
            Schema::table('societies', fn (Blueprint $table) => $table->dropColumn('timezone'));
        }
    }
};
