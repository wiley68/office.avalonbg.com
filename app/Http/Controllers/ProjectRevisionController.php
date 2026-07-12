<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRevisionRequest;
use App\Http\Requests\UpdateProjectRevisionRequest;
use App\Models\Project;
use App\Models\ProjectRevision;
use Illuminate\Http\RedirectResponse;

class ProjectRevisionController extends Controller
{
    public function store(StoreProjectRevisionRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $sortOrder = $request->validated('sort_order');

        if ($sortOrder === null) {
            $max = $project->revisions()->max('sort_order');
            $sortOrder = $max === null ? 0 : ((int) $max + 1);
        }

        $project->revisions()->create([
            ...$request->validated(),
            'sort_order' => $sortOrder,
        ]);

        return back();
    }

    public function update(
        UpdateProjectRevisionRequest $request,
        Project $project,
        ProjectRevision $revision,
    ): RedirectResponse {
        $this->authorize('update', $project);

        abort_unless($revision->project_id === $project->id, 404);

        $revision->update($request->validated());

        return back();
    }

    public function destroy(Project $project, ProjectRevision $revision): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($revision->project_id === $project->id, 404);

        $revision->delete();

        return back();
    }
}
