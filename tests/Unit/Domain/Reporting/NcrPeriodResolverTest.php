<?php

use App\Domain\Reporting\NcrPeriodResolver;
use App\Models\SystemSetting;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->resolver = new NcrPeriodResolver;
});

it('resolves a weekly period as monday to saturday', function (string $anchor, string $start, string $end) {
    $period = $this->resolver->resolve(
        SystemSetting::NCR_PERIOD_MODE_WEEKLY,
        CarbonImmutable::parse($anchor),
    );

    expect($period->start->toDateString())->toBe($start)
        ->and($period->end->toDateString())->toBe($end)
        ->and($period->mode)->toBe(SystemSetting::NCR_PERIOD_MODE_WEEKLY);
})->with([
    'monday' => ['2026-10-12', '2026-10-12', '2026-10-17'],
    'midweek' => ['2026-10-14', '2026-10-12', '2026-10-17'],
    'saturday' => ['2026-10-17', '2026-10-12', '2026-10-17'],
    'sunday belongs to the ending week' => ['2026-10-18', '2026-10-12', '2026-10-17'],
    'year boundary' => ['2026-12-31', '2026-12-28', '2027-01-02'],
]);

it('resolves a decile period into 1-10, 11-20 and 21 to end of month', function (string $anchor, string $start, string $end) {
    $period = $this->resolver->resolve(
        SystemSetting::NCR_PERIOD_MODE_DECILE,
        CarbonImmutable::parse($anchor),
    );

    expect($period->start->toDateString())->toBe($start)
        ->and($period->end->toDateString())->toBe($end)
        ->and($period->mode)->toBe(SystemSetting::NCR_PERIOD_MODE_DECILE);
})->with([
    'first day' => ['2026-10-01', '2026-10-01', '2026-10-10'],
    'tenth day' => ['2026-10-10', '2026-10-01', '2026-10-10'],
    'eleventh day' => ['2026-10-11', '2026-10-11', '2026-10-20'],
    'twentieth day' => ['2026-10-20', '2026-10-11', '2026-10-20'],
    'twenty-first day' => ['2026-10-21', '2026-10-21', '2026-10-31'],
    'last day' => ['2026-10-31', '2026-10-21', '2026-10-31'],
    'leap february' => ['2024-02-25', '2024-02-21', '2024-02-29'],
    'year boundary' => ['2026-12-25', '2026-12-21', '2026-12-31'],
]);

it('steps to the previous and next period of the same mode', function () {
    $week = $this->resolver->resolve(SystemSetting::NCR_PERIOD_MODE_WEEKLY, CarbonImmutable::parse('2026-10-14'));

    expect($this->resolver->previous($week)->start->toDateString())->toBe('2026-10-05')
        ->and($this->resolver->previous($week)->end->toDateString())->toBe('2026-10-10')
        ->and($this->resolver->next($week)->start->toDateString())->toBe('2026-10-19')
        ->and($this->resolver->next($week)->end->toDateString())->toBe('2026-10-24');

    $decile = $this->resolver->resolve(SystemSetting::NCR_PERIOD_MODE_DECILE, CarbonImmutable::parse('2026-10-15'));

    expect($this->resolver->previous($decile)->start->toDateString())->toBe('2026-10-01')
        ->and($this->resolver->previous($decile)->end->toDateString())->toBe('2026-10-10')
        ->and($this->resolver->next($decile)->start->toDateString())->toBe('2026-10-21')
        ->and($this->resolver->next($decile)->end->toDateString())->toBe('2026-10-31');
});

it('rejects an unknown mode', function () {
    $this->resolver->resolve('fortnightly', CarbonImmutable::parse('2026-10-14'));
})->throws(InvalidArgumentException::class);

it('knows whether a period is still being processed', function () {
    $period = $this->resolver->resolve(SystemSetting::NCR_PERIOD_MODE_WEEKLY, CarbonImmutable::parse('2026-10-14'));

    expect($period->isCurrent(CarbonImmutable::parse('2026-10-15')))->toBeTrue()
        ->and($period->isCurrent(CarbonImmutable::parse('2026-10-17')))->toBeTrue()
        ->and($period->isCurrent(CarbonImmutable::parse('2026-10-18')))->toBeFalse();
});
