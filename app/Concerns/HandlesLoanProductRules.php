<?php

namespace App\Concerns;

use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\TermUnit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\Rule;

/**
 * The validation rules shared by the loan product create and update forms.
 *
 * A product may only offer a term unit or a payment frequency that the
 * collection calendar and the amortization generator actually implement, so a
 * product can never be configured in a way the engine cannot honour.
 */
trait HandlesLoanProductRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function loanProductRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('loan_products', 'code'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'term' => ['required', 'integer', 'min:1', 'max:3650'],
            'term_unit' => ['required', Rule::in(TermUnit::values())],
            'payment_frequency' => ['required', Rule::in(PaymentFrequency::values())],
            'exclude_sundays' => ['required', 'boolean'],
            'exclude_saturdays' => ['required', 'boolean'],
            'release_date_is_day_one' => ['required', 'boolean'],
            'interest_method' => ['required', Rule::in(InterestMethod::values())],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * The validated product, with the code upper cased, the rate carried as an
     * exact decimal string and the switches as real booleans.
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     description: string|null,
     *     term: int,
     *     term_unit: string,
     *     payment_frequency: string,
     *     exclude_sundays: bool,
     *     exclude_saturdays: bool,
     *     release_date_is_day_one: bool,
     *     interest_method: string,
     *     interest_rate: string,
     *     is_active: bool
     * }
     */
    public function loanProductAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'code' => strtoupper($this->validated('code')),
            'description' => $this->validated('description'),
            'term' => (int) $this->validated('term'),
            'term_unit' => $this->validated('term_unit'),
            'payment_frequency' => $this->validated('payment_frequency'),
            'exclude_sundays' => $this->boolean('exclude_sundays'),
            'exclude_saturdays' => $this->boolean('exclude_saturdays'),
            'release_date_is_day_one' => $this->boolean('release_date_is_day_one'),
            'interest_method' => $this->validated('interest_method'),
            'interest_rate' => $this->decimal('interest_rate'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * Unchecked switches are not submitted by the browser, so an absent key
     * means false rather than "leave unchanged".
     */
    public function prepareForValidation(): void
    {
        $this->merge([
            'exclude_sundays' => $this->boolean('exclude_sundays'),
            'exclude_saturdays' => $this->boolean('exclude_saturdays'),
            'release_date_is_day_one' => $this->boolean('release_date_is_day_one'),
            'is_active' => $this->boolean('is_active'),
            'description' => $this->clean('description'),
        ]);
    }

    protected function clean(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalise a typed rate to an exact two decimal string.
     *
     * The value never passes through a float, so "10.125" is stored as the exact
     * "10.13" instead of whatever the nearest binary value happens to be.
     */
    protected function decimal(string $key): string
    {
        $value = str_replace([',', ' ', "\u{20B1}"], '', (string) $this->input($key));

        return BigDecimal::of($value)
            ->toScale(2, RoundingMode::HalfUp)
            ->__toString();
    }
}
