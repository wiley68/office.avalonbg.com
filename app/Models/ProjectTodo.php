<?php

namespace App\Models;

use App\Enums\ProjectTodoStatus;
use Database\Factories\ProjectTodoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ProjectTodoStatus $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'body', 'status', 'completed_at'])]
class ProjectTodo extends Model
{
    /** @use HasFactory<ProjectTodoFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectTodoStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
