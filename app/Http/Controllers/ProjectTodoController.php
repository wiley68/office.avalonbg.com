<?php

namespace App\Http\Controllers;

use App\Enums\ProjectTodoStatus;
use App\Http\Requests\StoreProjectTodoRequest;
use App\Http\Requests\UpdateProjectTodoRequest;
use App\Models\Project;
use App\Models\ProjectTodo;
use Illuminate\Http\RedirectResponse;

class ProjectTodoController extends Controller
{
    public function store(StoreProjectTodoRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->todos()->create([
            'body' => $request->validated('body'),
            'status' => ProjectTodoStatus::Active,
        ]);

        return back();
    }

    public function update(
        UpdateProjectTodoRequest $request,
        Project $project,
        ProjectTodo $todo,
    ): RedirectResponse {
        $this->authorize('update', $project);

        abort_unless($todo->project_id === $project->id, 404);

        $todo->update([
            'body' => $request->validated('body'),
        ]);

        return back();
    }

    public function toggle(Project $project, ProjectTodo $todo): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($todo->project_id === $project->id, 404);

        if ($todo->status === ProjectTodoStatus::Completed) {
            $todo->update([
                'status' => ProjectTodoStatus::Active,
                'completed_at' => null,
            ]);
        } else {
            $todo->update([
                'status' => ProjectTodoStatus::Completed,
                'completed_at' => now(),
            ]);
        }

        return back();
    }

    public function destroy(Project $project, ProjectTodo $todo): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($todo->project_id === $project->id, 404);

        $todo->delete();

        return back();
    }
}
