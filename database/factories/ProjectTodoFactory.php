<?php

namespace Database\Factories;

use App\Enums\ProjectTodoStatus;
use App\Models\Project;
use App\Models\ProjectTodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTodo>
 */
class ProjectTodoFactory extends Factory
{
    protected $model = ProjectTodo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'body' => fake()->sentence(),
            'status' => ProjectTodoStatus::Active,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectTodoStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
