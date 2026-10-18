<?php

use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Payments\CollectionPostingService;
use App\Domain\Payments\LoanBalanceCalculator;
use App\Enums\BorrowerStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\LoanConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * The bulk arrears lookup exists so a report can sum arrears for a whole book
 * without hydrating every installment. It must never disagree with the
 * single loan calculation the rest of the system relies on.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(LoanConfigurationSeeder::class);

    $this->travelTo(CarbonImmutable::parse('2026-10-03'));

    $this->makeReleasedLoan = function (): Loan {
        $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);
        $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

        $loan = app(LoanDraftService::class)->create([
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_date' => '2026-10-03',
            'gross_loan_amount' => '10000.00',
        ]);

        $loan->forceFill(['status' => Loan::STATUS_DRAFT])->save();

        return app(LoanPostService::class)->post($loan);
    };

    $this->post = function (Loan $loan, string $date, string $amount): Payment {
        $service = app(CollectionPostingService::class);
        $collection = $service->open(CarbonImmutable::parse($date));
        $payment = $service->addPayment($collection, $loan, $amount);
        $service->post($collection);

        return $payment;
    };
});

it('returns no arrears when there are no loans', function () {
    $calculator = app(LoanBalanceCalculator::class);

    expect($calculator->arrearsAsOfMany([], CarbonImmutable::parse('2026-10-11')))->toBe([]);
});

it('matches the single loan calculation across many loans and dates', function () {
    $calculator = app(LoanBalanceCalculator::class);

    $paidLoan = ($this->makeReleasedLoan)();
    ($this->post)($paidLoan, '2026-10-05', '40.00');

    $untouchedLoan = ($this->makeReleasedLoan)();
    ($this->post)($untouchedLoan, '2026-10-12', '100.00');

    $laterOnlyLoan = ($this->makeReleasedLoan)();

    $ids = [$paidLoan->getKey(), $untouchedLoan->getKey(), $laterOnlyLoan->getKey()];

    foreach (['2026-10-04', '2026-10-05', '2026-10-07', '2026-10-11', '2026-10-13', '2026-11-01'] as $date) {
        $asOf = CarbonImmutable::parse($date);
        $bulk = $calculator->arrearsAsOfMany($ids, $asOf);

        foreach ([$paidLoan, $untouchedLoan, $laterOnlyLoan] as $loan) {
            $expected = $calculator->arrearsAsOf($loan, $asOf)->toDecimalString();
            $actual = ($bulk[$loan->getKey()] ?? Money::zero())->toDecimalString();

            expect($actual)->toBe($expected, "arrears mismatch for {$loan->loan_number} as of {$date}");
        }
    }
});

it('counts the unpaid remainder of installments due on or before the date', function () {
    $calculator = app(LoanBalanceCalculator::class);

    $loan = ($this->makeReleasedLoan)();
    ($this->post)($loan, '2026-10-05', '40.00');

    $asOf = CarbonImmutable::parse('2026-10-07');

    expect($calculator->arrearsAsOfMany([$loan->getKey()], $asOf)[$loan->getKey()]->toDecimalString())
        ->toBe('260.00')
        ->and($calculator->arrearsAsOf($loan, $asOf)->toDecimalString())
        ->toBe('260.00');
});

it('ignores an other component allocation in both calculations', function () {
    $calculator = app(LoanBalanceCalculator::class);

    $loan = ($this->makeReleasedLoan)();
    $payment = ($this->post)($loan, '2026-10-05', '100.00');

    $installment = $loan->amortizations()->orderBy('installment_number')->skip(1)->first();

    PaymentAllocation::query()->create([
        'payment_id' => $payment->getKey(),
        'loan_amortization_id' => $installment->getKey(),
        'component' => PaymentAllocation::COMPONENT_OTHER,
        'amount' => '30.00',
    ]);

    $asOf = CarbonImmutable::parse('2026-10-07');

    expect($calculator->arrearsAsOfMany([$loan->getKey()], $asOf)[$loan->getKey()]->toDecimalString())
        ->toBe('200.00')
        ->and($calculator->arrearsAsOf($loan, $asOf)->toDecimalString())
        ->toBe('200.00');
});
