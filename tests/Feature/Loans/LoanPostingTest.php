<?php

use App\Domain\Access\Permission;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Enums\BorrowerStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LoanConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(LoanConfigurationSeeder::class);
});

it('posts a draft loan and stamps the release date', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_CREATE);

    $loan = app(LoanDraftService::class)->create([
        'borrower_id' => Borrower::factory()->create(['status' => BorrowerStatus::Active])->id,
        'loan_product_id' => LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail()->id,
        'application_date' => '2026-10-03',
        'gross_loan_amount' => '10000.00',
    ]);

    $response = $this->actingAs($user)->from(route('loans.show', $loan))->post(route('loans.post', $loan));

    $loan->refresh();

    expect($loan->status)->toBe(Loan::STATUS_ACTIVE)
        ->and($loan->release_date)->not->toBeNull();
});

it('freezes a schedule of the product term when a loan is posted', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03'));

    $loan = app(LoanDraftService::class)->create([
        'borrower_id' => Borrower::factory()->create(['status' => BorrowerStatus::Active])->id,
        'loan_product_id' => LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail()->id,
        'application_date' => '2026-10-03',
        'gross_loan_amount' => '10000.00',
    ]);

    app(LoanPostService::class)->post($loan);

    $schedule = $loan->amortizations()->get();
    $product = $loan->loanProduct;

    expect($schedule)->toHaveCount($product->term)
        ->and($schedule->first()->installment_number)->toBe(1)
        ->and($schedule->first()->due_date->toDateString())->toBe('2026-10-05')
        ->and($schedule->last()->installment_number)->toBe($product->term);

    $sum = $schedule->reduce(
        fn (string $carry, $installment): string => bcadd($carry, $installment->total_due, 2),
        '0.00',
    );

    expect($sum)->toBe((string) $loan->total_amount_payable)
        ->and($schedule->contains(fn ($installment) => $installment->due_date->dayOfWeek === CarbonImmutable::SUNDAY))->toBeFalse();
});

it('shows the frozen schedule on the loan page after posting', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03'));

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $loan = app(LoanDraftService::class)->create([
        'borrower_id' => Borrower::factory()->create(['status' => BorrowerStatus::Active])->id,
        'loan_product_id' => LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail()->id,
        'application_date' => '2026-10-03',
        'gross_loan_amount' => '10000.00',
    ]);
    app(LoanPostService::class)->post($loan);

    $this->actingAs($user)
        ->get("/loans/{$loan->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Show')
            ->where('loan.status', Loan::STATUS_ACTIVE)
            ->where('loan.release_date', '2026-10-03')
            ->has('loan.amortization', $loan->loanProduct->term)
            ->where('loan.amortization.0.due_date', '2026-10-05')
            ->where('loan.amortization.0.total_due', '100.00')
        );
});
