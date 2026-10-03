<?php

namespace App\Enums;

/**
 * How a deduction type produces its amount.
 *
 * A percentage is applied to the gross loan amount. A fixed amount is the same
 * for every loan, regardless of the amount borrowed.
 */
enum DeductionCalculationMethod: string
{
    case PercentageOfLoanAmount = 'percentage_of_loan_amount';

    case FixedAmount = 'fixed_amount';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::PercentageOfLoanAmount => 'Percentage of loan amount',
            self::FixedAmount => 'Fixed amount',
        };
    }

    /**
     * The field this method needs, and the field it must not have.
     *
     * @return array{required: string, forbidden: string}
     */
    public function fields(): array
    {
        return match ($this) {
            self::PercentageOfLoanAmount => ['required' => 'rate', 'forbidden' => 'fixed_amount'],
            self::FixedAmount => ['required' => 'fixed_amount', 'forbidden' => 'rate'],
        };
    }
}
