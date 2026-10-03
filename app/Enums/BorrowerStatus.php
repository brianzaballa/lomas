<?php

namespace App\Enums;

/**
 * The lifecycle of a borrower record.
 *
 * A borrower is never deleted, because loans and payments must always be able
 * to resolve the person they belong to. An inactive borrower cannot receive a
 * new loan but keeps its loans, payments and history.
 */
enum BorrowerStatus: string
{
    case Active = 'active';

    case Inactive = 'inactive';

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
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
