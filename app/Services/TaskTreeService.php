<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TaskTreeService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function nestedTreeForProject(Project $project): Collection
    {
        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with(['revision:id,label', 'documents:id,original_name'])
            ->orderBy('sort_order')
            ->get();

        return $this->buildTree($tasks);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, array<string, mixed>>
     */
    private function buildTree(Collection $tasks, ?int $parentId = null): Collection
    {
        return $tasks
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Task $task) use ($tasks): array {
                return [
                    'id' => $task->id,
                    'project_id' => $task->project_id,
                    'parent_id' => $task->parent_id,
                    'project_revision_id' => $task->project_revision_id,
                    'revision' => $task->revision ? [
                        'id' => $task->revision->id,
                        'label' => $task->revision->label,
                    ] : null,
                    'name' => $task->name,
                    'description' => $task->description,
                    'status' => $task->status->value,
                    'sort_order' => $task->sort_order,
                    'completed_at' => $task->completed_at?->toIso8601String(),
                    'created_at' => $task->created_at?->toIso8601String(),
                    'documents' => $task->documents->map(fn($document) => [
                        'id' => $document->id,
                        'original_name' => $document->original_name,
                    ])->values()->all(),
                    'children' => $this->buildTree($tasks, $task->id)->all(),
                ];
            });
    }

    public function nextSortOrder(Project $project, ?int $parentId): int
    {
        $max = Task::query()
            ->where('project_id', $project->id)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        return $max === null ? 0 : ((int) $max + 1);
    }

    /**
     * @throws ValidationException
     */
    public function assertCanComplete(Task $task): void
    {
        if ($task->parent_id === null) {
            return;
        }

        $parent = $task->relationLoaded('parent')
            ? $task->parent
            : Task::query()->find($task->parent_id);

        if ($parent !== null && $parent->status !== TaskStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => __('projects.tasks.errors.parent_not_completed'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertProjectCanComplete(Project $project): void
    {
        $incompleteCount = Task::query()
            ->where('project_id', $project->id)
            ->where('status', '!=', TaskStatus::Completed)
            ->count();

        if ($incompleteCount > 0) {
            throw ValidationException::withMessages([
                'status' => __('projects.errors.incomplete_tasks'),
            ]);
        }
    }

    public function reorder(Task $task, ?int $parentId, int $sortOrder): void
    {
        if ($parentId !== null) {
            $parent = Task::query()->findOrFail($parentId);

            if ($parent->project_id !== $task->project_id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('projects.tasks.errors.invalid_parent'),
                ]);
            }

            if ($parent->id === $task->id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('projects.tasks.errors.invalid_parent'),
                ]);
            }
        }

        $task->update([
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function reorderSiblings(Project $project, ?int $parentId, array $taskIds): void
    {
        foreach ($taskIds as $index => $taskId) {
            Task::query()
                ->where('project_id', $project->id)
                ->where('id', $taskId)
                ->where('parent_id', $parentId)
                ->update(['sort_order' => $index]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyTaskStatusAttributes(array $data, ?Task $task = null): array
    {
        $status = $data['status'] ?? $task?->status?->value ?? TaskStatus::Active->value;

        if ($status === TaskStatus::Completed->value) {
            if ($task === null || $task->status !== TaskStatus::Completed) {
                $data['completed_at'] = now();
            }
        } else {
            $data['completed_at'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyProjectStatusAttributes(array $data, ?Project $project = null): array
    {
        $status = $data['status'] ?? $project?->status?->value ?? ProjectStatus::Active->value;

        if ($status === ProjectStatus::Completed->value) {
            if ($project !== null) {
                $this->assertProjectCanComplete($project);
            }

            if ($project === null || $project->status !== ProjectStatus::Completed) {
                $data['completed_at'] = now();
            }
        } else {
            $data['completed_at'] = null;
        }

        return $data;
    }
}
