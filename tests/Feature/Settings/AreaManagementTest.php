<?php

use App\Domain\Access\Permission;
use App\Models\Area;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('keeps guests and unauthorized users out of area settings', function () {
    $this->get(route('areas.index'))->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('areas.index'))->assertForbidden();
});

it('lists areas to a user with settings permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $area = Area::query()->create([
        'code' => 'MNL',
        'name' => 'Manila',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('areas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Areas')
            ->has('areas', 1)
            ->where('areas.0.id', $area->id)
        );
});

it('creates an area', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('areas.store'), [
            'code' => 'MNL',
            'name' => 'Manila',
            'is_active' => true,
        ])
        ->assertRedirect(route('areas.index'))
        ->assertSessionHasNoErrors();

    expect(Area::query()->sole()->code)->toBe('MNL');
});
