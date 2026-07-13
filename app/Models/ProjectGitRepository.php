<?php

namespace App\Models;

use Database\Factories\ProjectGitRepositoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $last_synced_at
 */
#[Fillable(['project_id', 'owner', 'repo', 'default_branch', 'access_token', 'last_synced_at'])]
class ProjectGitRepository extends Model
{
    /** @use HasFactory<ProjectGitRepositoryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function repositoryUrl(): string
    {
        return 'https://github.com/' . $this->owner . '/' . $this->repo;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'last_synced_at' => 'datetime',
        ];
    }
}
