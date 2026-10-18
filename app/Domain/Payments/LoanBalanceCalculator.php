<?php

namespace App\Domain\Payments;

use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Derives a loan's balances from its posted payment allocations.
 *
 * Nothing here is stored independently of the transactions: the paid amount is
 * the sum of allocations on posted payments, the outstanding balance is the
 * amount payable minus that sum, and past due is read from the frozen schedule.
 * A reversed payment simply stops counting. See AGENTS.md sections 9, 38 and
 * 63.1.10.
 */
class LoanBalanceCalculator
{
    /**
     * @return array{
     *     total_amount_payable: string,
     *     total_amount_paid: string,
     *     outstanding_balance: string,
     *     unapplied_credit: string,
     *     past_due_amount: string
     * }
     */
    public function summary(Loan $loan): array
    {
        $totalPayable = Money::of($loan->total_amount_payable);
        $paid = $this->effectivePaid($loan);

        $outstanding = $totalPayable->subtract($paid);
        $outstanding = $outstanding->isNegative() ? Money::zero() : $outstanding;

        return [
            'total_amount_payable' => $totalPayable->toDecimalString(),
            'total_amount_paid' => $paid->toDecimalString(),
            'outstanding_balance' => $outstanding->toDecimalString(),
            'unapplied_credit' => $this->unappliedCredit($loan)->toDecimalString(),
            'past_due_amount' => $this->pastDue($loan)->toDecimalString(),
        ];
    }

    /**
     * The installments that still owe something, oldest first.
     *
     * The per-component remainder is derived from the effective allocations, not
     * from the cached columns, so a reversal can never leave the waterfall
     * allocating against an already settled installment.
     *
     * @return list<array{loan_amortization_id: string, interest_remaining: string, principal_remaining: string}>
     */
    public function openInstallments(Loan $loan): array
    {
        $totals = $this->effectiveTotals($loan);

        $open = [];

        foreach ($loan->amortizations()->orderBy('installment_number')->get() as $installment) {
            $paid = $totals[$installment->getKey()] ?? ['interest' => Money::zero(), 'principal' => Money::zero()];

            $interestRemaining = $this->clampPositive(
                Money::of($installment->interest_due)->subtract($paid['interest']),
            );
            $principalRemaining = $this->clampPositive(
                Money::of($installment->principal_due)->subtract($paid['principal']),
            );

            if (! $interestRemaining->isPositive() && ! $principalRemaining->isPositive()) {
                continue;
            }

            $open[] = [
                'loan_amortization_id' => (string) $installment->getKey(),
                'interest_remaining' => $interestRemaining->toDecimalString(),
                'principal_remaining' => $principalRemaining->toDecimalString(),
            ];
        }

        return $open;
    }

    /**
     * Refresh the cached paid, remaining and status columns of the schedule from
     * the effective allocations.
     *
     * The columns are a convenience read; the allocations stay the source of
     * truth. This runs after a posting or a reversal.
     */
    public function syncAmortizations(Loan $loan): void
    {
        $totals = $this->effectiveTotals($loan);

        foreach ($loan->amortizations()->get() as $installment) {
            $paid = $totals[$installment->getKey()] ?? ['interest' => Money::zero(), 'principal' => Money::zero()];

            $totalPaid = $paid['interest']->add($paid['principal']);
            $remaining = Money::of($installment->total_due)->subtract($totalPaid);

            $installment->forceFill([
                'paid_amount' => $totalPaid->toDecimalString(),
                'remaining_amount' => $this->clampPositive($remaining)->toDecimalString(),
                'status' => $this->statusFor($totalPaid, $installment),
            ])->save();
        }
    }

    private function statusFor(Money $paid, LoanAmortization $installment): string
    {
        if (! $paid->isPositive()) {
            return LoanAmortization::STATUS_PENDING;
        }

        return $paid->greaterThanOrEqual(Money::of($installment->total_due))
            ? LoanAmortization::STATUS_PAID
            : LoanAmortization::STATUS_PARTIALLY_PAID;
    }

    private function effectivePaid(Loan $loan): Money
    {
        $paid = Money::zero();

        foreach ($this->effectiveTotals($loan) as $totals) {
            $paid = $paid->add($totals['interest'])->add($totals['principal']);
        }

        return $paid;
    }

    private function unappliedCredit(Loan $loan): Money
    {
        return $loan->payments()
            ->where('status', Payment::STATUS_POSTED)
            ->pluck('unapplied_amount')
            ->reduce(
                fn (Money $carry, $amount): Money => $carry->add(Money::of((string) $amount)),
                Money::zero(),
            );
    }

    private function pastDue(Loan $loan): Money
    {
        return $loan->amortizations()
            ->where('remaining_amount', '>', 0)
            ->whereDate('due_date', '<', Carbon::now()->toDateString())
            ->pluck('remaining_amount')
            ->reduce(
                fn (Money $carry, $amount): Money => $carry->add(Money::of((string) $amount)),
                Money::zero(),
            );
    }

    /**
     * The amount still owed at the end of a given date.
     *
     * This is the historical counterpart of pastDue(): it counts only payments
     * dated on or before the date, so reprinting a past period always produces
     * the same figure. Every installment due on or before the date that is not
     * fully settled by then contributes its remainder, which is what makes
     * arrears carry forward week over week. See AGENTS.md sections 34 to 38.
     */
    public function arrearsAsOf(Loan $loan, CarbonInterface $asOf): Money
    {
        $asOfDate = $asOf->toDateString();
        $totals = $this->effectiveTotalsAsOf($loan, $asOfDate);

        $arrears = Money::zero();

        foreach ($loan->amortizations()->whereDate('due_date', '<=', $asOfDate)->get() as $installment) {
            $paid = $totals[$installment->getKey()] ?? ['interest' => Money::zero(), 'principal' => Money::zero()];

            $remaining = Money::of($installment->total_due)
                ->subtract($paid['interest'])
                ->subtract($paid['principal']);

            $arrears = $arrears->add($this->clampPositive($remaining));
        }

        return $arrears;
    }

    /**
     * Arrears as of a date for many loans at once, keyed by loan id.
     *
     * Produces exactly the same figure as calling arrearsAsOf() once per loan,
     * but derives it in the database so a report does not have to hydrate every
     * installment of the whole book to sum them. See AGENTS.md sections 34 to
     * 38.
     *
     * @param  iterable<string>  $loanIds
     * @return array<string, Money>
     */
    public function arrearsAsOfMany(iterable $loanIds, CarbonInterface $asOf): array
    {
        $ids = [];

        foreach ($loanIds as $id) {
            $ids[] = (string) $id;
        }

        if ($ids === []) {
            return [];
        }

        $asOfDate = $asOf->toDateString();

        $rows = DB::table('loan_amortizations as la')
            ->leftJoinSub(
                DB::table('payment_allocations as pa')
                    ->join('payments as p', 'p.id', '=', 'pa.payment_id')
                    ->where('p.status', Payment::STATUS_POSTED)
                    ->whereDate('p.payment_date', '<=', $asOfDate)
                    ->where('pa.component', '<>', PaymentAllocation::COMPONENT_OTHER)
                    ->whereIn('pa.loan_amortization_id', function ($query) use ($ids): void {
                        $query->select('id')->from('loan_amortizations')->whereIn('loan_id', $ids);
                    })
                    ->groupBy('pa.loan_amortization_id')
                    ->selectRaw('pa.loan_amortization_id, SUM(pa.amount) as paid'),
                'a',
                'a.loan_amortization_id',
                '=',
                'la.id',
            )
            ->whereIn('la.loan_id', $ids)
            ->whereDate('la.due_date', '<=', $asOfDate)
            ->groupBy('la.loan_id')
            ->selectRaw('la.loan_id, SUM(CASE WHEN la.total_due - COALESCE(a.paid, 0) > 0 THEN la.total_due - COALESCE(a.paid, 0) ELSE 0 END) as arrears')
            ->pluck('arrears', 'loan_id');

        $arrears = [];

        foreach ($rows as $loanId => $amount) {
            $arrears[(string) $loanId] = Money::of((string) $amount);
        }

        return $arrears;
    }

    /**
     * Per-installment effective totals, keyed by installment id.
     *
     * @return array<string, array{interest: Money, principal: Money}>
     */
    private function effectiveTotals(Loan $loan): array
    {
        return $this->totalsByInstallment(
            PaymentAllocation::query()
                ->whereHas('payment', fn ($query) => $query
                    ->where('loan_id', $loan->getKey())
                    ->where('status', Payment::STATUS_POSTED))
                ->get(['loan_amortization_id', 'component', 'amount']),
        );
    }

    /**
     * Effective totals as they stood on a given date.
     *
     * Only posted payments dated on or before the date count, so an allocation
     * made later never rewrites an earlier period's arrears.
     *
     * @return array<string, array{interest: Money, principal: Money}>
     */
    private function effectiveTotalsAsOf(Loan $loan, string $asOfDate): array
    {
        return $this->totalsByInstallment(
            PaymentAllocation::query()
                ->whereHas('payment', fn ($query) => $query
                    ->where('loan_id', $loan->getKey())
                    ->where('status', Payment::STATUS_POSTED)
                    ->whereDate('payment_date', '<=', $asOfDate))
                ->get(['loan_amortization_id', 'component', 'amount']),
        );
    }

    /**
     * Sum allocations into interest and principal per installment.
     *
     * @param  EloquentCollection<int, PaymentAllocation>  $allocations
     * @return array<string, array{interest: Money, principal: Money}>
     */
    private function totalsByInstallment(EloquentCollection $allocations): array
    {
        $totals = [];

        foreach ($allocations->groupBy('loan_amortization_id') as $installmentId => $rows) {
            $interest = Money::zero();
            $principal = Money::zero();

            foreach ($rows as $row) {
                $amount = Money::of($row->amount);

                if ($row->component === PaymentAllocation::COMPONENT_PRINCIPAL) {
                    $principal = $principal->add($amount);
                } elseif ($row->component !== PaymentAllocation::COMPONENT_OTHER) {
                    $interest = $interest->add($amount);
                }
            }

            $totals[(string) $installmentId] = ['interest' => $interest, 'principal' => $principal];
        }

        return $totals;
    }

    private function clampPositive(Money $amount): Money
    {
        return $amount->isPositive() ? $amount : Money::zero();
    }
}
