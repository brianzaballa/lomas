<?php

namespace App\Domain\Loans;

use App\Models\Loan;
use App\Models\LoanAmortization;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

/**
 * Builds the frozen collection schedule of a released loan.
 *
 * The schedule is generated once, at release, and never regenerated. See
 * AGENTS.md sections 14 and 63.1.9.
 *
 * Interest is withheld at release, so the schedule collects the gross loan
 * amount and every installment is principal. See section 63.1.2.
 */
class AmortizationScheduleGenerator
{
    /**
     * The schedule for a loan being released.
     *
     * @return list<array{
     *     installment_number: int,
     *     due_date: string,
     *     principal_due: string,
     *     interest_due: string,
     *     total_due: string,
     *     paid_amount: string,
     *     remaining_amount: string,
     *     status: string
     * }>
     */
    public function generate(Loan $loan, CarbonInterface $releaseDate): array
    {
        $product = $loan->loanProduct;

        $dates = CollectionCalendar::forProduct($product)->generateCollectionDates(
            $releaseDate,
            $product->term,
            $product->release_date_is_day_one,
        );

        return $this->installments($loan->total_amount_payable, $dates);
    }

    /**
     * Split the total amount payable across the collection dates.
     *
     * The installments are exact decimals and always sum to the total amount
     * payable. A division that does not come out even is settled on the final
     * installment, so rounding never leaves a residual or invents a peso. See
     * AGENTS.md section 18.
     *
     * @param  list<CarbonInterface>  $dates
     * @return list<array{
     *     installment_number: int,
     *     due_date: string,
     *     principal_due: string,
     *     interest_due: string,
     *     total_due: string,
     *     paid_amount: string,
     *     remaining_amount: string,
     *     status: string
     * }>
     */
    public function installments(string $totalAmountPayable, array $dates): array
    {
        $count = count($dates);

        if ($count === 0) {
            return [];
        }

        $total = BigDecimal::of($totalAmountPayable)->toScale(2, RoundingMode::HalfUp);

        // Rounded down, so the installments before the last can never exceed the
        // total and the final installment carries the non-negative remainder.
        $base = $total->dividedBy((string) $count, 2, RoundingMode::Down);
        $remainder = $total->minus($base->multipliedBy((string) $count));

        $installments = [];

        foreach ($dates as $index => $date) {
            $amount = $index === $count - 1 ? $base->plus($remainder) : $base;
            $value = $amount->__toString();

            $installments[] = [
                'installment_number' => $index + 1,
                'due_date' => $date->toDateString(),
                'principal_due' => $value,
                'interest_due' => '0.00',
                'total_due' => $value,
                'paid_amount' => '0.00',
                'remaining_amount' => $value,
                'status' => LoanAmortization::STATUS_PENDING,
            ];
        }

        return $installments;
    }
}
