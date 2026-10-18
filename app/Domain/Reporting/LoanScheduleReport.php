<?php

namespace App\Domain\Reporting;

use App\Domain\Payments\LoanBalanceCalculator;
use App\Domain\Settings\SystemSettings;
use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Support\Money;

/**
 * Builds the print data for a loan's amortization schedule.
 *
 * This is a read model: it formats the frozen schedule for a dedicated print
 * layout and holds no financial rule of its own. Long schedules are split into
 * side-by-side columns so a 100-day loan fits on far fewer sheets of paper.
 * See AGENTS.md sections 18, 36, 57 and 63.1.9.
 */
class LoanScheduleReport
{
    /**
     * Installment rows kept in one printed column before the schedule wraps.
     *
     * Tuned for A4 portrait: tall enough to use the page, short enough that the
     * header and summary still fit above the first column.
     */
    private const MAX_ROWS_PER_COLUMN = 45;

    public function __construct(
        private readonly SystemSettings $settings,
        private readonly LoanBalanceCalculator $balances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Loan $loan): array
    {
        $loan->loadMissing(['borrower', 'loanProduct', 'amortizations']);

        $settings = $this->settings->get();

        $installments = [];

        foreach ($loan->amortizations->sortBy('installment_number') as $installment) {
            $installments[] = [
                'number' => $installment->installment_number,
                'due_date' => $installment->due_date->format('D M j, Y'),
                'principal' => $this->settings->formatAmount(Money::of($installment->principal_due)),
                'interest' => $this->settings->formatAmount(Money::of($installment->interest_due)),
                'total' => $this->settings->formatAmount(Money::of($installment->total_due)),
                'paid' => $this->settings->formatAmount(Money::of($installment->paid_amount)),
                'balance' => $this->settings->formatAmount(Money::of($installment->remaining_amount)),
                'status' => $installment->statusLabel(),
            ];
        }

        $hasInterest = $loan->amortizations
            ->contains(fn (LoanAmortization $installment): bool => Money::of($installment->interest_due)->isPositive());

        $balance = $this->balances->summary($loan);

        return [
            'business' => [
                'name' => $settings->business_name,
                'address' => $settings->business_address,
                'contact_phone' => $settings->contact_phone,
                'contact_email' => $settings->contact_email,
            ],
            'title' => 'Amortization Schedule',
            'loan' => [
                'number' => $loan->loan_number,
                'status' => $loan->statusLabel(),
                'borrower_name' => $loan->borrower->fullName(),
                'borrower_number' => $loan->borrower->borrower_number,
                'product_name' => $loan->loanProduct->name,
                'product_code' => $loan->loanProduct->code,
                'release_date' => $loan->release_date?->format('M j, Y') ?? '—',
                'application_date' => $loan->application_date?->format('M j, Y') ?? '—',
            ],
            'summary' => [
                'gross_loan_amount' => $this->settings->formatAmount(Money::of($loan->gross_loan_amount)),
                'interest_amount' => $this->settings->formatAmount(Money::of($loan->interest_amount)),
                'deductions_amount' => $this->settings->formatAmount(Money::of($loan->deductions_amount)),
                'net_proceeds_amount' => $this->settings->formatAmount(Money::of($loan->net_proceeds_amount)),
                'total_amount_payable' => $this->settings->formatAmount(Money::of($loan->total_amount_payable)),
                'total_amount_paid' => $this->settings->formatAmount(Money::of($balance['total_amount_paid'])),
                'outstanding_balance' => $this->settings->formatAmount(Money::of($balance['outstanding_balance'])),
            ],
            'currency' => $settings->currency,
            'has_interest' => $hasInterest,
            'installments' => $installments,
            'columns' => $this->columns($installments),
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    }

    /**
     * Split the schedule into balanced, side-by-side columns.
     *
     * @param  list<array<string, mixed>>  $installments
     * @return list<list<array<string, mixed>>>
     */
    private function columns(array $installments): array
    {
        $count = count($installments);

        if ($count === 0) {
            return [];
        }

        $columnCount = max(1, (int) ceil($count / self::MAX_ROWS_PER_COLUMN));
        $rowsPerColumn = max(1, (int) ceil($count / $columnCount));

        return array_chunk($installments, $rowsPerColumn);
    }
}
