<?php

namespace App\Http\Controllers;

use App\Enums\ProjectTodoStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\TaskTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Project::class);

        return Inertia::render('projects/Index');
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'revisions' => fn ($query) => $query->orderByDesc('sort_order'),
            'documents:id,original_name,description,mime_type',
            'gitRepository:id,project_id,owner,repo,default_branch,access_token',
            'todos' => fn ($query) => $query
                ->orderByRaw("CASE WHEN status = '".ProjectTodoStatus::Active->value."' THEN 0 ELSE 1 END")
                ->orderByDesc('created_at'),
        ])->loadCount([
            'tasks',
            'emailLinks',
        ]);

        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('projects/Show', [
            'hasImapConfigured' => $user->imapAccount !== null,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status->value,
                'expected_completion_at' => $project->expected_completion_at?->format('Y-m-d'),
                'completed_at' => $project->completed_at?->toIso8601String(),
                'created_at' => $project->created_at?->toIso8601String(),
                'revisions' => $project->revisions->map(fn ($revision) => [
                    'id' => $revision->id,
                    'label' => $revision->label,
                    'description' => $revision->description,
                    'sort_order' => $revision->sort_order,
                ])->values(),
                'documents' => $project->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'description' => $document->description,
                    'mime_type' => $document->mime_type,
                ])->values(),
                'git_repository' => $project->gitRepository ? [
                    'owner' => $project->gitRepository->owner,
                    'repo' => $project->gitRepository->repo,
                    'default_branch' => $project->gitRepository->default_branch,
                    'repository_url' => $project->gitRepository->repositoryUrl(),
                    'has_access_token' => filled($project->gitRepository->access_token),
                ] : null,
                'tasks_count' => $project->tasks_count,
                'email_links_count' => $project->email_links_count,
                'todos' => $project->todos->map(fn ($todo) => [
                    'id' => $todo->id,
                    'body' => $todo->body,
                    'status' => $todo->status->value,
                    'created_at' => $todo->created_at?->toIso8601String(),
                    'completed_at' => $todo->completed_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function store(StoreProjectRequest $request, TaskTreeService $taskTreeService): RedirectResponse
    {
        $this->authorize('create', Project::class);

        /** @var User $user */
        $user = Auth::user();

        $data = $taskTreeService->applyProjectStatusAttributes($request->validated());

        $user->projects()->create($data);

        return to_route('projects.index');
    }

    public function update(
        UpdateProjectRequest $request,
        Project $project,
        TaskTreeService $taskTreeService,
    ): RedirectResponse {
        $this->authorize('update', $project);

        $data = $taskTreeService->applyProjectStatusAttributes($request->validated(), $project);

        $project->update($data);

        return to_route('projects.show', $project);
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return to_route('projects.index');
    }
}
