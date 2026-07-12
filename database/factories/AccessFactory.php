<?php

namespace Database\Factories;

use App\Models\Access;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Access>
 */
class AccessFactory extends Factory
{
    protected $model = Access::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'content' => fake()->sentence(),
            'is_encrypted' => false,
        ];
    }

    public function encrypted(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_encrypted' => true,
        ]);
    }
}
