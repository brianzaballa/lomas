<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable monetary value backed by an exact decimal number.
 *
 * Floating point values are rejected on purpose: money must never be
 * calculated with binary floating point. Every operation rounds half up to
 * the currency scale so that repeated operations stay predictable.
 */
final class Money implements JsonSerializable, Stringable
{
    public const SCALE = 2;

    private function __construct(private readonly BigDecimal $amount) {}

    /**
     * Create a money value from an integer number of minor units or an exact decimal string.
     *
     * @throws InvalidArgumentException when a float or a malformed value is given
     */
    public static function of(float|int|string|BigDecimal $amount): self
    {
        return new self(self::decimal($amount)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    /**
     * Convert a value to an exact decimal number, rejecting floats.
     *
     * @throws InvalidArgumentException when a float or a malformed value is given
     */
    private static function decimal(float|int|string|BigDecimal $value): BigDecimal
    {
        if (is_float($value)) {
            throw new InvalidArgumentException('Money cannot be created from a float; use an integer or an exact decimal string.');
        }

        return $value instanceof BigDecimal ? $value : BigDecimal::of($value);
    }

    public static function zero(): self
    {
        return self::of(0);
    }

    public function add(self $other): self
    {
        return new self($this->amount->plus($other->amount)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public function subtract(self $other): self
    {
        return new self($this->amount->minus($other->amount)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    /**
     * Multiply by a whole number, e.g. a quantity of installments.
     */
    public function multiply(float|int|string|BigDecimal $multiplier): self
    {
        return self::of($this->amount->multipliedBy(self::decimal($multiplier)));
    }

    /**
     * Apply a percentage rate, e.g. $money->percentage(10) for ten percent.
     *
     * @throws InvalidArgumentException when a float is given
     */
    public function percentage(float|int|string|BigDecimal $percent): self
    {
        return self::of(
            $this->amount
                ->multipliedBy(self::decimal($percent))
                ->dividedBy(100, self::SCALE, RoundingMode::HalfUp),
        );
    }

    public function divide(float|int|string|BigDecimal $divisor): self
    {
        return self::of($this->amount->dividedBy(self::decimal($divisor), self::SCALE, RoundingMode::HalfUp));
    }

    /**
     * Split the amount into the given number of parts without losing a single minor unit.
     *
     * The parts are as even as possible and the rounding remainder is added to
     * the final part, so the parts always add up to exactly this amount.
     *
     * @return list<self>
     */
    public function allocate(int $parts): array
    {
        if ($parts < 1) {
            throw new InvalidArgumentException('Money must be allocated to at least one part.');
        }

        $share = self::of($this->amount->dividedBy($parts, self::SCALE, RoundingMode::Down));
        $allocated = [];

        for ($index = 1; $index < $parts; $index++) {
            $allocated[] = $share;
        }

        $remainder = $this->subtract($share->multiply($parts - 1));

        $allocated[] = $remainder;

        return $allocated;
    }

    public function abs(): self
    {
        return self::of($this->amount->abs());
    }

    public function negated(): self
    {
        return new self($this->amount->multipliedBy(-1));
    }

    public function compare(self $other): int
    {
        return $this->amount->compareTo($other->amount);
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->compare($other) >= 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return $this->compare($other) <= 0;
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    /**
     * Total of the given amounts.
     */
    public static function sum(self ...$amounts): self
    {
        return array_reduce($amounts, static fn (self $total, self $amount): self => $total->add($amount), self::zero());
    }

    public function toDecimalString(): string
    {
        return (string) $this->amount->toScale(self::SCALE, RoundingMode::Unnecessary);
    }

    public function toString(): string
    {
        return $this->toDecimalString();
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimalString();
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }
}
