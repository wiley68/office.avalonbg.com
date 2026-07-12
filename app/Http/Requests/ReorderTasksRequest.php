<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where(
                    fn($query) => $query->where('project_id', $project->id),
                ),
            ],
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('tasks', 'id')->where(
                    fn($query) => $query->where('project_id', $project->id),
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Project $project */
            $project = $this->route('project');
            $parentId = $this->input('parent_id');
            /** @var list<int> $taskIds */
            $taskIds = $this->input('task_ids');

            $tasks = Task::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $taskIds)
                ->get();

            foreach ($tasks as $task) {
                if ($task->parent_id !== $parentId) {
                    $validator->errors()->add(
                        'task_ids',
                        __('projects.tasks.errors.invalid_reorder'),
                    );

                    return;
                }
            }

            $siblingCount = Task::query()
                ->where('project_id', $project->id)
                ->where('parent_id', $parentId)
                ->count();

            if (count($taskIds) !== $siblingCount) {
                $validator->errors()->add(
                    'task_ids',
                    __('projects.tasks.errors.invalid_reorder'),
                );
            }
        });
    }
}
