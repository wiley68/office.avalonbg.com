<?php

namespace App\Models;

use App\Concerns\PreservesCreator;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'project_id',
    'user_id',
    'parent_id',
    'project_revision_id',
    'name',
    'description',
    'status',
    'sort_order',
    'completed_at',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, PreservesCreator;

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            $task->group_id = $task->project()->firstOrFail()->group_id;
        });

        static::saving(function (Task $task): void {
            if ($task->parent_id !== null && ! self::query()->where('project_id', $task->project_id)->whereKey($task->parent_id)->exists()) {
                throw new LogicException('The parent task must belong to the same project.');
            }

            if ($task->project_revision_id !== null && ! ProjectRevision::query()->where('project_id', $task->project_id)->whereKey($task->project_revision_id)->exists()) {
                throw new LogicException('The revision must belong to the same project.');
            }
        });
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<ProjectRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ProjectRevision::class, 'project_revision_id');
    }

    /**
     * @return BelongsToMany<Document, $this>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class)->using(DocumentTask::class)->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
