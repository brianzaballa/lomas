<?php

namespace Database\Factories;

use App\Enums\DeductionCalculationMethod;
use App\Models\LoanDeductionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanDeductionType>
 */
class LoanDeductionTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->bothify('DT-??##??')),
            'description' => null,
            'calculation_method' => DeductionCalculationMethod::PercentageOfLoanAmount,
            'rate' => '1.00',
            'fixed_amount' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function fixed(string|int|float $amount = '50.00'): static
    {
        return $this->state(fn (array $attributes): array => [
            'calculation_method' => DeductionCalculationMethod::FixedAmount,
            'rate' => null,
            'fixed_amount' => $amount,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
