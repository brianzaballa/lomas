<?php

use App\Support\Money;

it('rejects floats so money is never calculated with binary floating point', function () {
    Money::of(10.5);
})->throws(InvalidArgumentException::class);

it('rounds to the currency scale', function () {
    expect(Money::of('1000.005')->toDecimalString())->toBe('1000.01')
        ->and(Money::of('1000.004')->toDecimalString())->toBe('1000.00')
        ->and(Money::of('1000')->toDecimalString())->toBe('1000.00');
});

it('accepts integers and exact decimal strings', function () {
    expect(Money::of(1100)->toDecimalString())->toBe('1100.00')
        ->and(Money::of('8850.50')->toDecimalString())->toBe('8850.50')
        ->and(Money::of('-0.05')->toDecimalString())->toBe('-0.05');
});

it('rejects grouped input so every amount has one canonical form', function () {
    Money::of('8,850.50');
})->throws(Exception::class);

it('adds and subtracts exactly', function () {
    expect(Money::of('0.10')->add(Money::of('0.20'))->toDecimalString())->toBe('0.30')
        ->and(Money::of('11000.00')->subtract(Money::of('1150.00'))->toDecimalString())->toBe('9850.00');
});

it('calculates a percentage of the loan amount', function () {
    expect(Money::of('10000')->percentage(10)->toDecimalString())->toBe('1000.00')
        ->and(Money::of('10000')->percentage(1)->toDecimalString())->toBe('100.00')
        ->and(Money::of('12345.67')->percentage('2.5')->toDecimalString())->toBe('308.64');
});

it('multiplies by whole numbers such as installment counts', function () {
    expect(Money::of('110.00')->multiply(100)->toDecimalString())->toBe('11000.00');
});

it('keeps every minor unit when allocating an amount', function (string $amount, int $parts, string $expectedTotal) {
    $allocated = Money::of($amount)->allocate($parts);

    expect($allocated)->toHaveCount($parts)
        ->and(Money::sum(...$allocated)->toDecimalString())->toBe($expectedTotal);
})->with([
    ['11000.00', 100, '11000.00'],
    ['100.00', 3, '100.00'],
    ['0.05', 3, '0.05'],
    ['1.00', 4, '1.00'],
    ['999999999.99', 7, '999999999.99'],
]);

it('spreads the rounding remainder over the last installment', function () {
    $allocated = Money::of('100.00')->allocate(3);

    expect(array_map(fn (Money $money): string => $money->toDecimalString(), $allocated))
        ->toBe(['33.33', '33.33', '33.34']);
});

it('refuses to allocate to fewer than one part', function () {
    Money::of('100.00')->allocate(0);
})->throws(InvalidArgumentException::class);

it('compares amounts', function () {
    expect(Money::of('100.00')->greaterThan(Money::of('99.99')))->toBeTrue()
        ->and(Money::of('100.00')->equals(Money::of('100')))->toBeTrue()
        ->and(Money::of('100.00')->lessThan(Money::of('100.01')))->toBeTrue()
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::of('-5.00')->isNegative())->toBeTrue()
        ->and(Money::of('-5.00')->abs()->toDecimalString())->toBe('5.00');
});

it('serialises as an exact decimal string', function () {
    expect(Money::of('8850.50')->jsonSerialize())->toBe('8850.50')
        ->and(json_encode(Money::of('8850.50')))->toBe('"8850.50"')
        ->and((string) Money::of('8850.50'))->toBe('8850.50');
});

it('sums a list of amounts', function () {
    expect(Money::sum(Money::of('110.10'), Money::of('110.20'), Money::of('0.70'))->toDecimalString())
        ->toBe('221.00');
});
