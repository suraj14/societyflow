<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Super Admin Permissions
            'manage_societies',
            'manage_subscriptions',
            'manage_global_settings',
            'view_system_reports',
            
            // Society Admin Permissions
            'manage_society_profile',
            'manage_buildings',
            'manage_flats',
            'manage_residents',
            'manage_staff',
            'manage_maintenance_bills',
            'view_payments',
            'manage_expenses',
            'manage_complaints',
            'manage_notices',
            'manage_facilities',
            'manage_visitors',
            'view_society_reports',
            'manage_society_settings',
            
            // Accountant Permissions
            'view_financial_reports',
            
            // Resident Permissions
            'view_own_bills',
            'make_payments',
            'raise_complaints',
            'view_notices',
            'book_facilities',
            'view_own_profile',
            
            // Staff Permissions
            'mark_attendance',
            'view_assigned_tasks',
            'update_complaints',
            'manage_visitor_entry',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Super Admin Role
        $superAdmin = Role::create(['name' => 'Super Admin']);
        $superAdmin->givePermissionTo([
            'manage_societies',
            'manage_subscriptions',
            'manage_global_settings',
            'view_system_reports',
        ]);

        // Society Admin Role
        $societyAdmin = Role::create(['name' => 'Society Admin']);
        $societyAdmin->givePermissionTo([
            'manage_society_profile',
            'manage_buildings',
            'manage_flats',
            'manage_residents',
            'manage_staff',
            'manage_maintenance_bills',
            'view_payments',
            'manage_expenses',
            'manage_complaints',
            'manage_notices',
            'manage_facilities',
            'manage_visitors',
            'view_society_reports',
            'manage_society_settings',
        ]);

        // Accountant Role
        $accountant = Role::create(['name' => 'Accountant']);
        $accountant->givePermissionTo([
            'manage_maintenance_bills',
            'view_payments',
            'manage_expenses',
            'view_financial_reports',
        ]);

        // Owner Role
        $owner = Role::create(['name' => 'Owner']);
        $owner->givePermissionTo([
            'view_own_bills',
            'make_payments',
            'raise_complaints',
            'view_notices',
            'book_facilities',
            'manage_visitors',
            'view_own_profile',
        ]);

        // Tenant Role
        $tenant = Role::create(['name' => 'Tenant']);
        $tenant->givePermissionTo([
            'view_own_bills',
            'make_payments',
            'raise_complaints',
            'view_notices',
            'book_facilities',
            'manage_visitors',
            'view_own_profile',
        ]);

        // Staff Role
        $staff = Role::create(['name' => 'Staff']);
        $staff->givePermissionTo([
            'mark_attendance',
            'view_assigned_tasks',
            'update_complaints',
            'manage_visitor_entry',
        ]);
    }
}