<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * This seeder runs only essential production seeders.
     * It sets up:
     * - Roles and permissions (RBAC)
     * - System settings
     * - Core services
     * - Initial admin accounts for demo
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemSettingsSeeder::class,
            ServicesSeeder::class,
            SimpleTwoAdminSeeder::class,
        ]);
    }
}