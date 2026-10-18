<?php

use App\Domain\Access\Permission;
use App\Domain\Audit\AuditAction;
use App\Domain\Settings\SystemSettings;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->settings = app(SystemSettings::class);
});

it('creates the settings row with the documented defaults', function () {
    $settings = $this->settings->get();

    expect($settings->business_name)->toBe('LoMaS')
        ->and($settings->currency)->toBe('PHP')
        ->and($settings->loan_number_format)->toBe('LN-{node}-{year}-{seq:6}')
        ->and($settings->borrower_number_format)->toBe('BR-{node}-{seq:6}')
        ->and($settings->ncr_period_mode)->toBe(SystemSetting::NCR_PERIOD_MODE_WEEKLY);
});

it('is limited to a single settings row', function () {
    $this->settings->get();

    expect(SystemSetting::query()->count())->toBe(1);
});

it('formats money for display without floating point drift', function () {
    expect($this->settings->formatMoney(Money::of('8850.50')))->toContain('8,850.50')
        ->and($this->settings->formatMoney(Money::of('1234567.89')))->toContain('1,234,567.89')
        ->and($this->settings->formatMoney(Money::of('-500.25')))->toStartWith('-');
});

it('shows the settings screen to an administrator', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->get(route('system-settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/System'));
});

it('hides the settings screen from a user without the permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $this->actingAs($user)->get(route('system-settings.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('system-settings.update'), [])->assertForbidden();
});

it('updates the settings and records the change in the audit trail', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->put(route('system-settings.update'), [
            'business_name' => 'Sampaloc Credit',
            'business_address' => '123 Rizal Street, Manila',
            'contact_email' => ' loans@sampaloc.test',
            'contact_phone' => '+63 2 8123 4567',
            'currency' => 'PHP',
            'currency_symbol' => 'PHP',
            'loan_number_format' => 'LN-{node}-{year}-{seq:6}',
            'borrower_number_format' => 'BR-{node}-{seq:6}',
            'ncr_period_mode' => 'weekly',
            'reason' => 'Renamed the business after registration.',
        ])
        ->assertRedirect(route('system-settings.edit'))
        ->assertSessionHasNoErrors();

    expect($this->settings->get()->business_name)->toBe('Sampaloc Credit');

    $log = AuditLog::query()
        ->where('action', AuditAction::SETTINGS_UPDATED)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($log->reason)->toBe('Renamed the business after registration.')
        ->and($log->old_values['business_name'])->toBe('LoMaS')
        ->and($log->new_values['business_name'])->toBe('Sampaloc Credit');
});

it('rejects a number format without a sequence placeholder', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->put(route('system-settings.update'), [
            'business_name' => 'LoMaS',
            'currency' => 'PHP',
            'currency_symbol' => 'PHP',
            'loan_number_format' => 'LN-2026',
            'borrower_number_format' => 'BR-{node}-{seq:6}',
            'ncr_period_mode' => 'weekly',
        ])
        ->assertSessionHasErrors('loan_number_format');

    expect($this->settings->get()->loan_number_format)->toBe('LN-{node}-{year}-{seq:6}');
});

it('rejects a number format without the node code because two offline nodes would collide', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->put(route('system-settings.update'), [
            'business_name' => 'LoMaS',
            'currency' => 'PHP',
            'currency_symbol' => 'PHP',
            'loan_number_format' => 'LN-{year}-{seq:6}',
            'borrower_number_format' => 'BR-{node}-{seq:6}',
            'ncr_period_mode' => 'weekly',
        ])
        ->assertSessionHasErrors('loan_number_format');

    expect($this->settings->get()->loan_number_format)->toBe('LN-{node}-{year}-{seq:6}');
});

it('updates the ncr period mode and rejects an unknown one', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $payload = [
        'business_name' => 'LoMaS',
        'currency' => 'PHP',
        'currency_symbol' => 'PHP',
        'loan_number_format' => 'LN-{node}-{year}-{seq:6}',
        'borrower_number_format' => 'BR-{node}-{seq:6}',
    ];

    $this->actingAs($user)
        ->put(route('system-settings.update'), $payload + ['ncr_period_mode' => 'fortnightly'])
        ->assertSessionHasErrors('ncr_period_mode');

    $this->actingAs($user)
        ->put(route('system-settings.update'), $payload + ['ncr_period_mode' => 'decile'])
        ->assertSessionHasNoErrors();

    expect($this->settings->ncrPeriodMode())->toBe(SystemSetting::NCR_PERIOD_MODE_DECILE);
});
