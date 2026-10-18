<?php

use App\Domain\Access\Permission;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Payments\CollectionPostingService;
use App\Enums\BorrowerStatus;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Collection;
use App\Models\Loan;
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

        $loan->forceFill(['status' => Loan::STATUS_DRAFT])->save();

        return app(LoanPostService::class)->post($loan);
    };

    $this->userWith = function (string ...$permissions): User {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    };
});

it('requires authentication for the daily collection', function () {
    $this->get(route('collections.daily'))->assertRedirect(route('login'));
});

it('refuses the daily collection without the view permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('collections.daily'))
        ->assertForbidden();
});

it('shows the loans that are due on the selected date', function () {
    ($this->makeReleasedLoan)();

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_VIEW))
        ->get(route('collections.daily', ['date' => '2026-10-05']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('collections/Daily')
            ->where('date', '2026-10-05')
            ->has('scheduled', 1)
            ->where('scheduled.0.remaining_amount', '100.00')
        );
});

it('filters the daily schedule by area and name', function () {
    $area = Area::query()->create([
        'code' => 'MNL',
        'name' => 'Manila',
        'is_active' => true,
    ]);

    $matched = ($this->makeReleasedLoan)();
    $matched->update(['area_id' => $area->id]);
    $matched->borrower->update(['first_name' => 'Juan', 'last_name' => 'Cruz']);

    $other = ($this->makeReleasedLoan)();
    $other->borrower->update(['first_name' => 'Ana', 'last_name' => 'Bautista']);

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_VIEW))
        ->get(route('collections.daily', ['date' => '2026-10-05', 'area' => $area->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('scheduled', 1)
            ->where('scheduled.0.loan_id', $matched->id)
            ->where('scheduled.0.area', 'Manila')
        );

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_VIEW))
        ->get(route('collections.daily', ['date' => '2026-10-05', 'name' => 'Bautista']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('scheduled', 1)
            ->where('scheduled.0.loan_id', $other->id)
        );
});

it('stages the entered amounts on an open collection day', function () {
    $loan = ($this->makeReleasedLoan)();

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_VIEW, Permission::PAYMENTS_CREATE))
        ->post(route('collections.stage'), [
            'business_date' => '2026-10-05',
            'entries' => [
                ['loan_id' => $loan->id, 'amount' => '100.00'],
            ],
        ])
        ->assertRedirect(route('collections.daily', ['date' => '2026-10-05']));

    $collection = Collection::query()->whereDate('business_date', '2026-10-05')->firstOrFail();

    expect($collection->isOpen())->toBeTrue()
        ->and($collection->payments()->first()->status)->toBe(Payment::STATUS_PENDING);
});

it('posts an open collection day as a unit', function () {
    $loan = ($this->makeReleasedLoan)();

    $posting = app(CollectionPostingService::class);
    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $posting->addPayment($collection, $loan, '100.00');

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_CREATE))
        ->post(route('collections.post', ['date' => '2026-10-05']))
        ->assertRedirect(route('collections.daily', ['date' => '2026-10-05']));

    expect($collection->refresh()->isPosted())->toBeTrue();
});

it('removes a staged payment from an open day', function () {
    $loan = ($this->makeReleasedLoan)();

    $posting = app(CollectionPostingService::class);
    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $posting->addPayment($collection, $loan, '100.00');

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_CREATE))
        ->delete(route('collections.payments.destroy', $payment))
        ->assertRedirect(route('collections.daily', ['date' => '2026-10-05']));

    expect(Payment::query()->whereKey($payment->getKey())->exists())->toBeFalse();
});

it('lists payments for a user who may view them', function () {
    $loan = ($this->makeReleasedLoan)();

    $posting = app(CollectionPostingService::class);
    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $posting->addPayment($collection, $loan, '100.00');
    $posting->post($collection);

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_VIEW))
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.amount', '100.00')
        );
});

it('reverses a posted payment over http when permitted', function () {
    $loan = ($this->makeReleasedLoan)();

    $posting = app(CollectionPostingService::class);
    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $posting->addPayment($collection, $loan, '100.00');
    $posting->post($collection);

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_REVERSE))
        ->post(route('payments.reverse', $payment), ['reason' => 'Wrong amount entered.'])
        ->assertRedirect();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_REVERSED);
});

it('refuses a reversal without the reverse permission', function () {
    $loan = ($this->makeReleasedLoan)();

    $posting = app(CollectionPostingService::class);
    $collection = $posting->open(CarbonImmutable::parse('2026-10-05'));
    $payment = $posting->addPayment($collection, $loan, '100.00');
    $posting->post($collection);

    $this->actingAs(($this->userWith)(Permission::PAYMENTS_VIEW))
        ->post(route('payments.reverse', $payment), ['reason' => 'Should be refused.'])
        ->assertForbidden();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_POSTED);
});
