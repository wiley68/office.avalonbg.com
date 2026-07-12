<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => ProjectStatus::Active,
            'expected_completion_at' => fake()->optional()->dateTimeBetween('now', '+6 months'),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function deferred(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Deferred,
        ]);
    }
}
