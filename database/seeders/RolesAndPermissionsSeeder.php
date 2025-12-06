<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // ###############################
        // permissions
        // ###############################
        $permissions = config("setup-config.permissions");

        // setup permissions
        foreach ($permissions as $permission) {
            if (!Permission::where([
                'name' => $permission,
                'guard_name' => 'api'
            ])->exists()) {
                Permission::create(['guard_name' => 'api', 'name' => $permission]);
            }
        }

        // setup roles
        $roles = config("setup-config.roles");
        foreach ($roles as $roleName) {
            Role::updateOrCreate(
                [
                    'name' => $roleName,
                    'guard_name' => 'api',
                ],
                [
                    'is_system_default' => 1,
                    'business_id' => null,
                    'is_default' => 1,
                    'is_default_for_business' => in_array($roleName, ['business_owner']) ? 1 : 0,
                ]
            );
        }

        // setup roles and permissions
        $role_permissions = config("setup-config.roles_permission");
        foreach ($role_permissions as $role_permission) {
            $role = Role::where(["name" => $role_permission["role"]])->first();

            $permissions = $role_permission["permissions"];

            // Get current permissions associated with the role
            $currentPermissions = $role->permissions()->pluck('name')->toArray();

            // Determine permissions to remove
            $permissionsToRemove = array_diff($currentPermissions, $permissions);

            // unassign permissions not included in the configuration
            if (!empty($permissionsToRemove)) {
                foreach ($permissionsToRemove as $permission) {
                    $role->revokePermissionTo($permission);
                }
            }

            // Assign permissions from the configuration
            $role->syncPermissions($permissions);
        }
    }
}
