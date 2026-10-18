<?php

use App\Domain\Access\Permission;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Payments\CollectionPostingService;
use App\Domain\Settings\SystemSettings;
use App\Enums\BorrowerStatus;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Models\LoanProduct;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\LoanConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(LoanConfigurationSeeder::class);

    $this->releaseFor = function (Borrower $borrower): Loan {
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

    $this->releasedLoan = function (): Loan {
        $borrower = Borrower::factory()->create(['status' => BorrowerStatus::Active]);

        return ($this->releaseFor)($borrower);
    };

    $this->collect = function (string $date, string $amount): void {
        $service = app(CollectionPostingService::class);
        $collection = $service->open(CarbonImmutable::parse($date));
        $service->addPayment($collection, ($this->releasedLoan)(), $amount);
        $service->post($collection);
    };

    $this->collectFor = function (Loan $loan, string $date, string $amount): void {
        $service = app(CollectionPostingService::class);
        $collection = $service->open(CarbonImmutable::parse($date));
        $service->addPayment($collection, $loan->refresh(), $amount);
        $service->post($collection);
    };

    $this->stageOnly = function (string $date, string $amount): void {
        $service = app(CollectionPostingService::class);
        $collection = $service->open(CarbonImmutable::parse($date));
        $service->addPayment($collection, ($this->releasedLoan)(), $amount);
    };

    /**
     * The raw scheduled amount due on or before a date, before any payment.
     */
    $this->scheduledThrough = function (Loan $loan, string $end): Money {
        return $loan->amortizations()
            ->whereDate('due_date', '<=', $end)
            ->get()
            ->reduce(
                fn (Money $carry, LoanAmortization $installment): Money => $carry->add(Money::of($installment->total_due)),
                Money::zero(),
            );
    };
});

afterEach(function () {
    Carbon::setTestNow();
});

it('requires reports.view to open the notes collection report', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/reports/notes-collection')->assertForbidden();
});

it('shows a monday to saturday week with no sunday row', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $this->actingAs($user)
        ->get('/reports/notes-collection?week=2026-10-07')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/NotesCollection')
            ->where('report.week_start', '2026-10-05')
            ->where('report.week_end', '2026-10-10')
            ->where('report.label', 'Oct 5 – Oct 10, 2026')
            ->has('report.days', 6)
            ->has('report.day_headers', 6)
            ->where('report.days.0.weekday', 'Monday')
            ->where('report.days.5.weekday', 'Saturday')
        );
});

it('recalculates the weekly total from posted payments only', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    ($this->collect)('2026-10-05', '1000.00');
    ($this->collect)('2026-10-06', '500.00');
    ($this->stageOnly)('2026-10-07', '900.00');
    ($this->collect)('2026-10-13', '700.00');

    $this->actingAs($user)
        ->get('/reports/notes-collection?week=2026-10-07')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.total', '1,500.00')
            ->where('report.total_count', 2)
            ->where('report.days.0.collected', '1,000.00')
            ->where('report.days.1.collected', '500.00')
        );
});

it('requires reports.export to export the report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $this->actingAs($user)
        ->get('/reports/notes-collection/export?week=2026-10-07')
        ->assertForbidden();
});

it('exports the member ledger as csv', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_EXPORT);

    ($this->collect)('2026-10-05', '1250.50');

    $response = $this->actingAs($user)
        ->get('/reports/notes-collection/export?week=2026-10-07&format=csv');

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertHeader('content-disposition', 'attachment; filename="notes-collection-2026-10-05.csv"');

    $body = $response->getContent();

    expect($body)->toContain('Total collected')
        ->toContain('Overdue')
        ->toContain('Grand total')
        ->toContain('1250.50');
});

it('exports the report as a landscape pdf', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_EXPORT);

    ($this->collect)('2026-10-05', '1000.00');

    $response = $this->actingAs($user)
        ->get('/reports/notes-collection/export?week=2026-10-07&download=1');

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('notes-collection-2026-10-05.pdf');
});

it('groups the report by area', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $manila = Area::query()->create([
        'code' => 'MNA',
        'name' => 'Manila',
        'is_active' => true,
    ]);

    $cavite = Area::query()->create([
        'code' => 'CVA',
        'name' => 'Cavite',
        'is_active' => true,
    ]);

    $firstLoan = ($this->releasedLoan)();
    $secondLoan = ($this->releasedLoan)();

    $firstNode = $firstLoan->node;
    $firstNode->area_id = $manila->id;
    $firstNode->save();

    $secondNode = $secondLoan->node;
    $secondNode->area_id = $cavite->id;
    $secondNode->save();

    $service = app(CollectionPostingService::class);
    $firstCollection = $service->open(CarbonImmutable::parse('2026-10-06'));
    $service->addPayment($firstCollection, $firstLoan->refresh(), '1000.00');
    $service->post($firstCollection);

    $secondCollection = $service->open(CarbonImmutable::parse('2026-10-07'));
    $service->addPayment($secondCollection, $secondLoan->refresh(), '500.00');
    $service->post($secondCollection);

    $this->actingAs($user)
        ->get('/reports/notes-collection?week=2026-10-07')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report.grouped_payments_by_area', 1)
            ->where('report.grouped_payments_by_area.0.area', 'Cavite')
        );
});

it('pivots payments per member per day, grouped by area and sorted by name', function () {
    Carbon::setTestNow('2026-10-05 09:00:00');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $area = Area::query()->create([
        'code' => 'MNA',
        'name' => 'Manila',
        'is_active' => true,
    ]);

    $cruz = Borrower::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Cruz',
        'status' => BorrowerStatus::Active,
    ]);

    $bautista = Borrower::factory()->create([
        'first_name' => 'Ana',
        'middle_name' => null,
        'last_name' => 'Bautista',
        'status' => BorrowerStatus::Active,
    ]);

    $cruzLoan = ($this->releaseFor)($cruz);
    $cruzLoan->update(['area_id' => $area->id]);

    $bautistaLoan = ($this->releaseFor)($bautista);
    $bautistaLoan->update(['area_id' => $area->id]);

    $service = app(CollectionPostingService::class);
    $collection = $service->open(CarbonImmutable::parse('2026-10-12'));
    $service->addPayment($collection, $bautistaLoan->refresh(), '250.00');
    $service->addPayment($collection, $cruzLoan->refresh(), '100.00');
    $service->post($collection);

    ($this->collectFor)($cruzLoan, '2026-10-13', '100.00');

    $this->actingAs($user)
        ->get('/reports/notes-collection?week=2026-10-12')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report.areas', 1)
            ->where('report.areas.0.area', 'Manila')
            ->where('report.areas.0.count', 2)
            ->where('report.areas.0.members.0.borrower_name', 'Ana Bautista')
            ->where('report.areas.0.members.0.days.2026-10-12', '250.00')
            ->where('report.areas.0.members.0.total_collected', '250.00')
            ->where('report.areas.0.members.1.borrower_name', 'Juan Cruz')
            ->where('report.areas.0.members.1.days.2026-10-13', '100.00')
            ->where('report.areas.0.members.1.total_collected', '200.00')
        );
});

it('marks a member overdue by the unpaid schedule carried into the week', function () {
    Carbon::setTestNow('2026-09-01 09:00:00');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $loan = ($this->releasedLoan)();

    Carbon::setTestNow('2026-10-11 09:00:00');

    // Overdue for a current period is only what was unpaid before it started.
    $expected = app(SystemSettings::class)->formatAmount(($this->scheduledThrough)($loan, '2026-10-11'));

    $this->actingAs($user)
        ->get('/reports/notes-collection?period=2026-10-12')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report.areas', 1)
            ->where('report.areas.0.members.0.overdue', $expected)
            ->where('report.areas.0.members.0.is_overdue', true)
            ->where('report.areas.0.members.0.total_collected', '0.00')
            ->where('report.grand.total_overdue', $expected)
        );
});

it('shows what is due inside a current period as collectibles, not overdue', function () {
    Carbon::setTestNow('2026-10-11 09:00:00');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $loan = ($this->releasedLoan)();

    $before = ($this->scheduledThrough)($loan, '2026-10-11');
    $through = ($this->scheduledThrough)($loan, '2026-10-17');

    $overdue = app(SystemSettings::class)->formatAmount($before);
    $collectibles = app(SystemSettings::class)->formatAmount($through->subtract($before));

    $this->actingAs($user)
        ->get('/reports/notes-collection?period=2026-10-12')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('report.areas', 1)
            ->where('report.period.is_current', true)
            ->where('report.areas.0.members.0.overdue', $overdue)
            ->where('report.areas.0.members.0.collectibles', $collectibles)
            ->where('report.areas.0.members.0.has_collectibles', true)
            ->where('report.grand.total_collectibles', $collectibles)
        );
});

it('reduces collectibles by payments made during the period', function () {
    Carbon::setTestNow('2026-10-11 09:00:00');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $loan = ($this->releasedLoan)();

    ($this->collectFor)($loan, '2026-10-12', '50.00');

    $before = ($this->scheduledThrough)($loan, '2026-10-11');
    $through = ($this->scheduledThrough)($loan, '2026-10-17');

    // The payment lowers what is still collectible in the period, but the
    // overdue carried in from before the period is unchanged by it.
    $overdue = app(SystemSettings::class)->formatAmount($before);
    $collectibles = app(SystemSettings::class)->formatAmount($through->subtract($before)->subtract(Money::of('50.00')));

    $this->actingAs($user)
        ->get('/reports/notes-collection?period=2026-10-12')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.areas.0.members.0.days.2026-10-12', '50.00')
            ->where('report.areas.0.members.0.total_collected', '50.00')
            ->where('report.areas.0.members.0.overdue', $overdue)
            ->where('report.areas.0.members.0.collectibles', $collectibles)
        );
});

it('keeps a past week arrears stable when a later payment is posted', function () {
    Carbon::setTestNow('2026-10-05 09:00:00');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $loan = ($this->releasedLoan)();

    ($this->collectFor)($loan, '2026-10-12', '100.00');

    // Arrears at the end of the week count only the 100.00 paid within it.
    $expected = app(SystemSettings::class)->formatAmount(
        ($this->scheduledThrough)($loan, '2026-10-17')->subtract(Money::of('100.00')),
    );

    ($this->collectFor)($loan, '2026-10-19', '300.00');

    // Once the week has passed it is closed: overdue is the figure it was
    // printed with, so the later 300.00 never rewrites it.
    Carbon::setTestNow('2026-10-20 09:00:00');

    $this->actingAs($user)
        ->get('/reports/notes-collection?period=2026-10-12')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.period.is_current', false)
            ->where('report.areas.0.members.0.total_collected', '100.00')
            ->where('report.areas.0.members.0.overdue', $expected)
            ->where('report.areas.0.members.0.collectibles', null)
        );
});

it('provisions an explicit range for the report period', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $this->actingAs($user)
        ->get('/reports/notes-collection?from=2026-10-05&to=2026-10-09')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.week_start', '2026-10-05')
            ->where('report.week_end', '2026-10-09')
            ->has('report.day_headers', 5)
        );
});

it('resolves the period from the configured decile mode', function () {
    app(SystemSettings::class)->update(['ncr_period_mode' => 'decile']);

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $this->actingAs($user)
        ->get('/reports/notes-collection?period=2026-10-15')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.period.mode', 'decile')
            ->where('report.period.start', '2026-10-11')
            ->where('report.period.end', '2026-10-20')
            ->has('report.day_headers', 10)
        );
});

it('accepts the legacy week parameter as a period anchor', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_VIEW);

    $this->actingAs($user)
        ->get('/reports/notes-collection?week=2026-10-07')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.period.start', '2026-10-05')
            ->where('report.period.end', '2026-10-10')
        );
});

it('exports collectibles as a column for a current period', function () {
    Carbon::setTestNow('2026-10-11 09:00:00');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_EXPORT);

    ($this->releasedLoan)();

    $response = $this->actingAs($user)
        ->get('/reports/notes-collection/export?period=2026-10-12&format=csv');

    $response->assertOk();

    expect($response->getContent())
        ->toContain('Collectibles')
        ->not->toContain('Week of');
});

it('omits collectibles from a closed period export', function () {
    Carbon::setTestNow('2026-10-25 09:00:00');

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::REPORTS_EXPORT);

    ($this->releasedLoan)();

    $response = $this->actingAs($user)
        ->get('/reports/notes-collection/export?period=2026-10-12&format=csv');

    $response->assertOk();

    expect($response->getContent())->not->toContain('Collectibles');
});
