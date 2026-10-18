<?php

use App\Domain\Access\Permission;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Payments\CollectionPostingService;
use App\Enums\BorrowerStatus;
use App\Models\Area;
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
            'application_date' => '2026-09-01',
            'gross_loan_amount' => '10000.00',
        ]);

        $loan->status = Loan::STATUS_DRAFT;
        $loan->save();

        return app(LoanPostService::class)->post($loan->refresh());
    };

    $this->collectInArea = function (string $date, array $entries): void {
        $service = app(CollectionPostingService::class);
        $collection = $service->open(CarbonImmutable::parse($date));

        foreach ($entries as [$areaName, $amount]) {
            $area = Area::query()->firstOrCreate(
                ['name' => $areaName],
                ['code' => strtoupper(substr($areaName, 0, 3)), 'is_active' => true],
            );

            $loan = ($this->releasedLoan)();
            $loan->area_id = $area->id;
            $loan->save();

            $service->addPayment($collection, $loan->refresh(), $amount);
        }

        $service->post($collection);
    };

    $this->userWith = function (string ...$permissions): User {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    };
});

it('requires reports.view to open the daily collection report', function () {
    $this->actingAs(User::factory()->create())
        ->get('/reports/daily-collection')
        ->assertForbidden();
});

it('lists the days payments grouped by area', function () {
    $user = ($this->userWith)(Permission::REPORTS_VIEW);

    ($this->collectInArea)('2026-10-06', [
        ['Manila', '1000.00'],
        ['Manila', '250.00'],
        ['Cavite', '500.00'],
    ]);
    ($this->collectInArea)('2026-10-07', [['Manila', '900.00']]);

    $this->actingAs($user)
        ->get('/reports/daily-collection?date=2026-10-06')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/DailyCollection')
            ->where('report.date', '2026-10-06')
            ->has('report.areas', 2)
            ->where('report.areas.0.area', 'Cavite')
            ->where('report.areas.0.count', 1)
            ->where('report.areas.0.total', '500.00')
            ->has('report.areas.0.payments', 1)
            ->where('report.areas.0.payments.0.amount', '500.00')
            ->where('report.areas.1.area', 'Manila')
            ->where('report.areas.1.count', 2)
            ->where('report.areas.1.total', '1,250.00')
            ->has('report.areas.1.payments', 2)
            ->where('report.areas.1.payments.0.borrower_name', fn ($name) => is_string($name) && $name !== '')
            ->where('report.areas.1.payments.0.amount', '1,000.00')
            ->where('report.areas.1.payments.1.amount', '250.00')
            ->where('report.total', '1,750.00')
            ->where('report.total_count', 3)
        );
});

it('falls back to the node area when the loan has none', function () {
    $user = ($this->userWith)(Permission::REPORTS_VIEW);

    $area = Area::query()->create([
        'code' => 'BAG',
        'name' => 'Baguio',
        'is_active' => true,
    ]);

    $loan = ($this->releasedLoan)();
    $loan->node()->firstOrFail()->update(['area_id' => $area->id]);

    $service = app(CollectionPostingService::class);
    $collection = $service->open(CarbonImmutable::parse('2026-10-06'));
    $service->addPayment($collection, $loan->refresh(), '400.00');
    $service->post($collection);

    $this->actingAs($user)
        ->get('/reports/daily-collection?date=2026-10-06')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report.areas', 1)
            ->where('report.areas.0.area', 'Baguio')
            ->where('report.total', '400.00')
        );
});

it('only counts posted payments for the selected date', function () {
    $user = ($this->userWith)(Permission::REPORTS_VIEW);

    ($this->collectInArea)('2026-10-06', [['Manila', '1000.00']]);

    $this->actingAs($user)
        ->get('/reports/daily-collection?date=2026-10-05')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.total', '0.00')
            ->where('report.total_count', 0)
            ->has('report.areas', 0)
        );
});

it('breaks the day down by payment method', function () {
    $user = ($this->userWith)(Permission::REPORTS_VIEW);

    ($this->collectInArea)('2026-10-06', [['Manila', '1000.00']]);

    $this->actingAs($user)
        ->get('/reports/daily-collection?date=2026-10-06')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report.methods', 1)
            ->where('report.methods.0.method', 'cash')
            ->where('report.methods.0.total', '1,000.00')
        );
});

it('requires reports.export to export the daily collection report', function () {
    $this->actingAs(($this->userWith)(Permission::REPORTS_VIEW))
        ->get('/reports/daily-collection/export')
        ->assertForbidden();
});

it('exports the daily collection report as csv', function () {
    $user = ($this->userWith)(Permission::REPORTS_EXPORT);

    ($this->collectInArea)('2026-10-06', [['Manila', '1250.50']]);

    $response = $this->actingAs($user)
        ->get('/reports/daily-collection/export?date=2026-10-06&format=csv');

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertHeader('content-disposition', 'attachment; filename="daily-collection-2026-10-06.csv"');

    expect($response->getContent())
        ->toContain('Area')
        ->toContain('Manila')
        ->toContain('1250.50');
});

it('exports the daily collection report as pdf', function () {
    $user = ($this->userWith)(Permission::REPORTS_EXPORT);

    ($this->collectInArea)('2026-10-06', [['Manila', '1000.00']]);

    $response = $this->actingAs($user)
        ->get('/reports/daily-collection/export?date=2026-10-06&download=1');

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('daily-collection-2026-10-06.pdf');
});
