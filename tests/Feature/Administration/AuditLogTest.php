<?php

use App\Domain\Access\Permission;
use App\Domain\Access\Role as RoleName;
use App\Domain\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('records the acting user, the change and the reason', function () {
    $user = User::factory()->create();
    $log = AuditLog::query()->create([
        'user_id' => $user->id,
        'action' => AuditAction::SETTINGS_UPDATED,
        'old_values' => ['business_name' => 'LoMaS'],
        'new_values' => ['business_name' => 'Sampaloc Credit'],
        'reason' => 'Business rename',
        'ip_address' => '127.0.0.1',
    ]);

    expect($log->user)->toBeInstanceOf(User::class)
        ->and($log->user->email)->toBe($user->email)
        ->and($log->old_values['business_name'])->toBe('LoMaS')
        ->and($log->reason)->toBe('Business rename')
        ->and($log->created_at)->not->toBeNull();
});

it('shows the audit log to a user with the audit permission', function () {
    $auditor = User::factory()->create();
    $auditor->givePermissionTo(Permission::AUDIT_VIEW);

    AuditLog::query()->create([
        'action' => AuditAction::SETTINGS_UPDATED,
        'new_values' => ['business_name' => 'Sampaloc Credit'],
        'created_at' => now(),
    ]);

    $this->actingAs($auditor)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administration/AuditLogs/Index')
            ->has('logs.data', 1)
            ->has('actions'),
        );
});

it('denies the audit log without the permission', function () {
    $officer = User::factory()->create();
    $officer->assignRole(RoleName::LOAN_OFFICER);

    $this->actingAs($officer)->get(route('audit-logs.index'))->assertForbidden();
});

it('filters the audit log by action and by user', function () {
    $auditor = User::factory()->create(['name' => 'Teresita Auditor']);
    $auditor->givePermissionTo(Permission::AUDIT_VIEW);

    $other = User::factory()->create();

    AuditLog::query()->create([
        'user_id' => $auditor->id,
        'action' => AuditAction::SETTINGS_UPDATED,
        'created_at' => now(),
    ]);

    AuditLog::query()->create([
        'user_id' => $other->id,
        'action' => AuditAction::ROLE_CREATED,
        'created_at' => now(),
    ]);

    $this->actingAs($auditor)
        ->get(route('audit-logs.index', ['action' => 'settings.']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', AuditAction::SETTINGS_UPDATED),
        );

    $this->actingAs($auditor)
        ->get(route('audit-logs.index', ['user' => 'Teresita']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logs.data', 1));

    $this->actingAs($auditor)
        ->get(route('audit-logs.index', ['user' => 'Nobody']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logs.data', 0));
});
