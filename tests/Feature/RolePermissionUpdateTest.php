<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Super Admin > Roles: editing which permissions a role carries. This is the
 * privilege-escalation surface, so besides "it works" it pins that only
 * Super Admin can do it (SuperAdminSurfaceTest covers the whole area), that
 * the Super Admin role itself cannot be edited, and that an unknown permission
 * name is refused without touching the role.
 */
class RolePermissionUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin()
    {
        return $this->makeUserWithRole($this->makeSociety(), 'Super Admin', ['society_id' => null]);
    }

    private function permission(string $name): Permission
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    public function test_super_admin_changes_a_roles_permissions_and_users_of_that_role_feel_it(): void
    {
        $this->permission('manage_widgets');
        $this->permission('view_widgets');
        $role  = $this->makeRole('Staff');
        $staff = $this->makeUserWithRole($this->makeSociety(), 'Staff');
        $this->assertFalse($staff->can('manage_widgets'));

        $this->actingAs($this->superAdmin())->put(route('super-admin.roles.update', $role), ['permissions' => ['manage_widgets', 'view_widgets']])
            ->assertSessionHas('success');

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertEqualsCanonicalizing(['manage_widgets', 'view_widgets'], $role->fresh()->permissions->pluck('name')->all());
        $this->assertTrue($staff->fresh()->can('manage_widgets'));

        // Taking one away is as real as granting one.
        $this->actingAs($this->superAdmin())->put(route('super-admin.roles.update', $role), ['permissions' => ['view_widgets']]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame(['view_widgets'], $role->fresh()->permissions->pluck('name')->all());
        $this->assertFalse($staff->fresh()->can('manage_widgets'));
    }

    public function test_the_super_admin_role_itself_cannot_be_edited(): void
    {
        $this->permission('manage_widgets');
        $role = $this->makeRole('Super Admin');
        $before = $role->permissions->pluck('name')->all();

        $this->actingAs($this->superAdmin())->put(route('super-admin.roles.update', $role), ['permissions' => []])
            ->assertSessionHas('error', 'Cannot modify Super Admin role.');

        $this->assertSame($before, $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_an_unknown_permission_name_is_refused_and_nothing_changes(): void
    {
        $this->permission('view_widgets');
        $role = $this->makeRole('Accountant');
        $role->givePermissionTo('view_widgets');

        $this->actingAs($this->superAdmin())->put(route('super-admin.roles.update', $role), ['permissions' => ['view_widgets', 'does_not_exist']])
            ->assertSessionHasErrors('permissions.1');

        $this->assertSame(['view_widgets'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_a_society_admin_cannot_grant_themselves_or_anyone_a_permission(): void
    {
        $this->permission('manage_widgets');
        $role  = $this->makeRole('Admin');
        $admin = $this->makeUserWithRole($this->makeSociety(), 'Admin');

        $response = $this->actingAs($admin)->put(route('super-admin.roles.update', $role), ['permissions' => ['manage_widgets']]);

        $this->assertContains($response->getStatusCode(), [302, 403]);
        $this->assertSame([], $role->fresh()->permissions->pluck('name')->all());
        $this->assertFalse($admin->fresh()->can('manage_widgets'));
    }
}
