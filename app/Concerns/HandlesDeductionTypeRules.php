<?php

namespace App\Concerns;

use App\Enums\DeductionCalculationMethod;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The validation rules shared by the deduction type create and update forms.
 *
 * The method decides which value is required: a percentage needs a rate, a
 * fixed amount needs an amount. The other value is never stored, so a type
 * cannot carry a rate and a fixed amount at the same time.
 */
trait HandlesDeductionTypeRules
{
    /**
     * Money and rates are stored with two decimal places.
     */
    protected const int SCALE = 2;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function deductionTypeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('loan_deduction_types', 'code'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'calculation_method' => ['required', Rule::in(DeductionCalculationMethod::values())],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * The validated deduction type with only the value that belongs to the
     * chosen calculation method.
     *
     * @return array{
     *     name: string,
     *     code: string,
     *     description: string|null,
     *     calculation_method: string,
     *     rate: string|null,
     *     fixed_amount: string|null,
     *     is_active: bool,
     *     sort_order: int
     * }
     */
    public function deductionTypeAttributes(): array
    {
        $method = DeductionCalculationMethod::from($this->validated('calculation_method'));
        $fields = $method->fields();

        $rate = $fields['required'] === 'rate' ? $this->decimal('rate') : null;
        $fixedAmount = $fields['required'] === 'fixed_amount' ? $this->decimal('fixed_amount') : null;

        return [
            'name' => $this->validated('name'),
            'code' => strtoupper($this->validated('code')),
            'description' => $this->validated('description'),
            'calculation_method' => $method->value,
            'rate' => $rate,
            'fixed_amount' => $fixedAmount,
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->validated('sort_order'),
        ];
    }

    /**
     * A percentage requires a rate and a fixed amount requires an amount.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $method = $this->input('calculation_method');

            if (! is_string($method) || DeductionCalculationMethod::tryFrom($method) === null) {
                return;
            }

            $fields = DeductionCalculationMethod::from($method)->fields();

            if ($this->input($fields['required']) === null || $this->input($fields['required']) === '') {
                $validator->errors()->add(
                    $fields['required'],
                    $fields['required'] === 'rate'
                        ? 'A percentage deduction needs a rate.'
                        : 'A fixed deduction needs an amount.',
                );
            }

            if ($this->filled($fields['forbidden'])) {
                $validator->errors()->add(
                    $fields['forbidden'],
                    $fields['forbidden'] === 'rate'
                        ? 'A fixed deduction does not use a rate.'
                        : 'A percentage deduction does not use a fixed amount.',
                );
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge([
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
     * Normalise a typed amount to an exact decimal string, never through a
     * float.
     */
    protected function decimal(string $key): string
    {
        $value = str_replace([',', ' ', "\u{20B1}"], '', (string) $this->input($key));

        return BigDecimal::of($value)->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }
}
