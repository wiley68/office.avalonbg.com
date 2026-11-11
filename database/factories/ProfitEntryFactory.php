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
            'user_id' => User::factory()->officeUser(),
            'group_id' => fn (array $attributes): int => User::query()->findOrFail($attributes['user_id'])->group()->firstOrFail()->id,
            'profit_type_id' => fn (array $attributes): int => ProfitType::factory()->create(['group_id' => $attributes['group_id']])->id,
            'date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'document_number' => fake()->optional()->bothify('DOC-####'),
            'description' => fake()->optional()->sentence(),
            'amount' => fake()->randomFloat(2, 10, 5000),
        ];
    }
}
