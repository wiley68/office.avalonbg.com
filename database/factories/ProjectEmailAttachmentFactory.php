<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\ProjectEmailAttachment;
use App\Models\ProjectEmailLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectEmailAttachment>
 */
class ProjectEmailAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_email_link_id' => ProjectEmailLink::factory()->archived(),
            'document_id' => function (array $attributes): int {
                $link = ProjectEmailLink::query()->findOrFail($attributes['project_email_link_id']);

                return Document::factory()->for($link->user)->create(['group_id' => $link->group_id])->id;
            },
            'imap_part' => '2',
        ];
    }
}
