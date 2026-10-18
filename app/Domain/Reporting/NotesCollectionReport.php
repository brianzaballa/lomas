<?php

namespace App\Domain\Reporting;

use App\Domain\Payments\LoanBalanceCalculator;
use App\Domain\Settings\SystemSettings;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * The Notes Collection Report.
 *
 * The report is built for one NCR period: a Monday to Saturday week or a
 * decile period, depending on the system settings. Every figure is derived
 * from posted payments and the frozen schedule, so the report always
 * reconciles with the transactions and stores no totals of its own. See
 * AGENTS.md sections 34, 35 and 38.
 *
 * The report is a member level ledger: one row per borrower, grouped by area,
 * with a column per day of the period, a total collected, the collectibles
 * due inside a period that is still being processed, and the arrears carried
 * in from before it.
 *
 * While a period is current (its last day has not passed), Overdue counts
 * only what was unpaid before the period started; what is due inside the
 * period and still unpaid is Collectibles. A past period is closed and shows
 * the arrears as of its last day, exactly as it was first printed.
 */
class NotesCollectionReport
{
    public function __construct(
        private readonly SystemSettings $settings,
        private readonly LoanBalanceCalculator $balances,
        private readonly NcrPostingService $postings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(NcrPeriod $period): array
    {
        $start = $period->start;
        $end = $period->end;

        $settings = $this->settings->get();

        $isCurrent = $period->isCurrent(CarbonImmutable::today());

        // A current period measures overdue before it starts, so the amounts
        // due inside it stay visible as collectibles instead of being folded
        // into arrears. A closed period keeps the figure it was printed with.
        $arrearsAsOf = $isCurrent ? $start->subDay() : $end;

        $payments = Payment::query()
            ->where('status', Payment::STATUS_POSTED)
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
            ->with(['loan.borrower', 'loan.area', 'loan.node.area'])
            ->orderBy('payment_date')
            ->orderBy('payment_number')
            ->get();

        $dayHeaders = $this->dayHeaders($start, $end);
        $dayDates = array_column($dayHeaders, 'date');

        $byDate = $payments->groupBy(fn (Payment $payment): string => $payment->payment_date->toDateString());

        $days = [];
        $weekTotal = Money::zero();

        foreach ($dayHeaders as $header) {
            $rows = $byDate->get($header['date'], collect());

            $collected = $rows->reduce(
                fn (Money $carry, Payment $payment): Money => $carry->add(Money::of($payment->amount)),
                Money::zero(),
            );

            $weekTotal = $weekTotal->add($collected);

            $days[] = [
                'date' => $header['date'],
                'weekday' => $header['weekday'],
                'is_today' => CarbonImmutable::parse($header['date'])->isToday(),
                'count' => $rows->count(),
                'collected' => $this->settings->formatAmount($collected),
            ];
        }

        $paymentsByArea = $payments->groupBy(function (Payment $payment): string {
            return $payment->loan->area->name
                ?? $payment->loan->node->area->name
                ?? 'Unassigned';
        });

        $groupedByArea = [];

        foreach ($paymentsByArea->sortKeys() as $areaName => $rows) {
            $areaTotal = $rows->reduce(
                fn (Money $carry, Payment $payment): Money => $carry->add(Money::of($payment->amount)),
                Money::zero(),
            );

            $groupedByArea[] = [
                'area' => $areaName,
                'count' => $rows->count(),
                'total' => $this->settings->formatAmount($areaTotal),
                'payments' => $rows->map(fn (Payment $payment): array => [
                    'date' => $payment->payment_date->toDateString(),
                    'payment_number' => $payment->payment_number,
                    'loan_number' => $payment->loan->loan_number,
                    'borrower_name' => $payment->loan->borrower->fullName(),
                    'payment_method' => $payment->payment_method,
                    'reference_number' => $payment->reference_number,
                    'amount' => $this->settings->formatAmount(Money::of($payment->amount)),
                    'status' => $payment->statusLabel(),
                ])->values()->all(),
            ];
        }

        $ledger = $this->ledger($period, $payments, $dayDates, $isCurrent, $arrearsAsOf);

        $posting = $this->postings->posting($period);

        return [
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'label' => $period->label(),
            'previous_week' => $this->previousAnchor($period),
            'next_week' => $this->nextAnchor($period),
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'label' => $period->label(),
                'mode' => $period->mode,
                'is_current' => $isCurrent,
            ],
            'posting' => [
                'status' => $posting?->status,
                'posted_at' => $posting?->posted_at?->toDateTimeString(),
            ],
            'day_headers' => $dayHeaders,
            'days' => $days,
            'areas' => $ledger['areas'],
            'grand' => $ledger['grand'],
            'total' => $this->settings->formatAmount($weekTotal),
            'total_count' => $payments->count(),
            'business' => [
                'name' => $settings->business_name,
                'address' => $settings->business_address,
                'contact_phone' => $settings->contact_phone,
                'contact_email' => $settings->contact_email,
            ],
            'currency' => $settings->currency,
            'generated_at' => now()->format('M j, Y g:i A'),
            'payments' => $payments->map(fn (Payment $payment): array => [
                'date' => $payment->payment_date->toDateString(),
                'weekday' => $payment->payment_date->format('l'),
                'payment_number' => $payment->payment_number,
                'loan_number' => $payment->loan->loan_number,
                'borrower_name' => $payment->loan->borrower->fullName(),
                'borrower_number' => $payment->loan->borrower->borrower_number,
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'amount' => $this->settings->formatAmount(Money::of($payment->amount)),
                'status' => $payment->statusLabel(),
            ])->values()->all(),
            'grouped_payments_by_area' => $groupedByArea,
        ];
    }

    /**
     * The member level pivot: one row per borrower, grouped by area.
     *
     * A member appears when they were expected to pay in the period, when they
     * paid in it, or when they still owe something by the end of it. The day
     * columns are the posted payments received and the total is their sum.
     *
     * For a current period the arrears column counts only what was unpaid
     * before the period started, and the collectibles column is the unpaid
     * part of the schedule due inside the period. For a closed period the
     * arrears column is the figure it was printed with and collectibles is
     * null.
     *
     * @param  SupportCollection<int, Payment>  $payments
     * @param  list<string>  $dayDates
     * @return array{areas: list<array<string, mixed>>, grand: array<string, mixed>}
     */
    private function ledger(NcrPeriod $period, SupportCollection $payments, array $dayDates, bool $isCurrent, CarbonImmutable $arrearsAsOf): array
    {
        $startDate = $period->start->toDateString();
        $endDate = $period->end->toDateString();

        $paidByLoanDate = [];

        foreach ($payments as $payment) {
            $date = $payment->payment_date->toDateString();

            $paidByLoanDate[$payment->loan_id][$date] = ($paidByLoanDate[$payment->loan_id][$date] ?? Money::zero())
                ->add(Money::of($payment->amount));
        }

        // Which loans have an installment due inside the period, and how much
        // of that is still unpaid, are looked up as sets instead of loading
        // every installment of every loan. Eager loading the schedules would
        // hold one model per installment for the whole book in memory, which a
        // real portfolio does not survive.
        $dueInRange = DB::table('loan_amortizations')
            ->whereBetween('due_date', [$startDate, $endDate])
            ->distinct()
            ->pluck('loan_id')
            ->flip();

        $collectiblesByLoan = $isCurrent
            ? DB::table('loan_amortizations')
                ->selectRaw('loan_id, SUM(remaining_amount) as total')
                ->whereBetween('due_date', [$startDate, $endDate])
                ->where('remaining_amount', '>', 0)
                ->groupBy('loan_id')
                ->pluck('total', 'loan_id')
            : collect();

        $byArea = [];

        Loan::query()
            ->whereIn('status', [
                Loan::STATUS_ACTIVE,
                
            ])
            ->where(function ($query) use ($startDate, $endDate): void {
                $query
                    ->whereHas('amortizations', fn ($installment) => $installment
                        ->whereBetween('due_date', [$startDate, $endDate]))
                    ->orWhereHas('amortizations', fn ($installment) => $installment
                        ->whereDate('due_date', '<=', $endDate)
                        ->where('remaining_amount', '>', 0))
                    ->orWhereHas('payments', fn ($payment) => $payment
                        ->where('status', Payment::STATUS_POSTED)
                        ->whereBetween('payment_date', [$startDate, $endDate]));
            })
            ->with(['borrower', 'area', 'node.area'])
            ->chunkById(1000, function ($loans) use (&$byArea, $dueInRange, $collectiblesByLoan, $paidByLoanDate, $isCurrent, $arrearsAsOf, $dayDates): void {
                $arrearsByLoan = $this->balances->arrearsAsOfMany($loans->modelKeys(), $arrearsAsOf);

                foreach ($loans as $loan) {
                    $hasDueInRange = $dueInRange->has($loan->getKey());

                    $hasPaymentInRange = ! empty($paidByLoanDate[$loan->getKey()]);

                    $arrears = $arrearsByLoan[$loan->getKey()] ?? Money::zero();

                    // Collectibles: the unpaid part of the schedule due inside
                    // the period. Only a period still being processed has them;
                    // a closed period folded them into its arrears figure when
                    // it ended.
                    $collectibles = $isCurrent
                        ? Money::of((string) ($collectiblesByLoan[$loan->getKey()] ?? '0'))
                        : null;

                    $hasCollectibles = $collectibles instanceof Money && $collectibles->isPositive();

                    if (! $hasDueInRange && ! $hasPaymentInRange && ! $arrears->isPositive() && ! $hasCollectibles) {
                        continue;
                    }

                    $borrower = $loan->borrower;
                    $areaName = $loan->area->name ?? $loan->node->area->name ?? 'Unassigned';
                    $key = $borrower->getKey();

                    if (! isset($byArea[$areaName][$key])) {
                        $byArea[$areaName][$key] = [
                            'borrower_number' => $borrower->borrower_number,
                            'borrower_name' => $borrower->fullName(),
                            'last_name' => (string) $borrower->last_name,
                            'first_name' => (string) $borrower->first_name,
                            'middle_name' => (string) ($borrower->middle_name ?? ''),
                            'loan_numbers' => [],
                            'collected' => Money::zero(),
                            'overdue' => Money::zero(),
                            'collectibles' => $isCurrent ? Money::zero() : null,
                            'days' => array_fill_keys($dayDates, Money::zero()),
                        ];
                    }

                    $byArea[$areaName][$key]['loan_numbers'][] = $loan->loan_number;
                    $byArea[$areaName][$key]['overdue'] = $byArea[$areaName][$key]['overdue']->add($arrears);

                    if ($hasCollectibles) {
                        $byArea[$areaName][$key]['collectibles'] = $byArea[$areaName][$key]['collectibles']->add($collectibles);
                    }

                    foreach ($dayDates as $date) {
                        $amount = $paidByLoanDate[$loan->getKey()][$date] ?? null;

                        if ($amount instanceof Money) {
                            $byArea[$areaName][$key]['days'][$date] = $byArea[$areaName][$key]['days'][$date]->add($amount);
                            $byArea[$areaName][$key]['collected'] = $byArea[$areaName][$key]['collected']->add($amount);
                        }
                    }
                }
            });

        ksort($byArea);

        $areas = [];
        $grandCollected = Money::zero();
        $grandOverdue = Money::zero();
        $grandCollectibles = $isCurrent ? Money::zero() : null;
        $grandCount = 0;

        foreach ($byArea as $areaName => $members) {
            $rows = array_values($members);

            usort($rows, fn (array $a, array $b): int => [$a['last_name'], $a['first_name'], $a['middle_name']]
                <=> [$b['last_name'], $b['first_name'], $b['middle_name']]);

            $areaCollected = Money::zero();
            $areaOverdue = Money::zero();
            $areaCollectibles = $isCurrent ? Money::zero() : null;
            $out = [];

            foreach ($rows as $row) {
                $amounts = [];

                foreach ($dayDates as $date) {
                    $amounts[$date] = $row['days'][$date]->isPositive()
                        ? $this->settings->formatAmount($row['days'][$date])
                        : '';
                }

                $areaCollected = $areaCollected->add($row['collected']);
                $areaOverdue = $areaOverdue->add($row['overdue']);

                if ($isCurrent) {
                    $areaCollectibles = $areaCollectibles->add($row['collectibles']);
                }

                $out[] = [
                    'borrower_number' => $row['borrower_number'],
                    'borrower_name' => $row['borrower_name'],
                    'loan_number' => implode(', ', $row['loan_numbers']),
                    'days' => $amounts,
                    'total_collected' => $this->settings->formatAmount($row['collected']),
                    'overdue' => $this->settings->formatAmount($row['overdue']),
                    'is_overdue' => $row['overdue']->isPositive(),
                    'collectibles' => $isCurrent ? $this->settings->formatAmount($row['collectibles']) : null,
                    'has_collectibles' => $isCurrent && $row['collectibles']->isPositive(),
                ];
            }

            $grandCollected = $grandCollected->add($areaCollected);
            $grandOverdue = $grandOverdue->add($areaOverdue);
            $grandCount += count($out);

            if ($isCurrent) {
                $grandCollectibles = $grandCollectibles->add($areaCollectibles);
            }

            $areas[] = [
                'area' => $areaName,
                'count' => count($out),
                'subtotal_collected' => $this->settings->formatAmount($areaCollected),
                'subtotal_overdue' => $this->settings->formatAmount($areaOverdue),
                'has_overdue' => $areaOverdue->isPositive(),
                'subtotal_collectibles' => $isCurrent ? $this->settings->formatAmount($areaCollectibles) : null,
                'has_collectibles' => $isCurrent && $areaCollectibles->isPositive(),
                'members' => $out,
            ];
        }

        return [
            'areas' => $areas,
            'grand' => [
                'count' => $grandCount,
                'total_collected' => $this->settings->formatAmount($grandCollected),
                'total_overdue' => $this->settings->formatAmount($grandOverdue),
                'has_overdue' => $grandOverdue->isPositive(),
                'total_collectibles' => $isCurrent ? $this->settings->formatAmount($grandCollectibles) : null,
                'has_collectibles' => $isCurrent && $grandCollectibles->isPositive(),
            ],
        ];
    }

    /**
     * One header per day in the period.
     *
     * @return list<array{date: string, weekday: string, label: string, short: string}>
     */
    private function dayHeaders(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $headers = [];

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $headers[] = [
                'date' => $date->toDateString(),
                'weekday' => $date->format('l'),
                'label' => $date->format('M j'),
                'short' => $date->format('D'),
            ];
        }

        return $headers;
    }

    /**
     * The anchor date that resolves to the period before this one.
     */
    private function previousAnchor(NcrPeriod $period): string
    {
        return $period->start->subDay()->toDateString();
    }

    /**
     * The anchor date that resolves to the period after this one.
     *
     * The day after a Saturday period is a Sunday, which still resolves into
     * the week that just ended, so the anchor steps past it.
     */
    private function nextAnchor(NcrPeriod $period): string
    {
        $anchor = $period->end->addDay();

        if ($period->mode === SystemSetting::NCR_PERIOD_MODE_WEEKLY && $anchor->isSunday()) {
            $anchor = $anchor->addDay();
        }

        return $anchor->toDateString();
    }
}
