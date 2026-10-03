<?php

use App\Domain\Access\Permission;
use App\Domain\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\LoanProduct;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->attributes = [
        'name' => '50-Day Loan',
        'code' => 'LOAN-50D',
        'description' => 'Fifty valid collection days.',
        'term' => 50,
        'term_unit' => 'collection_days',
        'payment_frequency' => 'daily',
        'exclude_sundays' => true,
        'exclude_saturdays' => false,
        'release_date_is_day_one' => false,
        'interest_method' => 'percentage_of_loan_amount',
        'interest_rate' => '10',
        'is_active' => true,
    ];
});

it('keeps guests out of the loan product settings', function () {
    $this->get(route('loan-products.index'))->assertRedirect(route('login'));
    $this->post(route('loan-products.store'), $this->attributes)->assertRedirect(route('login'));
});

it('hides loan products from a user without the settings permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $this->actingAs($user)->get(route('loan-products.index'))->assertForbidden();
    $this->actingAs($user)->post(route('loan-products.store'), $this->attributes)->assertForbidden();
});

it('lists loan products to a user with the settings permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $product = LoanProduct::factory()->create(['name' => '100-Day Loan']);

    $this->actingAs($user)
        ->get(route('loan-products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/LoanProducts')
            ->has('products', 1)
            ->where('products.0.id', $product->id)
            ->has('interestMethods', 1)
            ->where('interestMethods.0.value', 'percentage_of_loan_amount')
            ->where('interestMethods.0.label', 'Percentage of loan amount')
        );
});

it('creates a loan product and records it in the audit trail', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('loan-products.store'), $this->attributes)
        ->assertRedirect(route('loan-products.index'))
        ->assertSessionHasNoErrors();

    $product = LoanProduct::query()->sole();

    expect($product->name)->toBe('50-Day Loan')
        ->and($product->code)->toBe('LOAN-50D')
        ->and($product->term)->toBe(50)
        ->and($product->term_unit->value)->toBe('collection_days')
        ->and($product->payment_frequency->value)->toBe('daily')
        ->and($product->exclude_sundays)->toBeTrue()
        ->and($product->exclude_saturdays)->toBeFalse()
        ->and($product->release_date_is_day_one)->toBeFalse()
        ->and($product->interest_method->value)->toBe('percentage_of_loan_amount')
        ->and($product->interest_rate)->toBe('10.00')
        ->and($product->is_active)->toBeTrue();

    $log = AuditLog::query()
        ->where('action', AuditAction::LOAN_PRODUCT_CREATED)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($log->auditable_type)->toBe(LoanProduct::class)
        ->and($log->auditable_id)->toBe($product->id)
        ->and($log->new_values['code'])->toBe('LOAN-50D');
});

it('stores the interest rate as an exact decimal', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)->post(route('loan-products.store'), [
        ...$this->attributes,
        'interest_rate' => '7.125',
    ])->assertSessionHasNoErrors();

    expect(LoanProduct::query()->sole()->interest_rate)->toBe('7.13');
});

it('upper cases the code and trims an empty description', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)->post(route('loan-products.store'), [
        ...$this->attributes,
        'code' => 'loan-60d',
        'description' => '   ',
    ])->assertSessionHasNoErrors();

    expect(LoanProduct::query()->sole())
        ->code->toBe('LOAN-60D')
        ->description->toBeNull();
});

it('rejects a duplicate product code', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);
    LoanProduct::factory()->create(['code' => 'LOAN-50D']);

    $this->actingAs($user)
        ->post(route('loan-products.store'), $this->attributes)
        ->assertSessionHasErrors('code');

    expect(LoanProduct::query()->count())->toBe(1);
});

it('rejects an unsupported term unit or payment frequency', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('loan-products.store'), [...$this->attributes, 'term_unit' => 'weeks'])
        ->assertSessionHasErrors('term_unit');

    $this->actingAs($user)
        ->post(route('loan-products.store'), [...$this->attributes, 'payment_frequency' => 'weekly'])
        ->assertSessionHasErrors('payment_frequency');

    expect(LoanProduct::query()->count())->toBe(0);
});

it('rejects an out of range term or interest rate', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)
        ->post(route('loan-products.store'), [...$this->attributes, 'term' => 0])
        ->assertSessionHasErrors('term');

    $this->actingAs($user)
        ->post(route('loan-products.store'), [...$this->attributes, 'interest_rate' => 150])
        ->assertSessionHasErrors('interest_rate');

    expect(LoanProduct::query()->count())->toBe(0);
});

it('updates a loan product and records the previous values', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $product = LoanProduct::factory()->create([
        'name' => '100-Day Loan',
        'code' => 'LOAN-100D',
        'interest_rate' => '10.00',
    ]);

    $this->actingAs($user)
        ->patch(route('loan-products.update', $product), [
            ...$this->attributes,
            'name' => '100-Day Loan',
            'code' => 'LOAN-100D',
            'term' => 100,
            'interest_rate' => '12',
            'exclude_saturdays' => true,
        ])
        ->assertRedirect(route('loan-products.index'))
        ->assertSessionHasNoErrors();

    expect($product->refresh())
        ->interest_rate->toBe('12.00')
        ->exclude_saturdays->toBeTrue();

    $log = AuditLog::query()
        ->where('action', AuditAction::LOAN_PRODUCT_UPDATED)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($log->old_values['interest_rate'])->toBe('10.00')
        ->and($log->new_values['interest_rate'])->toBe('12.00')
        ->and($log->old_values['exclude_saturdays'])->toBeFalse()
        ->and($log->new_values['exclude_saturdays'])->toBeTrue();
});

it('allows a product to keep its own code when renamed', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $product = LoanProduct::factory()->create(['code' => 'LOAN-100D']);

    $this->actingAs($user)
        ->patch(route('loan-products.update', $product), [
            ...$this->attributes,
            'code' => 'LOAN-100D',
            'name' => '100 Collection Days',
        ])
        ->assertSessionHasNoErrors();

    expect($product->refresh()->name)->toBe('100 Collection Days');
});

it('writes no audit entry when an update changes nothing', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $product = LoanProduct::factory()->create([
        'name' => '100-Day Loan',
        'code' => 'LOAN-100D',
        'term' => 100,
        'interest_rate' => '10.00',
        'exclude_saturdays' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('loan-products.update', $product), [
            'name' => '100-Day Loan',
            'code' => 'LOAN-100D',
            'description' => null,
            'term' => 100,
            'term_unit' => 'collection_days',
            'payment_frequency' => 'daily',
            'exclude_sundays' => true,
            'release_date_is_day_one' => false,
            'interest_method' => 'percentage_of_loan_amount',
            'interest_rate' => '10.00',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(AuditLog::query()
        ->where('action', AuditAction::LOAN_PRODUCT_UPDATED)
        ->count()
    )->toBe(0);
});

it('does not let a user without the settings permission update a product', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $product = LoanProduct::factory()->create(['name' => '100-Day Loan']);

    $this->actingAs($user)
        ->patch(route('loan-products.update', $product), [
            ...$this->attributes,
            'name' => 'Renamed',
        ])
        ->assertForbidden();

    expect($product->refresh()->name)->toBe('100-Day Loan');
});
