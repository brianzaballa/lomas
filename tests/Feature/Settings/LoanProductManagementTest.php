<?php

use App\Actions\LoanProducts\SyncLoanProductDeductions;
use App\Domain\Access\Permission;
use App\Domain\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\LoanDeductionType;
use App\Models\LoanProduct;
use App\Models\LoanProductDeductionType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

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

it('links the deduction types a product charges, in the order given', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $docStamp = LoanDeductionType::factory()->create(['name' => 'Documentary Stamp', 'code' => 'DOC_STAMP', 'sort_order' => 1]);
    $passbook = LoanDeductionType::factory()->fixed('50.00')->create(['name' => 'Passbook', 'code' => 'PASSBOOK', 'sort_order' => 2]);

    $this->actingAs($user)->post(route('loan-products.store'), [
        ...$this->attributes,
        'deduction_type_ids' => [$passbook->id, $docStamp->id],
    ])->assertSessionHasNoErrors();

    $product = LoanProduct::query()->sole();

    expect($product->deductionTypes()
        ->orderBy('loan_product_deduction_types.sort_order')
        ->pluck('loan_deduction_types.code')
        ->all()
    )->toBe(['PASSBOOK', 'DOC_STAMP'])
        ->and($product->deductionTypes()
            ->orderBy('loan_product_deduction_types.sort_order')
            ->pluck('loan_product_deduction_types.sort_order')
            ->all()
        )->toBe([1, 2]);

    $log = AuditLog::query()
        ->where('action', AuditAction::LOAN_PRODUCT_CREATED)
        ->firstOrFail();

    expect($log->new_values['deduction_codes'])->toBe(['PASSBOOK', 'DOC_STAMP']);
});

it('creates a product with no deductions when none are chosen', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)->post(route('loan-products.store'), $this->attributes)
        ->assertSessionHasNoErrors();

    expect(LoanProduct::query()->sole()->deductionTypes)->toHaveCount(0);
});

it('rejects a deduction type that does not exist', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $this->actingAs($user)->post(route('loan-products.store'), [
        ...$this->attributes,
        'deduction_type_ids' => [(string) Str::uuid()],
    ])->assertSessionHasErrors('deduction_type_ids.0');

    expect(LoanProduct::query()->count())->toBe(0);
});

it('unlinks a deduction type that is no longer chosen and audits the change', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $docStamp = LoanDeductionType::factory()->create(['code' => 'DOC_STAMP']);
    $insurance = LoanDeductionType::factory()->create(['code' => 'INSURANCE']);

    $product = LoanProduct::factory()->create(['name' => '100-Day Loan', 'code' => 'LOAN-100D']);
    app(SyncLoanProductDeductions::class)->handle($product, [$docStamp->id, $insurance->id]);

    $this->actingAs($user)->patch(route('loan-products.update', $product), [
        ...$this->attributes,
        'code' => 'LOAN-100D',
        'term' => 100,
        'deduction_type_ids' => [$insurance->id],
    ])->assertSessionHasNoErrors();

    expect($product->deductionTypes()->pluck('loan_deduction_types.code')->all())
        ->toBe(['INSURANCE'])
        ->and(LoanProductDeductionType::query()->where('loan_product_id', $product->id)->count())
        ->toBe(1);

    $log = AuditLog::query()
        ->where('action', AuditAction::LOAN_PRODUCT_UPDATED)
        ->firstOrFail();

    expect($log->old_values['deduction_codes'])->toBe(['DOC_STAMP', 'INSURANCE'])
        ->and($log->new_values['deduction_codes'])->toBe(['INSURANCE']);
});

it('unlinks every deduction when a product is saved with none chosen', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $product = LoanProduct::factory()->create(['name' => '100-Day Loan', 'code' => 'LOAN-100D']);
    app(SyncLoanProductDeductions::class)->handle($product, [
        LoanDeductionType::factory()->create(['code' => 'DOC_STAMP'])->id,
    ]);

    // An unticked box is not submitted, so the form posts no ids at all. The
    // links must still be cleared, not left behind by an empty filter.
    $this->actingAs($user)->patch(route('loan-products.update', $product), [
        ...$this->attributes,
        'code' => 'LOAN-100D',
        'term' => 100,
    ])->assertSessionHasNoErrors();

    expect($product->deductionTypes)->toHaveCount(0)
        ->and(LoanProductDeductionType::query()->where('loan_product_id', $product->id)->count())
        ->toBe(0);
});

it('offers every deduction type in the product settings', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::SETTINGS_MANAGE);

    $docStamp = LoanDeductionType::factory()->create(['name' => 'Documentary Stamp', 'sort_order' => 1]);
    $passbook = LoanDeductionType::factory()->fixed('50.00')->create(['name' => 'Passbook', 'sort_order' => 2]);

    $product = LoanProduct::factory()->create();
    app(SyncLoanProductDeductions::class)->handle($product, [$docStamp->id]);

    $this->actingAs($user)
        ->get(route('loan-products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/LoanProducts')
            ->has('deductionTypes', 2)
            ->where('deductionTypes.0.id', $docStamp->id)
            ->where('deductionTypes.1.id', $passbook->id)
            ->where('products.0.deduction_type_ids', [$docStamp->id])
            ->where('products.0.deduction_labels', ['Documentary Stamp ('.$docStamp->code.')'])
        );
});
