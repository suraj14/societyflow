<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Normalizes the legacy "Villa Owner" / "Apartment Owner" roles into the
 * single canonical "Owner" role. Property type (apartment/villa) is a
 * property attribute, not a business role - Villa Owner and Apartment
 * Owner have always carried identical permission sets (see
 * RolesAndPermissionsSeeder), so this changes only the role label, not
 * any capability.
 *
 * Additive and reversible until --force is passed: a user who already
 * holds "Owner" is left alone; a user holding only the legacy roles gets
 * "Owner" added and the legacy role(s) removed in the same transaction.
 * Nothing else about the user (id, email, phone, society_id, owner_id,
 * flats, payments, complaints, visitors, access passes) is touched -
 * this command only calls Spatie's role pivot methods.
 *
 * Reports by default; --force actually writes.
 */
class MigrateToOwnerRole extends Command
{
    protected $signature = 'users:migrate-to-owner-role
                            {--force : Actually migrate. Without this the command only reports counts.}';

    protected $description = 'Merge Villa Owner / Apartment Owner into the canonical Owner role';

    public function handle(): int
    {
        $villaOwnerRole = Role::where('name', 'Villa Owner')->where('guard_name', 'web')->first();
        $apartmentOwnerRole = Role::where('name', 'Apartment Owner')->where('guard_name', 'web')->first();
        $ownerRole = Role::where('name', 'Owner')->where('guard_name', 'web')->first();

        if (!$ownerRole) {
            $this->error('The "Owner" role does not exist yet - run RolesAndPermissionsSeeder first.');

            return self::FAILURE;
        }

        $villaOwnerUsers = $villaOwnerRole ? User::role('Villa Owner')->get() : collect();
        $apartmentOwnerUsers = $apartmentOwnerRole ? User::role('Apartment Owner')->get() : collect();
        $existingOwnerCount = User::role('Owner')->count();

        $toMigrate = $villaOwnerUsers->merge($apartmentOwnerUsers)->unique('id');

        $this->info("Existing 'Owner' holders: {$existingOwnerCount}");
        $this->info("'Villa Owner' holders: {$villaOwnerUsers->count()}");
        $this->info("'Apartment Owner' holders: {$apartmentOwnerUsers->count()}");
        $this->info("Users needing migration (union, already-Owner excluded automatically by assignRole): {$toMigrate->count()}");

        if ($toMigrate->isEmpty()) {
            $this->info('Nothing to migrate.');

            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->table(
                ['ID', 'Name', 'Email', 'Roles before'],
                $toMigrate->map(fn (User $u) => [$u->id, $u->name, $u->email, $u->getRoleNames()->implode(', ')])
            );
            $this->comment('Report only. Re-run with --force to migrate.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($toMigrate) {
            foreach ($toMigrate as $user) {
                $hadVilla = $user->hasRole('Villa Owner');
                $hadApartment = $user->hasRole('Apartment Owner');

                $user->assignRole('Owner');

                if ($hadVilla) {
                    $user->removeRole('Villa Owner');
                }
                if ($hadApartment) {
                    $user->removeRole('Apartment Owner');
                }
            }
        });

        $finalOwnerCount = User::role('Owner')->count();
        $this->info("Migration complete. 'Owner' holders now: {$finalOwnerCount} (expected {$existingOwnerCount} + {$toMigrate->count()} = " . ($existingOwnerCount + $toMigrate->count()) . ')');

        if ($finalOwnerCount !== $existingOwnerCount + $toMigrate->count()) {
            $this->warn('Final count does not match the expected sum - a user may have already held Owner alongside a legacy role. Investigate before treating this as final.');
        }

        return self::SUCCESS;
    }
}
