<?php

use App\Domain\Loans\LoanCalculator;
use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\TermUnit;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class, TestCase::class);

it('calculates the initial 100-day product preview', function () {
    $product = LoanProduct::factory()->create([
        'term' => 100,
        'term_unit' => TermUnit::CollectionDays,
        'payment_frequency' => PaymentFrequency::Daily,
        'exclude_sundays' => true,
        'exclude_saturdays' => false,
        'release_date_is_day_one' => false,
        'interest_method' => InterestMethod::PercentageOfLoanAmount,
        'interest_rate' => '10.00',
    ]);

    $preview = app(LoanCalculator::class)->preview($product, '10000.00');

    expect($preview)->toMatchArray([
        'gross_loan_amount' => '10000.00',
        'interest_amount' => '1000.00',
        'deductions_amount' => '1000.00',
        'net_proceeds_amount' => '9000.00',
        'total_amount_payable' => '10000.00',
        'daily_amortization' => '100.00',
        'collection_days' => 100,
    ]);
});

it('rounds the preview exactly with decimal-safe math', function () {
    $product = LoanProduct::factory()->create([
        'term' => 3,
        'interest_rate' => '10.00',
    ]);

    $preview = app(LoanCalculator::class)->preview($product, '1000.01');

    expect($preview['interest_amount'])->toBe('100.00')
        ->and($preview['daily_amortization'])->toBe('333.34');
});

it('replaces the product rate with an interest override', function () {
    $product = LoanProduct::factory()->create([
        'term' => 100,
        'interest_rate' => '10.00',
    ]);

    $preview = app(LoanCalculator::class)->preview($product, '10000.00', '750.00');

    expect($preview)->toMatchArray([
        'gross_loan_amount' => '10000.00',
        'interest_amount' => '750.00',
        'deductions_amount' => '750.00',
        'net_proceeds_amount' => '9250.00',
        'total_amount_payable' => '10000.00',
        'daily_amortization' => '100.00',
    ]);
});

it('treats a blank interest override as no override', function () {
    $product = LoanProduct::factory()->create([
        'term' => 100,
        'interest_rate' => '10.00',
    ]);

    $calculator = app(LoanCalculator::class);

    expect($calculator->preview($product, '10000.00', ''))->toBe($calculator->preview($product, '10000.00', null))
        ->and($calculator->preview($product, '10000.00', '   '))->toBe($calculator->preview($product, '10000.00', null));
});

it('rounds an interest override to cents without float drift', function () {
    $product = LoanProduct::factory()->create([
        'term' => 100,
        'interest_rate' => '10.00',
    ]);

    $preview = app(LoanCalculator::class)->preview($product, '10000.00', '1234.567');

    expect($preview['interest_amount'])->toBe('1234.57')
        ->and($preview['deductions_amount'])->toBe('1234.57')
        ->and($preview['net_proceeds_amount'])->toBe('8765.43');
});
