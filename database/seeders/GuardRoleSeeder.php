<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class GuardRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Guard role with same permissions as Staff
        $guard = Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'web']);
        $staff = Role::where('name', 'Staff')->first();
        if ($staff) {
            $guard->syncPermissions($staff->permissions);
        } else {
            $guard->syncPermissions([
                'view_all_visitors', 'create_visitor', 'checkin_visitor',
                'checkout_visitor', 'approve_visitor', 'view_services',
                'view_notices', 'view_events',
            ]);
        }

        $this->command->info('Guard role created/updated successfully.');

        // Create a test Guard user (only if society exists)
        $society = \App\Models\Society::first();
        if (!$society) {
            $this->command->warn('No society found — skipping test guard user creation.');
            return;
        }

        $guardUser = User::firstOrCreate(
            ['email' => 'guard@societyflow.com'],
            [
                'name'       => 'Security Guard',
                'phone'      => '9000000001',
                'password'   => Hash::make('guard123'),
                'status'     => 'active',
                'society_id' => $society->id,
            ]
        );

        $guardUser->syncRoles(['Guard']);
        $this->command->info("Test guard user: guard@societyflow.com / guard123");
    }
}
