<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRevision>
 */
class ProjectRevisionFactory extends Factory
{
    protected $model = ProjectRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'label' => 'v'.fake()->numerify('#.#'),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
