<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\TaskTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProjectApiController extends Controller
{
    public function index(Request $request, TaskTreeService $taskTreeService): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,name,status,expected_completion_at,completed_at,created_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;
        $sortBy = $validated['sort_by'] ?? 'id';
        $sortOrder = ($validated['sort_desc'] ?? 1) ? 'desc' : 'asc';
        $filter = $validated['search'] ?? '';

        $query = Project::query()
            ->where('user_id', $user->id)
            ->withCount(['documents', 'tasks', 'emailLinks'])
            ->withExists('gitRepository')
            ->with([
                'tasks' => fn ($query) => $query
                    ->select('id', 'project_id', 'parent_id', 'name', 'status', 'sort_order')
                    ->orderBy('sort_order'),
                'latestRevision' => fn ($query) => $query->select(
                    'project_revisions.id',
                    'project_revisions.project_id',
                    'project_revisions.label',
                ),
            ]);

        if ($filter !== '') {
            $query->where(function ($q) use ($filter) {
                $q->where('name', 'like', "%{$filter}%")
                    ->orWhere('description', 'like', "%{$filter}%");

                if (is_numeric($filter)) {
                    $q->orWhere('id', (int) $filter);
                }
            });
        }

        $projects = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json(
            $projects->through(function (Project $project) use ($taskTreeService) {
                $data = $project->toArray();
                unset($data['tasks'], $data['git_repository_exists']);

                return [
                    ...$data,
                    'has_git_repository' => (bool) $project->git_repository_exists,
                    'tasks_timeline' => $taskTreeService->flatTimelineForProject($project),
                ];
            }),
        );
    }
}
