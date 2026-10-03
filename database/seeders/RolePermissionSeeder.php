<?php

namespace Database\Seeders;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the permissions and the initial roles.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::all() as $permission) {
            PermissionModel::findOrCreate($permission, 'web');
        }

        foreach (Role::defaultPermissions() as $name => $permissions) {
            RoleModel::findOrCreate($name, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
