<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `services` is a global catalogue of service types (Maid, Cook, Driver...)
 * with no society_id - that part is by design. What is not by design is that
 * it contains duplicates, so residents see the same service listed twice in
 * the mobile app.
 *
 * Two earlier migrations (remove_duplicate_services and
 * remove_duplicate_services_fix) tried to clean this up and left a duplicate
 * behind, because neither addressed the reason duplicates keep appearing:
 * nothing stops them being inserted. This adds the unique index that does.
 *
 * No reference is orphaned. Every service_providers.service_id and
 * service_requests.service_id pointing at a duplicate is repointed to the
 * surviving (lowest-id) row before the duplicate is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('services')) {
            return;
        }

        $this->mergeDuplicates();

        // Prevent recurrence. This is the actual fix; the merge above only
        // cleans up what the missing constraint already allowed in.
        if (!$this->uniqueIndexExists()) {
            Schema::table('services', function (Blueprint $table) {
                $table->unique(['name', 'category'], 'services_name_category_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('services') || !$this->uniqueIndexExists()) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique('services_name_category_unique');
        });
    }

    /**
     * Collapse rows sharing a name+category onto the lowest id, repointing
     * every foreign key first.
     */
    private function mergeDuplicates(): void
    {
        $groups = DB::table('services')
            ->selectRaw('LOWER(name) as lname, LOWER(category) as lcategory, COUNT(*) as total')
            ->groupBy('lname', 'lcategory')
            ->having('total', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('services')
                ->whereRaw('LOWER(name) = ?', [$group->lname])
                ->whereRaw('LOWER(category) = ?', [$group->lcategory])
                ->orderBy('id')
                ->pluck('id');

            $keep   = $ids->first();
            $remove = $ids->slice(1)->values();

            if ($remove->isEmpty()) {
                continue;
            }

            foreach (['service_providers', 'service_requests'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'service_id')) {
                    DB::table($table)->whereIn('service_id', $remove)->update(['service_id' => $keep]);
                }
            }

            DB::table('services')->whereIn('id', $remove)->delete();
        }
    }

    private function uniqueIndexExists(): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return collect($connection->select("PRAGMA index_list('services')"))
                ->contains(fn ($i) => $i->name === 'services_name_category_unique');
        }

        return collect($connection->select('SHOW INDEX FROM `services`'))
            ->contains(fn ($i) => $i->Key_name === 'services_name_category_unique');
    }
};
