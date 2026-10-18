<?php

namespace App\Console\Commands;

use App\Actions\Borrowers\CreateBorrower;
use App\Domain\Loans\CollectionCalendar;
use App\Domain\Loans\LoanDraftService;
use App\Domain\Loans\LoanPostService;
use App\Domain\Nodes\NodeIdentity;
use App\Domain\Payments\PaymentAllocator;
use App\Domain\Payments\PaymentNumberGenerator;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Collection;
use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Models\LoanProduct;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RecreateDemoDataCommand extends Command
{
    protected $signature = 'demo:recreate
        {--force : Skip confirmation prompt}
        {--loans=900 : Number of loans to generate}
        {--min-per-area=100 : Minimum borrowers per area}
        {--max-per-area=200 : Maximum borrowers per area}';

    protected $description = 'Wipe and recreate the full LoMaS dummy dataset (dev-only).';

    private const AREAS = ['T1', 'T2', 'T3', 'T4', 'T5', 'SA'];

    private const MIX_WEIGHTS = [
        'completed' => 0.20,
        'good' => 0.30,
        'bad' => 0.15,
        'delinquent' => 0.20,
        'long_overdue' => 0.15,
    ];

    private const START_DATE = '2026-01-01';

    private const END_DATE = '2026-10-14';

    private const LONG_OVERDUE_CUTOFF = '2026-06-30';

    private const MIN_LOAN_AMOUNT = 5000.0;

    private const MAX_LOAN_AMOUNT = 50000.0;

    private const INSERT_CHUNK = 1000;

    /** @var array<string, Area> */
    private array $areas = [];

    public function handle(
        LoanDraftService $draftService,
        
        LoanPostService $postService,
        CreateBorrower $createBorrower,
    ): int {
        if (App::environment('production') && ! $this->option('force')) {
            $this->error('This command cannot run in production without --force.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will WIPE all dummy data (loans, borrowers, payments, collections, amortizations, deductions, audit logs, NCR postings, areas, sequences) and recreate it. Continue?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        ini_set('memory_limit', '1024M');

        $admin = User::query()->first();
        if ($admin) {
            Auth::login($admin);
        }

        $t0 = microtime(true);
        $timed = function (string $label, callable $fn) {
            $s = microtime(true);
            $result = $fn();
            $this->info(sprintf('  [%s] %.1fs', $label, microtime(true) - $s));

            return $result;
        };

        try {
            $this->info('Wiping demo data...');
            $this->wipe();

            $this->info('Seeding areas...');
            $this->seedAreas();

            $this->info('Creating borrowers...');
            $borrowers = $timed('borrowers', fn () => $this->createBorrowers($createBorrower));

            $this->info(sprintf('Creating ~%d loans...', (int) $this->option('loans')));
            $loans = $timed('loans', fn () => $this->createLoans($draftService,  $postService, $borrowers));

            $this->info('Posting collections (this may take a while)...');
            $timed('posting', fn () => $this->postCollections($loans));

            $this->info('Rebuilding amortization caches...');
            $timed('finalise', fn () => $this->finaliseStatuses());

            $this->newLine();
            $this->info(sprintf('Total %.1fs', microtime(true) - $t0));
            $this->info('Done.');
            $this->report();
        } finally {
            Carbon::setTestNow();
            Auth::logout();
        }

        return self::SUCCESS;
    }

    private function wipe(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'payment_allocations',
            'payments',
            'collections',
            'loan_amortizations',
            'loan_deductions',
            'loans',
            'borrowers',
            'areas',
            'audit_logs',
            'ncr_postings',
            'sync_events',
            'sync_batches',
        ] as $table) {
            DB::table($table)->truncate();
        }

        DB::table('sequences')->update(['next_value' => 1]);

        Schema::enableForeignKeyConstraints();
    }

    private function seedAreas(): void
    {
        foreach (self::AREAS as $code) {
            $name = $code === 'SA' ? 'San Antonio' : $code;
            $this->areas[$code] = Area::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }
    }

    /**
     * @return array<int, Borrower>
     */
    private function createBorrowers(CreateBorrower $createBorrower): array
    {
        $borrowers = [];
        $areaList = array_values($this->areas);

        $min = (int) $this->option('min-per-area');
        $max = (int) $this->option('max-per-area');

        $bar = $this->output->createProgressBar(count($areaList) * $max);
        $bar->start();

        foreach ($areaList as $area) {
            $count = rand($min, $max);

            for ($i = 0; $i < $count; $i++) {
                $borrower = $createBorrower->handle([
                    'first_name' => fake()->firstName(),
                    'middle_name' => fake()->optional(0.6)->firstName(),
                    'last_name' => fake()->lastName(),
                    'birth_date' => Carbon::now()->subYears(rand(21, 65))->format('Y-m-d'),
                    'mobile_number' => '09'.rand(100000000, 999999999),
                    'address' => fake()->streetAddress().', Tabaco City, Albay',
                    'default_area_id' => $area->id,
                ]);

                $borrowers[] = $borrower;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        return $borrowers;
    }

    /**
     * @param  array<int, Borrower>  $borrowers
     * @return array<int, Loan>
     */
    private function createLoans(
        LoanDraftService $draftService,
        
        LoanPostService $postService,
        array $borrowers,
    ): array {
        $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();

        $target = (int) $this->option('loans');
        $loans = [];

        $bar = $this->output->createProgressBar($target);
        $bar->start();

        $shuffled = $borrowers;
        shuffle($shuffled);

        // A dedicated group of good payors gets a long-standing history: several
        // loans released in order, each larger than the one before. It is kept
        // to roughly a quarter of the portfolio so the other scenarios still
        // make up the bulk of the book.
        $repeatLoanCap = (int) round($target * 0.25);
        $repeatPoolSize = min(count($shuffled), max(20, (int) ($target / 12)));
        $repeatPool = array_slice($shuffled, 0, $repeatPoolSize);

        foreach ($repeatPool as $borrower) {
            if (count($loans) >= $repeatLoanCap) {
                break;
            }

            $history = rand(3, 6);
            $base = (float) rand(8000, 20000);
            $release = Carbon::createFromFormat('Y-m-d', self::START_DATE)
                ->startOfDay()
                ->addDays(rand(0, 60));

            for ($k = 0; $k < $history && count($loans) < $repeatLoanCap; $k++) {
                $loan = $this->createSingleLoan(
                    $draftService,
                    
                    $postService,
                    $product,
                    $borrower,
                    'good',
                    $release->copy(),
                    $base,
                );

                if ($loan === null) {
                    break;
                }

                $loan->scenario = 'good';
                $loans[] = $loan;
                $bar->advance();

                $base *= 1 + rand(25, 40) / 100;
                $release = $release->copy()->addDays(rand(25, 60));

                if ($release->gt(Carbon::createFromFormat('Y-m-d', self::END_DATE))) {
                    break;
                }
            }
        }

        $attempts = 0;
        $maxAttempts = $target * 4;

        while (count($loans) < $target && $attempts < $maxAttempts) {
            $attempts++;
            $borrower = $borrowers[array_rand($borrowers)];
            $scenario = $this->pickScenario();

            $loan = $this->createSingleLoan(
                $draftService,
                
                $postService,
                $product,
                $borrower,
                $scenario,
            );

            if ($loan === null) {
                continue;
            }

            $loan->scenario = $scenario;
            $loans[] = $loan;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $loans;
    }

    private function pickScenario(): string
    {
        $r = rand(1, 10000) / 10000.0;
        $cumulative = 0.0;

        foreach (self::MIX_WEIGHTS as $scenario => $weight) {
            $cumulative += $weight;
            if ($r <= $cumulative + 0.0001) {
                return $scenario;
            }
        }

        return 'good';
    }

    private function createSingleLoan(
        LoanDraftService $draftService,
        
        LoanPostService $postService,
        LoanProduct $product,
        Borrower $borrower,
        string $scenario,
        ?Carbon $releaseDate = null,
        ?float $base = null,
    ): ?Loan {
        $base ??= (float) rand((int) self::MIN_LOAN_AMOUNT, (int) self::MAX_LOAN_AMOUNT);
        $base = max(self::MIN_LOAN_AMOUNT, min(self::MAX_LOAN_AMOUNT * 1.5, $base));

        if ($releaseDate === null) {
            $start = Carbon::createFromFormat('Y-m-d', self::START_DATE)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', self::END_DATE)->startOfDay();

            // Problem payors are released earlier so their arrears are visible
            // by the reporting cut-off. A long-overdue loan released in August
            // has no installment due before it stops paying.
            $latest = match ($scenario) {
                'long_overdue' => $start->copy()->addMonths(2)->subDay(),
                'bad', 'delinquent' => $start->copy()->addMonths(4)->subDay(),
                default => $end,
            };

            $releaseDate = $start->copy()->addDays(rand(0, (int) $start->diffInDays($latest)))->startOfDay();
        }

        try {
            Carbon::setTestNow($releaseDate);

            $loan = $draftService->create([
                'borrower_id' => $borrower->id,
                'loan_product_id' => $product->id,
                'gross_loan_amount' => number_format($base, 2, '.', ''),
                'application_date' => $releaseDate->copy()->subDays(rand(0, 3))->format('Y-m-d'),
            ]);

            
            $loan = $postService->post($loan);

            $this->assignOverpayDate($loan, $scenario, $product);

            return $loan;
        } catch (\Throwable $e) {
            return null;
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Flag a share of the well-paying loans for an overpayment.
     *
     * The overpay date falls after the loan's final due date so the extra amount
     * cannot settle an installment and stays as an unapplied credit.
     */
    private function assignOverpayDate(Loan $loan, string $scenario, LoanProduct $product): void
    {
        if (! in_array($scenario, ['completed', 'good'], true)) {
            return;
        }

        if (rand(1, 100) > 12) {
            return;
        }

        $lastDue = $loan->amortizations()->max('due_date');
        if ($lastDue === null) {
            return;
        }

        $calendar = (new CollectionCalendar)->forProduct($product);
        $end = Carbon::createFromFormat('Y-m-d', self::END_DATE)->startOfDay();

        $candidate = Carbon::parse($lastDue)->startOfDay()->addDay();
        while ($candidate->lte($end) && ! $calendar->isCollectionDay($candidate)) {
            $candidate->addDay();
        }

        if ($candidate->lte($end) && $calendar->isCollectionDay($candidate)) {
            $loan->overpay_date = $candidate->toDateString();
        }
    }

    /**
     * Build the payment history in bulk.
     *
     * The allocation waterfall is still the domain `PaymentAllocator` and the
     * numbers still come from `PaymentNumberGenerator`, but the payments and
     * allocations are written in batches and the per-installment caches are
     * rebuilt once per loan afterwards. Posting day by day through the service
     * is correct but repaints every installment of every touched loan on every
     * day, which is far too slow for a bulk dataset.
     *
     * @param  array<int, Loan>  $loans
     */
    private function postCollections(array $loans): void
    {
        $product = LoanProduct::query()->where('code', 'LOAN-100D')->firstOrFail();
        $calendar = (new CollectionCalendar)->forProduct($product);

        $nodeId = app(NodeIdentity::class)->id();
        $adminId = Auth::id();
        $allocator = app(PaymentAllocator::class);
        $numbers = app(PaymentNumberGenerator::class);

        $schedules = [];
        $order = [];
        foreach ($loans as $loan) {
            $list = [];
            foreach ($loan->amortizations()->orderBy('installment_number')->get() as $amortization) {
                $list[] = [
                    'id' => (string) $amortization->getKey(),
                    'due_date' => $amortization->due_date->toDateString(),
                    'interest_remaining' => (float) $amortization->interest_due,
                    'principal_remaining' => (float) $amortization->principal_due,
                ];
            }
            $schedules[$loan->id] = $list;
        }

        $start = Carbon::createFromFormat('Y-m-d', self::START_DATE)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', self::END_DATE)->startOfDay();

        $paymentBuffer = [];
        $allocationBuffer = [];
        $collections = [];

        $flush = function () use (&$paymentBuffer, &$allocationBuffer): void {
            if ($paymentBuffer !== []) {
                foreach (array_chunk($paymentBuffer, self::INSERT_CHUNK) as $chunk) {
                    DB::table('payments')->insert($chunk);
                }
                $paymentBuffer = [];
            }
            if ($allocationBuffer !== []) {
                foreach (array_chunk($allocationBuffer, self::INSERT_CHUNK) as $chunk) {
                    DB::table('payment_allocations')->insert($chunk);
                }
                $allocationBuffer = [];
            }
        };

        $bar = $this->output->createProgressBar((int) $start->diffInDays($end) + 1);
        $bar->start();

        $current = $start->copy();
        while ($current->lte($end)) {
            if (! $calendar->isCollectionDay($current)) {
                $current->addDay();
                $bar->advance();

                continue;
            }

            $date = $current->toDateString();
            $timestamp = $current->copy()->endOfDay()->toDateTimeString();

            // Phase one: decide the day's payments and allocate them.
            $dayEntries = [];
            foreach ($loans as $loan) {
                $amount = $this->paymentAmountFor($loan, $date, $schedules[$loan->id]);

                if ($amount === null || $amount <= 0.01) {
                    continue;
                }

                $amount = round($amount, 2);
                $open = [];
                foreach ($schedules[$loan->id] as $installment) {
                    if ($installment['interest_remaining'] > 0.001 || $installment['principal_remaining'] > 0.001) {
                        $open[] = [
                            'loan_amortization_id' => $installment['id'],
                            'interest_remaining' => number_format($installment['interest_remaining'], 2, '.', ''),
                            'principal_remaining' => number_format($installment['principal_remaining'], 2, '.', ''),
                        ];
                    }
                }

                $result = $allocator->allocate(Money::of(number_format($amount, 2, '.', '')), $open);
                $this->applyAllocations($schedules[$loan->id], $result['allocations']);

                $dayEntries[] = [
                    'loan' => $loan,
                    'amount' => $amount,
                    'result' => $result,
                ];
            }

            if ($dayEntries === []) {
                $current->addDay();
                $bar->advance();

                continue;
            }

            // Phase two: draw one block of numbers for the whole day.
            $numbersBlock = $numbers->nextBlock(count($dayEntries), $current);

            $collectionId = $collections[$date]['id'] ?? null;
            if ($collectionId === null) {
                $collectionId = (string) Str::uuid7();
                $collections[$date] = ['id' => $collectionId, 'count' => 0, 'total' => 0.0];
            }

            foreach ($dayEntries as $i => $entry) {
                $loan = $entry['loan'];
                $amount = $entry['amount'];
                $result = $entry['result'];

                $collections[$date]['count']++;
                $collections[$date]['total'] += $amount;

                $paymentId = (string) Str::uuid7();

                $paymentBuffer[] = [
                    'id' => $paymentId,
                    'node_id' => $nodeId,
                    'payment_number' => $numbersBlock[$i],
                    'loan_id' => $loan->id,
                    'collection_id' => $collectionId,
                    'payment_date' => $date,
                    'amount' => number_format($amount, 2, '.', ''),
                    'allocated_amount' => $result['allocated'],
                    'unapplied_amount' => $result['unapplied'],
                    'payment_method' => Payment::METHOD_CASH,
                    'reference_number' => null,
                    'received_by' => $adminId,
                    'status' => Payment::STATUS_POSTED,
                    'reversal_of_payment_id' => null,
                    'reversed_at' => null,
                    'reversed_by' => null,
                    'reversal_reason' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                foreach ($result['allocations'] as $allocation) {
                    $allocationBuffer[] = [
                        'id' => (string) Str::uuid7(),
                        'node_id' => $nodeId,
                        'payment_id' => $paymentId,
                        'loan_amortization_id' => $allocation['loan_amortization_id'],
                        'component' => $allocation['component'],
                        'amount' => $allocation['amount'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            if (count($paymentBuffer) >= self::INSERT_CHUNK) {
                $flush();
            }

            $current->addDay();
            $bar->advance();
        }

        $flush();
        Carbon::setTestNow();

        $collectionRows = [];
        foreach ($collections as $date => $data) {
            $collectionRows[] = [
                'id' => $data['id'],
                'node_id' => $nodeId,
                'business_date' => $date,
                'status' => Collection::STATUS_POSTED,
                'prepared_by' => $adminId,
                'prepared_at' => $date.' 08:00:00',
                'posted_by' => $adminId,
                'posted_at' => $date.' 17:00:00',
                'payment_count' => $data['count'],
                'total_amount' => number_format($data['total'], 2, '.', ''),
                'created_at' => $date.' 08:00:00',
                'updated_at' => $date.' 17:00:00',
            ];
        }

        foreach (array_chunk($collectionRows, self::INSERT_CHUNK) as $chunk) {
            DB::table('collections')->insert($chunk);
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Decide the collection amount for one loan on one date.
     *
     * @param  array<int, array{id: string, due_date: string, interest_remaining: float, principal_remaining: float}>  $schedule
     */
    private function paymentAmountFor(Loan $loan, string $date, array $schedule): ?float
    {
        // The installment due on this exact day, and the total still owed for
        // every installment due on or before it.
        $newDue = 0.0;
        $arrears = 0.0;
        foreach ($schedule as $installment) {
            if ($installment['due_date'] <= $date) {
                $amount = $installment['interest_remaining'] + $installment['principal_remaining'];
                $arrears += $amount;

                if ($installment['due_date'] === $date) {
                    $newDue += $amount;
                }
            }
        }

        if (($loan->overpay_date ?? null) === $date && $arrears <= 0.01) {
            return (float) rand(500, 3000);
        }

        if ($arrears <= 0.01) {
            return null;
        }

        // Well-paying borrowers settle everything they owe whenever they pay,
        // so they catch up after a missed day and finish at maturity. Problem
        // borrowers pay at most the day's own installment, so anything they
        // skip stays in arrears and compounds.
        return match ($loan->scenario ?? 'good') {
            'completed' => $arrears,
            'good' => rand(1, 100) <= 95 ? $arrears : null,
            'bad' => rand(1, 100) <= 60 ? $newDue : null,
            'delinquent' => rand(1, 100) <= 50 ? round($newDue * (rand(30, 80) / 100.0), 2) : null,
            'long_overdue' => $this->longOverdueAmount($date, $newDue),
            default => $arrears,
        };
    }

    private function longOverdueAmount(string $date, float $newDue): ?float
    {
        if ($date > self::LONG_OVERDUE_CUTOFF) {
            return null;
        }

        return rand(1, 100) <= 75 ? $newDue : null;
    }

    /**
     * Reduce the in-memory schedule by the allocations the waterfall produced.
     *
     * @param  array<int, array{id: string, due_date: string, interest_remaining: float, principal_remaining: float}>  $schedule
     * @param  list<array{loan_amortization_id: string, component: string, amount: string}>  $allocations
     */
    private function applyAllocations(array &$schedule, array $allocations): void
    {
        if ($allocations === []) {
            return;
        }

        $index = [];
        foreach ($schedule as $i => $installment) {
            $index[$installment['id']] = $i;
        }

        foreach ($allocations as $allocation) {
            $i = $index[$allocation['loan_amortization_id']] ?? null;
            if ($i === null) {
                continue;
            }

            $value = (float) $allocation['amount'];

            if ($allocation['component'] === PaymentAllocation::COMPONENT_INTEREST) {
                $schedule[$i]['interest_remaining'] = round($schedule[$i]['interest_remaining'] - $value, 2);
            } else {
                $schedule[$i]['principal_remaining'] = round($schedule[$i]['principal_remaining'] - $value, 2);
            }
        }
    }

    /**
     * Rebuild the cached amortization columns and close fully-paid loans.
     *
     * This is the batch equivalent of LoanBalanceCalculator::syncAmortizations()
     * followed by closing loans that owe nothing. Running the calculator per
     * loan would issue one update per installment, which is far too slow for a
     * seed of this size; the result is identical.
     */
    private function finaliseStatuses(): void
    {
        $nodeId = app(NodeIdentity::class)->id();

        DB::update(<<<'SQL'
            UPDATE loan_amortizations AS la
            LEFT JOIN (
                SELECT pa.loan_amortization_id AS aid, SUM(pa.amount) AS paid
                FROM payment_allocations AS pa
                INNER JOIN payments AS p ON p.id = pa.payment_id
                WHERE p.status = ? AND pa.component <> ?
                GROUP BY pa.loan_amortization_id
            ) AS t ON t.aid = la.id
            SET la.paid_amount = COALESCE(t.paid, 0),
                la.remaining_amount = GREATEST(la.total_due - COALESCE(t.paid, 0), 0),
                la.status = CASE
                    WHEN COALESCE(t.paid, 0) <= 0 THEN ?
                    WHEN COALESCE(t.paid, 0) >= la.total_due THEN ?
                    ELSE ?
                END
            WHERE la.node_id = ?
        SQL, [
            Payment::STATUS_POSTED,
            PaymentAllocation::COMPONENT_OTHER,
            LoanAmortization::STATUS_PENDING,
            LoanAmortization::STATUS_PAID,
            LoanAmortization::STATUS_PARTIALLY_PAID,
            $nodeId,
        ]);

        DB::update(<<<'SQL'
            UPDATE loans AS l
            SET l.status = ?, l.updated_at = ?
            WHERE l.node_id = ?
              AND l.status IN (?, ?, ?)
              AND NOT EXISTS (
                  SELECT 1 FROM loan_amortizations AS la
                  WHERE la.loan_id = l.id AND la.remaining_amount > 0
              )
        SQL, [
            Loan::STATUS_COMPLETED,
            now()->toDateTimeString(),
            $nodeId,
            Loan::STATUS_RELEASED,
            Loan::STATUS_ACTIVE,
            Loan::STATUS_PAST_DUE,
        ]);
    }

    private function report(): void
    {
        $this->table(['Metric', 'Count'], [
            ['Borrowers', Borrower::query()->count()],
            ['Loans', Loan::query()->count()],
            ['  Completed', Loan::query()->where('status', Loan::STATUS_COMPLETED)->count()],
            ['  Released', Loan::query()->where('status', Loan::STATUS_RELEASED)->count()],
            ['Amortizations', LoanAmortization::query()->count()],
            ['Collections (posted)', Collection::query()->where('status', Collection::STATUS_POSTED)->count()],
            ['Payments (posted)', Payment::query()->where('status', Payment::STATUS_POSTED)->count()],
            ['Payments with unapplied > 0', Payment::query()->where('unapplied_amount', '>', 0)->count()],
            ['Areas', Area::query()->count()],
        ]);
    }
}
