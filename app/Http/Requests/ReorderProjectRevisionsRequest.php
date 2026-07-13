<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\ProjectRevision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderProjectRevisionsRequest extends FormRequest
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
            'revision_ids' => ['required', 'array', 'min:1'],
            'revision_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('project_revisions', 'id')->where(
                    fn ($query) => $query->where('project_id', $project->id),
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
            /** @var list<int> $revisionIds */
            $revisionIds = $this->input('revision_ids');

            $revisionCount = ProjectRevision::query()
                ->where('project_id', $project->id)
                ->count();

            if (count($revisionIds) !== $revisionCount) {
                $validator->errors()->add(
                    'revision_ids',
                    __('projects.revisions.errors.invalid_reorder'),
                );
            }
        });
    }
}
