<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderProjectRevisionsRequest;
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

        $sortOrder = $project->revisions()->max('sort_order');
        $sortOrder = $sortOrder === null ? 0 : ((int) $sortOrder + 1);

        $project->revisions()->create([
            'label' => $request->validated('label'),
            'description' => $request->validated('description'),
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

    public function reorder(
        ReorderProjectRevisionsRequest $request,
        Project $project,
    ): RedirectResponse {
        $this->authorize('update', $project);

        /** @var list<int> $revisionIds */
        $revisionIds = $request->validated('revision_ids');
        $count = count($revisionIds);

        foreach ($revisionIds as $index => $revisionId) {
            ProjectRevision::query()
                ->where('project_id', $project->id)
                ->whereKey($revisionId)
                ->update(['sort_order' => $count - 1 - $index]);
        }

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
