<?php

namespace Database\Factories;

use App\Enums\ProfitTypeKind;
use App\Models\ProfitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitType>
 */
class ProfitTypeFactory extends Factory
{
    protected $model = ProfitType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'kind' => fake()->randomElement(ProfitTypeKind::cases()),
        ];
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ProfitTypeKind::Income,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ProfitTypeKind::Expense,
        ]);
    }
}
