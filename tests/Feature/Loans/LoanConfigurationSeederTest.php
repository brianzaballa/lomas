<?php

use App\Models\LoanDeductionType;
use App\Models\LoanProduct;
use Database\Seeders\LoanConfigurationSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The seeded configuration is the starting point of the business, so its values
 * are asserted explicitly: a wrong default here changes every loan.
 */
it('seeds the 100-day product with the answers of record', function () {
    $this->seed(LoanConfigurationSeeder::class);

    $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

    expect($product->name)->toBe('100-Day Loan')
        ->and($product->term)->toBe(100)
        ->and($product->term_unit->value)->toBe('collection_days')
        ->and($product->payment_frequency->value)->toBe('daily')
        // Monday to Saturday.
        ->and($product->exclude_sundays)->toBeTrue()
        ->and($product->exclude_saturdays)->toBeFalse()
        // The first collection is the next collection day.
        ->and($product->release_date_is_day_one)->toBeFalse()
        ->and($product->interest_method->value)->toBe('percentage_of_loan_amount')
        ->and($product->interest_rate)->toBe('10.00')
        ->and($product->is_active)->toBeTrue();
});

it('seeds the documentary stamp and passbook only', function () {
    $this->seed(LoanConfigurationSeeder::class);

    expect(LoanDeductionType::query()->count())->toBe(2);

    $stamp = LoanDeductionType::query()->where('code', 'DOC_STAMP')->firstOrFail();

    expect($stamp->name)->toBe('Documentary Stamp')
        ->and($stamp->calculation_method->value)->toBe('percentage_of_loan_amount')
        ->and($stamp->rate)->toBe('1.00')
        ->and($stamp->fixed_amount)->toBeNull()
        ->and($stamp->is_active)->toBeTrue()
        ->and($stamp->sort_order)->toBe(1);

    $passbook = LoanDeductionType::query()->where('code', 'PASSBOOK')->firstOrFail();

    expect($passbook->name)->toBe('Passbook')
        ->and($passbook->calculation_method->value)->toBe('fixed_amount')
        ->and($passbook->fixed_amount)->toBe('50.00')
        ->and($passbook->rate)->toBeNull()
        ->and($passbook->sort_order)->toBe(2);
});

it('never seeds interest as a deduction type', function () {
    $this->seed(LoanConfigurationSeeder::class);

    // The interest rate belongs to the loan product, so no deduction type may
    // carry it.
    expect(LoanDeductionType::query()
        ->whereIn('calculation_method', ['percentage_of_loan_amount'])
        ->pluck('code')
        ->all()
    )->toBe(['DOC_STAMP'])
        ->and(LoanProduct::query()->sole()->interest_rate)->toBe('10.00');
});

it('does not duplicate the configuration when seeded again', function () {
    $this->seed(LoanConfigurationSeeder::class);
    $this->seed(LoanConfigurationSeeder::class);

    expect(LoanProduct::query()->count())->toBe(1)
        ->and(LoanDeductionType::query()->count())->toBe(2);
});

it('keeps a staff change to a seeded product when re-seeded', function () {
    $this->seed(LoanConfigurationSeeder::class);

    LoanProduct::query()->where('code', 'LOAN-100D')->update(['interest_rate' => '12.00']);

    $this->seed(LoanConfigurationSeeder::class);

    expect(LoanProduct::query()->sole()->interest_rate)->toBe('12.00');
});

it('creates no sample borrowers and no audit noise', function () {
    $this->seed(LoanConfigurationSeeder::class);

    // Configuration only. Borrowers, loans, schedules and payments arrive with
    // the later phases and must never be invented by a seeder.
    expect(DB::table('borrowers')->count())->toBe(0)
        ->and(DB::table('loan_products')->count())->toBe(1)
        ->and(DB::table('loan_deduction_types')->count())->toBe(2)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});
