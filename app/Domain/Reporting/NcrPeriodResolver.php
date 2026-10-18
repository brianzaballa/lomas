<?php

namespace App\Domain\Reporting;

use App\Models\SystemSetting;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Resolves a date to the Notes Collection Report period that contains it.
 *
 * weekly: Monday to Saturday. The report never runs on Sunday because no
 * collection day falls on it.
 *
 * decile: the 1st to the 10th, the 11th to the 20th, and the 21st to the last
 * day of the month.
 *
 * Navigation (previous / next) steps to the adjacent period of the same mode,
 * so a caller never has to know the boundaries of either mode.
 */
class NcrPeriodResolver
{
    public function resolve(string $mode, CarbonImmutable $anchor): NcrPeriod
    {
        $anchor = $anchor->startOfDay();

        return match ($mode) {
            SystemSetting::NCR_PERIOD_MODE_WEEKLY => $this->weekly($anchor),
            SystemSetting::NCR_PERIOD_MODE_DECILE => $this->decile($anchor),
            default => throw new InvalidArgumentException("Unknown NCR period mode [{$mode}]."),
        };
    }

    public function previous(NcrPeriod $period): NcrPeriod
    {
        return $this->resolve($period->mode, $period->start->subDay());
    }

    public function next(NcrPeriod $period): NcrPeriod
    {
        // The day after a Saturday period is a Sunday, which still resolves
        // into the period that just ended, so step a day at a time until the
        // resolution lands past it.
        $anchor = $period->end->addDay();

        do {
            $next = $this->resolve($period->mode, $anchor);

            if ($next->start->lessThanOrEqualTo($period->end)) {
                $anchor = $anchor->addDay();
            }
        } while ($next->start->lessThanOrEqualTo($period->end));

        return $next;
    }

    private function weekly(CarbonImmutable $anchor): NcrPeriod
    {
        $start = $anchor->startOfWeek(CarbonImmutable::MONDAY);

        return new NcrPeriod($start, $start->addDays(5), SystemSetting::NCR_PERIOD_MODE_WEEKLY);
    }

    private function decile(CarbonImmutable $anchor): NcrPeriod
    {
        $start = match (true) {
            $anchor->day <= 10 => $anchor->setDay(1),
            $anchor->day <= 20 => $anchor->setDay(11),
            default => $anchor->setDay(21),
        };

        // The last period of the month runs to its end; the others are ten days.
        $end = $start->day === 21 ? $start->endOfMonth() : $start->addDays(9);

        return new NcrPeriod($start, $end, SystemSetting::NCR_PERIOD_MODE_DECILE);
    }
}
