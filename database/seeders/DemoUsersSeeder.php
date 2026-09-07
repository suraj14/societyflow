<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Society;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create a demo society
        $society = Society::first() ?? Society::create([
            'name' => 'Demo Society',
            'slug' => 'demo-society',
            'address' => '123 Demo Street',
            'city' => 'Demo City',
            'state' => 'Demo State',
            'country' => 'USA',
            'pincode' => '12345',
            'status' => 'active',
        ]);

        // 1. Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@societyflow.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'phone' => '1234567890',
                'status' => 'active',
            ]
        );
        $superAdmin->syncRoles(['Super Admin']);

        // 2. Society Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@societyflow.com'],
            [
                'name' => 'Society Admin',
                'society_id' => $society->id,
                'password' => Hash::make('password'),
                'phone' => '1234567891',
                'status' => 'active',
            ]
        );
        $admin->syncRoles(['Admin']);

        // 3. Owner (Multi-Property - has both Villa Owner and Apartment Owner roles)
        $villaOwner = User::firstOrCreate(
            ['email' => 'villaowner@societyflow.com'],
            [
                'name' => 'John Property Owner',
                'society_id' => $society->id,
                'password' => Hash::make('password'),
                'phone' => '1234567892',
                'status' => 'active',
            ]
        );
        // Assign both Villa Owner and Apartment Owner roles for comprehensive property management
        $villaOwner->syncRoles(['Villa Owner', 'Apartment Owner']);

        // 4. Apartment Owner
        $apartmentOwner = User::firstOrCreate(
            ['email' => 'owner@societyflow.com'],
            [
                'name' => 'Jane Apartment Owner',
                'society_id' => $society->id,
                'password' => Hash::make('password'),
                'phone' => '1234567893',
                'status' => 'active',
            ]
        );
        $apartmentOwner->syncRoles(['Apartment Owner']);

        // 5. Tenant
        $tenant = User::firstOrCreate(
            ['email' => 'tenant@societyflow.com'],
            [
                'name' => 'Bob Tenant',
                'society_id' => $society->id,
                'password' => Hash::make('password'),
                'phone' => '1234567894',
                'status' => 'active',
            ]
        );
        $tenant->syncRoles(['Tenant']);

        // 6. Staff (Security)
        $staff = User::firstOrCreate(
            ['email' => 'staff@societyflow.com'],
            [
                'name' => 'Security Guard',
                'society_id' => $society->id,
                'password' => Hash::make('password'),
                'phone' => '1234567895',
                'status' => 'active',
            ]
        );
        $staff->syncRoles(['Staff']);

        // 7. Accountant
        $accountant = User::firstOrCreate(
            ['email' => 'accountant@societyflow.com'],
            [
                'name' => 'Finance Accountant',
                'society_id' => $society->id,
                'password' => Hash::make('password'),
                'phone' => '1234567896',
                'status' => 'active',
            ]
        );
        $accountant->syncRoles(['Accountant']);

        $this->command->info('Demo users created successfully!');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Super Admin', 'superadmin@societyflow.com', 'password'],
                ['Admin', 'admin@societyflow.com', 'password'],
                ['Owner', 'villaowner@societyflow.com', 'password'],
                ['Tenant', 'tenant@societyflow.com', 'password'],
                ['Staff', 'staff@societyflow.com', 'password'],
                ['Accountant', 'accountant@societyflow.com', 'password'],
            ]
        );
    }
}
