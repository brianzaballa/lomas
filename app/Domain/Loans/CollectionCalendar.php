<?php

namespace App\Domain\Loans;

use App\Models\LoanProduct;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The collection days of a loan, driven by the weekend rules of a product.
 *
 * One place decides whether a date is collected on, so the amortization
 * generator, the reports and the collection screen can never disagree about
 * which days count.
 *
 * The calendar is built from the product rules. It holds no schedule and no
 * amounts: generating the schedule itself is the job of the amortization
 * generator.
 */
class CollectionCalendar
{
    public function __construct(
        protected readonly bool $excludeSundays = true,
        protected readonly bool $excludeSaturdays = true,
    ) {}

    public static function forProduct(LoanProduct $product): self
    {
        return new self($product->exclude_sundays, $product->exclude_saturdays);
    }

    public function isCollectionDay(CarbonInterface $date): bool
    {
        $day = (int) $date->format('N');

        return match ($day) {
            7 => ! $this->excludeSundays,
            6 => ! $this->excludeSaturdays,
            default => true,
        };
    }

    /**
     * The first collection day strictly after the given date.
     */
    public function nextCollectionDay(CarbonInterface $date): CarbonImmutable
    {
        $next = $date->toImmutable()->addDay();

        while (! $this->isCollectionDay($next)) {
            $next = $next->addDay();
        }

        return $next;
    }

    /**
     * The last collection day strictly before the given date.
     */
    public function previousCollectionDay(CarbonInterface $date): CarbonImmutable
    {
        $previous = $date->toImmutable()->subDay();

        while (! $this->isCollectionDay($previous)) {
            $previous = $previous->subDay();
        }

        return $previous;
    }
}
