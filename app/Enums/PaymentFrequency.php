<?php

namespace App\Enums;

/**
 * How often a loan product collects.
 *
 * Only daily collection is supported today.
 */
enum PaymentFrequency: string
{
    case Daily = 'daily';

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
            self::Daily => 'Daily',
        };
    }
}
