<?php

namespace Database\Factories;

use App\Models\ProfitEntry;
use App\Models\ProfitType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitEntry>
 */
class ProfitEntryFactory extends Factory
{
    protected $model = ProfitEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'profit_type_id' => ProfitType::factory(),
            'date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'document_number' => fake()->optional()->bothify('DOC-####'),
            'amount' => fake()->randomFloat(2, 10, 5000),
        ];
    }
}
