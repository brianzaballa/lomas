<?php

namespace Database\Seeders;

use App\Actions\Borrowers\CreateBorrower;
use App\Domain\Loans\CollectionCalendar;
use App\Domain\Loans\LoanApprovalService;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanReleaseService;
use App\Domain\Payments\CollectionPostingService;
use App\Domain\Payments\LoanBalanceCalculator;
use App\Enums\BorrowerStatus;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Demo borrowers, loans and collections, so the reports and the dashboard have
 * something real to show on a development installation.
 *
 * Every row is produced through the same services the application uses, so the
 * schedules are generated exactly as they are at release, payments are posted
 * through the collection day workflow and every report figure is derived from
 * payment allocations. Nothing here reaches around a business rule. See
 * AGENTS.md sections 34 to 39 and 38.
 *
 * Run it explicitly, it is deliberately not part of DatabaseSeeder:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    /**
     * The collection areas the demo borrowers are spread across.
     *
     * Keyed by the area code so a borrower can reference one by code. Areas are
     * created idempotently, so running the seeder again never duplicates them.
     *
     * @var array<string, string>
     */
    protected const AREAS = [
        'MNL' => 'Manila',
        'CAV' => 'Cavite',
        'LAG' => 'Laguna',
        'BUL' => 'Bulacan',
    ];

    /**
     * The demo borrowers, in the order LOAN_PLANS refers to them.
     *
     * `area` points at a code in AREAS; it becomes the borrower's default area,
     * and therefore the area their loan is grouped under on the collection
     * report.
     *
     * @var list<array<string, string|null>>
     */
    protected const BORROWERS = [
        [
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Reyes',
            'suffix' => null, 'birth_date' => '1985-03-14', 'mobile_number' => '09171234501',
            'address' => '12 Mabini St, Barangay Poblacion, Manila', 'area' => 'MNL',
        ],
        [
            'first_name' => 'Jose', 'middle_name' => 'Cruz', 'last_name' => 'dela Cruz',
            'suffix' => null, 'birth_date' => '1979-11-02', 'mobile_number' => '09182345602',
            'address' => '45 Rizal Ave, Barangay San Roque, Manila', 'area' => 'MNL',
        ],
        [
            'first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Villanueva',
            'suffix' => null, 'birth_date' => '1992-06-21', 'mobile_number' => '09193456703',
            'address' => '8 Bonifacio St, Barangay Sta. Cruz, Cavite', 'area' => 'CAV',
        ],
        [
            'first_name' => 'Pedro', 'middle_name' => 'Lim', 'last_name' => 'Bautista',
            'suffix' => null, 'birth_date' => '1974-01-30', 'mobile_number' => '09204567804',
            'address' => '220 Quezon Blvd, Barangay Tondo, Cavite', 'area' => 'CAV',
        ],
        [
            'first_name' => 'Luzviminda', 'middle_name' => 'Garcia', 'last_name' => 'Aquino',
            'suffix' => null, 'birth_date' => '1988-09-09', 'mobile_number' => '09215678905',
            'address' => '31 Aurora Blvd, Barangay Sampaloc, Laguna', 'area' => 'LAG',
        ],
        [
            'first_name' => 'Carlo', 'middle_name' => 'Ramos', 'last_name' => 'Mendoza',
            'suffix' => null, 'birth_date' => '1995-04-17', 'mobile_number' => '09226789006',
            'address' => '7 Legarda St, Barangay Quiapo, Laguna', 'area' => 'LAG',
        ],
        [
            'first_name' => 'Rosa', 'middle_name' => 'Diaz', 'last_name' => 'Fernandez',
            'suffix' => null, 'birth_date' => '1983-12-05', 'mobile_number' => '09237890107',
            'address' => '5 Malhacan Rd, Barangay Malhacan, Bulacan', 'area' => 'BUL',
        ],
        [
            'first_name' => 'Miguel', 'middle_name' => 'Santos', 'last_name' => 'Torres',
            'suffix' => null, 'birth_date' => '1990-07-23', 'mobile_number' => '09248901208',
            'address' => '19 Guiguinto Rd, Barangay Poblacion, Bulacan', 'area' => 'BUL',
        ],
        [
            'first_name' => 'Elena', 'middle_name' => 'Cruz', 'last_name' => 'Ramos',
            'suffix' => null, 'birth_date' => '1996-02-11', 'mobile_number' => '09259012309',
            'address' => '63 Taft Ave, Barangay Ermita, Manila', 'area' => 'MNL',
        ],
        [
            'first_name' => 'Ramon', 'middle_name' => 'Perez', 'last_name' => 'Villamor',
            'suffix' => 'Jr.', 'birth_date' => '1970-05-19', 'mobile_number' => '09260123410',
            'address' => '88 Aguinaldo Hwy, Barangay Poblacion, Cavite', 'area' => 'CAV',
        ],
        [
            'first_name' => 'Corazon', 'middle_name' => 'Bautista', 'last_name' => 'Dizon',
            'suffix' => null, 'birth_date' => '1987-10-27', 'mobile_number' => '09271234511',
            'address' => '42 National Hwy, Barangay Real, Laguna', 'area' => 'LAG',
        ],
        [
            'first_name' => 'Antonio', 'middle_name' => 'Garcia', 'last_name' => 'Salazar',
            'suffix' => null, 'birth_date' => '1993-08-08', 'mobile_number' => '09282345612',
            'address' => '11 MacArthur Hwy, Barangay San Juan, Bulacan', 'area' => 'BUL',
        ],
    ];

    /**
     * The loan each demo borrower receives.
     *
     * days_ago is measured from today, so the schedule always overlaps the
     * current week and the weeks before it, whatever day the seeder is run.
     * The style decides how the borrower pays and therefore which installment
     * statuses the reports and the printed schedule show.
     *
     *   full        pays the whole daily installment
     *   partial     pays half of it, so arrears build up
     *   weekly_skip skips every sixth collection day (a Saturday)
     *   payoff      pays daily, then clears the balance with an extra amount
     *               on the last collection day, leaving an unapplied credit
     *
     * @var list<array{borrower: int, amount: string, days_ago: int, style: string}>
     */
    protected const LOAN_PLANS = [
        ['borrower' => 0, 'amount' => '10000.00', 'days_ago' => 30, 'style' => 'full'],
        ['borrower' => 1, 'amount' => '15000.00', 'days_ago' => 26, 'style' => 'weekly_skip'],
        ['borrower' => 2, 'amount' => '20000.00', 'days_ago' => 20, 'style' => 'partial'],
        ['borrower' => 3, 'amount' => '5000.00', 'days_ago' => 14, 'style' => 'full'],
        ['borrower' => 4, 'amount' => '12000.00', 'days_ago' => 9, 'style' => 'payoff'],
        ['borrower' => 5, 'amount' => '30000.00', 'days_ago' => 3, 'style' => 'full'],
        ['borrower' => 6, 'amount' => '8000.00', 'days_ago' => 25, 'style' => 'full'],
        ['borrower' => 7, 'amount' => '18000.00', 'days_ago' => 18, 'style' => 'partial'],
        ['borrower' => 8, 'amount' => '6000.00', 'days_ago' => 12, 'style' => 'weekly_skip'],
        ['borrower' => 9, 'amount' => '25000.00', 'days_ago' => 6, 'style' => 'full'],
        ['borrower' => 10, 'amount' => '9000.00', 'days_ago' => 4, 'style' => 'payoff'],
        ['borrower' => 11, 'amount' => '17000.00', 'days_ago' => 1, 'style' => 'full'],
    ];

    public function run(): void
    {
        if (Payment::query()->exists()) {
            $this->command->warn('Demo data already present (posted payments exist). Nothing to do.');

            return;
        }

        $admin = User::query()->where('email', config('lomas.admin.email'))->first();

        if ($admin instanceof User) {
            Auth::login($admin);
        }

        try {
            $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

            $areas = $this->ensureAreas();
            $borrowers = $this->createBorrowers($areas);
            $entries = $this->createLoans($product, $borrowers);
            $posted = $this->postCollections($product, $entries);

            $this->command->info(sprintf(
                'Demo data ready: %d areas, %d borrowers, %d loans, %d posted collection days.',
                count($areas),
                count($borrowers),
                count($entries),
                $posted,
            ));
            $this->command->info('Open Reports > Daily Collection Report and Notes Collection Report.');
        } finally {
            Auth::logout();
        }
    }

    /**
     * Create the demo areas, reusing any that already exist by code.
     *
     * @return array<string, Area> keyed by area code
     */
    private function ensureAreas(): array
    {
        $areas = [];

        foreach (self::AREAS as $code => $name) {
            $areas[$code] = Area::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }

        return $areas;
    }

    /**
     * @param  array<string, Area>  $areas
     * @return list<Borrower>
     */
    private function createBorrowers(array $areas): array
    {
        $action = app(CreateBorrower::class);
        $borrowers = [];

        foreach (self::BORROWERS as $attributes) {
            $area = $attributes['area'];
            unset($attributes['area']);

            $borrowers[] = $action->handle([
                ...$attributes,
                'status' => BorrowerStatus::Active->value,
                'default_area_id' => $areas[$area]->getKey(),
            ]);
        }

        return $borrowers;
    }

    /**
     * Encode, approve and release one loan per plan.
     *
     * The release date is backdated by running the real services under a mock
     * clock, so the frozen schedule starts in the past and the collections that
     * follow land in weeks the reports can show.
     *
     * @param  list<Borrower>  $borrowers
     * @return list<array{loan: Loan, style: string, daily: Money}>
     */
    private function createLoans(LoanProduct $product, array $borrowers): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $drafts = app(LoanDraftService::class);
        $approval = app(LoanApprovalService::class);
        $release = app(LoanReleaseService::class);

        $entries = [];

        foreach (self::LOAN_PLANS as $plan) {
            $releaseDate = $today->subDays($plan['days_ago']);

            Carbon::setTestNow($releaseDate);

            try {
                $loan = $drafts->create([
                    'borrower_id' => $borrowers[$plan['borrower']]->getKey(),
                    'loan_product_id' => $product->getKey(),
                    'gross_loan_amount' => $plan['amount'],
                    'application_date' => $releaseDate->toDateString(),
                ]);

                $loan = $approval->approve($loan);
                $loan = $release->release($loan);
            } finally {
                Carbon::setTestNow();
            }

            $entries[] = [
                'loan' => $loan,
                'style' => $plan['style'],
                'daily' => Money::of($loan->gross_loan_amount)->divide($product->term),
            ];
        }

        return $entries;
    }

    /**
     * Post one collection day per valid collection date since the earliest
     * release, so the weekly report and the printed schedules have history.
     *
     * @param  list<array{loan: Loan, style: string, daily: Money}>  $entries
     * @return int the number of collection days posted
     */
    private function postCollections(LoanProduct $product, array $entries): int
    {
        $calendar = CollectionCalendar::forProduct($product);
        $posting = app(CollectionPostingService::class);
        $balances = app(LoanBalanceCalculator::class);

        $today = CarbonImmutable::now()->startOfDay();

        $entries = [...$entries, ...$this->existingCollectableLoans($product, $entries)];

        if ($entries === []) {
            return 0;
        }

        $start = $today;

        foreach ($entries as $entry) {
            $first = $entry['loan']->release_date->toImmutable()->addDay();

            if ($first->lessThan($start)) {
                $start = $first;
            }
        }

        $posted = 0;
        $counters = [];

        for ($date = $start; $date->lessThanOrEqualTo($today); $date = $date->addDay()) {
            if (! $calendar->isCollectionDay($date)) {
                continue;
            }

            $collection = $posting->open($date);
            $staged = 0;

            foreach ($entries as $position => $entry) {
                $loan = $entry['loan'];

                if ($loan->release_date->toImmutable()->greaterThanOrEqualTo($date)) {
                    continue;
                }

                $outstanding = Money::of($balances->summary($loan)['outstanding_balance']);

                if (! $outstanding->isPositive()) {
                    continue;
                }

                $counters[$position] = ($counters[$position] ?? 0) + 1;
                $amount = $this->amountFor($entry, $counters[$position], $outstanding, $date->isSameDay($today));

                if (! $amount->isPositive()) {
                    continue;
                }

                $posting->addPayment($collection, $loan, $amount->toDecimalString());
                $staged++;
            }

            if ($staged > 0) {
                $posting->post($collection);
                $posted++;
            }
        }

        return $posted;
    }

    /**
     * Any loan already in the database that can be collected on, so no released
     * loan is left invisible to the collection screen and the reports.
     *
     * @param  list<array{loan: Loan, style: string, daily: Money}>  $entries
     * @return list<array{loan: Loan, style: string, daily: Money}>
     */
    private function existingCollectableLoans(LoanProduct $product, array $entries): array
    {
        $known = array_map(static fn (array $entry): string => $entry['loan']->getKey(), $entries);

        $loans = Loan::query()
            ->whereIn('status', [Loan::STATUS_RELEASED, Loan::STATUS_ACTIVE, Loan::STATUS_PAST_DUE])
            ->whereNotIn('id', $known)
            ->get();

        return array_values($loans->map(static fn (Loan $loan): array => [
            'loan' => $loan,
            'style' => 'full',
            'daily' => Money::of($loan->gross_loan_amount)->divide($product->term),
        ])->all());
    }

    /**
     * The amount collected from one loan on one collection day.
     *
     * @param  array{loan: Loan, style: string, daily: Money}  $entry
     */
    private function amountFor(array $entry, int $index, Money $outstanding, bool $isLastDay): Money
    {
        return match ($entry['style']) {
            'partial' => $entry['daily']->multiply('0.5'),
            'weekly_skip' => $index % 6 === 5 ? Money::zero() : $entry['daily'],
            'payoff' => $isLastDay ? $outstanding->add(Money::of('500.00')) : $entry['daily'],
            default => $entry['daily'],
        };
    }
}
