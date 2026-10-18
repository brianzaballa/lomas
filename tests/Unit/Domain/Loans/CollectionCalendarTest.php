<?php

use App\Domain\Loans\CollectionCalendar;
use App\Models\LoanProduct;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The last test builds a calendar from a stored product.
uses(RefreshDatabase::class, TestCase::class);

/**
 * 1 October 2026 is a Thursday, so the week below runs:
 *
 *   Thu 01  Fri 02  Sat 03  Sun 04  Mon 05  Tue 06  Wed 07  Thu 08
 */
function day(int $octoberDay): CarbonImmutable
{
    return CarbonImmutable::parse("2026-10-{$octoberDay}");
}

it('collects Monday to Saturday when only Sundays are excluded', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: false);

    expect($calendar->isCollectionDay(day(1)))->toBeTrue()
        ->and($calendar->isCollectionDay(day(2)))->toBeTrue()
        ->and($calendar->isCollectionDay(day(3)))->toBeTrue()
        ->and($calendar->isCollectionDay(day(4)))->toBeFalse();
});

it('collects Monday to Friday when both weekend days are excluded', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: true);

    expect($calendar->isCollectionDay(day(2)))->toBeTrue()
        ->and($calendar->isCollectionDay(day(3)))->toBeFalse()
        ->and($calendar->isCollectionDay(day(4)))->toBeFalse();
});

it('skips Sunday to reach the next collection day', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: true);

    // Friday moves to Monday.
    expect($calendar->nextCollectionDay(day(2))->toDateString())->toBe('2026-10-05')
        // Saturday moves past Saturday and Sunday.
        ->and($calendar->nextCollectionDay(day(3))->toDateString())->toBe('2026-10-05')
        // Sunday moves to Monday.
        ->and($calendar->nextCollectionDay(day(4))->toDateString())->toBe('2026-10-05');
});

it('keeps Saturday when Saturday is collected', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: false);

    expect($calendar->nextCollectionDay(day(2))->toDateString())->toBe('2026-10-03')
        ->and($calendar->nextCollectionDay(day(3))->toDateString())->toBe('2026-10-05');
});

it('walks back to the previous collection day', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: true);

    expect($calendar->previousCollectionDay(day(5))->toDateString())->toBe('2026-10-02')
        ->and($calendar->previousCollectionDay(day(4))->toDateString())->toBe('2026-10-02');
});

it('crosses a month and a year boundary', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: false);

    // Tuesday 29 September 2026 is followed by Wednesday 30 September.
    expect($calendar->nextCollectionDay(CarbonImmutable::parse('2026-09-29'))->toDateString())
        ->toBe('2026-09-30')
        // Friday 30 October 2026 is followed by Saturday 31 October.
        ->and($calendar->nextCollectionDay(CarbonImmutable::parse('2026-10-30'))->toDateString())
        ->toBe('2026-10-31')
        // Friday 31 December 2026 is followed by Saturday 1 January 2027.
        ->and($calendar->nextCollectionDay(CarbonImmutable::parse('2026-12-31'))->toDateString())
        ->toBe('2027-01-01');
});

it('handles the leap day', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: false);

    // Saturday 29 February 2028 is a collection day, so the next is Monday.
    expect($calendar->nextCollectionDay(CarbonImmutable::parse('2028-02-29'))->toDateString())
        ->toBe('2028-03-01');
});

it('excludes Sundays always by default', function () {
    expect((new CollectionCalendar)->isCollectionDay(day(4)))->toBeFalse()
        ->and((new CollectionCalendar)->isCollectionDay(day(1)))->toBeTrue();
});

it('builds the calendar from a product', function () {
    $product = LoanProduct::factory()->create([
        'exclude_sundays' => true,
        'exclude_saturdays' => false,
    ]);

    $calendar = CollectionCalendar::forProduct($product);

    expect($product->isCollectionDay(day(3)))->toBeTrue()
        ->and($product->isCollectionDay(day(4)))->toBeFalse()
        ->and($calendar->nextCollectionDay(day(3))->toDateString())->toBe('2026-10-05');
});

it('generates the requested number of collection days', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: false);

    // Thursday 1 October is skipped by default, then Fri, Sat, (skip Sun), Mon, Tue.
    $dates = $calendar->generateCollectionDates(day(1), 5);

    expect(array_map(fn ($date) => $date->toDateString(), $dates))->toBe([
        '2026-10-02',
        '2026-10-03',
        '2026-10-05',
        '2026-10-06',
        '2026-10-07',
    ]);
});

it('counts the start date as the first day only when asked', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: false);

    $withStart = $calendar->generateCollectionDates(day(1), 2, includeStart: true);
    $withoutStart = $calendar->generateCollectionDates(day(1), 2);

    expect($withStart[0]->toDateString())->toBe('2026-10-01')
        ->and($withoutStart[0]->toDateString())->toBe('2026-10-02');
});

it('moves past a non-collection start date even when including it', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: false);

    // Sunday 4 October is never a collection day.
    $dates = $calendar->generateCollectionDates(day(4), 1, includeStart: true);

    expect($dates[0]->toDateString())->toBe('2026-10-05');
});

it('generates an exact term across a month and year boundary', function () {
    $calendar = new CollectionCalendar(excludeSundays: true, excludeSaturdays: false);

    $dates = $calendar->generateCollectionDates(CarbonImmutable::parse('2026-12-20'), 100);

    expect($dates)->toHaveCount(100)
        ->and($dates[0]->toDateString())->toBe('2026-12-21')
        // No Sunday is ever present.
        ->and(collect($dates)->contains(fn ($date) => $date->dayOfWeek === CarbonImmutable::SUNDAY))->toBeFalse();
});

it('returns no dates when the term is zero', function () {
    $calendar = new CollectionCalendar;

    expect($calendar->generateCollectionDates(day(1), 0))->toBe([]);
});
