<?php

namespace App\Http\Controllers\Administration;

use App\Domain\Access\Permission as PermissionName;
use App\Domain\Access\Role as RoleName;
use App\Domain\Access\RoleService;
use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\RoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly RoleService $roles,
    ) {}

    /**
     * List the roles and their permissions.
     */
    public function index(): Response
    {
        $this->authorize(PermissionName::ROLES_MANAGE);

        return Inertia::render('administration/Roles/Index', [
            'roles' => Role::query()
                ->with('permissions')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $this->roles->permissionNames($role),
                    'users_count' => $role->users()->count(),
                ])
                ->values()
                ->all(),
            'permissionGroups' => PermissionName::grouped(),
            'permissionLabels' => PermissionName::labels(),
            'defaultRoles' => RoleName::all(),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(RoleRequest $request): RedirectResponse
    {
        $permissions = $request->validated('permissions', []);

        $role = DB::transaction(function () use ($request, $permissions): Role {
            $role = $this->roles->create($request->validated('name'), $permissions);

            $this->audit->recordCreated(AuditAction::ROLE_CREATED, $role, [
                'name' => $role->name,
                'permissions' => $permissions,
            ]);

            return $role;
        });

        $this->success("Role [{$role->name}] created.");

        return to_route('roles.index');
    }

    /**
     * Update an existing role.
     */
    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $permissions = $request->validated('permissions', []);

        DB::transaction(function () use ($request, $role, $permissions): void {
            $old = [
                'name' => $role->name,
                'permissions' => $this->roles->permissionNames($role),
            ];

            $role = $this->roles->update($role, $request->validated('name'), $permissions);

            $this->audit->recordUpdated(AuditAction::ROLE_UPDATED, $role, $old, [
                'name' => $role->name,
                'permissions' => $permissions,
            ]);
        });

        $this->success("Role [{$role->name}] updated.");

        return to_route('roles.index');
    }

    /**
     * Delete a role that is not assigned to any user.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize(PermissionName::ROLES_MANAGE);

        abort_if($role->users()->exists(), 422, 'Reassign the users of this role before deleting it.');

        DB::transaction(function () use ($role): void {
            $attributes = ['name' => $role->name, 'permissions' => $this->roles->permissionNames($role)];

            $name = $role->name;
            $this->roles->delete($role);

            $this->audit->record(AuditAction::ROLE_DELETED, reason: "Role [{$name}] deleted.", oldValues: $attributes);
        });

        $this->success('Role deleted.');

        return to_route('roles.index');
    }
}
