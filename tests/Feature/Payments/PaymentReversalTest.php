<?php

use App\Domain\Audit\AuditAction;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Payments\CollectionPostingService;
use App\Domain\Payments\LoanBalanceCalculator;
use App\Domain\Payments\PaymentReversalService;
use App\Enums\BorrowerStatus;
use App\Models\AuditLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\User;
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

it('reverses a posted payment and restores the schedule', function () {
    $this->actingAs(User::factory()->create());

    $loan = ($this->makeReleasedLoan)();
    $posting = app(CollectionPostingService::class);

    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $posting->addPayment($collection, $loan, '100.00');
    $posting->post($collection);

    app(PaymentReversalService::class)->reverse($payment, 'Wrong loan selected.');

    $payment->refresh();
    $first = $loan->amortizations()->orderBy('installment_number')->first();

    expect($payment->status)->toBe(Payment::STATUS_REVERSED)
        ->and($payment->reversal_reason)->toBe('Wrong loan selected.')
        ->and($payment->reversed_at)->not->toBeNull()
        ->and($first->status)->toBe(LoanAmortization::STATUS_PENDING)
        ->and($first->remaining_amount)->toBe('100.00')
        ->and(app(LoanBalanceCalculator::class)->summary($loan)['total_amount_paid'])->toBe('0.00');
});

it('removes the reversed amount from the collection totals', function () {
    $this->actingAs(User::factory()->create());

    $loan = ($this->makeReleasedLoan)();
    $posting = app(CollectionPostingService::class);

    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $posting->addPayment($collection, $loan, '100.00');
    $posting->post($collection);

    expect($collection->refresh()->total_amount)->toBe('100.00');

    app(PaymentReversalService::class)->reverse($payment, 'Duplicate entry.');

    $collection->refresh();

    expect($collection->total_amount)->toBe('0.00')
        ->and($collection->payment_count)->toBe(0);
});

it('writes an audit entry for a reversal', function () {
    $this->actingAs(User::factory()->create());

    $loan = ($this->makeReleasedLoan)();
    $posting = app(CollectionPostingService::class);

    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $posting->addPayment($collection, $loan, '100.00');
    $posting->post($collection);

    app(PaymentReversalService::class)->reverse($payment, 'Posted to the wrong day.');

    $log = AuditLog::query()->where('action', AuditAction::PAYMENT_REVERSED)->first();

    expect($log)->not->toBeNull()
        ->and($log->reason)->toBe('Posted to the wrong day.')
        ->and($log->new_values['status'])->toBe(Payment::STATUS_REVERSED);
});

it('refuses to reverse a payment that is not posted', function () {
    $loan = ($this->makeReleasedLoan)();
    $posting = app(CollectionPostingService::class);

    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $posting->addPayment($collection, $loan, '100.00');

    expect(fn () => app(PaymentReversalService::class)->reverse($payment, 'Not posted yet.'))
        ->toThrow(LogicException::class);
});
