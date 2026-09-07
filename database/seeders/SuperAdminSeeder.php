<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin user
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@societyflow.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        // Assign Super Admin role
        $superAdmin->assignRole('Super Admin');

        $this->command->info('Super Admin created successfully!');
        $this->command->info('Email: admin@societyflow.com');
        $this->command->info('Password: password');
    }
}