<?php

use App\Domain\Access\Permission;
use App\Domain\Access\Role as RoleName;
use App\Domain\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole(RoleName::ADMINISTRATOR);
});

it('lists the seeded roles with their permissions', function () {
    $this->actingAs($this->administrator)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/Roles/Index')
            ->has('roles', 5)
            ->has('permissionGroups')
            ->where('roles.0.name', RoleName::ADMINISTRATOR),
        );
});

it('gives the administrator every permission', function () {
    expect(Role::findByName(RoleName::ADMINISTRATOR)->permissions()->count())
        ->toBe(count(Permission::all()));
});

it('denies the role screens without the permission', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(RoleName::VIEWER);

    $this->actingAs($viewer)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($viewer)->post(route('roles.store'), [])->assertForbidden();
});

it('creates a role with the chosen permissions', function () {
    $this->actingAs($this->administrator)
        ->post(route('roles.store'), [
            'name' => 'Auditor',
            'permissions' => [Permission::AUDIT_VIEW, Permission::LOANS_VIEW],
        ])
        ->assertRedirect(route('roles.index'))
        ->assertSessionHasNoErrors();

    $role = Role::findByName('Auditor');

    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe([Permission::AUDIT_VIEW, Permission::LOANS_VIEW]);

    $log = AuditLog::query()->where('action', AuditAction::ROLE_CREATED)->firstOrFail();

    expect($log->new_values['name'])->toBe('Auditor');
});

it('allows a role without any permission', function () {
    $this->actingAs($this->administrator)
        ->post(route('roles.store'), ['name' => 'Read Only', 'permissions' => []])
        ->assertSessionHasNoErrors();

    expect(Role::findByName('Read Only')->permissions()->count())->toBe(0);
});

it('rejects a duplicate role name', function () {
    $this->actingAs($this->administrator)
        ->post(route('roles.store'), ['name' => RoleName::COLLECTOR, 'permissions' => []])
        ->assertSessionHasErrors('name');
});

it('rejects an unknown permission', function () {
    $this->actingAs($this->administrator)
        ->post(route('roles.store'), [
            'name' => 'Auditor',
            'permissions' => ['loans.explode'],
        ])
        ->assertSessionHasErrors('permissions.0');
});

it('updates the permissions of an existing role', function () {
    $this->actingAs($this->administrator)
        ->patch(route('roles.update', Role::findByName(RoleName::VIEWER)), [
            'name' => RoleName::VIEWER,
            'permissions' => [Permission::AUDIT_VIEW],
        ])
        ->assertRedirect(route('roles.index'))
        ->assertSessionHasNoErrors();

    expect(Role::findByName(RoleName::VIEWER)->permissions->pluck('name')->all())
        ->toBe([Permission::AUDIT_VIEW]);

    $log = AuditLog::query()->where('action', AuditAction::ROLE_UPDATED)->firstOrFail();

    expect($log->old_values['permissions'])->toContain(Permission::LOANS_VIEW);
});

it('deletes a role that is not assigned to anybody', function () {
    $this->actingAs($this->administrator)
        ->delete(route('roles.destroy', Role::findByName(RoleName::VIEWER)))
        ->assertRedirect(route('roles.index'));

    expect(Role::query()->where('name', RoleName::VIEWER)->exists())->toBeFalse();

    $log = AuditLog::query()->where('action', AuditAction::ROLE_DELETED)->firstOrFail();

    expect($log->old_values['name'])->toBe(RoleName::VIEWER)
        ->and($log->reason)->toContain(RoleName::VIEWER);
});

it('refuses to delete a role that is still assigned to a user', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(RoleName::VIEWER);

    $this->actingAs($this->administrator)
        ->delete(route('roles.destroy', Role::findByName(RoleName::VIEWER)))
        ->assertStatus(422);

    expect(Role::query()->where('name', RoleName::VIEWER)->exists())->toBeTrue();
});
