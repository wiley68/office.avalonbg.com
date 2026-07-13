<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectGitRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectGitRepository>
 */
class ProjectGitRepositoryFactory extends Factory
{
    protected $model = ProjectGitRepository::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'owner' => 'laravel',
            'repo' => 'framework',
            'default_branch' => 'main',
            'access_token' => null,
            'last_synced_at' => null,
        ];
    }
}
