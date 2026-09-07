<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define simplified permissions
        $permissions = [
            // Facilities
            'view_facility',
            'book_facility',
            'view_own_bookings',
            'manage_facilities',
            
            // Visitors
            'view_own_visitors',
            'create_visitor',
            'approve_visitor',
            'checkin_visitor',
            'checkout_visitor',
            'view_all_visitors',
            
            // Tickets/Complaints
            'create_ticket',
            'view_own_ticket',
            'view_all_tickets',
            'assign_ticket',
            'resolve_ticket',
            
            // Bills & Payments
            'view_own_bills',
            'view_all_bills',
            'pay_bill',
            'manage_bills',
            
            // Services
            'view_services',
            'clock_service',
            'manage_services',
            
            // Notices
            'view_notices',
            'create_notice',
            'manage_notices',
            
            // Events
            'view_events',
            'create_event',
            'manage_events',
            
            // Admin/Management
            'manage_society',
            'manage_buildings',
            'manage_units',
            'manage_villas',
            'access_settings',
            'view_reports',
            'access_super_admin',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create Roles and assign permissions
        $this->createSuperAdmin();
        $this->createAdmin();
        $this->createVillaOwner();
        $this->createApartmentOwner();
        $this->createTenant();
        $this->createStaff();
        $this->createGuard();
        $this->createAccountant();

        $this->command->info('Roles and Permissions seeded successfully!');
    }

    private function createSuperAdmin(): void
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::all());
    }

    private function createAdmin(): void
    {
        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Management permissions (Society-scoped)
            'manage_society',
            'manage_buildings',
            'manage_units',
            'manage_villas',
            'manage_facilities',
            'manage_bills',
            'manage_services',
            'manage_notices',
            'access_settings',
            'view_reports',
            
            // View permissions (Society-scoped)
            'view_facility',
            'view_all_visitors',
            'view_all_tickets',
            'view_all_bills',
            'view_services',
            'view_notices',
            'view_events',
            
            // Action permissions
            'assign_ticket',
            'resolve_ticket',
            'checkin_visitor',
            'checkout_visitor',
            'approve_visitor',
            'create_notice',
            'create_event',
        ]);
    }

    private function createVillaOwner(): void
    {
        $role = Role::firstOrCreate(['name' => 'Villa Owner', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Facilities: Can BOOK only, NOT create/manage
            'view_facility',
            'book_facility',
            'view_own_bookings',
            // Visitors
            'view_own_visitors',
            'create_visitor',
            'approve_visitor',
            // Tickets
            'create_ticket',
            'view_own_ticket',
            // Bills
            'view_own_bills',
            'pay_bill',
            // Services
            'view_services',
            'clock_service',
            // Notices
            'view_notices',
        ]);
    }

    private function createApartmentOwner(): void
    {
        $role = Role::firstOrCreate(['name' => 'Apartment Owner', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Facilities: Can BOOK only, NOT create/manage
            'view_facility',
            'book_facility',
            'view_own_bookings',
            // Visitors
            'view_own_visitors',
            'create_visitor',
            'approve_visitor',
            // Tickets
            'create_ticket',
            'view_own_ticket',
            // Bills
            'view_own_bills',
            'pay_bill',
            // Services
            'view_services',
            'clock_service',
            // Notices
            'view_notices',
        ]);
    }

    private function createTenant(): void
    {
        $role = Role::firstOrCreate(['name' => 'Tenant', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Facilities: Can BOOK only, NOT create/manage
            'view_facility',
            'book_facility',
            'view_own_bookings',
            // Visitors
            'view_own_visitors',
            'create_visitor',
            // Tickets
            'create_ticket',
            'view_own_ticket',
            // Bills
            'view_own_bills',
            // Services
            'view_services',
            'clock_service',
            // Notices
            'view_notices',
        ]);
    }

    private function createStaff(): void
    {
        $role = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $role->syncPermissions([
            // Visitors - CORE FEATURE for Staff
            'view_all_visitors',
            'create_visitor',
            'checkin_visitor',
            'checkout_visitor',
            'approve_visitor',
            
            // Tickets/Complaints - Limited access
            'view_all_tickets',
            'resolve_ticket',
            
            // Services - View and clock only
            'view_services',
            'clock_service',
            
            // Notices - Read only
            'view_notices',
            
            // Events - Read only
            'view_events',
        ]);
    }

    private function createGuard(): void
    {
        $role = Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'web']);
        $role->syncPermissions([
            'view_all_visitors',
            'create_visitor',
            'checkin_visitor',
            'checkout_visitor',
            'approve_visitor',
            'view_services',
            'view_notices',
            'view_events',
        ]);
    }

    private function createAccountant(): void
    {
        $role = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $role->syncPermissions([
            'view_all_bills',
            'manage_bills',
            'view_reports',
            'view_notices',
        ]);
    }
}
