<?php

namespace Database\Factories;

use App\Domain\Nodes\NodeIdentity;
use App\Enums\BorrowerStatus;
use App\Models\Borrower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Borrower>
 */
class BorrowerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // A borrower belongs to the node that registered it.
            'node_id' => app(NodeIdentity::class)->id(),
            'borrower_number' => 'BR-'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'birth_date' => fake()->optional()->date('1940-01-01', '2004-12-31'),
            'mobile_number' => '09'.fake()->numerify('#########'),
            'address' => fake()->address(),
            'status' => BorrowerStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BorrowerStatus::Inactive,
        ]);
    }
}
