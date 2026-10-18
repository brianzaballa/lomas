<?php

use App\Domain\Loans\AmortizationScheduleGenerator;
use App\Models\LoanAmortization;
use Carbon\CarbonImmutable;

function dates(string ...$dates): array
{
    return array_map(fn (string $date) => CarbonImmutable::parse($date), $dates);
}

it('splits an even amount into equal installments', function () {
    $installments = app(AmortizationScheduleGenerator::class)
        ->installments('10000.00', dates('2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'));

    expect($installments)->toHaveCount(4);

    foreach ($installments as $installment) {
        expect($installment['total_due'])->toBe('2500.00')
            ->and($installment['principal_due'])->toBe('2500.00')
            ->and($installment['interest_due'])->toBe('0.00')
            ->and($installment['paid_amount'])->toBe('0.00')
            ->and($installment['remaining_amount'])->toBe('2500.00')
            ->and($installment['status'])->toBe(LoanAmortization::STATUS_PENDING);
    }
});

it('settles a non-even division on the final installment', function () {
    $installments = app(AmortizationScheduleGenerator::class)
        ->installments('10000.00', dates('2026-10-05', '2026-10-06', '2026-10-07'));

    expect(array_column($installments, 'total_due'))->toBe([
        '3333.33',
        '3333.33',
        '3333.34',
    ]);
});

it('settles a two-cent recurring remainder on the final installment', function () {
    $installments = app(AmortizationScheduleGenerator::class)
        ->installments('1000.01', dates('2026-10-05', '2026-10-06', '2026-10-07'));

    expect(array_column($installments, 'total_due'))->toBe([
        '333.33',
        '333.33',
        '333.35',
    ]);
});

it('always sums the installments to the exact total payable', function () {
    $generator = app(AmortizationScheduleGenerator::class);

    foreach (['10000.00', '1000.01', '999.99', '0.05', '123456789.01'] as $total) {
        foreach ([1, 3, 7, 100] as $count) {
            $installments = $generator->installments(
                $total,
                array_map(fn (int $i) => CarbonImmutable::parse('2026-10-05')->addDays($i), range(0, $count - 1)),
            );

            $sum = '0.00';

            foreach ($installments as $installment) {
                $sum = bcadd($sum, $installment['total_due'], 2);
            }

            expect($sum)->toBe($total, "total {$total} over {$count}");
        }
    }
});

it('carries the whole amount on the single installment when there is one day', function () {
    $installments = app(AmortizationScheduleGenerator::class)
        ->installments('10000.00', dates('2026-10-05'));

    expect($installments)->toHaveCount(1)
        ->and($installments[0]['total_due'])->toBe('10000.00');
});

it('keeps sub-cent totals exact on the final installment', function () {
    $installments = app(AmortizationScheduleGenerator::class)
        ->installments('0.01', dates('2026-10-05', '2026-10-06', '2026-10-07'));

    expect(array_column($installments, 'total_due'))->toBe([
        '0.00',
        '0.00',
        '0.01',
    ]);
});

it('returns no installments when there are no dates', function () {
    expect(app(AmortizationScheduleGenerator::class)->installments('10000.00', []))->toBe([]);
});
