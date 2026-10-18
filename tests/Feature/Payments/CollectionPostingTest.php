<?php

use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Payments\CollectionPostingService;
use App\Domain\Payments\LoanBalanceCalculator;
use App\Enums\BorrowerStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Models\LoanProduct;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Database\Seeders\LoanConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;

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


        return app(LoanPostService::class)->post($loan);
    };
});

it('posts a full installment and explains the balance from the allocation', function () {
    $loan = ($this->makeReleasedLoan)();
    $service = app(CollectionPostingService::class);

    $collection = $service->open(CarbonImmutable::parse('2026-10-05'));
    $service->addPayment($collection, $loan, '100.00');
    $service->post($collection);

    $first = $loan->amortizations()->orderBy('installment_number')->first();

    expect($first->status)->toBe(LoanAmortization::STATUS_PAID)
        ->and($first->paid_amount)->toBe('100.00')
        ->and($first->remaining_amount)->toBe('0.00');

    $summary = app(LoanBalanceCalculator::class)->summary($loan);

    expect($summary['total_amount_paid'])->toBe('100.00')
        ->and($summary['outstanding_balance'])->toBe('9900.00');
});

it('keeps a partial payment on the installment instead of marking it paid', function () {
    $loan = ($this->makeReleasedLoan)();
    $service = app(CollectionPostingService::class);

    $collection = $service->open(CarbonImmutable::parse('2026-10-05'));
    $service->addPayment($collection, $loan, '40.00');
    $service->post($collection);

    $first = $loan->amortizations()->orderBy('installment_number')->first();

    expect($first->status)->toBe(LoanAmortization::STATUS_PARTIALLY_PAID)
        ->and($first->paid_amount)->toBe('40.00')
        ->and($first->remaining_amount)->toBe('60.00')
        ->and(app(LoanBalanceCalculator::class)->summary($loan)['outstanding_balance'])->toBe('9960.00');
});

it('keeps an overpayment as an unapplied credit', function () {
    $loan = ($this->makeReleasedLoan)();
    $service = app(CollectionPostingService::class);

    $collection = $service->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $service->addPayment($collection, $loan, '10050.00');
    $service->post($collection);

    $payment->refresh();

    expect($payment->allocated_amount)->toBe('10000.00')
        ->and($payment->unapplied_amount)->toBe('50.00')
        ->and($loan->amortizations()->where('status', LoanAmortization::STATUS_PAID)->count())->toBe(100);

    $summary = app(LoanBalanceCalculator::class)->summary($loan);

    expect($summary['outstanding_balance'])->toBe('0.00')
        ->and($summary['unapplied_credit'])->toBe('50.00');
});

it('settles arrears oldest first when more than one installment is due', function () {
    $loan = ($this->makeReleasedLoan)();
    $service = app(CollectionPostingService::class);

    $collection = $service->open(CarbonImmutable::parse('2026-10-07'));
    $service->addPayment($collection, $loan, '250.00');
    $service->post($collection);

    $installments = $loan->amortizations()->orderBy('installment_number')->limit(3)->get();

    expect($installments[0]->status)->toBe(LoanAmortization::STATUS_PAID)
        ->and($installments[1]->status)->toBe(LoanAmortization::STATUS_PAID)
        ->and($installments[2]->status)->toBe(LoanAmortization::STATUS_PARTIALLY_PAID)
        ->and($installments[2]->remaining_amount)->toBe('50.00');
});

it('does not count a staged payment before the day is posted', function () {
    $loan = ($this->makeReleasedLoan)();
    $service = app(CollectionPostingService::class);

    $collection = $service->open(CarbonImmutable::parse('2026-10-05'));
    $service->addPayment($collection, $loan, '100.00');

    expect($collection->payments()->first()->status)->toBe(Payment::STATUS_PENDING)
        ->and(app(LoanBalanceCalculator::class)->summary($loan)['total_amount_paid'])->toBe('0.00');
});

it('refuses to change a collection day once it is posted', function () {
    $loan = ($this->makeReleasedLoan)();
    $service = app(CollectionPostingService::class);

    $collection = $service->open(CarbonImmutable::parse('2026-10-05'));
    $service->addPayment($collection, $loan, '100.00');
    $service->post($collection);

    expect(fn () => $service->addPayment($collection->refresh(), $loan, '100.00'))
        ->toThrow(LogicException::class)
        ->and(fn () => $service->post($collection->refresh()))
        ->toThrow(LogicException::class);
});

it('gives each payment a node qualified number', function () {
    $loan = ($this->makeReleasedLoan)();
    $service = app(CollectionPostingService::class);

    $collection = $service->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $service->addPayment($collection, $loan, '100.00');

    expect($payment->payment_number)->toBe('PY-'.config('lomas.node.code').'-2026-000001');
});
