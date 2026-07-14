<?php

namespace Database\Factories;

use App\Enums\ProjectEmailLinkStatus;
use App\Models\Project;
use App\Models\ProjectEmailLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectEmailLink>
 */
class ProjectEmailLinkFactory extends Factory
{
    protected $model = ProjectEmailLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'folder' => 'INBOX',
            'imap_uid' => fake()->unique()->numberBetween(1, 99999),
            'uidvalidity' => fake()->numberBetween(1, 999999),
            'subject' => fake()->sentence(),
            'from_name' => fake()->name(),
            'from_address' => fake()->safeEmail(),
            'sent_at' => now(),
            'status' => ProjectEmailLinkStatus::Active,
            'last_verified_at' => now(),
        ];
    }
}
