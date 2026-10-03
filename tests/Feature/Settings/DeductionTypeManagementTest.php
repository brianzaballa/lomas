<?php

use App\Domain\Access\Permission;
use App\Domain\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\LoanDeductionType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->attributes = [
        'name' => 'Processing Fee',
        'code' => 'PROCESSING',
        'description' => 'One time fee.',
        'calculation_method' => 'percentage_of_loan_amount',
        'rate' => '2',
        'is_active' => true,
        'sort_order' => 3,
    ];
});

it('keeps guests out of the deduction type settings', function () {
    $this->get(route('deduction-types.index'))->assertRedirect(route('login'));
    $this->post(route('deduction-types.store'), $this->attributes)->assertRedirect(route('login'));
});

it('hides deduction types from a user without the settings permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $this->actingAs($user)->get(route('deduction-types.index'))->assertForbidden();
    $this->actingAs($user)->post(route('deduction-types.store'), $this->attributes)->assertForbidden();
});

it('lists deduction types with a formatted fixed amount', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $passbook = LoanDeductionType::factory()->fixed('50')->create([
        'name' => 'Passbook',
        'sort_order' => 2,
    ]);
    $stamp = LoanDeductionType::factory()->create([
        'name' => 'Documentary Stamp',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('deduction-types.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/DeductionTypes')
            ->has('types', 2)
            // Ordered by sort order, then name.
            ->where('types.0.id', $stamp->id)
            ->where('types.0.rate_label', '1%')
            ->where('types.1.id', $passbook->id)
            ->where('types.1.fixed_amount_label', fn (string $label): bool => str_contains($label, '50.00'))
            ->where('currency_symbol', fn (string $symbol): bool => $symbol !== '')
        );
});

it('creates a percentage deduction type', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('deduction-types.store'), $this->attributes)
        ->assertRedirect(route('deduction-types.index'))
        ->assertSessionHasNoErrors();

    $type = LoanDeductionType::query()->sole();

    expect($type->name)->toBe('Processing Fee')
        ->and($type->code)->toBe('PROCESSING')
        ->and($type->calculation_method->value)->toBe('percentage_of_loan_amount')
        ->and($type->rate)->toBe('2.00')
        ->and($type->fixed_amount)->toBeNull()
        ->and($type->is_active)->toBeTrue()
        ->and($type->sort_order)->toBe(3);

    $log = AuditLog::query()
        ->where('action', AuditAction::DEDUCTION_TYPE_CREATED)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($log->new_values['code'])->toBe('PROCESSING');
});

it('creates a fixed deduction type and clears the rate', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('deduction-types.store'), [
            'name' => 'Passbook',
            'code' => 'PASSBOOK',
            'calculation_method' => 'fixed_amount',
            'fixed_amount' => '50',
            'is_active' => true,
            'sort_order' => 2,
        ])
        ->assertSessionHasNoErrors();

    $type = LoanDeductionType::query()->sole();

    expect($type->calculation_method->value)->toBe('fixed_amount')
        ->and($type->fixed_amount)->toBe('50.00')
        ->and($type->rate)->toBeNull();
});

it('stores a fixed amount as an exact decimal', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)->post(route('deduction-types.store'), [
        'name' => 'Insurance',
        'code' => 'INSURANCE',
        'calculation_method' => 'fixed_amount',
        'fixed_amount' => '123.455',
        'is_active' => true,
        'sort_order' => 4,
    ])->assertSessionHasNoErrors();

    expect(LoanDeductionType::query()->sole()->fixed_amount)->toBe('123.46');
});

it('requires a rate for a percentage deduction', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('deduction-types.store'), [...$this->attributes, 'rate' => null])
        ->assertSessionHasErrors('rate');

    expect(LoanDeductionType::query()->count())->toBe(0);
});

it('requires an amount for a fixed deduction', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('deduction-types.store'), [
            'name' => 'Passbook',
            'code' => 'PASSBOOK',
            'calculation_method' => 'fixed_amount',
            'is_active' => true,
            'sort_order' => 2,
        ])
        ->assertSessionHasErrors('fixed_amount');
});

it('rejects a value that does not belong to the calculation method', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('deduction-types.store'), [
            'name' => 'Passbook',
            'code' => 'PASSBOOK',
            'calculation_method' => 'fixed_amount',
            'fixed_amount' => '50',
            'rate' => '5',
            'is_active' => true,
            'sort_order' => 2,
        ])
        ->assertSessionHasErrors('rate');

    expect(LoanDeductionType::query()->count())->toBe(0);
});

it('rejects an unsupported calculation method or a duplicate code', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);
    LoanDeductionType::factory()->create(['code' => 'PROCESSING']);

    $this->actingAs($user)
        ->post(route('deduction-types.store'), [...$this->attributes, 'calculation_method' => 'per_installment'])
        ->assertSessionHasErrors('calculation_method');

    $this->actingAs($user)
        ->post(route('deduction-types.store'), $this->attributes)
        ->assertSessionHasErrors('code');

    expect(LoanDeductionType::query()->count())->toBe(1);
});

it('updates a deduction type and records the previous values', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $stamp = LoanDeductionType::factory()->create([
        'name' => 'Documentary Stamp',
        'code' => 'DOC_STAMP',
        'rate' => '1.00',
    ]);

    $this->actingAs($user)
        ->patch(route('deduction-types.update', $stamp), [
            ...$this->attributes,
            'code' => 'DOC_STAMP',
            'name' => 'Documentary Stamp',
            'rate' => '1.5',
        ])
        ->assertRedirect(route('deduction-types.index'))
        ->assertSessionHasNoErrors();

    expect($stamp->refresh()->rate)->toBe('1.50');

    $log = AuditLog::query()
        ->where('action', AuditAction::DEDUCTION_TYPE_UPDATED)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($log->old_values['rate'])->toBe('1.00')
        ->and($log->new_values['rate'])->toBe('1.50');
});

it('switches a deduction type to a fixed amount', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $type = LoanDeductionType::factory()->create([
        'name' => 'Insurance',
        'code' => 'INSURANCE',
        'rate' => '2.00',
    ]);

    $this->actingAs($user)
        ->patch(route('deduction-types.update', $type), [
            'name' => 'Insurance',
            'code' => 'INSURANCE',
            'calculation_method' => 'fixed_amount',
            'fixed_amount' => '75.50',
            'is_active' => true,
            'sort_order' => 5,
        ])
        ->assertSessionHasNoErrors();

    expect($type->refresh())
        ->calculation_method->value->toBe('fixed_amount')
        ->fixed_amount->toBe('75.50')
        ->rate->toBeNull();
});

it('does not let a user without the settings permission update a type', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $type = LoanDeductionType::factory()->create([
        'name' => 'Documentary Stamp',
        'rate' => '1.00',
    ]);

    $this->actingAs($user)
        ->patch(route('deduction-types.update', $type), [
            ...$this->attributes,
            'code' => $type->code,
            'rate' => '9',
        ])
        ->assertForbidden();

    expect($type->refresh()->rate)->toBe('1.00');
});
