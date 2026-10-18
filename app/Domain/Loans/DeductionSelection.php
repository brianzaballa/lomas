<?php

namespace App\Domain\Loans;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * What the encoder chose for one particular loan.
 *
 * The product decides which deductions exist. On top of that an encoder may
 * switch any configured deduction off for a single loan, and may add a custom
 * charge that belongs to no product at all. See AGENTS.md section 63.1.3.
 *
 * Both are recorded in the loan's deduction snapshot, which is why the same
 * object is used for the preview and for the persisted draft.
 */
final readonly class DeductionSelection
{
    public const int MAX_CUSTOM_DEDUCTIONS = 10;

    /**
     * @param  list<string>  $excludedCodes  deduction type codes switched off
     * @param  list<array{name: string, amount: string}>  $customDeductions  fixed charges added for this loan only
     */
    public function __construct(
        private array $excludedCodes = [],
        private array $customDeductions = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }

    /**
     * Read the selection out of unvalidated request input.
     *
     * The shape is decided here rather than in a controller, so the preview
     * endpoint, the screen render and the save form all read a selection the
     * same way. Anything unusable is dropped: the caller is expected to have
     * validated it already, and a blank row in a form is not a charge.
     */
    public static function fromInput(mixed $excludedCodes, mixed $customDeductions): self
    {
        $codes = [];

        foreach ((array) $excludedCodes as $code) {
            if (is_string($code) && trim($code) !== '') {
                $codes[] = trim($code);
            }
        }

        $custom = [];

        foreach ((array) $customDeductions as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            $amount = $entry['amount'] ?? null;

            if ($name === '' || ! is_numeric($amount)) {
                continue;
            }

            $custom[] = [
                'name' => $name,
                'amount' => BigDecimal::of((string) $amount)->toScale(2, RoundingMode::HalfUp)->__toString(),
            ];
        }

        return new self($codes, $custom);
    }

    /**
     * A configured deduction the encoder switched off for this loan.
     */
    public function excludes(string $code): bool
    {
        return in_array($code, $this->excludedCodes, true);
    }

    /**
     * The excluded codes, so the encoding screen can restore what was chosen
     * when it is rendered again after a search or a back navigation.
     *
     * @return list<string>
     */
    public function excludedCodes(): array
    {
        return $this->excludedCodes;
    }

    /**
     * A custom charge is always applied. Dropping it means removing it from
     * the form, so there is no "excluded" state for it.
     *
     * @return list<array{name: string, amount: string}>
     */
    public function customDeductions(): array
    {
        return $this->customDeductions;
    }
}
