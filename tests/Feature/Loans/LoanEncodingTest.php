<?php

use App\Domain\Access\Permission;
use App\Enums\BorrowerStatus;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanDeductionType;
use App\Models\LoanProduct;
use App\Models\User;
use Database\Seeders\LoanConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(LoanConfigurationSeeder::class);
});

it('shows the new loan screen to an authorized encoder', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $this->actingAs($user)
        ->get(route('loans.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Create')
            ->has('products', 1)
        );
});

it('preselects the borrower and product when encoding is refreshed', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);
    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->get(route('loans.create', [
            'search' => $borrower->borrower_number,
            'product' => $product->id,
            'amount' => '10000.00',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Create')
            ->where('selectedBorrowerId', null)
            ->where('search', $borrower->borrower_number)
            ->where('selectedProductId', $product->id)
            ->where('grossLoanAmount', '10000.00')
            ->has('preview')
        );
});

it('searches borrowers on the loan create screen', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $borrower = Borrower::factory()->create([
        'status' => BorrowerStatus::Active,
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
    ]);

    $this->actingAs($user)
        ->get(route('loans.create', ['search' => 'Ana']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Create')
            ->has('borrowers', 1)
            ->where('borrowers.0.id', $borrower->id)
        );
});

it('opens loan encoding from the borrower profile with the borrower preselected', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::BORROWERS_VIEW);
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);

    $this->actingAs($user)
        ->get(route('borrowers.show', $borrower))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('borrowers/Show')
            ->where('borrower.create_loan_url', route('loans.create', ['borrower' => $borrower->id]))
        );

    $this->actingAs($user)
        ->get(route('loans.create', ['borrower' => $borrower->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Create')
            ->where('selectedBorrowerId', $borrower->id)
        );
});

it('recalculates the preview on demand without re-rendering the screen', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
        ]))
        ->assertOk()
        ->assertJson([
            'gross_loan_amount' => '10000.00',
            'interest_amount' => '1000.00',
            'other_deductions_amount' => '150.00',
            'deductions_amount' => '1150.00',
            'net_proceeds_amount' => '8850.00',
            'total_amount_payable' => '10000.00',
            'daily_amortization' => '100.00',
            'collection_days' => 100,
        ]);
});

it('breaks the preview down into every deduction the product charges', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $preview = $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
        ]))
        ->assertOk()
        ->json();

    expect(array_column($preview['deductions'], 'code'))->toBe(['INTEREST', 'DOC_STAMP', 'PASSBOOK'])
        ->and(array_column($preview['deductions'], 'amount'))->toBe(['1000.00', '100.00', '50.00'])
        ->and($preview['deductions'][0]['deduction_type_id'])->toBeNull()
        ->and($preview['deductions'][1]['deduction_type_id'])->not->toBeNull()
        ->and(array_column($preview['deductions'], 'is_toggleable'))->toBe([false, true, true]);
});

it('leaves a deduction the encoder switched off out of the totals', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $preview = $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
            'excluded_deduction_codes' => ['PASSBOOK'],
        ]))
        ->assertOk()
        ->json();

    expect($preview['other_deductions_amount'])->toBe('100.00')
        ->and($preview['deductions_amount'])->toBe('1100.00')
        ->and($preview['net_proceeds_amount'])->toBe('8900.00')
        // Still returned, so the encoder can switch it back on.
        ->and(array_column($preview['deductions'], 'is_applied'))->toBe([true, true, false]);
});

it('adds a custom charge to a single loan', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $preview = $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
            'custom_deductions' => [['name' => 'Insurance', 'amount' => '250.00']],
        ]))
        ->assertOk()
        ->json();

    $custom = end($preview['deductions']);

    expect($custom)->toMatchArray([
        'name' => 'Insurance',
        'amount' => '250.00',
        'deduction_type_id' => null,
        'code' => null,
        'is_custom' => true,
        'is_applied' => true,
    ])->and($preview['other_deductions_amount'])->toBe('400.00')
        ->and($preview['deductions_amount'])->toBe('1400.00')
        ->and($preview['net_proceeds_amount'])->toBe('8600.00');
});

it('applies an interest override to the recalculated preview', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
            'interest_override_amount' => '750.00',
        ]))
        ->assertOk()
        ->assertJson([
            'gross_loan_amount' => '10000.00',
            'interest_amount' => '750.00',
            'other_deductions_amount' => '150.00',
            'deductions_amount' => '900.00',
            'net_proceeds_amount' => '9100.00',
            'total_amount_payable' => '10000.00',
            'daily_amortization' => '100.00',
        ]);
});

it('applies an interest override when re-rendering the encoding screen', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->get(route('loans.create', [
            'product' => $product->id,
            'amount' => '10000.00',
            'interest_override_amount' => '750.00',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Create')
            ->where('interestOverrideAmount', '750.00')
            ->where('preview.interest_amount', '750.00')
            ->where('preview.net_proceeds_amount', '9100.00')
        );
});

it('restores the deduction selection when the encoding screen is re-rendered', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    // A search re-renders the screen, so what the encoder switched off and
    // typed has to survive the round trip.
    $this->actingAs($user)
        ->get(route('loans.create', [
            'product' => $product->id,
            'amount' => '10000.00',
            'excluded_deduction_codes' => ['PASSBOOK'],
            'custom_deductions' => [['name' => 'Insurance', 'amount' => '250.00']],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Create')
            ->where('excludedDeductionCodes', ['PASSBOOK'])
            ->where('customDeductions.0.name', 'Insurance')
            ->where('customDeductions.0.amount', '250.00')
            // Interest 1,000 + doc stamp 100 + insurance 250.
            ->where('preview.other_deductions_amount', '350.00')
            ->where('preview.deductions_amount', '1350.00')
            ->where('preview.net_proceeds_amount', '8650.00')
        );
});

it('does not charge a deduction type that is globally inactive', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    // Retiring a type stops new loans from charging it. A loan already
    // encoded keeps its own snapshot, which is why the rate itself is left
    // alone here.
    LoanDeductionType::query()->where('code', 'PASSBOOK')->update(['is_active' => false]);

    $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
        ]))
        ->assertOk()
        ->assertJsonPath('other_deductions_amount', '100.00')
        ->assertJsonPath('deductions_amount', '1100.00')
        ->assertJsonPath('net_proceeds_amount', '8900.00');
});

it('shows the preview the encoder is about to save', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);
    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $preview = $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
            'interest_override_amount' => '750.00',
            'excluded_deduction_codes' => ['PASSBOOK'],
            'custom_deductions' => [['name' => 'Insurance', 'amount' => '250.00']],
        ]))
        ->assertOk()
        ->json();

    $this->actingAs($user)->post(route('loans.store'), [
        'borrower_id' => $borrower->id,
        'loan_product_id' => $product->id,
        'application_date' => '2026-10-03',
        'gross_loan_amount' => '10000.00',
        'interest_override_amount' => '750.00',
        'excluded_deduction_codes' => ['PASSBOOK'],
        'custom_deductions' => [['name' => 'Insurance', 'amount' => '250.00']],
    ])->assertRedirect();

    $loan = Loan::query()->sole();

    expect($loan->interest_amount)->toBe($preview['interest_amount'])
        ->and($loan->deductions_amount)->toBe($preview['deductions_amount'])
        ->and($loan->net_proceeds_amount)->toBe($preview['net_proceeds_amount'])
        ->and($loan->total_amount_payable)->toBe($preview['total_amount_payable'])
        ->and($loan->gross_loan_amount)->toBe($preview['gross_loan_amount']);
});

it('snapshots every applied deduction onto the loan', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);
    $docStamp = LoanDeductionType::query()->where('code', 'DOC_STAMP')->firstOrFail();

    $this->actingAs($user)->post(route('loans.store'), [
        'borrower_id' => $borrower->id,
        'loan_product_id' => LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail()->id,
        'application_date' => '2026-10-03',
        'gross_loan_amount' => '10000.00',
        'excluded_deduction_codes' => ['PASSBOOK'],
        'custom_deductions' => [['name' => 'Insurance', 'amount' => '250.00']],
    ])->assertRedirect();

    $loan = Loan::query()->sole();
    $deductions = $loan->deductions()->get();

    // The passbook row is absent, which is how "not applied" is recorded.
    expect(array_column($deductions->pluck('code')->all(), null, null))->toHaveCount(3)
        ->and($deductions->pluck('code')->all())->toBe(['INTEREST', 'DOC_STAMP', null]);

    $docStampRow = $deductions->firstWhere('code', 'DOC_STAMP');

    expect($docStampRow->deduction_type_id)->toBe($docStamp->id)
        ->and($docStampRow->name)->toBe('Documentary Stamp')
        ->and($docStampRow->rate)->toBe('1.00')
        ->and($docStampRow->base_amount)->toBe('10000.00')
        ->and($docStampRow->amount)->toBe('100.00')
        ->and($docStampRow->node_id)->toBe($loan->node_id);

    $interest = $deductions->firstWhere('code', 'INTEREST');

    expect($interest->is_interest)->toBeTrue()
        ->and($interest->deduction_type_id)->toBeNull()
        ->and($interest->amount)->toBe('1000.00');

    $insurance = $deductions->firstWhere('name', 'Insurance');

    // A custom charge is recorded as "belongs to no deduction type, and is not
    // interest". That is what makes it distinguishable after the fact.
    expect($insurance->deduction_type_id)->toBeNull()
        ->and($insurance->is_interest)->toBeFalse()
        ->and($insurance->amount)->toBe('250.00')
        ->and($insurance->fixed_amount)->toBe('250.00');
});

it('keeps a loan unchanged when the deduction configuration later changes', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);

    $this->actingAs($user)->post(route('loans.store'), [
        'borrower_id' => $borrower->id,
        'loan_product_id' => LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail()->id,
        'application_date' => '2026-10-03',
        'gross_loan_amount' => '10000.00',
    ])->assertRedirect();

    $loan = Loan::query()->sole();

    LoanDeductionType::query()->where('code', 'DOC_STAMP')->update([
        'rate' => '2.50',
        'fixed_amount' => '75.00',
    ]);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    expect($loan->deductions_amount)->toBe('1150.00')
        ->and($loan->deductions()->where('code', 'DOC_STAMP')->sole()->amount)->toBe('100.00')
        ->and($product->activeDeductionTypes()->firstWhere('code', 'DOC_STAMP')->rate)->toBe('2.50');
});

it('rejects a preview request that carries an unusable amount', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => 'not-a-number',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('gross_loan_amount');
});

it('rejects a negative interest override', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
            'interest_override_amount' => '-1.00',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('interest_override_amount');
});

it('keeps the preview endpoint closed to users without the loan create permission', function () {
    $user = User::factory()->create();

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('loans.preview', [
            'loan_product_id' => $product->id,
            'gross_loan_amount' => '10000.00',
        ]))
        ->assertForbidden();
});

it('stores a draft loan with preview values recalculated on the server', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);
    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $response = $this->actingAs($user)
        ->post(route('loans.store'), [
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_date' => '2026-10-03',
            'gross_loan_amount' => '10000.00',
        ]);

    $loan = Loan::query()->firstOrFail();

    $response->assertRedirect(route('loans.show', $loan));

    $this->actingAs($user)
        ->get(route('loans.show', $loan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Show')
            ->where('loan.loan_number', $loan->loan_number)
            ->where('loan.borrower.full_name', $borrower->fullName())
        );

    expect($loan->status)->toBe(Loan::STATUS_DRAFT)
        ->and($loan->borrower_id)->toBe($borrower->id)
        ->and($loan->loan_product_id)->toBe($product->id)
        ->and($loan->gross_loan_amount)->toBe('10000.00')
        ->and($loan->interest_amount)->toBe('1000.00')
        ->and($loan->deductions_amount)->toBe('1150.00')
        ->and($loan->net_proceeds_amount)->toBe('8850.00')
        ->and($loan->total_amount_payable)->toBe('10000.00')
        ->and($loan->loan_number)->toStartWith('LN-');
});

it('stores a draft loan with an interest override amount', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);
    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->post(route('loans.store'), [
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_date' => '2026-10-03',
            'gross_loan_amount' => '10000.00',
            'interest_override_amount' => '750.00',
        ])
        ->assertRedirect();

    $loan = Loan::query()->sole();

    expect($loan->interest_amount)->toBe('750.00')
        ->and($loan->deductions_amount)->toBe('900.00')
        ->and($loan->net_proceeds_amount)->toBe('9100.00');
});

it('defaults the loan area from the borrower when none is chosen', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $area = Area::query()->create([
        'code' => 'MNL',
        'name' => 'Manila',
        'is_active' => true,
    ]);

    $borrower = Borrower::factory()->create([
        'status' => BorrowerStatus::Active,
        'default_area_id' => $area->id,
    ]);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->post(route('loans.store'), [
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_date' => '2026-10-03',
            'gross_loan_amount' => '10000.00',
        ])
        ->assertRedirect();

    expect(Loan::query()->sole()->area_id)->toBe($area->id);
});

it('lets the encoder override the borrower default area on the loan', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $manila = Area::query()->create([
        'code' => 'MNL',
        'name' => 'Manila',
        'is_active' => true,
    ]);

    $cavite = Area::query()->create([
        'code' => 'CAV',
        'name' => 'Cavite',
        'is_active' => true,
    ]);

    $borrower = Borrower::factory()->create([
        'status' => BorrowerStatus::Active,
        'default_area_id' => $manila->id,
    ]);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    $this->actingAs($user)
        ->post(route('loans.store'), [
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_date' => '2026-10-03',
            'gross_loan_amount' => '10000.00',
            'area_id' => $cavite->id,
        ])
        ->assertRedirect();

    expect(Loan::query()->sole()->area_id)->toBe($cavite->id);
});
