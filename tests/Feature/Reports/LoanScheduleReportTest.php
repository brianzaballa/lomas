<?php

use App\Domain\Access\Permission;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Reporting\LoanScheduleReport;
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

    $this->releasedLoan = function (): Loan {
        $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);
        $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

        $loan = app(LoanDraftService::class)->create([
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_date' => '2026-10-03',
            'gross_loan_amount' => '10000.00',
        ]);

        $loan->status = Loan::STATUS_DRAFT;
        $loan->save();

        return app(LoanPostService::class)->post($loan->refresh());
    };
});

it('requires a signed in user with loans.view to render the schedule', function () {
    $loan = ($this->releasedLoan)();

    $this->get("/loans/{$loan->id}/schedule")->assertRedirect('/login');
});

it('renders the amortization schedule as a pdf', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05'));

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $loan = ($this->releasedLoan)();

    $response = $this->actingAs($user)->get("/loans/{$loan->id}/schedule");

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');

    expect($response->headers->get('content-disposition'))
        ->toContain('inline')
        ->toContain($loan->loan_number);
});

it('offers the schedule as a file download', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $loan = ($this->releasedLoan)();

    $response = $this->actingAs($user)->get("/loans/{$loan->id}/schedule?download=1");

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('amortization-schedule-'.$loan->loan_number.'.pdf');
});

it('lays a 100 day schedule out in paper efficient columns', function () {
    $loan = ($this->releasedLoan)();

    $data = app(LoanScheduleReport::class)->data($loan);

    expect($data['installments'])->toHaveCount(100)
        ->and($data['columns'])->toHaveCount(3)
        ->and($data['columns'][0])->toHaveCount(34)
        ->and($data['has_interest'])->toBeFalse();
});

it('prints grouped amounts without a repeated currency symbol', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03'));

    $loan = ($this->releasedLoan)();

    $data = app(LoanScheduleReport::class)->data($loan);

    expect($data['summary']['gross_loan_amount'])->toBe('10,000.00')
        ->and($data['summary']['total_amount_payable'])->toBe('10,000.00')
        ->and($data['summary']['net_proceeds_amount'])->toBe('8,850.00')
        ->and($data['installments'][0]['total'])->toBe('100.00')
        ->and($data['installments'][0]['due_date'])->toBe('Mon Oct 5, 2026');
});
