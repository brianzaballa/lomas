<?php

namespace App\Domain\Access;

use Spatie\Permission\Models\Role;

/**
 * Role persistence for the administration screens.
 *
 * Controllers stay thin by delegating every write to this service.
 */
final class RoleService
{
    /**
     * Create a role with the given permissions.
     *
     * @param  list<string>  $permissionNames
     */
    public function create(string $name, array $permissionNames): Role
    {
        $role = new Role(['name' => $name]);
        $role->save();

        $role->syncPermissions($permissionNames);

        return $role;
    }

    /**
     * Rename a role and replace its permissions.
     *
     * @param  list<string>  $permissionNames
     */
    public function update(Role $role, string $name, array $permissionNames): Role
    {
        $role->update(['name' => $name]);

        $role->syncPermissions($permissionNames);

        return $role;
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    /**
     * The permission codes currently assigned to the role.
     *
     * @return list<string>
     */
    public function permissionNames(Role $role): array
    {
        return array_values(
            $role->permissions
                ->pluck('name')
                ->map(static fn (mixed $name): string => (string) $name)
                ->all(),
        );
    }
}
