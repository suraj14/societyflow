<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete duplicate services, keeping the first occurrence of each name.
        //
        // The original statement wrapped the per-name list in a second
        // aggregate:
        //
        //     WHERE id NOT IN (SELECT MIN(id) FROM (
        //         SELECT MIN(id) as id FROM services GROUP BY name
        //     ) as temp)
        //
        // The outer MIN() collapses that list to ONE id - the smallest in the
        // table - so the DELETE removed every service except a single row.
        // On a fresh install this wiped the entire 17-row catalogue seeded by
        // 2026_01_10_150000_remove_duplicate_services moments earlier, leaving
        // only "Maid". The inner GROUP BY was already the correct answer.
        //
        // Guarded so it cannot empty a catalogue that has no duplicates.
        $keepIds = DB::table('services')
            ->selectRaw('MIN(id) as id')
            ->groupBy('name')
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return;
        }

        DB::table('services')->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse this migration
    }
};
