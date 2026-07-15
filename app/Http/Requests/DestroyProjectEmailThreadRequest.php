<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\ProjectEmailLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DestroyProjectEmailThreadRequest extends FormRequest
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
        return [
            'link_ids' => ['required', 'array', 'min:1'],
            'link_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $project = $this->route('project');

            if (! $project instanceof Project) {
                return;
            }

            $linkIds = $this->input('link_ids', []);

            if (! is_array($linkIds) || $linkIds === []) {
                return;
            }

            $validCount = ProjectEmailLink::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $linkIds)
                ->count();

            if ($validCount !== count($linkIds)) {
                $validator->errors()->add('link_ids', 'invalid');
            }
        });
    }
}
