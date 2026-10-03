<?php

namespace App\Console\Commands;

use App\Models\Resident;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Repairs the specific data inconsistency this bug produced: Tenant
 * Assignments used to write to `residents` (type='tenant') while Tenant
 * Management always read/wrote `tenants.flat_id` - two unrelated tables for
 * the same fact. A tenant assigned only through the old "Tenant
 * Assignments" screen has a Tenant row with flat_id still null and a
 * separate, matching active Resident row that actually recorded the
 * assignment.
 *
 * This is a narrow, deterministic backfill: for a Tenant row with a null
 * flat_id, look for an active Resident(type=tenant) row for the *same
 * user_id* and copy its flat_id and move_in_date across. It only fills an
 * empty field - it never overwrites an existing flat_id, and it never
 * guesses across ambiguous matches (CLAUDE.md's standing rule for this
 * class of repair - see UserFlatResolver's own docblock).
 *
 * Report by default; --force to write.
 */
class RepairTenantFlatAssignments extends Command
{
    protected $signature = 'tenants:repair-flat-assignments
                            {--force : Actually write the repair. Without this the command only reports.}';

    protected $description = 'Backfill tenants.flat_id from a matching legacy Resident(type=tenant) record';

    public function handle(): int
    {
        $candidates = Tenant::whereNull('flat_id')
            ->whereNotNull('user_id')
            ->get()
            ->map(function (Tenant $tenant) {
                $resident = Resident::where('type', 'tenant')
                    ->where('status', 'active')
                    ->where('user_id', $tenant->user_id)
                    ->whereNotNull('flat_id')
                    ->first();

                return $resident ? [$tenant, $resident] : null;
            })
            ->filter();

        if ($candidates->isEmpty()) {
            $this->info('No inconsistent tenant/resident records found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Tenant', 'user_id', 'Resident flat_id (will fill)', 'Resident move_in_date'],
            $candidates->map(fn ($pair) => [$pair[0]->name, $pair[0]->user_id, $pair[1]->flat_id, $pair[1]->move_in_date])
        );

        if (!$this->option('force')) {
            $this->comment('Report only. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        foreach ($candidates as [$tenant, $resident]) {
            $tenant->update([
                'flat_id' => $resident->flat_id,
                'move_in_date' => $tenant->move_in_date ?? $resident->move_in_date,
            ]);
        }

        $this->info("Repaired {$candidates->count()} tenant record(s).");

        return self::SUCCESS;
    }
}
