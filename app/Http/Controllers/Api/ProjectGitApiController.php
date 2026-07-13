<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\GitHubApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectGitApiController extends Controller
{
    public function show(Project $project, GitHubApiService $gitHubApiService): JsonResponse
    {
        Gate::authorize('view', $project);

        $gitRepository = $project->gitRepository;

        if ($gitRepository === null) {
            return response()->json([
                'data' => null,
            ]);
        }

        $page = max((int) request()->integer('page', 1), 1);
        $perPage = min(max((int) request()->integer('per_page', 30), 1), 100);

        $repository = $gitHubApiService->repository($gitRepository);
        $commits = $gitHubApiService->commits($gitRepository, $page, $perPage);

        $gitRepository->update(['last_synced_at' => now()]);

        return response()->json([
            'data' => $gitHubApiService->buildOverview(
                $gitRepository,
                $repository,
                $commits,
                $page,
                $perPage,
            ),
        ]);
    }
}
