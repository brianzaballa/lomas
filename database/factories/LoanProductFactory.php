<?php

namespace Database\Factories;

use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\TermUnit;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
{
    /**
     * The defaults mirror the seeded 100-day product so a test only states the
     * rule it actually cares about.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => "{$name} loan",
            'code' => strtoupper(fake()->unique()->bothify('LP-??##??')),
            'description' => null,
            'term' => 100,
            'term_unit' => TermUnit::CollectionDays,
            'payment_frequency' => PaymentFrequency::Daily,
            'exclude_sundays' => true,
            'exclude_saturdays' => false,
            'release_date_is_day_one' => false,
            'interest_method' => InterestMethod::PercentageOfLoanAmount,
            'interest_rate' => '10.00',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
