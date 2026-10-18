<?php

use App\Domain\Access\Permission;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Payments\CollectionPostingService;
use App\Domain\Payments\PaymentReversalService;
use App\Domain\Reporting\NcrPeriodResolver;
use App\Domain\Reporting\NcrPostingService;
use App\Enums\BorrowerStatus;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\NcrPosting;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LoanConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(LoanConfigurationSeeder::class);

    Carbon::setTestNow('2026-10-11 09:00:00');

    $this->releasedLoan = function (): Loan {
        $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);

        $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

        $loan = app(LoanDraftService::class)->create([
            'borrower_id' => $borrower->id,
            'loan_product_id' => $product->id,
            'application_date' => '2026-09-01',
            'gross_loan_amount' => '10000.00',
        ]);

        $loan->status = Loan::STATUS_DRAFT;
        $loan->save();

        return app(LoanPostService::class)->post($loan->refresh());
    };

    $this->collect = function (Loan $loan, string $date, string $amount): void {
        $service = app(CollectionPostingService::class);
        $collection = $service->open(CarbonImmutable::parse($date));
        $service->addPayment($collection, $loan->refresh(), $amount);
        $service->post($collection);
    };

    $this->week = fn () => app(NcrPeriodResolver::class)
        ->resolve(SystemSetting::NCR_PERIOD_MODE_WEEKLY, CarbonImmutable::parse('2026-10-12'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('requires reports.post to post or unpost a period', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $this->actingAs($user)
        ->post('/reports/notes-collection/post?period=2026-10-12')
        ->assertForbidden();
});

it('posts a period and writes it as an audited posting', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_POST);

    $this->actingAs($user)
        ->post('/reports/notes-collection/post?period=2026-10-12')
        ->assertRedirect();

    $posting = NcrPosting::query()->firstOrFail();

    expect($posting->status)->toBe(NcrPosting::STATUS_POSTED)
        ->and($posting->period_start->toDateString())->toBe('2026-10-12')
        ->and($posting->period_end->toDateString())->toBe('2026-10-17')
        ->and($posting->posted_by)->toBe($user->id);

    $this->assertDatabaseHas('audit_logs', ['action' => 'ncr_period.posted']);
});

it('locks the payments dated inside a posted period', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_POST);

    $loan = ($this->releasedLoan)();

    $this->actingAs($user)
        ->post('/reports/notes-collection/post?period=2026-10-12')
        ->assertRedirect();

    // Staging a payment on a locked day is refused.
    $service = app(CollectionPostingService::class);
    $collection = $service->open(CarbonImmutable::parse('2026-10-13'));

    expect(fn () => $service->addPayment($collection, $loan->refresh(), '100.00'))
        ->toThrow(LogicException::class);

    // A day outside the period is unaffected.
    $outside = $service->open(CarbonImmutable::parse('2026-10-20'));
    $service->addPayment($outside, $loan->refresh(), '100.00');

    expect($outside->payments()->count())->toBe(1);
});

it('refuses to reverse a payment dated inside a posted period', function () {
    $loan = ($this->releasedLoan)();

    ($this->collect)($loan, '2026-10-13', '100.00');

    $payment = $loan->payments()->firstOrFail();

    app(NcrPostingService::class)->post(($this->week)());

    expect(fn () => app(PaymentReversalService::class)->reverse($payment, 'mistake'))
        ->toThrow(LogicException::class);

    expect($payment->refresh()->isPosted())->toBeTrue();
});

it('unposts a period and restores its payments for correction', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_POST);

    $loan = ($this->releasedLoan)();
    ($this->collect)($loan, '2026-10-13', '100.00');

    $payment = $loan->payments()->firstOrFail();

    $this->actingAs($user)
        ->post('/reports/notes-collection/post?period=2026-10-12')
        ->assertRedirect();

    $this->actingAs($user)
        ->post('/reports/notes-collection/unpost?period=2026-10-12')
        ->assertRedirect();

    expect(NcrPosting::query()->firstOrFail()->status)->toBe(NcrPosting::STATUS_OPEN);

    // Reversal is allowed again once the period is unposted.
    app(PaymentReversalService::class)->reverse($payment, 'mistake');

    expect($payment->refresh()->status)->toBe(Payment::STATUS_REVERSED);

    $this->assertDatabaseHas('audit_logs', ['action' => 'ncr_period.unposted']);
});

it('reports the posting status to the screen', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW, Permission::REPORTS_POST);

    $this->actingAs($user)
        ->post('/reports/notes-collection/post?period=2026-10-12')
        ->assertRedirect();

    $this->actingAs($user)
        ->get('/reports/notes-collection?period=2026-10-12')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.posting.status', 'posted'));
});
