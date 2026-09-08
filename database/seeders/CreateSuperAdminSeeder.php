<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateSuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // This creates the single most privileged account in the system.
        // It used to hardcode Hash::make('password') and print the credential
        // - and superadmin@societyflow.com is one of the 31 accounts found
        // still using it. Running this against production would hand the
        // whole platform to anyone who knows the email address.
        if (app()->environment('production') && !env('SEED_ALLOW_IN_PRODUCTION')) {
            $this->command->error('Refusing to run in production. Set SEED_ALLOW_IN_PRODUCTION=true to override.');

            return;
        }

        // Create Super Admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);

        // Generated per run. Set SEED_ADMIN_PASSWORD to pin a known value
        // for local development.
        $password = env('SEED_ADMIN_PASSWORD') ?: \App\Helpers\TemporaryPassword::generate();

        // Create Super Admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@societyflow.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => 'active',
                'society_id' => null, // Super Admin doesn't belong to any specific society
            ]
        );

        // Assign Super Admin role
        if (!$superAdmin->hasRole('Super Admin')) {
            $superAdmin->assignRole('Super Admin');
        }

        $this->command->info('Super Admin created successfully!');
        $this->command->info('Email: superadmin@societyflow.com');

        // firstOrCreate leaves an existing account's password untouched, so
        // only report the generated one when this run actually created it.
        if ($superAdmin->wasRecentlyCreated) {
            $this->command->info("Password: {$password}");
            $this->command->warn('Record this now - it is generated per run and not stored.');
        } else {
            $this->command->info('Account already existed; its password was left unchanged.');
        }
    }
}