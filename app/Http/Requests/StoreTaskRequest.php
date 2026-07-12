<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && ($this->user()?->can('create', [Task::class, $project]) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where(
                    fn ($query) => $query->where('project_id', $project?->id),
                ),
            ],
            'project_revision_id' => [
                'nullable',
                'integer',
                Rule::exists('project_revisions', 'id')->where(
                    fn ($query) => $query->where('project_id', $project?->id),
                ),
            ],
        ];
    }
}
