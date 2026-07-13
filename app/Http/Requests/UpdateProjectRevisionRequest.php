<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\ProjectRevision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $revision = $this->route('revision');

        return $project instanceof Project
            && $revision instanceof ProjectRevision
            && $revision->project_id === $project->id
            && ($this->user()?->can('update', $project) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
