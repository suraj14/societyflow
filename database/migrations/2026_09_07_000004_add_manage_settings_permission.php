<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Admin\EmailTemplateMappingController authorizes all five of its actions
 * against a 'manage_settings' permission that was never created. Spatie's
 * Gate::before only resolves real permission names, so the check fell
 * through to "no policy, no ability" and denied - making the email template
 * mapping screens return 403 for every role, Super Admin included.
 *
 * The permission is added to RolesAndPermissionsSeeder for fresh installs;
 * this migration is what gets it into databases that are already seeded.
 * Idempotent and additive: no existing permission or assignment is touched.
 */
return new class extends Migration
{
    private const PERMISSION = 'manage_settings';

    /** Roles that already hold access_settings and should hold this too. */
    private const ROLES = ['Super Admin', 'Admin'];

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name'       => self::PERMISSION,
            'guard_name' => 'web',
        ]);

        foreach (self::ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role && !$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
