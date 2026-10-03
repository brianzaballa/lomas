<?php

use App\Domain\Access\Role as RoleName;
use App\Domain\Audit\AuditAction;
use App\Domain\Nodes\NodeIdentity;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists the users and the assignable roles to an administrator', function () {
    $administrator = User::factory()->create(['name' => 'System Administrator']);
    $administrator->assignRole(RoleName::ADMINISTRATOR);

    User::factory()->create(['name' => 'Rosario Collector']);

    $this->actingAs($administrator)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/Users/Index')
            ->has('users.data', 2)
            ->where('filters.search', null),
        );

    $this->actingAs($administrator)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/Users/Create')
            ->has('roles', 5),
        );

    $this->actingAs($administrator)
        ->get(route('users.index', ['search' => 'Rosario']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Rosario Collector'),
        );
});

it('searches users by name and email', function () {
    $administrator = User::factory()->create(['name' => 'System Administrator']);
    $administrator->assignRole(RoleName::ADMINISTRATOR);

    User::factory()->create(['name' => 'Rosario Collector']);
    User::factory()->create(['name' => 'Emilio_encoder']);

    $this->actingAs($administrator)
        ->get(route('users.index', ['search' => 'emilio']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Emilio_encoder'),
        );
});

it('denies the user screens without the permission', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::VIEWER);

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    $this->actingAs($user)->get(route('users.create'))->assertForbidden();
    $this->actingAs($user)->post(route('users.store'), [])->assertForbidden();
});

it('creates a user with the selected roles and audits the change', function () {
    $administrator = User::factory()->create(['name' => 'System Administrator']);
    $administrator->assignRole(RoleName::ADMINISTRATOR);

    $this->actingAs($administrator)
        ->post(route('users.store'), [
            'name' => 'Amparo Encoder',
            'email' => 'amparo@lomas.test',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'roles' => [RoleName::ENCODER],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $created = User::query()->where('email', 'amparo@lomas.test')->firstOrFail();

    expect($created->hasRole(RoleName::ENCODER))->toBeTrue()
        ->and($created->hasVerifiedEmail())->toBeTrue()
        ->and($created->role_names)->toBe([RoleName::ENCODER]);

    $log = AuditLog::query()->where('action', AuditAction::USER_CREATED)->firstOrFail();

    // The morph pointer is text, because audited domain records are keyed by
    // uuid while users and roles are node local and keyed by integer.
    expect($log->auditable_type)->toBe((new User)->getMorphClass())
        ->and($log->auditable_id)->toBe((string) $created->id)
        ->and($log->user_id)->toBe($administrator->id)
        ->and($log->node_id)->toBe(app(NodeIdentity::class)->id())
        ->and($log->user_label)->toBe(config('lomas.node.code').' - '.$administrator->email);
});

it('validates the user form', function () {
    $administrator = User::factory()->create(['name' => 'System Administrator']);
    $administrator->assignRole(RoleName::ADMINISTRATOR);

    $this->actingAs($administrator)
        ->post(route('users.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'short',
            'roles' => ['Unknown Role'],
        ])
        ->assertSessionHasErrors(['name', 'email', 'password', 'roles.0']);
});

it('updates a user and records the roles that changed', function () {
    $administrator = User::factory()->create(['name' => 'System Administrator']);
    $administrator->assignRole(RoleName::ADMINISTRATOR);

    $encoder = User::factory()->create(['name' => 'Amparo Encoder']);
    $encoder->assignRole(RoleName::ENCODER);

    $this->actingAs($administrator)
        ->patch(route('users.update', $encoder), [
            'name' => 'Amparo Lopez',
            'email' => $encoder->email,
            'roles' => [RoleName::COLLECTOR],
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $encoder->refresh();

    expect($encoder->name)->toBe('Amparo Lopez')
        ->and($encoder->role_names)->toBe([RoleName::COLLECTOR]);

    expect(AuditLog::query()->where('action', AuditAction::USER_UPDATED)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', AuditAction::USER_ROLES_UPDATED)->exists())->toBeTrue();
});

it('keeps a password when the password field is left empty', function () {
    $administrator = User::factory()->create(['name' => 'System Administrator']);
    $administrator->assignRole(RoleName::ADMINISTRATOR);

    $encoder = User::factory()->create();
    $encoder->assignRole(RoleName::ENCODER);
    $originalPassword = $encoder->password;

    $this->actingAs($administrator)
        ->patch(route('users.update', $encoder), [
            'name' => $encoder->name,
            'email' => $encoder->email,
            'roles' => [RoleName::ENCODER],
        ])
        ->assertSessionHasNoErrors();

    expect($encoder->refresh()->password)->toBe($originalPassword);
});

it('refuses to remove the own access to user management', function () {
    $administrator = User::factory()->create(['name' => 'System Administrator']);
    $administrator->assignRole(RoleName::ADMINISTRATOR);

    $this->actingAs($administrator)
        ->patch(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'roles' => [RoleName::VIEWER],
        ])
        ->assertSessionHasErrors('roles');

    expect($administrator->refresh()->hasRole(RoleName::ADMINISTRATOR))->toBeTrue();
});
