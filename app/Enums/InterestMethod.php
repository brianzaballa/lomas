<?php

namespace App\Enums;

/**
 * How a loan product calculates its interest.
 *
 * TODO: CONFIRM a reducing balance method is only added when a product needs
 * it. The initial product charges interest on the original amount.
 */
enum InterestMethod: string
{
    case PercentageOfLoanAmount = 'percentage_of_loan_amount';

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
        };
    }
}
