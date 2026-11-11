<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => fn (array $attributes): int => Project::query()->findOrFail($attributes['project_id'])->user_id,
            'parent_id' => null,
            'project_revision_id' => null,
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Active,
            'sort_order' => 0,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
