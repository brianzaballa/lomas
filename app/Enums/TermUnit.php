<?php

namespace App\Enums;

/**
 * The unit the term of a loan product is expressed in.
 *
 * Only collection days are supported today. The term always counts valid
 * collection days, never calendar days, so weekly and monthly products are
 * added here when a product actually needs them.
 */
enum TermUnit: string
{
    case CollectionDays = 'collection_days';

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
            self::CollectionDays => 'Collection days',
        };
    }
}
