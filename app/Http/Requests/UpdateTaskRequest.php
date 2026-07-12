<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('update', $task) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where(
                    fn ($query) => $query->where('project_id', $task?->project_id),
                ),
                Rule::notIn([$task?->id]),
            ],
            'project_revision_id' => [
                'nullable',
                'integer',
                Rule::exists('project_revisions', 'id')->where(
                    fn ($query) => $query->where('project_id', $task?->project_id),
                ),
            ],
        ];
    }
}
