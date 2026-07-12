<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('reorder', $task) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where(
                    fn ($query) => $query->where('project_id', $task?->project_id),
                ),
                Rule::notIn([$task?->id]),
            ],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
