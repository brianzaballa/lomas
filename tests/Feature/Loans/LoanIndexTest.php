<?php

use App\Domain\Access\Permission;
use App\Domain\Loans\LoanDraftService;
use App\Enums\BorrowerStatus;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Node;
use App\Models\User;
use Database\Seeders\LoanConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(LoanConfigurationSeeder::class);
});

function encodeLoan(Borrower $borrower, string $amount = '10000.00'): Loan
{
    return app(LoanDraftService::class)->create([
        'borrower_id' => $borrower->id,
        'loan_product_id' => LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail()->id,
        'application_date' => '2026-10-03',
        'gross_loan_amount' => $amount,
    ]);
}

it('requires a signed in user with permission to view the list', function () {
    $this->get(route('loans.index'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('loans.index'))->assertForbidden();
});

it('lists encoded loans with their borrower and product', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $borrower = Borrower::factory()->create([
        'status' => BorrowerStatus::Active,
    ]);

    $loan = encodeLoan($borrower);

    $this->actingAs($user)
        ->get(route('loans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('loans/Index')
            ->has('loans.data', 1)
            ->where('loans.data.0.loan_number', $loan->loan_number)
            ->where('loans.data.0.borrower.borrower_number', $borrower->borrower_number)
            ->where('loans.data.0.loan_product.code', 'LOAN-100D')
            ->where('loans.data.0.status', Loan::STATUS_DRAFT)
        );
});

it('filters the list by status', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $draft = encodeLoan(Borrower::factory()->create(['status' => BorrowerStatus::Active]));
    $released = encodeLoan(Borrower::factory()->create(['status' => BorrowerStatus::Active]));
    $released->status = Loan::STATUS_ACTIVE;
    $released->save();

    $this->actingAs($user)
        ->get(route('loans.index', ['status' => Loan::STATUS_ACTIVE]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $released->id)
            ->where('filters.status', Loan::STATUS_ACTIVE)
        );

    expect($draft->id)->not->toBe($released->id);
});

it('ignores a status that is not a real lifecycle status', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $otherLoan = encodeLoan(Borrower::factory()->create(['status' => BorrowerStatus::Active]));

    $otherArea = Area::query()->create([
        'code' => 'CEB',
        'name' => 'Cebu',
        'is_active' => true,
    ]);

    $otherNode = Node::query()->create([
        'id' => (string) str()->uuid(),
        'code' => 'CEB1',
        'name' => 'Cebu Branch',
        'role' => Node::ROLE_BRANCH,
        'timezone' => 'UTC',
        'is_active' => true,
        'last_applied_serial' => 0,
        'area_id' => $otherArea->id,
    ]);

    $otherLoan->update(['node_id' => $otherNode->id]);

    $this->actingAs($user)
        ->get(route('loans.index', ['status' => 'not-a-status']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('filters.status', null)
        );
});

it('searches by loan number and by borrower name', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $borrower = Borrower::factory()->create([
        'status' => BorrowerStatus::Active,
        'first_name' => 'Maria',
        'last_name' => 'Santos',
    ]);

    $loan = encodeLoan($borrower);
    encodeLoan(Borrower::factory()->create([
        'status' => BorrowerStatus::Active,
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]));

    $this->actingAs($user)
        ->get(route('loans.index', ['search' => $loan->loan_number]))
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $loan->id)
        );

    $this->actingAs($user)
        ->get(route('loans.index', ['search' => 'Santos']))
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $loan->id)
        );
});

it('groups loans by their area', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $area = Area::query()->create([
        'code' => 'MNL',
        'name' => 'Manila',
        'is_active' => true,
    ]);

    $loan = encodeLoan(Borrower::factory()->create(['status' => BorrowerStatus::Active]));
    $loan->update(['area_id' => $area->id]);

    $this->actingAs($user)
        ->get(route('loans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('groupedLoansByArea.Manila', 1)
        );
});

it('filters loans by area', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::LOANS_VIEW);

    $area = Area::query()->create([
        'code' => 'MNL',
        'name' => 'Manila',
        'is_active' => true,
    ]);

    $loan = encodeLoan(Borrower::factory()->create(['status' => BorrowerStatus::Active]));
    $loan->update(['area_id' => $area->id]);

    encodeLoan(Borrower::factory()->create(['status' => BorrowerStatus::Active]));

    $this->actingAs($user)
        ->get(route('loans.index', ['area' => $area->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('loans.data', 1)
            ->where('loans.data.0.id', $loan->id)
            ->where('filters.area', $area->id)
        );
});
