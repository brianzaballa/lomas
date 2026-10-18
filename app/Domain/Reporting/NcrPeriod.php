<?php

namespace App\Domain\Reporting;

use Carbon\CarbonImmutable;

/**
 * One Notes Collection Report period.
 *
 * A period is the unit the NCR is viewed and posted in: a Monday to Saturday
 * week, or a decile period (1-10, 11-20, 21 to the last day of the month).
 * The mode lives on the system settings; the period itself is only a pair of
 * dates, so a posted period stays valid even if the mode later changes.
 */
final class NcrPeriod
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $mode,
    ) {}

    /**
     * The date string that identifies this period in a query string.
     */
    public function anchor(): string
    {
        return $this->start->toDateString();
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->greaterThanOrEqualTo($this->start)
            && $date->lessThanOrEqualTo($this->end);
    }

    /**
     * A period is still being processed while its last day has not passed, so
     * overdue is measured before the period starts and the unpaid amounts due
     * inside it are collectibles. A past period is closed: overdue is the
     * arrears as of its last day, exactly as first printed.
     */
    public function isCurrent(CarbonImmutable $today): bool
    {
        return $this->end->greaterThanOrEqualTo($today->startOfDay());
    }

    public function label(): string
    {
        if ($this->start->year === $this->end->year) {
            return $this->start->format('M j').' – '.$this->end->format('M j, Y');
        }

        return $this->start->format('M j, Y').' – '.$this->end->format('M j, Y');
    }
}
